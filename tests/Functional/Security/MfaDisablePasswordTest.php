<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Service\TotpSecretEncryptionService;
use App\Tests\Functional\AbstractFunctionalTest;

/**
 * Verifies that disabling MFA (TOTP or email) requires the user's current password.
 * A wrong password must be rejected and leave MFA state unchanged in the database.
 */
class MfaDisablePasswordTest extends AbstractFunctionalTest
{
    // -------------------------------------------------------------------------
    // TOTP disable
    // -------------------------------------------------------------------------

    public function test_totp_disable_rejects_wrong_password(): void
    {
        $user = $this->createUserWithTotp();

        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/mfa');
        $token = $crawler->filter('form[action*="totp/disable"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/mfa/totp/disable', [
            '_token' => $token,
            'current_password' => 'WrongPassword99!',
        ]);

        $this->assertResponseRedirects('/mfa');
        $this->client->followRedirect();
        $this->assertSelectorExists('.flash-error');
    }

    public function test_totp_disable_with_wrong_password_does_not_change_db(): void
    {
        $user = $this->createUserWithTotp();
        $userId = $user->getId();

        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/mfa');
        $token = $crawler->filter('form[action*="totp/disable"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/mfa/totp/disable', [
            '_token' => $token,
            'current_password' => 'WrongPassword99!',
        ]);

        $this->em->clear();
        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->getMfaSecret(), 'mfaSecret must not be wiped when wrong password is submitted');
        $this->assertSame('totp', $fresh->getMfaMethods());
        $this->assertTrue($fresh->isMfaEnabled());
    }

    // -------------------------------------------------------------------------
    // Email MFA disable
    // -------------------------------------------------------------------------

    public function test_email_disable_rejects_wrong_password(): void
    {
        $user = $this->createUserWithEmailMfa();

        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/mfa');
        $token = $crawler->filter('form[action*="email/disable"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/mfa/email/disable', [
            '_token' => $token,
            'current_password' => 'WrongPassword99!',
        ]);

        $this->assertResponseRedirects('/mfa');
        $this->client->followRedirect();
        $this->assertSelectorExists('.flash-error');
    }

    public function test_email_disable_with_wrong_password_does_not_change_db(): void
    {
        $user = $this->createUserWithEmailMfa();
        $userId = $user->getId();

        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/mfa');
        $token = $crawler->filter('form[action*="email/disable"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/mfa/email/disable', [
            '_token' => $token,
            'current_password' => 'WrongPassword99!',
        ]);

        $this->em->clear();
        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertSame('email', $fresh->getMfaMethods(), 'mfaMethods must not be cleared when wrong password is submitted');
        $this->assertTrue($fresh->isMfaEnabled());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUserWithTotp(): User
    {
        /** @var TotpSecretEncryptionService $encryptionService */
        $encryptionService = static::getContainer()->get(TotpSecretEncryptionService::class);

        $user = $this->createUser();
        $user->setMfaSecret($encryptionService->encrypt('TESTSECRETABCDEFGH'));
        $user->setMfaMethods('totp');
        $user->setIsMfaEnabled(true);
        $this->em->flush();

        // Refresh from DB so the PostLoad listener decrypts mfaSecret into
        // decryptedMfaSecret. Without this, isTotpAuthenticationEnabled() returns
        // false (decryptedMfaSecret is null on the in-memory entity) and the
        // controller does not render the disable form.
        $this->em->refresh($user);

        return $user;
    }

    private function createUserWithEmailMfa(): User
    {
        $user = $this->createUser();
        $user->setMfaMethods('email');
        $user->setIsMfaEnabled(true);
        $this->em->flush();

        return $user;
    }

}
