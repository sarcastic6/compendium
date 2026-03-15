<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\WorkFormDto;
use App\Entity\Author;
use App\Entity\Metadata;
use App\Entity\MetadataType;
use App\Entity\Work;
use App\Repository\AuthorRepository;
use App\Repository\MetadataRepository;
use App\Repository\MetadataTypeRepository;
use Doctrine\ORM\EntityManagerInterface;

class WorkService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorRepository $authorRepository,
        private readonly MetadataRepository $metadataRepository,
        private readonly MetadataTypeRepository $metadataTypeRepository,
    ) {
    }

    /**
     * Creates a new Work from the DTO, finding or creating Authors and Metadata as needed.
     *
     * @throws \InvalidArgumentException if a metadata type enforces single values but multiple are submitted
     */
    public function createWork(WorkFormDto $dto): Work
    {
        $work = new Work($dto->type, $dto->title);
        $work->setSummary($dto->summary);
        $work->setSeries($dto->series);
        $work->setPlaceInSeries($dto->placeInSeries);
        $work->setLanguage($dto->language);
        $work->setPublishedDate($dto->publishedDate);
        $work->setLastUpdatedDate($dto->lastUpdatedDate);
        $work->setWords($dto->words);
        $work->setChapters($dto->chapters);
        $work->setLink($dto->link);
        $work->setSourceType($dto->sourceType);
        $work->setStarred($dto->starred);

        foreach ($dto->authors as $authorName) {
            $name = trim((string) $authorName);
            if ($name === '') {
                continue;
            }
            $work->addAuthor($this->findOrCreateAuthor($name));
        }

        $this->applyMetadata($work, $dto->metadata);

        $this->entityManager->persist($work);
        $this->entityManager->flush();

        return $work;
    }

    /**
     * @param array<int, array{metadataType: MetadataType, name: string}> $metadataEntries
     */
    private function applyMetadata(Work $work, array $metadataEntries): void
    {
        // Track how many entries we're adding per type for multipleAllowed enforcement
        $typeCount = [];

        foreach ($metadataEntries as $entry) {
            $metadataType = $entry['metadataType'] ?? null;
            $name = isset($entry['name']) ? trim((string) $entry['name']) : '';

            if (!($metadataType instanceof MetadataType) || $name === '') {
                continue;
            }

            $typeId = $metadataType->getId();

            // Enforce MetadataType.multipleAllowed — application-level constraint because
            // the uniqueness is per-work-per-type, which can't be expressed as a simple DB constraint.
            if (!$metadataType->isMultipleAllowed()) {
                if (isset($typeCount[$typeId]) && $typeCount[$typeId] > 0) {
                    throw new \InvalidArgumentException(sprintf(
                        'Metadata type "%s" does not allow multiple values per work.',
                        $metadataType->getName(),
                    ));
                }
            }

            $typeCount[$typeId] = ($typeCount[$typeId] ?? 0) + 1;

            $work->addMetadata($this->findOrCreateMetadata($name, $metadataType));
        }
    }

    private function findOrCreateAuthor(string $name): Author
    {
        $existing = $this->authorRepository->findOneBy(['name' => $name]);
        if ($existing !== null) {
            return $existing;
        }

        $author = new Author($name);
        $this->entityManager->persist($author);

        return $author;
    }

    private function findOrCreateMetadata(string $name, MetadataType $type): Metadata
    {
        $existing = $this->metadataRepository->findOneBy([
            'name' => $name,
            'metadataType' => $type,
        ]);
        if ($existing !== null) {
            return $existing;
        }

        $metadata = new Metadata($name, $type);
        $this->entityManager->persist($metadata);

        return $metadata;
    }
}
