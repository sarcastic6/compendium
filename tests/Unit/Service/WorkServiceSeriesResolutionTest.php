<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\WorkFormDto;
use App\Entity\Series;
use App\Enum\SourceType;
use App\Enum\WorkType;
use App\Repository\MetadataRepository;
use App\Repository\MetadataTypeRepository;
use App\Repository\SeriesRepository;
use App\Service\WorkService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WorkService three-way series resolution.
 *
 * Covers: seriesId set → load by ID, seriesId null + name → find-or-create,
 * both null → no series, and invalid seriesId → exception.
 */
class WorkServiceSeriesResolutionTest extends TestCase
{
    private EntityManagerInterface $em;
    private SeriesRepository $seriesRepo;
    private WorkService $service;

    protected function setUp(): void
    {
        $this->em = $this->createStub(EntityManagerInterface::class);
        $metadataRepo = $this->createStub(MetadataRepository::class);
        $metadataTypeRepo = $this->createStub(MetadataTypeRepository::class);
        $this->seriesRepo = $this->createStub(SeriesRepository::class);

        $metadataRepo->method('findOneBy')->willReturn(null);
        $metadataTypeRepo->method('findOneBy')->willReturn(null);

        $this->service = new WorkService($this->em, $metadataRepo, $metadataTypeRepo, $this->seriesRepo);
    }

    private function makeDto(): WorkFormDto
    {
        $dto = new WorkFormDto();
        $dto->type = WorkType::Fanfiction;
        $dto->title = 'Test Work';
        $dto->sourceType = SourceType::Manual;

        return $dto;
    }

    public function test_series_id_set_loads_existing_series(): void
    {
        $series = new Series('Existing Series');

        $this->seriesRepo->method('find')->willReturn($series);

        $dto = $this->makeDto();
        $dto->seriesId = 42;
        $dto->seriesName = 'Ignored Name';

        $work = $this->service->createWork($dto);

        $this->assertSame($series, $work->getSeries());
    }

    public function test_invalid_series_id_throws_exception(): void
    {
        $this->seriesRepo->method('find')->willReturn(null);

        $dto = $this->makeDto();
        $dto->seriesId = 99999;
        $dto->seriesName = 'Some Name';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Series with ID 99999 not found');

        $this->service->createWork($dto);
    }

    public function test_no_series_id_with_name_creates_series(): void
    {
        $this->seriesRepo->method('findOneBy')->willReturn(null);

        $dto = $this->makeDto();
        $dto->seriesId = null;
        $dto->seriesName = 'New Series';

        $work = $this->service->createWork($dto);

        $this->assertNotNull($work->getSeries());
        $this->assertSame('New Series', $work->getSeries()->getName());
    }

    public function test_both_null_means_no_series(): void
    {
        $dto = $this->makeDto();
        $dto->seriesId = null;
        $dto->seriesName = null;

        $work = $this->service->createWork($dto);

        $this->assertNull($work->getSeries());
    }

    public function test_empty_series_name_means_no_series(): void
    {
        $dto = $this->makeDto();
        $dto->seriesId = null;
        $dto->seriesName = '   ';

        $work = $this->service->createWork($dto);

        $this->assertNull($work->getSeries());
    }
}
