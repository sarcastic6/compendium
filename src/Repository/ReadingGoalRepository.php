<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReadingGoal;
use App\Entity\User;
use App\Enum\GoalType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReadingGoal>
 */
class ReadingGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadingGoal::class);
    }

    /**
     * Returns all goals for the given user and year.
     *
     * @return ReadingGoal[]
     */
    public function findByUserAndYear(User $user, int $year): array
    {
        return $this->createQueryBuilder('rg')
            ->where('rg.user = :user')
            ->andWhere('rg.year = :year')
            ->setParameter('user', $user)
            ->setParameter('year', $year)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns a single goal for a specific user, year, and type — or null if not set.
     */
    public function findByUserYearAndType(User $user, int $year, GoalType $type): ?ReadingGoal
    {
        return $this->createQueryBuilder('rg')
            ->where('rg.user = :user')
            ->andWhere('rg.year = :year')
            ->andWhere('rg.goalType = :type')
            ->setParameter('user', $user)
            ->setParameter('year', $year)
            ->setParameter('type', $type->value)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns all distinct years for which the user has set at least one goal,
     * sorted descending. Used to show past-year goals read-only on the goals page.
     *
     * @return int[]
     */
    public function findYearsWithGoals(User $user): array
    {
        $rows = $this->createQueryBuilder('rg')
            ->select('DISTINCT rg.year')
            ->where('rg.user = :user')
            ->setParameter('user', $user)
            ->orderBy('rg.year', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $r): int => (int) $r['year'], $rows);
    }
}
