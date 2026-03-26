<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserAchievementRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Records that a user has unlocked a specific achievement.
 *
 * Rows are immutable after creation except for notified_at, which is set
 * the first time the user visits the achievements page after unlocking.
 *
 * unlocked_at  — the historical date the threshold was actually crossed
 *                (backfilled from reading data, not the insert timestamp)
 * notified_at  — NULL until the user sees the achievement on the page;
 *                used for "New!" badges and to prevent re-flashing
 * created_at   — when the row was inserted (may differ from unlocked_at for backfill)
 */
#[ORM\Entity(repositoryClass: UserAchievementRepository::class)]
#[ORM\Table(name: 'user_achievements')]
#[ORM\UniqueConstraint(name: 'uq_ua_user_key', columns: ['user_id', 'achievement_key'])]
#[ORM\Index(name: 'idx_ua_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_ua_user_notified', columns: ['user_id', 'notified_at'])]
class UserAchievement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(length: 100)]
    private string $achievementKey;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $unlockedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $notifiedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(User $user, string $achievementKey, DateTimeImmutable $unlockedAt)
    {
        $this->user           = $user;
        $this->achievementKey = $achievementKey;
        $this->unlockedAt     = $unlockedAt;
        $this->createdAt      = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAchievementKey(): string
    {
        return $this->achievementKey;
    }

    public function getUnlockedAt(): DateTimeImmutable
    {
        return $this->unlockedAt;
    }

    public function getNotifiedAt(): ?DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function markNotified(): void
    {
        $this->notifiedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
