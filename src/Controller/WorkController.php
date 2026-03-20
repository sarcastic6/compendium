<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\WorkFormDto;
use App\Entity\MetadataType;
use App\Form\WorkFormType;
use App\Repository\MetadataTypeRepository;
use App\Repository\WorkRepository;
use App\Scraper\ScrapedWorkDto;
use App\Service\ImportService;
use App\Service\WorkService;
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

        return $this->render('work/new.html.twig', [
            'form' => $form,
            'metadataTypes' => $metadataTypes,
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

        $result = $this->importService->mapToWorkFormDto($scraped);

        foreach ($result->warnings as $warning) {
            $this->addFlash('warning', $warning);
        }

        return $result->dto;
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
     * Step 1b: Select an existing Work to create a ReadingEntry for (e.g., re-reads).
     */
    #[Route('/select', name: 'app_work_select', methods: ['GET'])]
    public function select(Request $request): Response
    {
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
            'works' => $works,
            'query' => $query,
        ]);
    }
}
