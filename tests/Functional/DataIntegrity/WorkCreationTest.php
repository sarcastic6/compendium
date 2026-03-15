<?php

declare(strict_types=1);

namespace App\Tests\Functional\DataIntegrity;

use App\Entity\Author;
use App\Entity\Metadata;
use App\Entity\Work;
use App\Enum\WorkType;
use App\Repository\AuthorRepository;
use App\Tests\Functional\AbstractFunctionalTest;

class WorkCreationTest extends AbstractFunctionalTest
{
    public function test_work_with_minimal_fields_persists(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/work/new');
        $this->submitFirstForm($this->client, [
            'work_form[type]' => 'Book',
            'work_form[title]' => 'My Test Book',
        ]);

        // Should redirect to reading entry creation
        $this->assertResponseRedirects();

        $this->em->clear();
        $work = $this->em->getRepository(Work::class)->findOneBy(['title' => 'My Test Book']);
        $this->assertNotNull($work);
        $this->assertSame(WorkType::Book, $work->getType());
    }

    public function test_duplicate_author_names_reuse_existing_author(): void
    {
        $existing = new Author('Jane Doe');
        $this->em->persist($existing);
        $this->em->flush();

        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/work/new');
        $csrfToken = $crawler->filter('input[name="work_form[_token]"]')->attr('value');
        $this->client->request('POST', '/work/new', [
            'work_form' => [
                'type' => 'Book',
                'title' => 'Book with Existing Author',
                'sourceType' => 'Manual',
                'authors' => ['Jane Doe'],
                '_token' => $csrfToken,
            ],
        ]);

        $this->em->clear();
        /** @var AuthorRepository $authorRepo */
        $authorRepo = $this->em->getRepository(Author::class);
        $authors = $authorRepo->findBy(['name' => 'Jane Doe']);
        $this->assertCount(1, $authors);

        $work = $this->em->getRepository(Work::class)->findOneBy(['title' => 'Book with Existing Author']);
        $this->assertNotNull($work);
        $this->assertCount(1, $work->getAuthors());
    }

    public function test_metadata_is_created_and_attached_to_work(): void
    {
        $pairingType = $this->createMetadataType('Pairing');

        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/work/new');
        $csrfToken = $crawler->filter('input[name="work_form[_token]"]')->attr('value');
        $this->client->request('POST', '/work/new', [
            'work_form' => [
                'type' => 'Fanfiction',
                'title' => 'Fic with Pairing',
                'sourceType' => 'Manual',
                'metadata' => [
                    ['metadataType' => $pairingType->getId(), 'name' => 'Character A/Character B'],
                ],
                '_token' => $csrfToken,
            ],
        ]);

        $this->em->clear();
        $work = $this->em->getRepository(Work::class)->findOneBy(['title' => 'Fic with Pairing']);
        $this->assertNotNull($work);
        $this->assertCount(1, $work->getMetadata());

        $metadata = $work->getMetadata()->first();
        $this->assertSame('Character A/Character B', $metadata->getName());
        $this->assertSame('Pairing', $metadata->getMetadataType()->getName());
    }

    public function test_duplicate_metadata_reuses_existing_metadata_entry(): void
    {
        $pairingType = $this->createMetadataType('Pairing');

        $existing = new Metadata('Character A/Character B', $pairingType);
        $this->em->persist($existing);
        $this->em->flush();
        $existingId = $existing->getId();

        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/work/new');
        $csrfToken = $crawler->filter('input[name="work_form[_token]"]')->attr('value');
        $this->client->request('POST', '/work/new', [
            'work_form' => [
                'type' => 'Fanfiction',
                'title' => 'Another Fic',
                'sourceType' => 'Manual',
                'metadata' => [
                    ['metadataType' => $pairingType->getId(), 'name' => 'Character A/Character B'],
                ],
                '_token' => $csrfToken,
            ],
        ]);

        $this->em->clear();
        $all = $this->em->getRepository(Metadata::class)->findBy(['name' => 'Character A/Character B']);
        $this->assertCount(1, $all);
        $this->assertSame($existingId, $all[0]->getId());
    }
}
