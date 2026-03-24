<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\WorkFormDto;
use App\Entity\MetadataType;
use App\Form\WorkFormType;
use App\Repository\MetadataRepository;
use App\Repository\MetadataTypeRepository;
use App\Repository\WorkRepository;
use App\Scraper\ScrapedWorkDto;
use App\Scraper\ScraperRegistry;
use App\Scraper\ScrapingException;
use App\Service\ImportService;
use App\Service\WorkService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/work')]
#[IsGranted('ROLE_USER')]
class WorkController extends AbstractController
{
    public function __construct(
        private readonly WorkService $workService,
        private readonly WorkRepository $workRepository,
        private readonly ImportService $importService,
        private readonly MetadataTypeRepository $metadataTypeRepository,
        private readonly MetadataRepository $metadataRepository,
        private readonly ScraperRegistry $scraperRegistry,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Step 1a: Create a new Work, then redirect to creating a ReadingEntry for it.
     */
    #[Route('/new', name: 'app_work_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $dto = $this->consumeImportSession($request);
        $form = $this->createForm(WorkFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $work = $this->workService->createWork($dto);
                $this->addFlash('success', 'work.created');

                return $this->redirectToRoute('app_reading_entry_new', ['workId' => $work->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        // Get Author MetadataType at render time so the autocomplete URL has a valid typeId.
        // findOrCreateAuthorType() flushes when creating a new entity, ensuring getId() != null.
        $authorType = $this->workService->findOrCreateAuthorType();

        // All non-Author metadata types, sorted by display order.
        $metadataTypes = $this->metadataTypeRepository->createQueryBuilder('mt')
            ->where('mt.name != :author')
            ->setParameter('author', 'Author')
            ->getQuery()
            ->getResult();

        usort($metadataTypes, static function (MetadataType $a, MetadataType $b): int {
            $order = MetadataType::DISPLAY_ORDER;
            $posA = array_search($a->getName(), $order, true);
            $posB = array_search($b->getName(), $order, true);
            $posA = $posA === false ? PHP_INT_MAX : $posA;
            $posB = $posB === false ? PHP_INT_MAX : $posB;

            return $posA !== $posB ? $posA <=> $posB : strcmp($a->getName(), $b->getName());
        });

        // Checkbox types: rendered as checkbox groups (small, stable vocabularies).
        $checkboxTypes = array_values(array_filter(
            $metadataTypes,
            static fn (MetadataType $t) => $t->isShowAsCheckboxes(),
        ));

        // Known metadata values for each checkbox type, for rendering the checkbox options.
        $checkboxOptions = $this->metadataRepository->findCheckboxOptionsByTypes($checkboxTypes);

        // Pre-computed set of known names per checkbox type (for "scraped extras" detection).
        $knownCheckboxNames = [];
        foreach ($checkboxOptions as $typeId => $options) {
            $knownCheckboxNames[$typeId] = array_map(static fn ($o) => $o['name'], $options);
        }

        // Resolve which checkboxes should be pre-checked from DTO data (AO3 import or POST re-render).
        // This method is read-only — the controller is responsible for removing the matched entries.
        $preselectedCheckboxNames = $this->workService->resolveCheckboxPreselections(
            $dto->metadata,
            $checkboxTypes,
        );

        // Remove checkbox-type entries from $dto->metadata so they don't also render as
        // autocomplete chips. They are represented by the $preselectedCheckboxNames instead.
        $checkboxTypeIds = array_map(static fn (MetadataType $t) => $t->getId(), $checkboxTypes);
        $dto->metadata = array_values(array_filter(
            $dto->metadata,
            static fn (array $entry) => !in_array(
                $entry['metadataType']->getId(),
                $checkboxTypeIds,
                true,
            ),
        ));

        // Build indexed chip data for autocomplete pre-population (grouped by MetadataType ID).
        // Each chip carries its form index so the template can emit correct hidden field names.
        $metadataByType = [];
        $metaIndex = 0;
        foreach ($dto->metadata as $entry) {
            $typeId = $entry['metadataType']->getId();
            if ($typeId === null) {
                continue;
            }
            $metadataByType[$typeId][] = [
                'index' => $metaIndex,
                'name'  => $entry['name'],
                'link'  => $entry['link'] ?? '',
            ];
            $metaIndex++;
        }

        // Author chips, indexed for hidden field emission.
        $authorChips = [];
        foreach ($dto->authors as $i => $author) {
            $authorChips[] = [
                'index' => $i,
                'name'  => $author['name'],
                'link'  => $author['link'] ?? '',
            ];
        }

        return $this->render('work/new.html.twig', [
            'form'                    => $form,
            'dto'                     => $dto,
            'metadataTypes'           => $metadataTypes,
            'checkboxOptions'         => $checkboxOptions,
            'knownCheckboxNames'      => $knownCheckboxNames,
            'preselectedCheckboxNames' => $preselectedCheckboxNames,
            'metadataByType'          => $metadataByType,
            'totalMetadataIndex'      => $metaIndex,
            'authorTypeId'            => $authorType->getId(),
            'authorChips'             => $authorChips,
        ]);
    }

    /**
     * Reads a ScrapedWorkDto from the session (if present), maps it to a WorkFormDto,
     * flashes any mapping warnings, and clears the session key.
     * Returns a blank WorkFormDto if no import data is in the session.
     */
    private function consumeImportSession(Request $request): WorkFormDto
    {
        $session = $request->getSession();
        $scraped = $session->get('import_scraped_work');

        if (!($scraped instanceof ScrapedWorkDto)) {
            return new WorkFormDto();
        }

        $session->remove('import_scraped_work');
        $session->remove('import_duplicate_work_id');

        $result = $this->importService->mapToWorkFormDto($scraped);

        foreach ($result->warnings as $warning) {
            $this->addFlash('warning', $warning);
        }

        return $result->dto;
    }

    /**
     * Re-scrapes a Work's source URL and updates its metadata in place.
     * Only works that have a source URL pointing to a supported scraper can be refreshed.
     */
    #[Route('/{id}/refresh', name: 'app_work_refresh', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refresh(int $id, Request $request): Response
    {
        $work = $this->workRepository->find($id);
        if ($work === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('refresh_work_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $link = $work->getLink();
        if ($link === null) {
            $this->addFlash('error', 'work.refresh.error.no_link');

            return $this->redirectToRoute('app_work_show', ['id' => $id]);
        }

        $scraper = $this->scraperRegistry->getScraperForUrl($link);
        if ($scraper === null) {
            $this->addFlash('error', 'work.refresh.error.unsupported_url');

            return $this->redirectToRoute('app_work_show', ['id' => $id]);
        }

        try {
            $scraped = $scraper->scrape($link);
        } catch (ScrapingException $e) {
            $this->logger->error('Work refresh scrape failed', [
                'work_id'     => $id,
                'url'         => $link,
                'http_status' => $e->getHttpStatus(),
                'error'       => $e->getMessage(),
            ]);
            $this->addFlash('error', 'work.refresh.error.scrape_failed');

            return $this->redirectToRoute('app_work_show', ['id' => $id]);
        }

        $result = $this->importService->mapToWorkFormDto($scraped);
        foreach ($result->warnings as $warning) {
            $this->addFlash('warning', $warning);
        }

        try {
            $this->workService->refreshWork($work, $result->dto);
            $this->addFlash('success', 'work.refresh.success');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_work_show', ['id' => $id]);
    }

    /**
     * Applies a pending import session to an existing Work instead of creating a new one.
     * Used when the import flow detects a duplicate URL and the user chooses to update.
     */
    #[Route('/{id}/update-from-import', name: 'app_work_update_from_import', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateFromImport(int $id, Request $request): Response
    {
        $work = $this->workRepository->find($id);
        if ($work === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('update_from_import_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $session = $request->getSession();
        $scraped = $session->get('import_scraped_work');
        if (!($scraped instanceof ScrapedWorkDto)) {
            $this->addFlash('error', 'import.error.session_expired');

            return $this->redirectToRoute('app_work_select');
        }

        $session->remove('import_scraped_work');
        $session->remove('import_duplicate_work_id');

        $result = $this->importService->mapToWorkFormDto($scraped);
        foreach ($result->warnings as $warning) {
            $this->addFlash('warning', $warning);
        }

        try {
            $this->workService->refreshWork($work, $result->dto);
            $this->addFlash('success', 'import.success.updated');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_work_select');
        }

        return $this->redirectToRoute('app_reading_entry_new', ['workId' => $work->getId()]);
    }

    /**
     * Read-only detail page for a single Work.
     */
    #[Route('/{id}', name: 'app_work_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $work = $this->workRepository->findWithAllRelations($id);

        if ($work === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('work/show.html.twig', [
            'work' => $work,
        ]);
    }

    /**
     * HTML fragment for the work preview offcanvas on the select page.
     * Returns a partial template (no base layout) consumed via fetch().
     */
    #[Route('/{id}/preview', name: 'app_work_preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function preview(int $id): Response
    {
        $work = $this->workRepository->findWithAllRelations($id);

        if ($work === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('work/_preview.html.twig', [
            'work' => $work,
        ]);
    }

    /**
     * Step 1b: Select an existing Work to create a ReadingEntry for (e.g., re-reads).
     * Also handles POST to import a Work from an external URL (merged from ImportController).
     */
    #[Route('/select', name: 'app_work_select', methods: ['GET', 'POST'])]
    public function select(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('import_url', $request->request->getString('_import_token'))) {
                throw $this->createAccessDeniedException();
            }

            $url = trim($request->request->getString('import_url'));

            if ($url === '') {
                $this->addFlash('error', 'import.url.not_blank');

                return $this->redirectToRoute('app_work_select');
            }

            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                $this->addFlash('error', 'import.url.invalid');

                return $this->redirectToRoute('app_work_select');
            }

            $scraper = $this->scraperRegistry->getScraperForUrl($url);
            if ($scraper === null) {
                $this->addFlash('error', 'import.error.unsupported_url');

                return $this->redirectToRoute('app_work_select');
            }

            try {
                $scraped = $scraper->scrape($url);
            } catch (ScrapingException $e) {
                $this->logger->error('Import scrape failed', [
                    'url'         => $url,
                    'http_status' => $e->getHttpStatus(),
                    'error'       => $e->getMessage(),
                ]);
                $this->addFlash('error', 'import.error.scrape_failed');

                return $this->redirectToRoute('app_work_select');
            }

            // Duplicate detection: if a work with this URL already exists, redirect back to
            // the select page with a prompt instead of silently creating a duplicate.
            if ($scraped->sourceUrl !== null) {
                $existing = $this->workRepository->findByLink($scraped->sourceUrl);
                if ($existing !== null) {
                    $request->getSession()->set('import_scraped_work', $scraped);
                    $request->getSession()->set('import_duplicate_work_id', $existing->getId());

                    return $this->redirectToRoute('app_work_select');
                }
            }

            // Store DTO in session — WorkController::new() reads it on the next GET and applies mapping
            $request->getSession()->set('import_scraped_work', $scraped);

            return $this->redirectToRoute('app_work_new');
        }

        // Check for a pending duplicate-work prompt set by the POST branch above.
        $duplicateWorkId = $request->getSession()->get('import_duplicate_work_id');
        $duplicateWork = null;
        if (is_int($duplicateWorkId)) {
            $duplicateWork = $this->workRepository->find($duplicateWorkId);
        }

        $query = $request->query->getString('q', '');
        $works = [];

        if ($query !== '') {
            $works = $this->workRepository->createQueryBuilder('w')
                ->where('LOWER(w.title) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $query . '%')
                ->orderBy('w.title', 'ASC')
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();
        }

        return $this->render('work/select.html.twig', [
            'works'         => $works,
            'query'         => $query,
            'duplicateWork' => $duplicateWork,
        ]);
    }
}
