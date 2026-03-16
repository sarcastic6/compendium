<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\WorkFormDto;
use App\Entity\Metadata;
use App\Entity\MetadataSourceLink;
use App\Entity\MetadataType;
use App\Entity\Series;
use App\Entity\SeriesSourceLink;
use App\Entity\Work;
use App\Enum\SourceType;
use App\Repository\MetadataRepository;
use App\Repository\MetadataTypeRepository;
use App\Repository\SeriesRepository;
use Doctrine\ORM\EntityManagerInterface;

class WorkService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MetadataRepository $metadataRepository,
        private readonly MetadataTypeRepository $metadataTypeRepository,
        private readonly SeriesRepository $seriesRepository,
    ) {
    }

    /**
     * Creates a new Work from the DTO, finding or creating related entities as needed.
     * Authors, metadata, and series are all resolved within a single transaction.
     *
     * @throws \InvalidArgumentException if a metadata type enforces single values but multiple are submitted
     */
    public function createWork(WorkFormDto $dto): Work
    {
        $work = new Work($dto->type, $dto->title);
        $work->setSummary($dto->summary);
        $work->setPlaceInSeries($dto->placeInSeries);
        $work->setLanguage($dto->language);
        $work->setPublishedDate($dto->publishedDate);
        $work->setLastUpdatedDate($dto->lastUpdatedDate);
        $work->setWords($dto->words);
        $work->setChapters($dto->chapters);
        $work->setLink($dto->link);
        $work->setSourceType($dto->sourceType);
        $work->setStarred($dto->starred);

        if ($dto->seriesName !== null && trim($dto->seriesName) !== '') {
            $series = $this->findOrCreateSeries(trim($dto->seriesName), $dto->seriesUrl, $dto->sourceType);
            $work->setSeries($series);
        }

        // Authors are stored as metadata with type='Author'. Build a combined entry list
        // so all metadata (including authors) flows through the same findOrCreate path.
        $allMetadata = $dto->metadata;
        if ($dto->authors !== []) {
            $authorType = $this->findOrCreateAuthorType();
            foreach ($dto->authors as $authorEntry) {
                $name = trim((string) ($authorEntry['name'] ?? ''));
                if ($name !== '') {
                    $allMetadata[] = [
                        'metadataType' => $authorType,
                        'name' => $name,
                        'link' => $authorEntry['link'] ?? null,
                    ];
                }
            }
        }

        $this->applyMetadata($work, $allMetadata, $dto->sourceType);

        $this->entityManager->persist($work);
        $this->entityManager->flush();

        return $work;
    }

    private function findOrCreateSeries(string $name, ?string $sourceUrl, SourceType $workSourceType): Series
    {
        $series = $this->seriesRepository->findOneBy(['name' => $name]);
        if ($series === null) {
            $series = new Series($name);
            $this->entityManager->persist($series);
        }

        if ($sourceUrl !== null && $sourceUrl !== '') {
            $this->upsertSeriesSourceLink($series, $workSourceType, $sourceUrl);
        }

        return $series;
    }

    /**
     * Finds or creates the 'Author' MetadataType.
     * The Author type is not seeded — it is created at runtime on first use.
     * multiple_allowed = true: a work can have multiple authors.
     */
    private function findOrCreateAuthorType(): MetadataType
    {
        $type = $this->metadataTypeRepository->findOneBy(['name' => 'Author']);
        if ($type === null) {
            $type = new MetadataType('Author', true);
            $this->entityManager->persist($type);
        }

        return $type;
    }

    /**
     * @param array<int, array{metadataType: MetadataType, name: string, link: string|null}> $metadataEntries
     */
    private function applyMetadata(Work $work, array $metadataEntries, SourceType $workSourceType): void
    {
        // Track how many entries we're adding per type for multipleAllowed enforcement
        $typeCount = [];

        foreach ($metadataEntries as $entry) {
            $metadataType = $entry['metadataType'] ?? null;
            $name = isset($entry['name']) ? trim((string) $entry['name']) : '';
            $sourceUrl = isset($entry['link']) && $entry['link'] !== '' ? (string) $entry['link'] : null;

            if (!($metadataType instanceof MetadataType) || $name === '') {
                continue;
            }

            // spl_object_id() is used instead of getId() because new (unpersisted) MetadataType objects
            // have getId() === null, which would cause all new types to share the same tracking key.
            $typeId = spl_object_id($metadataType);

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

            $work->addMetadata($this->findOrCreateMetadata($name, $metadataType, $sourceUrl, $workSourceType));
        }
    }

    private function findOrCreateMetadata(
        string $name,
        MetadataType $type,
        ?string $sourceUrl,
        SourceType $workSourceType,
    ): Metadata {
        $existing = $this->metadataRepository->findOneBy([
            'name' => $name,
            'metadataType' => $type,
        ]);

        if ($existing !== null) {
            if ($sourceUrl !== null) {
                $this->upsertMetadataSourceLink($existing, $workSourceType, $sourceUrl);
            }

            return $existing;
        }

        $metadata = new Metadata($name, $type);
        $this->entityManager->persist($metadata);

        if ($sourceUrl !== null) {
            $this->upsertMetadataSourceLink($metadata, $workSourceType, $sourceUrl);
        }

        return $metadata;
    }

    private function upsertMetadataSourceLink(Metadata $metadata, SourceType $sourceType, string $url): void
    {
        foreach ($metadata->getSourceLinks() as $existing) {
            if ($existing->getSourceType() === $sourceType) {
                $existing->setLink($url);

                return;
            }
        }

        $sourceLink = new MetadataSourceLink($metadata, $sourceType, $url);
        $metadata->addSourceLink($sourceLink);
        $this->entityManager->persist($sourceLink);
    }

    private function upsertSeriesSourceLink(Series $series, SourceType $sourceType, string $url): void
    {
        foreach ($series->getSourceLinks() as $existing) {
            if ($existing->getSourceType() === $sourceType) {
                $existing->setLink($url);

                return;
            }
        }

        $sourceLink = new SeriesSourceLink($series, $sourceType, $url);
        $series->addSourceLink($sourceLink);
        $this->entityManager->persist($sourceLink);
    }
}
