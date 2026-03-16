<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SourceType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'metadata_source_links')]
#[ORM\UniqueConstraint(name: 'uq_metadata_source_link', columns: ['metadata_id', 'source_type'])]
#[ORM\Index(name: 'idx_metadata_source_link_metadata', columns: ['metadata_id'])]
class MetadataSourceLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Metadata::class, inversedBy: 'sourceLinks')]
    #[ORM\JoinColumn(name: 'metadata_id', nullable: false, onDelete: 'RESTRICT')]
    private Metadata $metadata;

    #[ORM\Column(type: 'string', length: 32, enumType: SourceType::class)]
    private SourceType $sourceType;

    #[ORM\Column(length: 1024)]
    private string $link;

    public function __construct(Metadata $metadata, SourceType $sourceType, string $link)
    {
        $this->metadata = $metadata;
        $this->sourceType = $sourceType;
        $this->link = $link;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    public function getSourceType(): SourceType
    {
        return $this->sourceType;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): static
    {
        $this->link = $link;

        return $this;
    }
}
