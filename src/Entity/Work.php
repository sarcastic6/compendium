<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SourceType;
use App\Enum\WorkType;
use App\Repository\WorkRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkRepository::class)]
#[ORM\Table(name: 'works')]
#[ORM\Index(name: 'idx_work_series', columns: ['series_id'])]
#[ORM\Index(name: 'idx_work_language', columns: ['language_id'])]
#[ORM\HasLifecycleCallbacks]
class Work
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32, enumType: WorkType::class)]
    private WorkType $type;

    #[ORM\Column(length: 500)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\ManyToOne(targetEntity: Series::class)]
    #[ORM\JoinColumn(name: 'series_id', nullable: true, onDelete: 'RESTRICT')]
    private ?Series $series = null;

    #[ORM\Column(nullable: true)]
    private ?int $placeInSeries = null;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[ORM\JoinColumn(name: 'language_id', nullable: true, onDelete: 'RESTRICT')]
    private ?Language $language = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $publishedDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $lastUpdatedDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $words = null;

    #[ORM\Column(nullable: true)]
    private ?int $chapters = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $link = null;

    #[ORM\Column(type: 'string', length: 32, enumType: SourceType::class)]
    private SourceType $sourceType = SourceType::Manual;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, Metadata> */
    #[ORM\ManyToMany(targetEntity: Metadata::class)]
    #[ORM\JoinTable(name: 'works_metadata')]
    private Collection $metadata;

    public function __construct(WorkType $type, string $title)
    {
        $this->type = $type;
        $this->title = $title;
        $this->metadata = new ArrayCollection();
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

    public function getType(): WorkType
    {
        return $this->type;
    }

    public function setType(WorkType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getSeries(): ?Series
    {
        return $this->series;
    }

    public function setSeries(?Series $series): static
    {
        $this->series = $series;

        return $this;
    }

    public function getPlaceInSeries(): ?int
    {
        return $this->placeInSeries;
    }

    public function setPlaceInSeries(?int $placeInSeries): static
    {
        $this->placeInSeries = $placeInSeries;

        return $this;
    }

    public function getLanguage(): ?Language
    {
        return $this->language;
    }

    public function setLanguage(?Language $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getPublishedDate(): ?DateTimeImmutable
    {
        return $this->publishedDate;
    }

    public function setPublishedDate(?DateTimeImmutable $publishedDate): static
    {
        $this->publishedDate = $publishedDate;

        return $this;
    }

    public function getLastUpdatedDate(): ?DateTimeImmutable
    {
        return $this->lastUpdatedDate;
    }

    public function setLastUpdatedDate(?DateTimeImmutable $lastUpdatedDate): static
    {
        $this->lastUpdatedDate = $lastUpdatedDate;

        return $this;
    }

    public function getWords(): ?int
    {
        return $this->words;
    }

    public function setWords(?int $words): static
    {
        $this->words = $words;

        return $this;
    }

    public function getChapters(): ?int
    {
        return $this->chapters;
    }

    public function setChapters(?int $chapters): static
    {
        $this->chapters = $chapters;

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

    public function getSourceType(): SourceType
    {
        return $this->sourceType;
    }

    public function setSourceType(SourceType $sourceType): static
    {
        $this->sourceType = $sourceType;

        return $this;
    }


    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function softDelete(): static
    {
        $this->deletedAt = new DateTimeImmutable();

        return $this;
    }

    public function restore(): static
    {
        $this->deletedAt = null;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, Metadata> */
    public function getMetadata(): Collection
    {
        return $this->metadata;
    }

    public function addMetadata(Metadata $metadata): static
    {
        if (!$this->metadata->contains($metadata)) {
            $this->metadata->add($metadata);
        }

        return $this;
    }

    public function removeMetadata(Metadata $metadata): static
    {
        $this->metadata->removeElement($metadata);

        return $this;
    }

    /**
     * Returns metadata entries with type 'Author'.
     * Authors are stored as metadata — this is a convenience helper for templates.
     *
     * @return Collection<int, Metadata>
     */
    public function getAuthors(): Collection
    {
        return $this->metadata->filter(
            static fn (Metadata $m) => $m->getMetadataType()->getName() === 'Author',
        );
    }
}
