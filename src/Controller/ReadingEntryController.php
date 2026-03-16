<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ReadingEntryFormDto;
use App\Entity\ReadingEntry;
use App\Entity\User;
use App\Form\ReadingEntryFormType;
use App\Repository\ReadingEntryRepository;
use App\Repository\WorkRepository;
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
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_reading_entry_list')]
    public function list(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 25;

        $total = $this->readingEntryRepository->countByUser($user);
        $entries = $this->readingEntryRepository->findByUser($user, $page, $limit);
        $totalPages = (int) ceil($total / $limit);

        return $this->render('reading_entry/list.html.twig', [
            'entries' => $entries,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
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
}
