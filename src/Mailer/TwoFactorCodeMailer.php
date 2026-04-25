<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Entity\User;
use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class TwoFactorCodeMailer implements AuthCodeMailerInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $mailerFrom,
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
            ->from(new Address($this->mailerFrom, 'Compendium'))
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
