<?php

declare(strict_types=1);

namespace App\Scraper;

/**
 * Primitive-only DTO carrying scraped work metadata.
 * Contains no entity references so it can be safely serialized to the session.
 *
 * The metadata array is keyed by the source's category name
 * (e.g. 'Rating', 'Warning', 'Fandom', 'Relationship', 'Character', 'Tag').
 * Each value is a list of tag strings for that category.
 */
class ScrapedWorkDto
{
    public ?string $title = null;

    /** @var list<string> */
    public array $authors = [];

    public ?string $summary = null;

    public ?string $language = null;

    public ?int $words = null;

    public ?int $chapters = null;

    public ?int $totalChapters = null;

    public ?string $publishedDate = null;

    public ?string $lastUpdatedDate = null;

    public ?string $sourceUrl = null;

    /** Source type string matching SourceType enum value, e.g. 'AO3'. */
    public ?string $sourceType = null;

    /** Work type string matching WorkType enum value, e.g. 'Fanfiction'. */
    public ?string $workType = null;

    public ?string $seriesName = null;

    public ?string $seriesUrl = null;

    public ?int $placeInSeries = null;

    /**
     * Metadata tags keyed by the source category name.
     *
     * @var array<string, list<string>>
     */
    public array $metadata = [];

    public bool $isComplete = false;
}
