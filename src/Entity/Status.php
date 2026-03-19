<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StatusRepository;
use Doctrine\ORM\Mapping as ORM;

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
     * True when the user is actively engaged with this work (Reading, On Hold).
     * False for terminal states (Completed, DNF) and not-yet-started (TBR).
     *
     * Used to determine whether word counts should be included in consumption
     * statistics — words are counted for any entry where the user has actually
     * started reading (hasBeenStarted = true OR countsAsRead = true).
     *
     * NOT to be confused with countsAsRead, which tracks completion.
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

    public function __construct(string $name, bool $hasBeenStarted = true, bool $countsAsRead = false)
    {
        $this->name = $name;
        $this->hasBeenStarted = $hasBeenStarted;
        $this->countsAsRead = $countsAsRead;
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
}
