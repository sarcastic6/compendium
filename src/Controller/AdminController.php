<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MetadataType;
use App\Entity\Status;
use App\Form\MetadataTypeFormType;
use App\Form\StatusFormType;
use App\Repository\MetadataTypeRepository;
use App\Repository\StatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StatusRepository $statusRepository,
        private readonly MetadataTypeRepository $metadataTypeRepository,
    ) {
    }

    #[Route('', name: 'app_admin_index')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    // --- Statuses ---

    #[Route('/statuses', name: 'app_admin_status_list')]
    public function statusList(): Response
    {
        return $this->render('admin/statuses/list.html.twig', [
            'statuses' => $this->statusRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/statuses/new', name: 'app_admin_status_new', methods: ['GET', 'POST'])]
    public function statusNew(Request $request): Response
    {
        $status = new Status('');
        $form = $this->createForm(StatusFormType::class, $status);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($status);
            $this->entityManager->flush();

            $this->addFlash('success', 'action.save');

            return $this->redirectToRoute('app_admin_status_list');
        }

        return $this->render('admin/statuses/new.html.twig', [
            'form' => $form,
        ]);
    }

    // --- Metadata Types ---

    #[Route('/metadata-types', name: 'app_admin_metadata_type_list')]
    public function metadataTypeList(): Response
    {
        return $this->render('admin/metadata_types/list.html.twig', [
            'metadata_types' => $this->metadataTypeRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/metadata-types/new', name: 'app_admin_metadata_type_new', methods: ['GET', 'POST'])]
    public function metadataTypeNew(Request $request): Response
    {
        $metadataType = new MetadataType('');
        $form = $this->createForm(MetadataTypeFormType::class, $metadataType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($metadataType);
            $this->entityManager->flush();

            $this->addFlash('success', 'action.save');

            return $this->redirectToRoute('app_admin_metadata_type_list');
        }

        return $this->render('admin/metadata_types/new.html.twig', [
            'form' => $form,
        ]);
    }
}
