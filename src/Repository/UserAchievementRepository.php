<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserAchievement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAchievement>
 */
class UserAchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAchievement::class);
    }

    /**
     * Returns all achievement keys already unlocked by this user.
     * Used by AchievementService to skip already-unlocked achievements.
     *
     * @return string[]
     */
    public function findKeysByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('ua')
            ->select('ua.achievementKey')
            ->where('ua.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'achievementKey');
    }

    /**
     * Returns all unlocked achievements for a user, most recently unlocked first.
     *
     * @return UserAchievement[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('ua')
            ->where('ua.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ua.unlockedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns unlocked achievements the user hasn't been notified about yet
     * (notifiedAt IS NULL). Used to generate flash messages.
     *
     * @return UserAchievement[]
     */
    public function findUnnotifiedByUser(User $user): array
    {
        return $this->createQueryBuilder('ua')
            ->where('ua.user = :user')
            ->andWhere('ua.notifiedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('ua.unlockedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sets notifiedAt = now on all un-notified achievements for the user.
     * Called when the user visits the achievements page.
     */
    public function markAllNotifiedForUser(User $user): void
    {
        $this->createQueryBuilder('ua')
            ->update()
            ->set('ua.notifiedAt', ':now')
            ->where('ua.user = :user')
            ->andWhere('ua.notifiedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
