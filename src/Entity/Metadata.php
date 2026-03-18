<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SourceType;
use App\Repository\MetadataRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MetadataRepository::class)]
#[ORM\Table(name: 'metadata')]
#[ORM\UniqueConstraint(name: 'uq_metadata_name_type', columns: ['name', 'metadata_type_id'])]
#[ORM\Index(name: 'idx_metadata_name', columns: ['name'])]
#[ORM\Index(name: 'idx_metadata_type', columns: ['metadata_type_id'])]
class Metadata
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: MetadataType::class)]
    #[ORM\JoinColumn(name: 'metadata_type_id', nullable: false, onDelete: 'RESTRICT')]
    private MetadataType $metadataType;

    /** @var Collection<int, MetadataSourceLink> */
    #[ORM\OneToMany(targetEntity: MetadataSourceLink::class, mappedBy: 'metadata', cascade: ['persist'])]
    private Collection $sourceLinks;

    public function __construct(string $name, MetadataType $metadataType)
    {
        $this->name = $name;
        $this->metadataType = $metadataType;
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

    public function getMetadataType(): MetadataType
    {
        return $this->metadataType;
    }

    public function setMetadataType(MetadataType $metadataType): static
    {
        $this->metadataType = $metadataType;

        return $this;
    }

    /** @return Collection<int, MetadataSourceLink> */
    public function getSourceLinks(): Collection
    {
        return $this->sourceLinks;
    }

    public function addSourceLink(MetadataSourceLink $sourceLink): static
    {
        if (!$this->sourceLinks->contains($sourceLink)) {
            $this->sourceLinks->add($sourceLink);
        }

        return $this;
    }

    /**
     * Returns the URL for the given source type, or null if none is stored.
     * Used by templates to conditionally render metadata as links.
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
}
