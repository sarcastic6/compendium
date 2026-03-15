<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ImportResult;
use App\Dto\WorkFormDto;
use App\Entity\Series;
use App\Enum\SourceType;
use App\Enum\WorkType;
use App\Repository\LanguageRepository;
use App\Repository\MetadataTypeRepository;
use App\Repository\SeriesRepository;
use App\Scraper\ScrapedWorkDto;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Maps a ScrapedWorkDto to a WorkFormDto with entity lookups.
 *
 * Mapping strategy:
 * - Scalar fields copied directly (title, summary, words, chapters, dates, link, sourceType)
 * - Language: looked up by name; null + warning if not found (languages are admin-managed)
 * - Series: looked up by name; auto-created if not found (consistent with Author pattern)
 * - Metadata: AO3 category names mapped to MetadataType names via synonym map;
 *             skipped with warning if MetadataType not found in database
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
        private readonly SeriesRepository $seriesRepository,
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
        foreach ($scraped->authors as $authorName) {
            $name = trim($authorName);
            if ($name !== '') {
                $dto->authors[] = $name;
            }
        }

        // Language — lookup only, no auto-create (admin-managed reference data)
        if ($scraped->language !== null) {
            $language = $this->languageRepository->findOneBy(['name' => $scraped->language]);
            if ($language !== null) {
                $dto->language = $language;
            } else {
                $warnings[] = sprintf(
                    "Language '%s' was not found in the database and was not set. Create it under Admin first.",
                    $scraped->language,
                );
            }
        }

        // Series — lookup by name; auto-create if not found
        if ($scraped->seriesName !== null) {
            $series = $this->seriesRepository->findOneBy(['name' => $scraped->seriesName]);
            if ($series === null) {
                $series = new Series($scraped->seriesName, $scraped->seriesUrl);
                $this->entityManager->persist($series);
                $this->entityManager->flush();
            }
            $dto->series = $series;
            $dto->placeInSeries = $scraped->placeInSeries;
        }

        // Metadata
        foreach ($scraped->metadata as $categoryName => $tagNames) {
            // Resolve category name through synonym map
            $typeName = self::CATEGORY_SYNONYM_MAP[$categoryName] ?? $categoryName;

            $metadataType = $this->metadataTypeRepository->findOneBy(['name' => $typeName]);
            if ($metadataType === null) {
                $warnings[] = sprintf(
                    "Metadata type '%s' was not found. Tags of this type were skipped.",
                    $typeName,
                );
                continue;
            }

            foreach ($tagNames as $tagName) {
                $name = trim((string) $tagName);
                if ($name === '') {
                    continue;
                }

                $dto->metadata[] = [
                    'metadataType' => $metadataType,
                    'name' => $name,
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
