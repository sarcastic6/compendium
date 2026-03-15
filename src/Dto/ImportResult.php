<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Result of mapping a ScrapedWorkDto to a WorkFormDto.
 * Carries the pre-filled form DTO and any warnings about fields that could
 * not be mapped (e.g., unknown language, missing MetadataType).
 */
class ImportResult
{
    /** @param list<string> $warnings */
    public function __construct(
        public readonly WorkFormDto $dto,
        public readonly array $warnings = [],
    ) {
    }
}
