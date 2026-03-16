<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ImportResult;
use App\Dto\WorkFormDto;
use App\Entity\Language;
use App\Entity\MetadataType;
use App\Enum\SourceType;
use App\Enum\WorkType;
use App\Repository\LanguageRepository;
use App\Repository\MetadataTypeRepository;
use App\Scraper\ScrapedWorkDto;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Maps a ScrapedWorkDto to a WorkFormDto with entity lookups.
 *
 * Mapping strategy:
 * - Scalar fields copied directly (title, summary, words, chapters, dates, link, sourceType)
 * - Authors: copied directly to dto->authors; WorkService translates to metadata with type='Author'
 * - Language: looked up by name; auto-created with informational notice if not found
 * - Series: name + URL passed as raw strings; WorkService does find-or-create + source link upsert
 * - Metadata: AO3 category names mapped to MetadataType names via synonym map;
 *             auto-created with informational notice if MetadataType not found in database
 */
class ImportService
{
    /**
     * Maps AO3 category names to the MetadataType names used in the application.
     * Keys are the category names from ScrapedWorkDto.metadata.
     * Values are the expected MetadataType.name values created by the admin.
     */
    private const CATEGORY_SYNONYM_MAP = [
        'Relationship' => 'Pairing',
    ];

    public function __construct(
        private readonly LanguageRepository $languageRepository,
        private readonly MetadataTypeRepository $metadataTypeRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function mapToWorkFormDto(ScrapedWorkDto $scraped): ImportResult
    {
        $dto = new WorkFormDto();
        $warnings = [];

        // Scalar fields
        $dto->title = $scraped->title;
        $dto->summary = $scraped->summary;
        $dto->words = $scraped->words;
        $dto->chapters = $scraped->chapters;
        $dto->link = $scraped->sourceUrl;

        // Dates
        $dto->publishedDate = $this->parseDate($scraped->publishedDate);
        $dto->lastUpdatedDate = $this->parseDate($scraped->lastUpdatedDate);

        // Source type
        if ($scraped->sourceType !== null) {
            $sourceType = SourceType::tryFrom($scraped->sourceType);
            if ($sourceType !== null) {
                $dto->sourceType = $sourceType;
            }
        }

        // Work type
        if ($scraped->workType !== null) {
            $workType = WorkType::tryFrom($scraped->workType);
            if ($workType !== null) {
                $dto->type = $workType;
            }
        }

        // Authors
        foreach ($scraped->authors as $authorEntry) {
            $name = trim($authorEntry['name']);
            if ($name !== '') {
                $dto->authors[] = ['name' => $name, 'link' => $authorEntry['link']];
            }
        }

        // Language — find or auto-create if not found
        if ($scraped->language !== null) {
            $language = $this->languageRepository->findOneBy(['name' => $scraped->language]);
            if ($language === null) {
                $language = new Language($scraped->language);
                $this->entityManager->persist($language);
                $this->entityManager->flush();
                $warnings[] = sprintf(
                    "Language '%s' was auto-created during import.",
                    $scraped->language,
                );
            }
            $dto->language = $language;
        }

        // Series — pass name + URL as raw strings; WorkService does find-or-create + source link upsert
        if ($scraped->seriesName !== null) {
            $dto->seriesName = $scraped->seriesName;
            $dto->seriesUrl = $scraped->seriesUrl;
            $dto->placeInSeries = $scraped->placeInSeries;
        }

        // Metadata
        foreach ($scraped->metadata as $categoryName => $tagNames) {
            // Resolve category name through synonym map
            $typeName = self::CATEGORY_SYNONYM_MAP[$categoryName] ?? $categoryName;

            $metadataType = $this->metadataTypeRepository->findOneBy(['name' => $typeName]);
            if ($metadataType === null) {
                $metadataType = new MetadataType($typeName, true);
                $this->entityManager->persist($metadataType);
                $this->entityManager->flush();
                $warnings[] = sprintf(
                    "Metadata type '%s' was auto-created during import.",
                    $typeName,
                );
            }

            foreach ($tagNames as $tagEntry) {
                $name = trim((string) $tagEntry['name']);
                if ($name === '') {
                    continue;
                }

                $dto->metadata[] = [
                    'metadataType' => $metadataType,
                    'name' => $name,
                    'link' => $tagEntry['link'],
                ];
            }
        }

        return new ImportResult($dto, $warnings);
    }

    private function parseDate(?string $dateString): ?\DateTimeImmutable
    {
        if ($dateString === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateString);

        return $date !== false ? $date : null;
    }
}
