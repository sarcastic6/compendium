<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserAchievement;
use App\Enum\AchievementDefinition;
use App\Repository\ReadingEntryRepository;
use App\Repository\UserAchievementRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Evaluates and manages achievement unlocks.
 *
 * Key design decisions:
 * - Achievement definitions live in AchievementDefinition enum (PHP code), not the DB.
 * - unlock records are stored in user_achievements with a historically accurate unlocked_at date.
 * - On first evaluation for a user, backfill runs automatically — all past reading data
 *   is scanned once to unlock any already-earned achievements with their real historical dates.
 * - Subsequent evaluations skip already-unlocked achievements (one query for the key list),
 *   making the hot path cheap.
 * - Single flush at end of evaluateAchievements() to avoid partial writes.
 */
class AchievementService
{
    public function __construct(
        private readonly ReadingEntryRepository $readingEntryRepository,
        private readonly UserAchievementRepository $userAchievementRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Main entry point. Evaluates all 20 achievements for the user.
     *
     * Already-unlocked achievements are skipped immediately (one query for the key set).
     * New unlocks are persisted with historically accurate dates.
     *
     * @return AchievementDefinition[] Newly unlocked definitions (empty if nothing new)
     */
    public function evaluateAchievements(User $user): array
    {
        $unlockedKeys = array_flip($this->userAchievementRepository->findKeysByUser($user));
        $newlyUnlocked = [];

        foreach (AchievementDefinition::cases() as $def) {
            if (isset($unlockedKeys[$def->value])) {
                continue; // already unlocked — skip
            }

            if (!$this->isConditionMet($user, $def)) {
                continue;
            }

            $unlockedAt = $this->computeHistoricalUnlockDate($user, $def);
            $achievement = new UserAchievement($user, $def->value, $unlockedAt);
            $this->entityManager->persist($achievement);
            $newlyUnlocked[] = $def;

            // Add to local key set so subsequent iterations don't re-check
            $unlockedKeys[$def->value] = true;
        }

        if (!empty($newlyUnlocked)) {
            $this->entityManager->flush();
        }

        return $newlyUnlocked;
    }

    /**
     * Returns whether the achievement condition is currently met for the user.
     * Dispatches to the correct repository method based on conditionType.
     */
    public function isConditionMet(User $user, AchievementDefinition $def): bool
    {
        $threshold = $def->getThreshold();

        return match ($def->getConditionType()) {
            'finished_count'   => $this->readingEntryRepository->countFinished($user) >= $threshold,
            'words_sum'        => $this->readingEntryRepository->getTotalWordsSumForUser($user) >= $threshold,
            'unique_fandoms'   => $this->readingEntryRepository->countDistinctMetadataForFinished($user, 'Fandom') >= $threshold,
            'unique_authors'   => $this->readingEntryRepository->countDistinctMetadataForFinished($user, 'Author') >= $threshold,
            'unique_languages' => $this->readingEntryRepository->countDistinctLanguagesForFinished($user) >= $threshold,
            'reread'           => $this->readingEntryRepository->getDateOfFirstReread($user) !== null,
            'long_work'        => $this->readingEntryRepository->hasFinishedWorkWithMinWords($user, $threshold),
            'rated_count'      => $this->readingEntryRepository->countRated($user) >= $threshold,
            'pinned_count'     => $this->countPinned($user) >= $threshold,
            default            => false,
        };
    }

    /**
     * Derives the historically accurate unlock date for an achievement.
     *
     * For count/sum-based achievements this is the date the Nth qualifying event
     * occurred, not the date this code ran. Falls back to now() if data is missing.
     */
    public function computeHistoricalUnlockDate(User $user, AchievementDefinition $def): \DateTimeImmutable
    {
        $threshold = $def->getThreshold();

        $date = match ($def->getConditionType()) {
            'finished_count' => $this->readingEntryRepository->getNthFinishedEntryDate($user, $threshold),

            'words_sum' => $this->computeWordSumMilestoneDate($user, $threshold),

            'unique_fandoms' => $this->computeUniqueMetadataMilestoneDate($user, 'Fandom', $threshold),

            'unique_authors' => $this->computeUniqueMetadataMilestoneDate($user, 'Author', $threshold),

            'unique_languages' => $this->computeUniqueLanguageMilestoneDate($user, $threshold),

            'reread' => $this->readingEntryRepository->getDateOfFirstReread($user),

            'long_work' => $this->readingEntryRepository->getDateOfFirstLongWorkFinished($user, $threshold),

            'rated_count' => $this->readingEntryRepository->getNthRatedEntryDate($user, $threshold),

            'pinned_count' => $this->readingEntryRepository->getNthPinnedEntryDate($user, $threshold),

            default => null,
        };

        return $date ?? new \DateTimeImmutable();
    }

    /**
     * Returns progress data for all achievements.
     * Unlocked achievements show 100% progress; locked ones show current/target.
     *
     * To avoid redundant queries, current values are fetched once per condition type
     * and reused across all achievements of that type.
     *
     * @return array<string, array{definition: AchievementDefinition, unlocked: bool, unlockedAt: \DateTimeImmutable|null, current: int, target: int, progressPct: float}>
     */
    public function getProgress(User $user): array
    {
        $unlockedRecords = [];
        foreach ($this->userAchievementRepository->findByUser($user) as $ua) {
            $unlockedRecords[$ua->getAchievementKey()] = $ua;
        }

        // Fetch current values per condition type (one query each, reused)
        $currentValues = $this->fetchCurrentValues($user);

        $progress = [];
        foreach (AchievementDefinition::cases() as $def) {
            $ua       = $unlockedRecords[$def->value] ?? null;
            $unlocked = $ua !== null;
            $current  = $currentValues[$def->getConditionType()] ?? 0;

            // For boolean conditions (reread, long_work), treat as 0 or 1
            if (in_array($def->getConditionType(), ['reread', 'long_work'], true)) {
                $current = $unlocked ? 1 : 0;
            }

            $target      = $def->getThreshold();
            $progressPct = $target > 0 ? min(100.0, ($current / $target) * 100) : 100.0;

            $progress[$def->value] = [
                'definition'  => $def,
                'unlocked'    => $unlocked,
                'unlockedAt'  => $ua?->getUnlockedAt(),
                'current'     => $current,
                'target'      => $target,
                'progressPct' => $unlocked ? 100.0 : $progressPct,
            ];
        }

        return $progress;
    }

    /**
     * Sets notifiedAt on all provided UserAchievement records and flushes.
     * Used to mark achievements as seen after a flash is rendered.
     *
     * @param UserAchievement[] $achievements
     */
    public function markNotified(array $achievements): void
    {
        foreach ($achievements as $ua) {
            $ua->markNotified();
        }
        if (!empty($achievements)) {
            $this->entityManager->flush();
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function countPinned(User $user): int
    {
        return (int) $this->readingEntryRepository->createQueryBuilder('re')
            ->select('COUNT(re.id)')
            ->where('re.user = :user')
            ->andWhere('re.pinned = :pinned')
            ->setParameter('user', $user)
            ->setParameter('pinned', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Computes the date when the running word sum first crossed $threshold.
     * Iterates entries ordered by dateFinished ASC, summing work.words.
     */
    private function computeWordSumMilestoneDate(User $user, int $threshold): ?\DateTimeImmutable
    {
        $entries = $this->readingEntryRepository->getFinishedEntriesWithWordsOrderedByDate($user);
        $runningSum = 0;

        foreach ($entries as $row) {
            $runningSum += (int) ($row['words'] ?? 0);
            if ($runningSum >= $threshold) {
                $dateStr = $row['dateFinished'] ?? $row['createdAt'];
                return $dateStr !== null ? new \DateTimeImmutable((string) $dateStr) : null;
            }
        }

        return null;
    }

    /**
     * Computes the date when the Nth distinct metadata value (of the given type)
     * appeared in finished entries.
     */
    private function computeUniqueMetadataMilestoneDate(User $user, string $typeName, int $threshold): ?\DateTimeImmutable
    {
        $entries = $this->readingEntryRepository->getFinishedEntriesWithMetadataOrderedByDate($user, $typeName);
        $seen    = [];

        foreach ($entries as $row) {
            $metaId = (int) $row['metadataId'];
            if (!isset($seen[$metaId])) {
                $seen[$metaId] = true;
                if (count($seen) >= $threshold) {
                    $dateStr = $row['dateFinished'] ?? $row['createdAt'];
                    return $dateStr !== null ? new \DateTimeImmutable((string) $dateStr) : null;
                }
            }
        }

        return null;
    }

    /**
     * Computes the date when the Nth distinct language appeared in finished entries.
     */
    private function computeUniqueLanguageMilestoneDate(User $user, int $threshold): ?\DateTimeImmutable
    {
        $entries = $this->readingEntryRepository->getFinishedEntriesWithLanguageOrderedByDate($user);
        $seen    = [];

        foreach ($entries as $row) {
            $langId = (int) $row['languageId'];
            if (!isset($seen[$langId])) {
                $seen[$langId] = true;
                if (count($seen) >= $threshold) {
                    $dateStr = $row['dateFinished'] ?? $row['createdAt'];
                    return $dateStr !== null ? new \DateTimeImmutable((string) $dateStr) : null;
                }
            }
        }

        return null;
    }

    /**
     * Fetches current progress values for each condition type in one pass.
     * Values are indexed by condition type string.
     *
     * For boolean conditions (reread, long_work), the value is not computed here
     * since they're handled separately in getProgress().
     *
     * @return array<string, int>
     */
    private function fetchCurrentValues(User $user): array
    {
        return [
            'finished_count'   => $this->readingEntryRepository->countFinished($user),
            'words_sum'        => $this->readingEntryRepository->getTotalWordsSumForUser($user),
            'unique_fandoms'   => $this->readingEntryRepository->countDistinctMetadataForFinished($user, 'Fandom'),
            'unique_authors'   => $this->readingEntryRepository->countDistinctMetadataForFinished($user, 'Author'),
            'unique_languages' => $this->readingEntryRepository->countDistinctLanguagesForFinished($user),
            'rated_count'      => $this->readingEntryRepository->countRated($user),
            'pinned_count'     => $this->countPinned($user),
        ];
    }
}
