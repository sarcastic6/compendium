<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Enum\UserRole;
use App\Tests\Functional\AbstractFunctionalTest;

class AccessControlTest extends AbstractFunctionalTest
{
    public function test_anonymous_user_redirected_from_reading_list(): void
    {
        $this->client->request('GET', '/reading-entries');
        $this->assertResponseRedirects('/login');
    }

    public function test_anonymous_user_redirected_from_admin(): void
    {
        $this->client->request('GET', '/admin');
        // Anonymous redirects to login, not 403
        $this->assertResponseRedirects('/login');
    }

    public function test_authenticated_user_can_access_reading_list(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries');
        $this->assertResponseIsSuccessful();
    }

    public function test_non_admin_gets_403_on_admin_routes(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/admin');
        $this->assertResponseStatusCodeSame(403);
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $this->createUser('admin@example.com', 'Admin', 'CorrectHorse99!', UserRole::Admin);
        $this->logIn($this->client, 'admin@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();
    }

    public function test_login_and_register_pages_accessible_anonymously(): void
    {
        $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/register');
        $this->assertResponseIsSuccessful();
    }
}
