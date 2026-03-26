<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GoalType;
use App\Repository\ReadingGoalRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A user-set yearly reading goal for a specific goal type.
 *
 * UNIQUE(user_id, year, goal_type) — one goal per type per year per user.
 * Users can adjust the target mid-year; the service uses upsert semantics.
 */
#[ORM\Entity(repositoryClass: ReadingGoalRepository::class)]
#[ORM\Table(name: 'reading_goals')]
#[ORM\UniqueConstraint(name: 'uq_rg_user_year_type', columns: ['user_id', 'year', 'goal_type'])]
#[ORM\Index(name: 'idx_rg_user_year', columns: ['user_id', 'year'])]
#[ORM\HasLifecycleCallbacks]
class ReadingGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column]
    private int $year;

    #[ORM\Column(type: 'string', length: 32, enumType: GoalType::class)]
    private GoalType $goalType;

    #[ORM\Column]
    private int $targetValue;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(User $user, int $year, GoalType $goalType, int $targetValue)
    {
        $this->user        = $user;
        $this->year        = $year;
        $this->goalType    = $goalType;
        $this->targetValue = $targetValue;
        $this->createdAt   = new DateTimeImmutable();
        $this->updatedAt   = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getGoalType(): GoalType
    {
        return $this->goalType;
    }

    public function getTargetValue(): int
    {
        return $this->targetValue;
    }

    public function setTargetValue(int $targetValue): void
    {
        $this->targetValue = $targetValue;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
