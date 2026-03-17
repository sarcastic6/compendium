<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ReadingEntryFormDto;
use App\Entity\ReadingEntry;
use App\Entity\Status;
use App\Entity\User;
use App\Form\ReadingEntryFormType;
use App\Repository\ReadingEntryRepository;
use App\Repository\StatusRepository;
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
        private readonly StatusRepository $statusRepository,
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

        $filterParams = [
            'status' => $request->query->get('status', ''),
            'q' => $request->query->get('q', ''),
            'author' => $request->query->get('author', ''),
            'starred' => $request->query->get('starred', ''),
            'rating' => $request->query->get('rating', ''),
            'dateFrom' => $request->query->get('dateFrom', ''),
            'dateTo' => $request->query->get('dateTo', ''),
            'spice' => $request->query->get('spice', ''),
            'type' => $request->query->get('type', ''),
        ];

        $hasFilters = array_filter($filterParams) !== [];

        $total = $this->readingEntryRepository->countByUserFiltered($user, $filterParams);
        $entries = $this->readingEntryRepository->findByUserFiltered($user, $filterParams, $page, $limit);
        $totalPages = (int) ceil($total / $limit);

        $allStatuses = $this->statusRepository->findAll();

        return $this->render('reading_entry/list.html.twig', [
            'entries' => $entries,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'filters' => $filterParams,
            'has_filters' => $hasFilters,
            'statuses' => $allStatuses,
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
}
