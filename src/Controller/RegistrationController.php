<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Mailer\VerificationEmailMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly VerificationEmailMailer $verificationEmailMailer,
    ) {
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        // Registration can be disabled via env var (e.g., in closed deployments)
        if (!filter_var($this->getParameter('app.registration_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            throw $this->createNotFoundException('Registration is disabled.');
        }

        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_reading_entry_list');
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            // Create the user once with a placeholder hash, then immediately overwrite it.
            // hashPassword() only needs the User object to satisfy PasswordAuthenticatedUserInterface — it does not read passwordHash.
            $user = new User($form->get('name')->getData(), $form->get('email')->getData(), 'placeholder');
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $plainPassword));

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $signatureComponents = $this->verifyEmailHelper->generateSignature(
                'app_verify_email',
                (string) $user->getId(),
                $user->getEmail(),
                ['id' => $user->getId()],
            );

            $this->verificationEmailMailer->sendVerificationEmail($user, $signatureComponents);

            $this->addFlash('success', 'auth.register.success');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form,
        ]);
    }
}
