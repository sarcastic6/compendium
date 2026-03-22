<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\Metadata;
use App\Entity\MetadataType;
use App\Entity\Series;
use App\Tests\Functional\AbstractFunctionalTest;

/**
 * Security tests for API search endpoints.
 *
 * Ensures unauthenticated requests are rejected and authenticated users
 * receive correct results from the metadata and series search APIs.
 */
class ApiSearchAuthTest extends AbstractFunctionalTest
{
    public function test_metadata_search_rejects_unauthenticated_request(): void
    {
        $this->client->request('GET', '/api/metadata/search?q=test&typeId=1');
        $this->assertResponseRedirects('/login');
    }

    public function test_series_search_rejects_unauthenticated_request(): void
    {
        $this->client->request('GET', '/api/series/search?q=test');
        $this->assertResponseRedirects('/login');
    }

    public function test_metadata_search_returns_results_for_authenticated_user(): void
    {
        $this->createUser();
        $this->logIn($this->client, 'user@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $type = $this->createMetadataType('Fandom');
        $metadata = new Metadata('Harry Potter', $type);
        $this->em->persist($metadata);
        $this->em->flush();

        $this->client->request('GET', '/api/metadata/search?q=Harry&typeId=' . $type->getId());

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Harry Potter', $data[0]['name']);
    }

    public function test_series_search_returns_results_for_authenticated_user(): void
    {
        $this->createUser();
        $this->logIn($this->client, 'user@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $series = new Series('The Lord of the Rings');
        $this->em->persist($series);
        $this->em->flush();

        $this->client->request('GET', '/api/series/search?q=Lord');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('The Lord of the Rings', $data[0]['name']);
    }

    public function test_metadata_search_returns_empty_for_short_query(): void
    {
        $this->createUser();
        $this->logIn($this->client, 'user@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $type = $this->createMetadataType('Fandom');
        $metadata = new Metadata('Harry Potter', $type);
        $this->em->persist($metadata);
        $this->em->flush();

        $this->client->request('GET', '/api/metadata/search?q=H&typeId=' . $type->getId());

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(0, $data);
    }

    public function test_metadata_search_returns_empty_for_invalid_type_id(): void
    {
        $this->createUser();
        $this->logIn($this->client, 'user@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/api/metadata/search?q=test&typeId=99999');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(0, $data);
    }

    public function test_series_search_returns_empty_for_short_query(): void
    {
        $this->createUser();
        $this->logIn($this->client, 'user@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $series = new Series('The Lord of the Rings');
        $this->em->persist($series);
        $this->em->flush();

        $this->client->request('GET', '/api/series/search?q=T');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(0, $data);
    }
}
