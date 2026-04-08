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
                $this->getMetadataJson($work, 'Author'),
                $work->getSeries()?->getName(),
                $work->getPlaceInSeries(),
                $work->getLanguage()?->getName(),
                $work->getPublishedDate()?->format('Y-m-d'),
                $work->getLastUpdatedDate()?->format('Y-m-d'),
                $work->getWords(),
                $work->getChapters(),
                $this->getSingleMetadata($work, 'Rating'),
                $this->getMetadataJson($work, 'Warning'),
                $this->getMetadataJson($work, 'Category'),
                $this->getMetadataJson($work, 'Fandom'),
                $this->getMetadataJson($work, 'Relationships'),
                $this->getMetadataJson($work, 'Character'),
                $this->getMetadataJson($work, 'Tag'),
                $work->getSummary(),
            ];
        }

        return $rows;
    }

    /**
     * Returns a JSON array of all metadata names of the given type,
     * or null if the work has no metadata of that type.
     *
     * JSON encoding is used instead of comma-separated strings because
     * metadata values (especially tags) can contain commas, making
     * comma-delimited values lossy on round-trip.
     */
    private function getMetadataJson(Work $work, string $typeName): ?string
    {
        $names = [];

        foreach ($work->getMetadata() as $metadata) {
            if ($metadata->getMetadataType()->getName() === $typeName) {
                $names[] = $metadata->getName();
            }
        }

        return $names !== [] ? json_encode($names, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * Returns the single metadata value of the given type, or null if absent.
     * Used for types where multiple_allowed is false (e.g. Rating).
     */
    private function getSingleMetadata(Work $work, string $typeName): ?string
    {
        foreach ($work->getMetadata() as $metadata) {
            if ($metadata->getMetadataType()->getName() === $typeName) {
                return $metadata->getName();
            }
        }

        return null;
    }
}
