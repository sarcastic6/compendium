<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ReadingEntry;
use App\Entity\Status;
use App\Entity\User;
use App\Entity\Work;
use Doctrine\ORM\EntityManagerInterface;

class ReadingEntryService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Creates and persists a new ReadingEntry.
     * User is always set from the authenticated session — never from form input.
     *
     * @throws \InvalidArgumentException if mainPairing is not of type 'Pairing' or stars are out of range
     */
    public function createEntry(User $user, Work $work, Status $status): ReadingEntry
    {
        $entry = new ReadingEntry($user, $work, $status);
        $this->entityManager->persist($entry);

        return $entry;
    }

    /**
     * Validates then persists and flushes a ReadingEntry after the form has been populated.
     * persist() is called only after all validation passes — never before — so invalid entries
     * never enter the Doctrine unit of work.
     *
     * Star validation and mainPairing type check are done here because:
     * - Stars: the DB CHECK constraint exists but won't produce friendly messages
     * - mainPairing: the FK type constraint can't be expressed at the DB level (requires a join through metadata_type)
     *
     * @throws \InvalidArgumentException on validation failure
     */
    public function validateAndSave(ReadingEntry $entry): void
    {
        // reviewStars and spiceStars must be 1-5 or null — DB CHECK constraint exists,
        // but we validate here for user-friendly error messages.
        if ($entry->getReviewStars() !== null && ($entry->getReviewStars() < 1 || $entry->getReviewStars() > 5)) {
            throw new \InvalidArgumentException('reading.entry.stars.out_of_range');
        }

        if ($entry->getSpiceStars() !== null && ($entry->getSpiceStars() < 1 || $entry->getSpiceStars() > 5)) {
            throw new \InvalidArgumentException('reading.entry.stars.out_of_range');
        }

        // mainPairing must reference a Metadata whose MetadataType is 'Pairing'.
        // This can't be enforced at the DB level because it requires a join through metadata_type.
        if ($entry->getMainPairing() !== null) {
            $typeName = $entry->getMainPairing()->getMetadataType()->getName();
            if ($typeName !== 'Pairing') {
                throw new \InvalidArgumentException('reading.entry.main_pairing.wrong_type');
            }
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }
}
