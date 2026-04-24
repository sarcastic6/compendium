<?php

declare(strict_types=1);

namespace App\Controller;

use App\Mailer\VerificationEmailMailer;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class VerifyEmailController extends AbstractController
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly VerificationEmailMailer $verificationEmailMailer,
    ) {
    }

    #[Route('/verify-email', name: 'app_verify_email')]
    public function verify(Request $request): Response
    {
        $id = $request->query->get('id');

        if ($id === null) {
            $this->addFlash('error', 'auth.verify_email.error.invalid_link');

            return $this->redirectToRoute('app_login');
        }

        $user = $this->userRepository->find((int) $id);

        if ($user === null) {
            $this->addFlash('error', 'auth.verify_email.error.invalid_link');

            return $this->redirectToRoute('app_login');
        }

        try {
            $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
                $request,
                (string) $user->getId(),
                $user->getEmail(),
            );
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('error', 'auth.verify_email.error.invalid_link');

            return $this->redirectToRoute('app_verify_email_resend');
        }

        $user->setIsVerified(true);
        $this->entityManager->flush();

        $this->addFlash('success', 'auth.verify_email.success');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify-email/resend', name: 'app_verify_email_resend')]
    public function resend(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email', '');

            $user = $this->userRepository->findByEmail((string) $email);

            // Always show the same message regardless of whether the address exists,
            // to avoid leaking which emails are registered.
            if ($user !== null && !$user->isVerified()) {
                $signatureComponents = $this->verifyEmailHelper->generateSignature(
                    'app_verify_email',
                    (string) $user->getId(),
                    $user->getEmail(),
                    ['id' => $user->getId()],
                );

                $this->verificationEmailMailer->sendVerificationEmail($user, $signatureComponents);
            }

            $this->addFlash('success', 'auth.verify_email.resend.sent');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/resend_verification.html.twig');
    }
}
