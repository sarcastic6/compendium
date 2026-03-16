<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Tests\Functional\AbstractFunctionalTest;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

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

        $this->em->clear();
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

        // KernelBrowser reboots the kernel (and therefore rebuilds the DI container) before each
        // request. disableReboot() prevents this so that the mock set below survives across the
        // two requests that follow.
        $this->client->disableReboot();

        // Replace TotpAuthenticatorInterface with a mock that accepts any code.
        // The test's purpose is the persistence flow (session secret → DB), not TOTP code
        // validation. Using the real TOTP library is timing-sensitive: a code generated at a
        // 30-second window boundary can expire before the POST is processed.
        $mockAuthenticator = $this->createStub(TotpAuthenticatorInterface::class);
        $mockAuthenticator->method('generateSecret')->willReturn('JBSWY3DPEHPK3PXP');
        $mockAuthenticator->method('getQRContent')->willReturn('otpauth://totp/test');
        $mockAuthenticator->method('checkCode')->willReturn(true);
        static::getContainer()->set(TotpAuthenticatorInterface::class, $mockAuthenticator);

        // Step 1 — load the enable page; the controller stores the pending secret in the session.
        $crawler = $this->client->request('GET', '/mfa/totp/enable');
        $this->assertResponseIsSuccessful();

        // Step 2 — submit any code; the mock accepts it unconditionally.
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $this->assertNotNull($csrfToken, 'Verify form must contain a _csrf_token field');

        $this->client->request('POST', '/mfa/totp/verify', [
            '_csrf_token' => $csrfToken,
            'code' => '000000',
        ]);

        // Step 3 — the controller must have flushed the encrypted secret to the DB.
        // $this->em was obtained at setUp() from the pre-reboot container; clear its identity
        // map so find() re-queries the database rather than returning the stale cached entity.
        $this->em->clear();
        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->getMfaSecret(), 'mfaSecret must be persisted to the DB after successful TOTP verification');
        $this->assertSame('totp', $fresh->getMfaMethods());
        $this->assertTrue($fresh->isMfaEnabled());
    }
}
