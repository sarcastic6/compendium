<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ReadingGoal;
use App\Entity\User;
use App\Enum\GoalType;
use App\Repository\ReadingEntryRepository;
use App\Repository\ReadingGoalRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Manages reading goals and computes progress against them.
 */
class ReadingGoalService
{
    public function __construct(
        private readonly ReadingEntryRepository $readingEntryRepository,
        private readonly ReadingGoalRepository $readingGoalRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Returns goals for the given user and year, each with current progress data.
     *
     * Progress percentage is capped at 100% (user may exceed their goal).
     *
     * @return array<int, array{goal: ReadingGoal, currentValue: int, progressPct: float}>
     */
    public function getGoalsWithProgress(User $user, int $year): array
    {
        $goals = $this->readingGoalRepository->findByUserAndYear($user, $year);

        if (empty($goals)) {
            return [];
        }

        // Fetch current values once (avoid redundant queries if both goal types exist)
        $currentEntries = null;
        $currentWords   = null;

        $result = [];
        foreach ($goals as $goal) {
            $currentValue = match ($goal->getGoalType()) {
                GoalType::EntriesCompleted => $currentEntries ??= $this->readingEntryRepository->countFinished($user, $year),
                GoalType::WordsRead        => $currentWords ??= $this->readingEntryRepository->getTotalWordsSumForUser($user, $year),
            };

            $target      = $goal->getTargetValue();
            $progressPct = $target > 0 ? min(100.0, ($currentValue / $target) * 100) : 100.0;

            $result[] = [
                'goal'         => $goal,
                'currentValue' => $currentValue,
                'progressPct'  => $progressPct,
            ];
        }

        return $result;
    }

    /**
     * Creates or updates a goal for the given user, year, and type.
     * Uses upsert semantics — if a goal of this type already exists for the year,
     * its targetValue is updated instead of creating a duplicate.
     */
    public function setGoal(User $user, int $year, GoalType $type, int $targetValue): ReadingGoal
    {
        $existing = $this->readingGoalRepository->findByUserYearAndType($user, $year, $type);

        if ($existing !== null) {
            $existing->setTargetValue($targetValue);
            $this->entityManager->flush();
            return $existing;
        }

        $goal = new ReadingGoal($user, $year, $type, $targetValue);
        $this->entityManager->persist($goal);
        $this->entityManager->flush();
        return $goal;
    }

    /**
     * Hard-deletes a reading goal. Caller must verify ownership before calling.
     */
    public function deleteGoal(ReadingGoal $goal): void
    {
        $this->entityManager->remove($goal);
        $this->entityManager->flush();
    }
}
