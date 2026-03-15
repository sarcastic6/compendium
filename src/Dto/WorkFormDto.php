<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\SourceType;
use App\Enum\WorkType;
use App\Entity\Language;
use App\Entity\Series;
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

    public ?Series $series = null;

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

    /** @var array<int, array{name: string}> */
    public array $authors = [];

    /** @var array<int, array{metadataTypeId: int, name: string}> */
    public array $metadata = [];
}
