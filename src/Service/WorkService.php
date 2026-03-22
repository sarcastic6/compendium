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

        // Three-way series resolution:
        // 1. seriesId set   → user selected an existing series from autocomplete; load by ID.
        // 2. seriesId null + seriesName non-empty → user typed a new name; find-or-create.
        // 3. Both null/empty → no series.
        if ($dto->seriesId !== null) {
            $series = $this->seriesRepository->find($dto->seriesId);
            if ($series === null) {
                // Non-existent ID means form tampering or the series was deleted between page
                // load and submit. Silently falling back to find-or-create would mask this.
                throw new \InvalidArgumentException(sprintf(
                    'Series with ID %d not found. It may have been deleted.',
                    $dto->seriesId,
                ));
            }
            $work->setSeries($series);
        } elseif ($dto->seriesName !== null && trim($dto->seriesName) !== '') {
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
     *
     * Public because the controller calls this at form render time (not just submit time)
     * to obtain the type ID for the author autocomplete URL. When a new entity is created,
     * we flush immediately so getId() returns a valid integer before this method returns.
     * This is a one-time operation — once the Author type exists, subsequent calls hit the
     * find path and skip the flush.
     */
    public function findOrCreateAuthorType(): MetadataType
    {
        $type = $this->metadataTypeRepository->findOneBy(['name' => 'Author']);
        if ($type !== null) {
            return $type;
        }

        $type = new MetadataType('Author', true);
        $this->entityManager->persist($type);
        // Flush immediately so getId() is populated before returning.
        // Without this, the template receives typeId=null and the autocomplete URL is broken.
        $this->entityManager->flush();

        return $type;
    }

    /**
     * Returns the names of DTO metadata entries that belong to checkbox types, grouped by
     * MetadataType ID. Used to pre-check checkboxes when the form renders with pre-populated
     * data (AO3 import or POST re-render after validation failure).
     *
     * This method is read-only: it only inspects $dtoMetadata, never mutates it.
     * The caller is responsible for removing the matched entries from $dto->metadata
     * to prevent them from also appearing as autocomplete chips.
     *
     * @param array<int, array{metadataType: MetadataType, name: string, link: string|null}> $dtoMetadata
     * @param MetadataType[] $checkboxTypes
     * @return array<int, string[]>
     */
    public function resolveCheckboxPreselections(array $dtoMetadata, array $checkboxTypes): array
    {
        $checkboxTypeIds = [];
        foreach ($checkboxTypes as $type) {
            $id = $type->getId();
            if ($id !== null) {
                $checkboxTypeIds[$id] = true;
            }
        }

        $result = [];
        foreach ($dtoMetadata as $entry) {
            $type = $entry['metadataType'] ?? null;
            if (!($type instanceof MetadataType)) {
                continue;
            }
            $typeId = $type->getId();
            if ($typeId === null || !isset($checkboxTypeIds[$typeId])) {
                continue;
            }
            $name = trim((string) ($entry['name'] ?? ''));
            if ($name !== '') {
                $result[$typeId][] = $name;
            }
        }

        return $result;
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
