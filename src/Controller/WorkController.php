<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\WorkFormDto;
use App\Form\WorkFormType;
use App\Repository\WorkRepository;
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
    ) {
    }

    /**
     * Step 1a: Create a new Work, then redirect to creating a ReadingEntry for it.
     */
    #[Route('/new', name: 'app_work_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $dto = new WorkFormDto();
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

        return $this->render('work/new.html.twig', [
            'form' => $form,
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
