<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MetadataTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MetadataTypeRepository::class)]
#[ORM\Table(name: 'metadata_types')]
#[ORM\UniqueConstraint(name: 'uq_metadata_type_name', columns: ['name'])]
class MetadataType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    /**
     * Preferred display order for metadata type sections on the work form.
     * Types not listed here appear at the end, sorted alphabetically.
     */
    public const DISPLAY_ORDER = [
        'Rating',
        'Warning',
        'Category',
        'Fandom',
        'Pairing',
        'Character',
        'Tag',
    ];

    /**
     * When false, a work may only have one Metadata entry of this type.
     * Enforced at the application level in WorkService — not a database constraint,
     * because the uniqueness depends on business context (work + type combination).
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $multipleAllowed = true;

    public function __construct(string $name, bool $multipleAllowed = true)
    {
        $this->name = $name;
        $this->multipleAllowed = $multipleAllowed;
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

    public function isMultipleAllowed(): bool
    {
        return $this->multipleAllowed;
    }

    public function setMultipleAllowed(bool $multipleAllowed): static
    {
        $this->multipleAllowed = $multipleAllowed;

        return $this;
    }
}
