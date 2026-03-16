<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Tests\Functional\AbstractFunctionalTest;

/**
 * Verifies that all MFA POST endpoints reject requests missing a valid CSRF token.
 *
 * Covers: Fix 1 — CSRF validation on MFA endpoints.
 */
class MfaCsrfTest extends AbstractFunctionalTest
{
    // --- /mfa/totp/disable ---

    public function test_totp_disable_rejects_missing_csrf_token(): void
    {
        $user = $this->createUser();
        $this->logIn($this->client, $user->getEmail(), 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('POST', '/mfa/totp/disable', []);

        $this->assertResponseRedirects('/mfa');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function test_totp_disable_with_missing_csrf_does_not_change_db(): void
    {
        $user = $this->createUser();

        // Give the user TOTP so there's something that could be erroneously removed.
        $user->setMfaSecret('some_encrypted_secret');
        $user->setMfaMethods('totp');
        $user->setIsMfaEnabled(true);
        $this->em->flush();

        $userId = $user->getId();

        $this->logIn($this->client, $user->getEmail(), 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('POST', '/mfa/totp/disable', []);

        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->getMfaSecret(), 'mfaSecret must not be wiped on CSRF-rejected request');
        $this->assertSame('totp', $fresh->getMfaMethods());
    }

    // --- /mfa/email/enable ---

    public function test_email_enable_rejects_missing_csrf_token(): void
    {
        $user = $this->createUser();
        $this->logIn($this->client, $user->getEmail(), 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('POST', '/mfa/email/enable', []);

        $this->assertResponseRedirects('/mfa');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function test_email_enable_with_missing_csrf_does_not_change_db(): void
    {
        $user = $this->createUser();
        $userId = $user->getId();

        $this->logIn($this->client, $user->getEmail(), 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('POST', '/mfa/email/enable', []);

        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->getMfaMethods(), 'mfaMethods must not be set on CSRF-rejected request');
        $this->assertFalse($fresh->isMfaEnabled());
    }

    // --- /mfa/email/disable ---

    public function test_email_disable_rejects_missing_csrf_token(): void
    {
        $user = $this->createUser();
        $this->logIn($this->client, $user->getEmail(), 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('POST', '/mfa/email/disable', []);

        $this->assertResponseRedirects('/mfa');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function test_email_disable_with_missing_csrf_does_not_change_db(): void
    {
        $user = $this->createUser();
        $user->setMfaMethods('email');
        $user->setIsMfaEnabled(true);
        $this->em->flush();

        $userId = $user->getId();

        $this->logIn($this->client, $user->getEmail(), 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('POST', '/mfa/email/disable', []);

        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertSame('email', $fresh->getMfaMethods(), 'mfaMethods must not be cleared on CSRF-rejected request');
    }

    // --- /mfa/totp/verify ---

    public function test_totp_verify_rejects_missing_csrf_token(): void
    {
        $user = $this->createUser();
        $this->logIn($this->client, $user->getEmail(), 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('POST', '/mfa/totp/verify', ['code' => '123456']);

        $this->assertResponseRedirects('/mfa');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }
}
