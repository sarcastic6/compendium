<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\TotpSecretEncryptionService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Decrypts the TOTP secret from the database into a transient property on User load.
 *
 * The database stores the TOTP secret encrypted. This listener fires on postLoad and decrypts
 * it into User::$decryptedMfaSecret so that getTotpAuthenticationConfiguration() can return
 * the plain secret without the entity needing to know about the encryption service.
 */
#[AsEntityListener(event: Events::postLoad, entity: User::class)]
class UserTotpDecryptListener
{
    public function __construct(
        private readonly TotpSecretEncryptionService $encryptionService,
    ) {
    }

    public function postLoad(User $user): void
    {
        $encryptedSecret = $user->getMfaSecret();
        if ($encryptedSecret === null) {
            return;
        }

        try {
            $user->setDecryptedMfaSecret($this->encryptionService->decrypt($encryptedSecret));
        } catch (\RuntimeException) {
            // Decryption failure (e.g., key rotation in progress).
            // Leave decryptedMfaSecret as null — TOTP will be unavailable until re-setup.
        }
    }
}
