<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ReadingEntryFormDto;
use App\Entity\ReadingEntry;
use App\Entity\Status;
use App\Entity\User;
use App\Form\ReadingEntryFormType;
use App\Repository\LanguageRepository;
use App\Repository\MetadataRepository;
use App\Repository\MetadataTypeRepository;
use App\Repository\ReadingEntryRepository;
use App\Repository\SeriesRepository;
use App\Repository\StatusRepository;
use App\Repository\WorkRepository;
use App\Service\AchievementService;
use App\Service\ReadingEntryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reading-entries')]
#[IsGranted('ROLE_USER')]
class ReadingEntryController extends AbstractController
{
    public function __construct(
        private readonly ReadingEntryService $readingEntryService,
        private readonly ReadingEntryRepository $readingEntryRepository,
        private readonly WorkRepository $workRepository,
        private readonly StatusRepository $statusRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MetadataTypeRepository $metadataTypeRepository,
        private readonly LanguageRepository $languageRepository,
        private readonly MetadataRepository $metadataRepository,
        private readonly SeriesRepository $seriesRepository,
        private readonly AchievementService $achievementService,
    ) {
    }

    #[Route('', name: 'app_reading_entry_list')]
    public function list(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Redirect statusName (set by status rankings drill-down links) to a status ID filter.
        // This normalises the URL so only one status filter param exists going forward.
        $statusName = $request->query->get('statusName', '');
        if ($statusName !== '') {
            $queryParams = $request->query->all();
            unset($queryParams['statusName']);
            $status = $this->statusRepository->findOneBy(['name' => $statusName]);
            if ($status !== null) {
                $queryParams['status'] = (string) $status->getId();
            }
            return $this->redirectToRoute('app_reading_entry_list', $queryParams);
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 25;

        // Parse metadata[] array: keys are metadata type names, values are filter strings.
        // e.g. metadata[Fandom]=Harry+Potter&metadata[Warning]=Violence
        $metadataRaw = $request->query->all('metadata');
        $metadataFilters = array_filter(
            array_map('strval', is_array($metadataRaw) ? $metadataRaw : []),
            static fn (string $v): bool => $v !== '',
        );

        $filterParams = [
            'status'      => $request->query->get('status', ''),
            'q'           => $request->query->get('q', ''),
            'author'      => $request->query->get('author', ''),
            'starred'     => $request->query->get('starred', ''),
            'rating'      => $request->query->get('rating', ''),
            'dateFrom'    => $request->query->get('dateFrom', ''),
            'dateTo'      => $request->query->get('dateTo', ''),
            'spice'       => $request->query->get('spice', ''),
            'type'        => $request->query->get('type', ''),
            'language'    => $request->query->get('language', ''),
            'mainPairing' => $request->query->get('mainPairing', ''),
            'metadata'    => $metadataFilters,
            // Set by spice distribution chart drill-down links only — always exact match.
            // The form's 'spice' param uses exact for 0 and minimum for 1–5.
            'spiceExact'  => $request->query->get('spiceExact', ''),
            'wordsMin'    => $request->query->get('wordsMin', ''),
            'wordsMax'    => $request->query->get('wordsMax', ''),
            // Set by Series rankings drill-down links. Value is a series ID (int as string).
            'series'      => $request->query->get('series', ''),
        ];

        // Use strict empty check so spice=0 (a valid value) is treated as an active filter.
        // PHP's array_filter() default treats '0' as falsy, which would incorrectly ignore it.
        // metadata is an array so it is checked separately.
        $stringParams = array_diff_key($filterParams, ['metadata' => null]);
        $hasFilters = array_filter($stringParams, static fn (string $v): bool => $v !== '') !== []
            || $metadataFilters !== [];

        $activeFilterCount = count(array_filter($stringParams, static fn (string $v): bool => $v !== ''))
            + count($metadataFilters);

        $allowedSorts = ['title', 'author', 'status', 'dateFinished'];
        $allowedDirs  = ['asc', 'desc'];
        $sort = $request->query->get('sort', 'dateFinished');
        $dir  = $request->query->get('dir', 'desc');
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'dateFinished';
        }
        if (!in_array($dir, $allowedDirs, true)) {
            $dir = 'desc';
        }

        $total = $this->readingEntryRepository->countByUserFiltered($user, $filterParams);
        $entries = $this->readingEntryRepository->findByUserFiltered($user, $filterParams, $page, $limit, $sort, $dir);
        $totalPages = (int) ceil($total / $limit);

        $allStatuses = $this->statusRepository->findAll();
        $allMetadataTypes = $this->metadataTypeRepository->findAll();
        $allLanguages = $this->languageRepository->findAll();
        $metadataDropdownValues = $this->metadataRepository->findDropdownValuesByTypeName();

        // Look up series name for the active series filter chip.
        $seriesName = null;
        if ($filterParams['series'] !== '') {
            $series = $this->seriesRepository->find((int) $filterParams['series']);
            $seriesName = $series?->getName();
        }

        // Summary stat strip — scoped to the active filter set when filters are applied,
        // or all-time when unfiltered. The 4th box shows "Completed" count when filtered
        // (since "This Year" is meaningless against an arbitrary filter) and "This Year"
        // count when unfiltered (library-wide context).
        // Finish rate denominator is always the entry count being shown ($total when filtered,
        // totalEntriesAllTime when unfiltered) — consistent with the all-time calculation.
        $currentYear = (int) date('Y');
        if ($hasFilters) {
            $statTotalWords = $this->readingEntryRepository->getTotalWordsSumFiltered($user, $filterParams);
            $statCompleted  = $this->readingEntryRepository->countFinishedFiltered($user, $filterParams);
            $statAvgReview  = $this->readingEntryRepository->getAverageRatingFiltered($user, $filterParams);
            $statFinishRate = $total > 0 ? (int) round($statCompleted / $total * 100) : 0;
            $statThisYear   = null;
        } else {
            $totalEntriesAllTime = $this->readingEntryRepository->countByUser($user);
            $finishedAllTime     = $this->readingEntryRepository->countFinished($user);
            $statTotalWords      = $this->readingEntryRepository->getTotalWordsSumForUser($user);
            $statAvgReview       = $this->readingEntryRepository->getAverageRating($user);
            $statFinishRate      = $totalEntriesAllTime > 0
                ? (int) round($finishedAllTime / $totalEntriesAllTime * 100)
                : 0;
            $statCompleted  = null;
            $statThisYear   = $this->readingEntryRepository->countByUser($user, $currentYear);
        }

        return $this->render('reading_entry/list.html.twig', [
            'entries' => $entries,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'filters' => $filterParams,
            'current_sort' => $sort,
            'current_dir'  => $dir,
            'has_filters' => $hasFilters,
            'active_filter_count' => $activeFilterCount,
            'statuses' => $allStatuses,
            'metadataTypes' => $allMetadataTypes,
            'languages' => $allLanguages,
            'metadataDropdownValues' => $metadataDropdownValues,
            'seriesName' => $seriesName,
            // Summary stat strip
            'stat_total_words'  => $statTotalWords,
            'stat_finish_rate'  => $statFinishRate,
            'stat_avg_review'   => $statAvgReview,
            'stat_completed'    => $statCompleted,
            'stat_this_year'    => $statThisYear,
            'stat_current_year' => $currentYear,
        ]);
    }

