<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MetadataRepository;
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

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $link = null;

    public function __construct(string $name, MetadataType $metadataType, ?string $link = null)
    {
        $this->name = $name;
        $this->metadataType = $metadataType;
        $this->link = $link;
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

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

        return $this;
    }
}
