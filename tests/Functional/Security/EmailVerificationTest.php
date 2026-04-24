<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Tests\Functional\AbstractFunctionalTest;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class EmailVerificationTest extends AbstractFunctionalTest
{
    public function test_unverified_user_cannot_log_in(): void
    {
        $this->createUser('unverified@example.com', 'Unverified', 'CorrectHorse99!', verified: false);

        $this->logIn($this->client, 'unverified@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.flash-error');
    }

    public function test_valid_verification_link_verifies_user(): void
    {
        $user = $this->createUser('new@example.com', 'New User', 'CorrectHorse99!', verified: false);

        /** @var VerifyEmailHelperInterface $helper */
        $helper = static::getContainer()->get(VerifyEmailHelperInterface::class);

        $signatureComponents = $helper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()],
        );

        $this->client->request('GET', $signatureComponents->getSignedUrl());
        $this->client->followRedirect();

        $this->assertRouteSame('app_login');
        $this->assertSelectorExists('.flash-success');

        // User can now log in
        $this->logIn($this->client, 'new@example.com', 'CorrectHorse99!');
        $this->assertResponseRedirects('/reading-entries');
    }

    public function test_invalid_verification_link_is_rejected(): void
    {
        $user = $this->createUser('tampered@example.com', 'Tampered', 'CorrectHorse99!', verified: false);

        $this->client->request('GET', '/verify-email?id=' . $user->getId() . '&token=invalid&expires=9999999999');
        $this->client->followRedirect();

        $this->assertSelectorExists('.flash-error');

        // User remains unable to log in
        $this->logIn($this->client, 'tampered@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();
        $this->assertSelectorExists('.flash-error');
    }
}
