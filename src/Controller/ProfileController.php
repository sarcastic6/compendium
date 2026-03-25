<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangeEmailFormType;
use App\Form\ChangePasswordFormType;
use App\Form\ProfileFormType;
use App\Repository\UserRepository;
use App\Service\PasswordValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('', name: 'app_profile')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $profileForm = $this->createForm(ProfileFormType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'profile.saved');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'profileForm' => $profileForm,
        ]);
    }

    #[Route('/change-email', name: 'app_profile_change_email', methods: ['GET', 'POST'])]
    public function changeEmail(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangeEmailFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Re-authentication required — changing email could lock out the account holder.
            $currentPassword = (string) $form->get('currentPassword')->getData();
            if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'profile.current_password.invalid');

                return $this->render('profile/change_email.html.twig', ['form' => $form]);
            }

            $newEmail = (string) $form->get('newEmail')->getData();

            // Reject if the address is already taken by another account.
            $existing = $this->userRepository->findOneBy(['email' => $newEmail]);
            if ($existing !== null && $existing->getId() !== $user->getId()) {
                $this->addFlash('error', 'profile.email.already_in_use');

                return $this->render('profile/change_email.html.twig', ['form' => $form]);
            }

            $user->setEmail($newEmail);
            $this->entityManager->flush();
            $this->addFlash('success', 'profile.email_changed');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/change_email.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/change-password', name: 'app_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = (string) $form->get('currentPassword')->getData();
            if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'profile.current_password.invalid');

                return $this->render('profile/change_password.html.twig', ['form' => $form]);
            }

            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $this->entityManager->flush();

            $this->addFlash('success', 'profile.password_changed');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form,
        ]);
    }
}
