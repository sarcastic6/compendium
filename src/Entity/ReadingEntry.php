<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReadingEntryRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReadingEntryRepository::class)]
#[ORM\Table(name: 'reading_entries')]
#[ORM\Index(name: 'idx_re_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_re_work', columns: ['work_id'])]
#[ORM\Index(name: 'idx_re_status', columns: ['status_id'])]
#[ORM\Index(name: 'idx_re_user_status', columns: ['user_id', 'status_id'])]
#[ORM\Index(name: 'idx_re_user_date_finished', columns: ['user_id', 'date_finished'])]
#[ORM\HasLifecycleCallbacks]
class ReadingEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Work::class)]
    #[ORM\JoinColumn(name: 'work_id', nullable: false, onDelete: 'RESTRICT')]
    private Work $work;

    #[ORM\ManyToOne(targetEntity: Status::class)]
    #[ORM\JoinColumn(name: 'status_id', nullable: false, onDelete: 'RESTRICT')]
    private Status $status;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $dateStarted = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $dateFinished = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastReadChapter = null;

    /**
     * Must be between 1 and 5 inclusive. Database CHECK constraint exists, but validation
     * is also enforced at the application level in ReadingEntryService to provide user-friendly
     * error messages before hitting the database.
     */
    #[ORM\Column(nullable: true, options: ['check' => 'review_stars BETWEEN 1 AND 5'])]
    private ?int $reviewStars = null;

    /**
     * Must be between 0 and 5 inclusive. 0 means 'ice cold' (no spice); NULL means not rated.
     * Database CHECK constraint exists, but validation is also enforced at the application level
     * in ReadingEntryService.
     */
    #[ORM\Column(nullable: true, options: ['check' => 'spice_stars BETWEEN 0 AND 5'])]
    private ?int $spiceStars = null;

    /**
     * Must reference a Metadata entity whose MetadataType name is 'Pairing'.
     * Enforced at the application level in ReadingEntryService — not a database FK constraint
     * because it requires a join through metadata_type, which can't be expressed as a simple FK.
     */
    #[ORM\ManyToOne(targetEntity: Metadata::class)]
    #[ORM\JoinColumn(name: 'main_pairing_id', nullable: true, onDelete: 'RESTRICT')]
    private ?Metadata $mainPairing = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comments = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $starred = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(User $user, Work $work, Status $status)
    {
        $this->user = $user;
        $this->work = $work;
        $this->status = $status;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
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

    public function getWork(): Work
    {
        return $this->work;
    }

    public function setWork(Work $work): static
    {
        $this->work = $work;

        return $this;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDateStarted(): ?DateTimeImmutable
    {
        return $this->dateStarted;
    }

    public function setDateStarted(?DateTimeImmutable $dateStarted): static
    {
        $this->dateStarted = $dateStarted;

        return $this;
    }

    public function getDateFinished(): ?DateTimeImmutable
    {
        return $this->dateFinished;
    }

    public function setDateFinished(?DateTimeImmutable $dateFinished): static
    {
        $this->dateFinished = $dateFinished;

        return $this;
    }

    public function getLastReadChapter(): ?int
    {
        return $this->lastReadChapter;
    }

    public function setLastReadChapter(?int $lastReadChapter): static
    {
        $this->lastReadChapter = $lastReadChapter;

        return $this;
    }

    public function getReviewStars(): ?int
    {
        return $this->reviewStars;
    }

    public function setReviewStars(?int $reviewStars): static
    {
        $this->reviewStars = $reviewStars;

        return $this;
    }

    public function getSpiceStars(): ?int
    {
        return $this->spiceStars;
    }

    public function setSpiceStars(?int $spiceStars): static
    {
        $this->spiceStars = $spiceStars;

        return $this;
    }

    public function getMainPairing(): ?Metadata
    {
        return $this->mainPairing;
    }

    public function setMainPairing(?Metadata $mainPairing): static
    {
        $this->mainPairing = $mainPairing;

        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): static
    {
        $this->comments = $comments;

        return $this;
    }

    public function isStarred(): bool
    {
        return $this->starred;
    }

    public function setStarred(bool $starred): static
    {
        $this->starred = $starred;

        return $this;
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
