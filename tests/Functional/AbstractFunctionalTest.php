<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\MetadataType;
use App\Entity\Status;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AbstractFunctionalTest extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Drop and recreate the schema for a clean state on each test
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function createUser(
        string $email = 'user@example.com',
        string $name = 'Test User',
        string $password = 'CorrectHorse99!',
        UserRole $role = UserRole::User,
        bool $disabled = false,
    ): User {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User($name, $email, '');
        $user->setPasswordHash($hasher->hashPassword($user, $password));
        $user->setRole($role);
        $user->setIsDisabled($disabled);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function createStatus(string $name = 'Reading', bool $hasBeenStarted = true, bool $countsAsRead = false): Status
    {
        $status = new Status($name, $hasBeenStarted, $countsAsRead);
        $this->em->persist($status);
        $this->em->flush();

        return $status;
    }

    protected function createMetadataType(string $name, bool $multipleAllowed = true): MetadataType
    {
        $type = new MetadataType($name, $multipleAllowed);
        $this->em->persist($type);
        $this->em->flush();

        return $type;
    }

    /**
     * Logs in by POSTing directly to the login endpoint.
     * More reliable than submitForm() which requires matching button text through translations.
     */
    protected function logIn(KernelBrowser $client, string $email, string $password): void
    {
        $crawler = $client->request('GET', '/login');
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/login', [
            '_username' => $email,
            '_password' => $password,
            '_remember_me' => false,
            '_csrf_token' => $csrfToken,
        ]);
    }

    /**
     * Submits the first form on the current page by posting its action URL directly.
     * Use this to avoid issues with translated button text in submitForm().
     *
     * @param array<string, mixed> $formData
     */
    protected function submitFirstForm(KernelBrowser $client, array $formData): void
    {
        $crawler = $client->getCrawler();
        $form = $crawler->filter('form')->first()->form($formData);
        $client->submit($form);
    }
}
