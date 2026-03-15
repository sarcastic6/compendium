<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\ImportResult;
use App\Entity\Language;
use App\Entity\MetadataType;
use App\Entity\Series;
use App\Enum\SourceType;
use App\Enum\WorkType;
use App\Repository\LanguageRepository;
use App\Repository\MetadataTypeRepository;
use App\Repository\SeriesRepository;
use App\Scraper\ScrapedWorkDto;
use App\Service\ImportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ImportServiceTest extends TestCase
{
    private ImportService $service;

    /** @var LanguageRepository&MockObject */
    private LanguageRepository $languageRepo;

    /** @var SeriesRepository&MockObject */
    private SeriesRepository $seriesRepo;

    /** @var MetadataTypeRepository&MockObject */
    private MetadataTypeRepository $metadataTypeRepo;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        // Use createStub() for dependencies that don't need call-count verification.
        // createMock() is used only in tests that assert persist()/flush() expectations.
        $this->languageRepo = $this->createStub(LanguageRepository::class);
        $this->seriesRepo = $this->createStub(SeriesRepository::class);
        $this->metadataTypeRepo = $this->createStub(MetadataTypeRepository::class);
        $this->em = $this->createStub(EntityManagerInterface::class);

        $this->service = new ImportService(
            $this->languageRepo,
            $this->seriesRepo,
            $this->metadataTypeRepo,
            $this->em,
        );
    }

    private function makeScraped(): ScrapedWorkDto
    {
        $dto = new ScrapedWorkDto();
        $dto->title = 'Test Work';
        $dto->authors = ['TestAuthor'];
        $dto->summary = 'A test summary.';
        $dto->words = 10000;
        $dto->chapters = 5;
        $dto->publishedDate = '2023-01-15';
        $dto->lastUpdatedDate = null;
        $dto->sourceUrl = 'https://archiveofourown.org/works/11111?view_adult=true';
        $dto->sourceType = 'AO3';
        $dto->workType = 'Fanfiction';
        $dto->language = null;
        $dto->seriesName = null;
        $dto->metadata = [];

        return $dto;
    }

    // --- Return type ---

    public function test_returns_import_result(): void
    {
        $this->languageRepo->method('findOneBy')->willReturn(null);

        $result = $this->service->mapToWorkFormDto($this->makeScraped());

        $this->assertInstanceOf(ImportResult::class, $result);
    }

    // --- Scalar field mapping ---

    public function test_maps_title(): void
    {
        $result = $this->service->mapToWorkFormDto($this->makeScraped());

        $this->assertSame('Test Work', $result->dto->title);
    }

    public function test_maps_summary(): void
    {
        $result = $this->service->mapToWorkFormDto($this->makeScraped());

        $this->assertSame('A test summary.', $result->dto->summary);
    }

    public function test_maps_words(): void
    {
        $result = $this->service->mapToWorkFormDto($this->makeScraped());

        $this->assertSame(10000, $result->dto->words);
    }

    public function test_maps_chapters(): void
    {
        $result = $this->service->mapToWorkFormDto($this->makeScraped());

        $this->assertSame(5, $result->dto->chapters);
    }

    public function test_maps_source_type_to_enum(): void
    {
        $result = $this->service->mapToWorkFormDto($this->makeScraped());

        $this->assertSame(SourceType::AO3, $result->dto->sourceType);
    }

    public function test_maps_work_type_to_enum(): void
    {
        $result = $this->service->mapToWorkFormDto($this->makeScraped());

        $this->assertSame(WorkType::Fanfiction, $result->dto->type);
    }

    public function test_maps_authors_array(): void
    {
        $result = $this->service->mapToWorkFormDto($this->makeScraped());

        $this->assertSame(['TestAuthor'], $result->dto->authors);
    }

    public function test_maps_published_date(): void
    {
        $result = $this->service->mapToWorkFormDto($this->makeScraped());

        $this->assertNotNull($result->dto->publishedDate);
        $this->assertSame('2023-01-15', $result->dto->publishedDate->format('Y-m-d'));
    }

    public function test_maps_link(): void
    {
        $result = $this->service->mapToWorkFormDto($this->makeScraped());

        $this->assertStringContainsString('archiveofourown.org/works/11111', $result->dto->link ?? '');
    }

    // --- Language lookup ---

    public function test_sets_language_when_found(): void
    {
        $language = new Language('English');
        $this->languageRepo->method('findOneBy')->willReturn($language);

        $scraped = $this->makeScraped();
        $scraped->language = 'English';

        $result = $this->service->mapToWorkFormDto($scraped);

        $this->assertSame($language, $result->dto->language);
        $this->assertEmpty($result->warnings);
    }

    public function test_auto_creates_language_when_not_found(): void
    {
        $this->languageRepo->method('findOneBy')->willReturn(null);

        $scraped = $this->makeScraped();
        $scraped->language = 'Klingon';

        $result = $this->service->mapToWorkFormDto($scraped);

        $this->assertNotNull($result->dto->language);
        $this->assertSame('Klingon', $result->dto->language->getName());
        $this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('Klingon', implode(' ', $result->warnings));
        $this->assertStringContainsString('auto-created', implode(' ', $result->warnings));
    }

    // --- Series handling ---

    public function test_sets_series_when_found_in_db(): void
    {
        $series = new Series('Existing Series');
        $this->seriesRepo->method('findOneBy')->willReturn($series);

        $scraped = $this->makeScraped();
        $scraped->seriesName = 'Existing Series';
        $scraped->placeInSeries = 1;

        $result = $this->service->mapToWorkFormDto($scraped);

        $this->assertSame($series, $result->dto->series);
        $this->assertSame(1, $result->dto->placeInSeries);
    }

    public function test_auto_creates_series_when_not_found(): void
    {
        $seriesRepo = $this->createStub(SeriesRepository::class);
        $seriesRepo->method('findOneBy')->willReturn(null);

        // Use createMock for em so we can verify persist+flush are called
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = new ImportService(
            $this->languageRepo,
            $seriesRepo,
            $this->metadataTypeRepo,
            $em,
        );

        $scraped = $this->makeScraped();
        $scraped->seriesName = 'New Series';
        $scraped->seriesUrl = 'https://archiveofourown.org/series/99999';
        $scraped->placeInSeries = 2;

        $result = $service->mapToWorkFormDto($scraped);

        $this->assertNotNull($result->dto->series);
        $this->assertSame('New Series', $result->dto->series->getName());
        $this->assertSame(2, $result->dto->placeInSeries);
    }

    // --- Metadata mapping ---

    public function test_maps_metadata_to_known_types(): void
    {
        $ratingType = new MetadataType('Rating', true);
        $this->metadataTypeRepo->method('findOneBy')->willReturn($ratingType);

        $scraped = $this->makeScraped();
        $scraped->metadata = ['Rating' => ['General Audiences']];

        $result = $this->service->mapToWorkFormDto($scraped);

        $this->assertCount(1, $result->dto->metadata);
        $this->assertSame($ratingType, $result->dto->metadata[0]['metadataType']);
        $this->assertSame('General Audiences', $result->dto->metadata[0]['name']);
    }

    public function test_applies_synonym_map_relationship_to_pairing(): void
    {
        $pairingType = new MetadataType('Pairing', true);
        $this->metadataTypeRepo
            ->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use ($pairingType): ?MetadataType {
                return $criteria['name'] === 'Pairing' ? $pairingType : null;
            });

        $scraped = $this->makeScraped();
        // AO3 calls them "Relationship"; our DB uses "Pairing"
        $scraped->metadata = ['Relationship' => ['Character A/Character B']];

        $result = $this->service->mapToWorkFormDto($scraped);

        $this->assertCount(1, $result->dto->metadata);
        $this->assertSame($pairingType, $result->dto->metadata[0]['metadataType']);
    }

    public function test_auto_creates_metadata_type_when_not_found(): void
    {
        $this->metadataTypeRepo->method('findOneBy')->willReturn(null);

        $scraped = $this->makeScraped();
        $scraped->metadata = ['UnknownCategory' => ['SomeTag']];

        $result = $this->service->mapToWorkFormDto($scraped);

        $this->assertCount(1, $result->dto->metadata);
        $this->assertSame('UnknownCategory', $result->dto->metadata[0]['metadataType']->getName());
        $this->assertSame('SomeTag', $result->dto->metadata[0]['name']);
        $this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('UnknownCategory', implode(' ', $result->warnings));
        $this->assertStringContainsString('auto-created', implode(' ', $result->warnings));
    }

    public function test_skips_empty_tag_names(): void
    {
        $ratingType = new MetadataType('Rating', true);
        $this->metadataTypeRepo->method('findOneBy')->willReturn($ratingType);

        $scraped = $this->makeScraped();
        $scraped->metadata = ['Rating' => ['  ', 'General Audiences', '']];

        $result = $this->service->mapToWorkFormDto($scraped);

        // Only "General Audiences" should be added; empty strings are skipped
        $this->assertCount(1, $result->dto->metadata);
    }

    // --- No warnings on clean import ---

    public function test_no_warnings_when_all_fields_map_cleanly(): void
    {
        $scraped = $this->makeScraped();
        // No language, no series, no metadata — nothing to look up

        $result = $this->service->mapToWorkFormDto($scraped);

        $this->assertEmpty($result->warnings);
    }
}
