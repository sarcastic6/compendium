<?php

declare(strict_types=1);

namespace App\Export;

use App\Entity\ReadingEntry;
use App\Entity\Work;

/**
 * Fidelity-oriented export format. All 29 fields as raw values.
 * Intended for external analysis and third-party portability — not importable into Compendium.
 */
class DataDumpExportFormat implements ExportFormatInterface
{
    private const array HEADERS = [
        'Title',
        'Work URL',
        'Source type',
        'Work type',
        'Status',
        'Date started',
        'Date finished',
        'Last read chapter',
        'Review',
        'Spice',
        'Main pairing',
        'Comments',
        'Pinned',
        'Author(s)',
        'Series name',
        'Place in series',
        'Language',
        'Published date',
        'Last updated date',
        'Words',
        'Chapters',
        'Rating',
        'Warnings',
        'Categories',
        'Fandom(s)',
        'Relationships',
        'Characters',
        'Tags',
        'Summary',
    ];

    public function getHeaders(): array
    {
        return self::HEADERS;
    }

    public function buildRows(array $entries): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $work = $entry->getWork();

            $rows[] = [
                $work->getTitle(),
                $work->getLink(),
                $work->getSourceType()->value,
                $work->getType()->value,
                $entry->getStatus()->getName(),
                $entry->getDateStarted()?->format('Y-m-d'),
                $entry->getDateFinished()?->format('Y-m-d'),
                $entry->getLastReadChapter(),
                $entry->getReviewStars(),
                $entry->getSpiceStars(),
                $entry->getMainPairing()?->getName(),
                $entry->getComments(),
                $entry->isPinned(),
                $this->getMetadataString($work, 'Author'),
                $work->getSeries()?->getName(),
                $work->getPlaceInSeries(),
                $work->getLanguage()?->getName(),
                $work->getPublishedDate()?->format('Y-m-d'),
                $work->getLastUpdatedDate()?->format('Y-m-d'),
                $work->getWords(),
                $work->getChapters(),
                $this->getMetadataString($work, 'Rating'),
                $this->getMetadataString($work, 'Warning'),
                $this->getMetadataString($work, 'Category'),
                $this->getMetadataString($work, 'Fandom'),
                $this->getMetadataString($work, 'Relationships'),
                $this->getMetadataString($work, 'Character'),
                $this->getMetadataString($work, 'Tag'),
                $work->getSummary(),
            ];
        }

        return $rows;
    }

    /**
     * Returns a comma-separated string of all metadata names of the given type,
     * or null if the work has no metadata of that type.
     */
    private function getMetadataString(Work $work, string $typeName): ?string
    {
        $names = [];

        foreach ($work->getMetadata() as $metadata) {
            if ($metadata->getMetadataType()->getName() === $typeName) {
                $names[] = $metadata->getName();
            }
        }

        return $names !== [] ? implode(', ', $names) : null;
    }
}
