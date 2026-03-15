<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class AuthFailureListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $passport = $event->getPassport();

        $identifier = $passport?->getUser()?->getUserIdentifier() ?? 'unknown';

        $this->logger->warning('Authentication failure', [
            'identifier' => $identifier,
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);
    }
}
