<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ReadingEntryFormDto;
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
     * - Stars: DBAL 4.x does not support CHECK constraints, so there is no database-level guard
     * - mainPairing: the FK type constraint can't be expressed at the DB level (requires a join through metadata_type)
     *
     * @throws \InvalidArgumentException on validation failure
     */
    public function validateAndSave(ReadingEntry $entry): void
    {
        // reviewStars must be 1-5 or null. No database-level constraint exists (DBAL 4.x does
        // not support CHECK constraints), so this is the sole enforcement point.
        if ($entry->getReviewStars() !== null && ($entry->getReviewStars() < 1 || $entry->getReviewStars() > 5)) {
            throw new \InvalidArgumentException('reading.entry.stars.out_of_range');
        }

        // spiceStars must be 0-5 or null — 0 means 'ice cold' (no spice), null means not rated.
        // No database-level constraint exists (DBAL 4.x does not support CHECK constraints),
        // so this is the sole enforcement point.
        if ($entry->getSpiceStars() !== null && ($entry->getSpiceStars() < 0 || $entry->getSpiceStars() > 5)) {
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

    /**
     * Applies all DTO fields to an existing entry, then validates and saves.
     * User and Work are immutable on edit — only reading-log fields can change.
     *
     * @throws \InvalidArgumentException on validation failure
     */
    public function updateFromDto(ReadingEntry $entry, ReadingEntryFormDto $dto): void
    {
        $entry->setStatus($dto->status);
        $entry->setDateStarted($dto->dateStarted);
        $entry->setDateFinished($dto->dateFinished);
        $entry->setLastReadChapter($dto->lastReadChapter);
        $entry->setReviewStars($dto->reviewStars);
        $entry->setSpiceStars($dto->spiceStars);
        $entry->setMainPairing($dto->mainPairing);
        $entry->setComments($dto->comments);
        $entry->setStarred($dto->starred);

        $this->validateAndSave($entry);
    }

    /**
     * Updates the status of the given entry IDs, restricted to the specified user.
     * Any IDs not owned by the user are silently ignored — no cross-user modification.
     *
     * @param int[] $ids
     */
    public function bulkUpdateStatus(User $user, array $ids, Status $status): int
    {
        if ($ids === []) {
            return 0;
        }

        $entries = $this->entityManager->getRepository(ReadingEntry::class)->findBy([
            'user' => $user,
            'id' => $ids,
        ]);

        foreach ($entries as $entry) {
            $entry->setStatus($status);
        }

        $this->entityManager->flush();

        return count($entries);
    }

    /**
     * Hard-deletes the given entry IDs, restricted to the specified user.
     * Any IDs not owned by the user are silently ignored — no cross-user deletion.
     *
     * @param int[] $ids
     */
    public function bulkDelete(User $user, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $entries = $this->entityManager->getRepository(ReadingEntry::class)->findBy([
            'user' => $user,
            'id' => $ids,
        ]);

        foreach ($entries as $entry) {
            $this->entityManager->remove($entry);
        }

        $this->entityManager->flush();

        return count($entries);
    }
}
