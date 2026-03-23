<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SourceType;
use App\Repository\SeriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeriesRepository::class)]
#[ORM\Table(name: 'series')]
#[ORM\UniqueConstraint(name: 'uq_series_name', columns: ['name'])]
class Series
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(nullable: true)]
    private ?int $numberOfParts = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalWords = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isComplete = null;

    /** @var Collection<int, SeriesSourceLink> */
    #[ORM\OneToMany(targetEntity: SeriesSourceLink::class, mappedBy: 'series', cascade: ['persist'])]
    private Collection $sourceLinks;

    public function __construct(string $name, ?int $numberOfParts = null)
    {
        $this->name = $name;
        $this->numberOfParts = $numberOfParts;
        $this->sourceLinks = new ArrayCollection();
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

    public function getNumberOfParts(): ?int
    {
        return $this->numberOfParts;
    }

    public function setNumberOfParts(?int $numberOfParts): static
    {
        $this->numberOfParts = $numberOfParts;

        return $this;
    }

    public function getTotalWords(): ?int
    {
        return $this->totalWords;
    }

    public function setTotalWords(?int $totalWords): static
    {
        $this->totalWords = $totalWords;

        return $this;
    }

    public function getIsComplete(): ?bool
    {
        return $this->isComplete;
    }

    public function setIsComplete(?bool $isComplete): static
    {
        $this->isComplete = $isComplete;

        return $this;
    }

    /** @return Collection<int, SeriesSourceLink> */
    public function getSourceLinks(): Collection
    {
        return $this->sourceLinks;
    }

    /**
     * Returns the URL for the given source type, or null if none is stored.
     * Used by templates to conditionally render the series name as a link.
     */
    public function getLinkForSource(SourceType $sourceType): ?string
    {
        foreach ($this->sourceLinks as $sourceLink) {
            if ($sourceLink->getSourceType() === $sourceType) {
                return $sourceLink->getLink();
            }
        }

        return null;
    }

    public function addSourceLink(SeriesSourceLink $sourceLink): static
    {
        if (!$this->sourceLinks->contains($sourceLink)) {
            $this->sourceLinks->add($sourceLink);
        }

        return $this;
    }
}
