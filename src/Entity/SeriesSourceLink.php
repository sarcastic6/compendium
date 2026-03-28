<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SourceType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'series_source_links')]
#[ORM\UniqueConstraint(name: 'uq_series_source_link', columns: ['series_id', 'source_type'])]
#[ORM\Index(name: 'idx_series_source_link_series', columns: ['series_id'])]
class SeriesSourceLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Series::class, inversedBy: 'sourceLinks')]
    #[ORM\JoinColumn(name: 'series_id', nullable: false, onDelete: 'RESTRICT')]
    private Series $series;

    #[ORM\Column(type: 'string', length: 32, enumType: SourceType::class)]
    private SourceType $sourceType;

    // Enforced at application level only — entities are created programmatically
    // (scraper, import service) and do not go through a Symfony Form validation flow.
    // The safe_url Twig filter is the primary XSS defence at the render layer.
    #[Assert\Url(protocols: ['http', 'https'])]
    #[ORM\Column(length: 1024)]
    private string $link;

    public function __construct(Series $series, SourceType $sourceType, string $link)
    {
        $this->series = $series;
        $this->sourceType = $sourceType;
        $this->link = $link;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeries(): Series
    {
        return $this->series;
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
