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

    /** Series source URL — populated by scraper only, null for manual entry. */
    #[Url(requireTld: false)]
    public ?string $seriesUrl = null;

    public ?int $placeInSeries = null;

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
