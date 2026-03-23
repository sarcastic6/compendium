<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\SourceType;
use App\Enum\WorkType;
use App\Entity\Language;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class WorkFormDto
{
    #[NotBlank]
    public ?WorkType $type = null;

    #[NotBlank]
    public ?string $title = null;

    public ?string $summary = null;

    /** Series name — WorkService does find-or-create. Null means no series. */
    public ?string $seriesName = null;

    /**
     * Series entity ID — set when the user selects an existing series from autocomplete.
     * When non-null, WorkService loads the series by ID rather than finding-or-creating by name.
     * Null means either a new series (use seriesName) or no series (both null/empty).
     */
    public ?int $seriesId = null;

    /** Series source URL — populated by scraper only, null for manual entry. */
    #[Url(requireTld: false)]
    public ?string $seriesUrl = null;

    public ?int $placeInSeries = null;

    /** Populated by scraper only; not exposed on the work form. */
    public ?int $seriesNumberOfParts = null;

    /** Populated by scraper only; not exposed on the work form. */
    public ?int $seriesTotalWords = null;

    /** Populated by scraper only; not exposed on the work form. */
    public ?bool $seriesIsComplete = null;

    public ?Language $language = null;

    public ?DateTimeImmutable $publishedDate = null;

    public ?DateTimeImmutable $lastUpdatedDate = null;

    public ?int $words = null;

    public ?int $chapters = null;

    #[Url(requireTld: false)]
    public ?string $link = null;

    public SourceType $sourceType = SourceType::Manual;

    public bool $starred = false;

    /** @var array<int, array{name: string, link: string|null}> */
    public array $authors = [];

    /** @var array<int, array{metadataType: \App\Entity\MetadataType, name: string, link: string|null}> */
    public array $metadata = [];
}