    #[Route('/{id}', name: 'app_reading_entry_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $entry = $this->readingEntryRepository->findByIdForUser($id, $user);
        if ($entry === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('reading_entry/show.html.twig', ['entry' => $entry]);
    }

    #[Route('/new/{workId}', name: 'app_reading_entry_new', requirements: ['workId' => '\d+'], methods: ['GET', 'POST'])]
    public function new(Request $request, int $workId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Fetch work — soft-deleted works are excluded by the SoftDeleteFilter by default
        $work = $this->workRepository->find($workId);

        if ($work === null) {
            throw $this->createNotFoundException('Work not found.');
        }

        $dto = new ReadingEntryFormDto();
        $form = $this->createForm(ReadingEntryFormType::class, $dto, [
            'work' => $work,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // User is always set from authenticated session — never from form input
            $entry = new ReadingEntry($user, $work, $dto->status);
            $entry->setDateStarted($dto->dateStarted);
            $entry->setDateFinished($dto->dateFinished);
            $entry->setLastReadChapter($dto->lastReadChapter);
            $entry->setReviewStars($dto->reviewStars);
            $entry->setSpiceStars($dto->spiceStars);
            $entry->setMainPairing($dto->mainPairing);
            $entry->setComments($dto->comments);
            $entry->setStarred($dto->starred);

            try {
                // persist() is called inside validateAndSave() — after validation — so invalid entries
                // are never added to the unit of work.
                $this->readingEntryService->validateAndSave($entry);
                $this->addFlash('success', 'reading.entry.added');
                $this->flashNewAchievements($user);

                return $this->redirectToRoute('app_reading_entry_list');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('reading_entry/new.html.twig', [
            'form' => $form,
            'work' => $work,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reading_entry_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $entry = $this->readingEntryRepository->findByIdForUser($id, $user);
        if ($entry === null) {
            throw $this->createNotFoundException();
        }

        $dto = ReadingEntryFormDto::fromEntity($entry);
        $form = $this->createForm(ReadingEntryFormType::class, $dto, [
            'work' => $entry->getWork(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->readingEntryService->updateFromDto($entry, $dto);
                $this->addFlash('success', 'reading.entry.updated');
                $this->flashNewAchievements($user);

                return $this->redirectToRoute('app_reading_entry_show', ['id' => $entry->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('reading_entry/edit.html.twig', [
            'form' => $form,
            'entry' => $entry,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_reading_entry_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $entry = $this->readingEntryRepository->findByIdForUser($id, $user);
        if ($entry === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('delete-entry-' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'security.csrf_invalid');

            return $this->redirectToRoute('app_reading_entry_show', ['id' => $id]);
        }

        $this->entityManager->remove($entry);
        $this->entityManager->flush();

        $this->addFlash('success', 'reading.entry.deleted');

        return $this->redirectToRoute('app_reading_entry_list');
    }

    #[Route('/bulk/status', name: 'app_reading_entry_bulk_status', methods: ['POST'])]
    public function bulkStatus(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('bulk-action', $request->request->get('_token'))) {
            $this->addFlash('error', 'security.csrf_invalid');

            return $this->redirectToRoute('app_reading_entry_list');
        }

        $ids = array_map('intval', (array) $request->request->all('ids'));
        $statusId = $request->request->getInt('status_id');

        $status = $this->statusRepository->find($statusId);
        if ($status === null) {
            $this->addFlash('error', 'reading.entry.status_not_found');

            return $this->redirectToRoute('app_reading_entry_list');
        }

        $count = $this->readingEntryService->bulkUpdateStatus($user, $ids, $status);

        $this->addFlash('success', 'reading.bulk.status_updated');
        $this->flashNewAchievements($user);

        return $this->redirectToRoute('app_reading_entry_list', $request->query->all());
    }

    #[Route('/bulk/delete', name: 'app_reading_entry_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('bulk-action', $request->request->get('_token'))) {
            $this->addFlash('error', 'security.csrf_invalid');

            return $this->redirectToRoute('app_reading_entry_list');
        }

        $ids = array_map('intval', (array) $request->request->all('ids'));

        $count = $this->readingEntryService->bulkDelete($user, $ids);

        $this->addFlash('success', 'reading.bulk.entries_deleted');

        return $this->redirectToRoute('app_reading_entry_list', $request->query->all());
    }

    /**
     * Evaluates achievements after a data-changing operation and adds a flash
     * message for each newly unlocked achievement.
     */
    private function flashNewAchievements(User $user): void
    {
        $newlyUnlocked = $this->achievementService->evaluateAchievements($user);
        foreach ($newlyUnlocked as $def) {
            $this->addFlash('achievement', $def->getUnlockedMessageKey());
        }
    }
}
