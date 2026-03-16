<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ProfileFormType;
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
    ) {
    }

    #[Route('', name: 'app_profile')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $originalEmail = $user->getEmail();

        $profileForm = $this->createForm(ProfileFormType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            // Re-authentication is required only when changing the email address, since
            // a stolen session could otherwise silently lock out the legitimate account holder.
            if ($user->getEmail() !== $originalEmail) {
                $currentPassword = (string) $profileForm->get('currentPassword')->getData();
                if ($currentPassword === '' || !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('error', 'profile.current_password.invalid');

                    return $this->render('profile/index.html.twig', ['profileForm' => $profileForm]);
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'profile.saved');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'profileForm' => $profileForm,
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
