<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SeriesRepository;
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

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $link = null;

    #[ORM\Column(nullable: true)]
    private ?int $numberOfParts = null;

    public function __construct(string $name, ?string $link = null, ?int $numberOfParts = null)
    {
        $this->name = $name;
        $this->link = $link;
        $this->numberOfParts = $numberOfParts;
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

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

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
}
