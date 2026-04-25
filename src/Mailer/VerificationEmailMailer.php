<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;

class VerificationEmailMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $mailerFrom,
    ) {
    }

    public function sendVerificationEmail(User $user, VerifyEmailSignatureComponents $signatureComponents): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'Compendium'))
            ->to($user->getEmail())
            ->subject('Verify your Compendium account')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'name' => $user->getName(),
                'signedUrl' => $signatureComponents->getSignedUrl(),
                'expiresAt' => $signatureComponents->getExpiresAt(),
            ]);

        $this->mailer->send($email);
    }
}
