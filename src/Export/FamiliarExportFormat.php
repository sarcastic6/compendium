<?php

declare(strict_types=1);

namespace App\Export;

use App\Entity\ReadingEntry;
use App\Entity\Status;
use App\Entity\Work;

/**
 * Presentation-oriented export format modelled after the original Google Sheets tracker.
 * Designed for round-trip import back into Compendium (import feature planned separately).
 *
 * 42 columns (A–AP). Blank columns (C, D, I, AD, AG–AP) are preserved as structural
 * placeholders matching the original spreadsheet layout.
 */
class FamiliarExportFormat implements ExportFormatInterface
{
    private const array HEADERS = [
        'Completion status', // A
        'Work URL',          // B
        null,                // C (blank)
        null,                // D (blank)
        'Title',             // E
        'Author(s)',         // F
        'Status',            // G
        'Finish date',       // H
        null,                // I (blank)
        'Review',            // J — star encoding
        'Review',            // K — heart encoding (same value, different character)
        'Spice',             // L
        'Series name',       // M
        'Place in series',   // N
        'Series URL',        // O
        'Rating',            // P
        'Warnings',          // Q
        'Categories',        // R
        'Fandom(s)',         // S
        'Main pairing',      // T
        'Relationships',     // U
        'Characters',        // V
        'Tags',              // W
        'Language',          // X
        'Published date',    // Y
        'Last updated date', // Z
        'Words',             // AA
        'Chapters',          // AB
        'Last read chapter', // AC
        null,                // AD (blank)
        'Summary',           // AE
        'Comments',          // AF
        null,                // AG (blank)
        null,                // AH (blank)
        null,                // AI (blank)
        null,                // AJ (blank)
        null,                // AK (blank)
        null,                // AL (blank)
        null,                // AM (blank)
        null,                // AN (blank)
        null,                // AO (blank)
        null,                // AP (blank)
    ];

    public function getHeaders(): array
    {
        return self::HEADERS;
    }

    public function buildRows(array $entries): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $work   = $entry->getWork();
            $status = $entry->getStatus();

            $rows[] = [
                $this->deriveCompletionStatus($status),           // A
                $work->getLink(),                                  // B
                null,                                             // C
                null,                                             // D
                $work->getTitle(),                                 // E
                $this->getMetadataString($work, 'Author'),         // F
                $status->getName(),                                // G
                $entry->getDateFinished()?->format('Y-m-d'),       // H
                null,                                             // I
                $this->formatStars($entry->getReviewStars(), '★'), // J
                $this->formatStars($entry->getReviewStars(), '♥'), // K
                $this->formatSpice($entry->getSpiceStars()),       // L
                $work->getSeries()?->getName(),                    // M
                $work->getPlaceInSeries(),                         // N
                $this->getSeriesUrl($work),                        // O
                $this->getMetadataString($work, 'Rating'),         // P
                $this->getMetadataString($work, 'Warning'),        // Q
                $this->getMetadataString($work, 'Category'),       // R
                $this->getMetadataString($work, 'Fandom'),         // S
                $entry->getMainPairing()?->getName(),              // T
                $this->getMetadataString($work, 'Relationships'),  // U
                $this->getMetadataString($work, 'Character'),      // V
                $this->getMetadataString($work, 'Tag'),            // W
                $work->getLanguage()?->getName(),                  // X
                $work->getPublishedDate()?->format('Y-m-d'),       // Y
                $work->getLastUpdatedDate()?->format('Y-m-d'),     // Z
                $work->getWords(),                                 // AA
                $work->getChapters(),                              // AB
                $entry->getLastReadChapter(),                      // AC
                null,                                             // AD
                $work->getSummary(),                               // AE
                $entry->getComments(),                             // AF
                null,                                             // AG
                null,                                             // AH
                null,                                             // AI
                null,                                             // AJ
                null,                                             // AK
                null,                                             // AL
                null,                                             // AM
                null,                                             // AN
                null,                                             // AO
                null,                                             // AP
            ];
        }

        return $rows;
    }

    /**
     * Derives the completion status string from Status flags alone.
     * Not based on status name — flag-based so re-named statuses produce correct output.
     *
     * | countsAsRead | hasBeenStarted | isActive | Output     |
     * |--------------|----------------|----------|------------|
     * | true         | (any)          | (any)    | 'Complete' |
     * | false        | true           | true     | 'WIP'      |
     * | false        | true           | false    | 'Abandoned'|
     * | false        | false          | (any)    | null       |
     */
    private function deriveCompletionStatus(Status $status): ?string
    {
        if ($status->countsAsRead()) {
            return 'Complete';
        }

        if (!$status->hasBeenStarted()) {
            return null;
        }

        return $status->isActive() ? 'WIP' : 'Abandoned';
    }

    /**
     * Repeats $char $stars times, or returns null if $stars is null.
     * Used for the star (column J) and heart (column K) review encodings.
     */
    private function formatStars(?int $stars, string $char): ?string
    {
        if ($stars === null) {
            return null;
        }

        return str_repeat($char, $stars);
    }

    /**
     * Encodes spice stars as emoji: null → null, 0 → '🚫', 1–5 → '🌶️' × N.
     * Null and 0 are distinct: null means not rated; 0 means explicitly no spice.
     */
    private function formatSpice(?int $spice): ?string
    {
        if ($spice === null) {
            return null;
        }

        if ($spice === 0) {
            return '🚫';
        }

        return str_repeat('🌶️', $spice);
    }

    /**
     * Returns the series URL matching the work's source type, falling back to
     * the first available source link if no type-specific match exists.
     */
    private function getSeriesUrl(Work $work): ?string
    {
        $series = $work->getSeries();

        if ($series === null) {
            return null;
        }

        $url = $series->getLinkForSource($work->getSourceType());

        if ($url !== null) {
            return $url;
        }

        $first = $series->getSourceLinks()->first();

        return $first !== false ? $first->getLink() : null;
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
