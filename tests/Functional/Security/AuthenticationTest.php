<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Tests\Functional\AbstractFunctionalTest;

class AuthenticationTest extends AbstractFunctionalTest
{
    public function test_login_with_valid_credentials_redirects_to_list(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');

        $this->assertResponseRedirects('/reading-entries');
    }

    public function test_login_with_wrong_password_shows_error(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');

        $this->logIn($this->client, 'alice@example.com', 'WrongPassword!');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.flash-error');
    }

    public function test_login_with_unknown_email_shows_error(): void
    {
        $this->logIn($this->client, 'nobody@example.com', 'SomePassword!');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.flash-error');
    }

    public function test_disabled_user_cannot_log_in(): void
    {
        $this->createUser('disabled@example.com', 'Disabled', 'CorrectHorse99!', disabled: true);

        $this->logIn($this->client, 'disabled@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.flash-error');
    }

    public function test_logout_redirects_to_login(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/logout');
        $this->assertResponseRedirects();
        $this->client->followRedirect();

        $this->assertRouteSame('app_login');
    }
}
