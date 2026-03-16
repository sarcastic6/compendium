<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Tests\Functional\AbstractFunctionalTest;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Verifies that sensitive profile changes require the current password.
 *
 * Covers: Fix 4 — re-authentication for email and password changes.
 */
class ReauthenticationTest extends AbstractFunctionalTest
{
    private const PASSWORD = 'CorrectHorse99!';

    // --- Password change ---

    public function test_password_change_rejected_without_current_password(): void
    {
        $user = $this->createUser(password: self::PASSWORD);
        $userId = $user->getId();
        $originalHash = $user->getPasswordHash();

        $this->logIn($this->client, $user->getEmail(), self::PASSWORD);
        $this->client->followRedirect();

        $this->client->request('GET', '/profile/change-password');
        $this->submitFirstForm($this->client, [
            'change_password_form[currentPassword]' => '',
            'change_password_form[plainPassword][first]' => 'NewValidPass99!',
            'change_password_form[plainPassword][second]' => 'NewValidPass99!',
        ]);

        // Symfony 7 returns 422 when form-level validation (NotBlank) fails.
        $this->assertResponseStatusCodeSame(422);

        $this->em->clear();
        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertSame($originalHash, $fresh->getPasswordHash(), 'Password hash must not change when currentPassword is missing');
    }

    public function test_password_change_rejected_with_wrong_current_password(): void
    {
        $user = $this->createUser(password: self::PASSWORD);
        $userId = $user->getId();
        $originalHash = $user->getPasswordHash();

        $this->logIn($this->client, $user->getEmail(), self::PASSWORD);
        $this->client->followRedirect();

        $this->client->request('GET', '/profile/change-password');
        $this->submitFirstForm($this->client, [
            'change_password_form[currentPassword]' => 'WrongPassword99!',
            'change_password_form[plainPassword][first]' => 'NewValidPass99!',
            'change_password_form[plainPassword][second]' => 'NewValidPass99!',
        ]);

        // Form is valid but the controller rejects the wrong password and re-renders (200).
        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertSame($originalHash, $fresh->getPasswordHash(), 'Password hash must not change when currentPassword is wrong');
    }

    public function test_password_change_succeeds_with_correct_current_password(): void
    {
        $user = $this->createUser(password: self::PASSWORD);
        $userId = $user->getId();
        $originalHash = $user->getPasswordHash();

        $this->logIn($this->client, $user->getEmail(), self::PASSWORD);
        $this->client->followRedirect();

        $this->client->request('GET', '/profile/change-password');
        $this->submitFirstForm($this->client, [
            'change_password_form[currentPassword]' => self::PASSWORD,
            'change_password_form[plainPassword][first]' => 'NewValidPass99!',
            'change_password_form[plainPassword][second]' => 'NewValidPass99!',
        ]);

        $this->assertResponseRedirects('/profile');

        $this->em->clear();
        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertNotSame($originalHash, $fresh->getPasswordHash(), 'Password hash must change after a successful password change');

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue(
            $hasher->isPasswordValid($fresh, 'NewValidPass99!'),
            'New password must be valid after a successful password change',
        );
    }

    // --- Email change ---

    public function test_email_change_rejected_without_current_password(): void
    {
        $user = $this->createUser(email: 'original@example.com', password: self::PASSWORD);
        $userId = $user->getId();

        $this->logIn($this->client, $user->getEmail(), self::PASSWORD);
        $this->client->followRedirect();

        $this->client->request('GET', '/profile');
        $this->submitFirstForm($this->client, [
            'profile_form[name]' => $user->getName(),
            'profile_form[email]' => 'changed@example.com',
            'profile_form[currentPassword]' => '',
        ]);

        // The profile form has no NotBlank on currentPassword — controller re-renders with 200 + flash.
        $this->assertResponseIsSuccessful();

        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertSame('original@example.com', $fresh->getEmail(), 'Email must not change without correct current password');
    }

    // --- Name-only change (no password required) ---

    public function test_display_name_change_succeeds_without_current_password(): void
    {
        $user = $this->createUser(password: self::PASSWORD);
        $userId = $user->getId();

        $this->logIn($this->client, $user->getEmail(), self::PASSWORD);
        $this->client->followRedirect();

        $this->client->request('GET', '/profile');
        $this->submitFirstForm($this->client, [
            'profile_form[name]' => 'Updated Name',
            'profile_form[email]' => $user->getEmail(),
            'profile_form[currentPassword]' => '',
        ]);

        $this->assertResponseRedirects('/profile');

        $this->em->clear();
        $fresh = $this->em->find(User::class, $userId);
        $this->assertNotNull($fresh);
        $this->assertSame('Updated Name', $fresh->getName());
    }
}
