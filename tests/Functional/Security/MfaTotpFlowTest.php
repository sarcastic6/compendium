<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Service\TotpSecretEncryptionService;
use App\Tests\Functional\AbstractFunctionalTest;
use OTPHP\TOTP;

/**
 * Verifies the TOTP setup session flow: the secret is kept in the session until
 * the user verifies a code, and only then written to the database.
 *
 * Covers: Fix 3 — TOTP secret not persisted to DB until after verification.
 */
class MfaTotpFlowTest extends AbstractFunctionalTest
{
    public function test_totp_secret_not_in_database_before_verification(): void
    {
        $user = $this->createUser();
        $userId = $user->getId();

        $this->logIn($this->client, $user->getEmail(), 'CorrectHorse99!');
        $this->client->followRedirect();

        // Visiting the enable page generates a pending secret in the session but does NOT flush to DB.
        $this->client->request('GET', '/mfa/totp/enable');
        $this->assertResponseIsSuccessful();

        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->getMfaSecret(), 'mfaSecret must remain NULL in the DB before the user verifies a TOTP code');
    }

    public function test_totp_secret_persisted_after_successful_verification(): void
    {
        $user = $this->createUser();
        $userId = $user->getId();

        $this->logIn($this->client, $user->getEmail(), 'CorrectHorse99!');
        $this->client->followRedirect();

        // Step 1 — load the enable page so the controller stores the pending secret in the session.
        $crawler = $this->client->request('GET', '/mfa/totp/enable');
        $this->assertResponseIsSuccessful();

        // Step 2 — read the encrypted pending secret from the session, decrypt it, and generate a valid code.
        $session = $this->client->getRequest()->getSession();
        $encryptedPending = $session->get('pending_totp_secret');
        $this->assertNotNull($encryptedPending, 'pending_totp_secret must be in the session after visiting /mfa/totp/enable');

        /** @var TotpSecretEncryptionService $encryptionService */
        $encryptionService = static::getContainer()->get(TotpSecretEncryptionService::class);
        $plainSecret = $encryptionService->decrypt((string) $encryptedPending);

        $validCode = TOTP::create($plainSecret)->now();

        // Step 3 — read the CSRF token from the verify form and submit with the valid code.
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $this->assertNotNull($csrfToken, 'Verify form must contain a _csrf_token field');

        $this->client->request('POST', '/mfa/totp/verify', [
            '_csrf_token' => $csrfToken,
            'code' => $validCode,
        ]);

        // Step 4 — the controller must have flushed the encrypted secret to the DB.
        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->getMfaSecret(), 'mfaSecret must be persisted to the DB after successful TOTP verification');
        $this->assertSame('totp', $fresh->getMfaMethods());
        $this->assertTrue($fresh->isMfaEnabled());
    }
}
