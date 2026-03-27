<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StatusRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StatusRepository::class)]
#[ORM\Table(name: 'statuses')]
#[ORM\UniqueConstraint(name: 'uq_status_name', columns: ['name'])]
class Status
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    /**
     * True when the user has actually begun reading this work (Reading, On Hold,
     * Completed, DNF). False only for not-yet-started statuses (TBR).
     *
     * Used to determine whether word counts should be included in consumption
     * statistics — only entries where the user has actually started reading
     * contribute to words-read figures.
     *
     * NOT to be confused with countsAsRead, which tracks successful completion.
     * countsAsRead = true implies hasBeenStarted = true (enforced by validateFlags()).
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $hasBeenStarted = true;

    /**
     * True only when this status represents a successfully completed read.
     * Typically only 'Completed'; never true for DNF, TBR, Reading, or On Hold.
     *
     * Used for:
     *   - "Works read" counts (Read Count column in rankings)
     *   - Trend chart data (reads completed per month/year)
     *   - Dashboard finished count and finish rate numerator
     *   - Word count stats (total/average words for completed reads)
     *
     * APPLICATION-LEVEL CONSTRAINT: this flag is set by the admin at runtime.
     * There is no database-level enforcement that only one status has this true.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $countsAsRead = false;

    /**
     * True when entries with this status should float to the top of the reading
     * list when sorting by completion date descending.
     *
     * Intended for statuses that represent active, in-progress reads (e.g. Reading)
     * so they remain visible and easy to update. DNF and On Hold should be false —
     * those entries are not actionable and should sort with completed entries.
     *
     * The admin decides per-status whether On Hold should count as active.
     *
     * APPLICATION-LEVEL CONSTRAINT: controlled by the admin at runtime.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $isActive = false;

    public function __construct(string $name, bool $hasBeenStarted = true, bool $countsAsRead = false, bool $isActive = false)
    {
        $this->name = $name;
        $this->hasBeenStarted = $hasBeenStarted;
        $this->countsAsRead = $countsAsRead;
        $this->isActive = $isActive;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function hasBeenStarted(): bool
    {
        return $this->hasBeenStarted;
    }

    public function setHasBeenStarted(bool $hasBeenStarted): static
    {
        $this->hasBeenStarted = $hasBeenStarted;

        return $this;
    }

    public function countsAsRead(): bool
    {
        return $this->countsAsRead;
    }

    public function setCountsAsRead(bool $countsAsRead): static
    {
        $this->countsAsRead = $countsAsRead;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * Enforces the invariant: countsAsRead = true implies hasBeenStarted = true.
     * A status cannot count as a completed read if the user never started the work.
     *
     * Enforced at the application level via Symfony validation because DBAL 4.x
     * does not support multi-column CHECK constraints. This runs automatically on
     * form submission and can be triggered anywhere via the Validator service.
     */
    #[Assert\Callback]
    public function validateFlags(ExecutionContextInterface $context): void
    {
        if ($this->countsAsRead && !$this->hasBeenStarted) {
            $context->buildViolation('A status cannot count as read if the work has not been started.')
                ->atPath('countsAsRead')
                ->addViolation();
        }
    }
}
