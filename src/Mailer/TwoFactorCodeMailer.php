<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Entity\User;
use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class TwoFactorCodeMailer implements AuthCodeMailerInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function sendAuthCode(TwoFactorInterface $user): void
    {
        $authCode = $user->getEmailAuthCode();
        if ($authCode === null) {
            return;
        }

        $name = $user instanceof User ? $user->getName() : null;

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@reading-stats.local', 'Reading Stats'))
            ->to($user->getEmailAuthRecipient())
            ->subject('Your Reading Stats login code')
            ->htmlTemplate('emails/2fa_code.html.twig')
            ->context([
                'name' => $name,
                'authCode' => $authCode,
            ]);

        $this->mailer->send($email);
    }
}
