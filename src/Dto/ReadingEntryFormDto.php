<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Metadata;
use App\Entity\ReadingEntry;
use App\Entity\Status;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints\NotNull;

class ReadingEntryFormDto
{
    #[NotNull]
    public ?Status $status = null;

    public ?DateTimeImmutable $dateStarted = null;

    public ?DateTimeImmutable $dateFinished = null;

    public ?int $lastReadChapter = null;

    public ?int $reviewStars = null;

    public ?int $spiceStars = null;

    public ?Metadata $mainPairing = null;

    public ?string $comments = null;

    public bool $starred = false;

    public static function fromEntity(ReadingEntry $entry): self
    {
        $dto = new self();
        $dto->status = $entry->getStatus();
        $dto->dateStarted = $entry->getDateStarted();
        $dto->dateFinished = $entry->getDateFinished();
        $dto->lastReadChapter = $entry->getLastReadChapter();
        $dto->reviewStars = $entry->getReviewStars();
        $dto->spiceStars = $entry->getSpiceStars();
        $dto->mainPairing = $entry->getMainPairing();
        $dto->comments = $entry->getComments();
        $dto->starred = $entry->isStarred();

        return $dto;
    }
}
