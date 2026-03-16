<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\WorkFormDto;
use App\Entity\MetadataType;
use App\Enum\SourceType;
use App\Enum\WorkType;
use App\Repository\MetadataRepository;
use App\Repository\MetadataTypeRepository;
use App\Repository\SeriesRepository;
use App\Service\WorkService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WorkService metadata enforcement of multipleAllowed on unpersisted types.
 *
 * The key regression being guarded here: before Fix 7, spl_object_id() was not used,
 * so all unpersisted MetadataType objects shared the same tracking key (null from getId()),
 * causing false "multiple not allowed" errors when two different new types were used together.
 *
 * Covers: Fix 7 — spl_object_id() used instead of getId() for tracking unsaved MetadataType objects.
 */
class WorkServiceMultipleAllowedTest extends TestCase
{
    private WorkService $service;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $metadataRepo = $this->createStub(MetadataRepository::class);
        $metadataTypeRepo = $this->createStub(MetadataTypeRepository::class);
        $seriesRepo = $this->createStub(SeriesRepository::class);

        // findOneBy on MetadataRepository returns null — all metadata is new.
        $metadataRepo->method('findOneBy')->willReturn(null);

        // findOneBy on MetadataTypeRepository returns null — Author type is new.
        $metadataTypeRepo->method('findOneBy')->willReturn(null);

        $this->service = new WorkService($em, $metadataRepo, $metadataTypeRepo, $seriesRepo);
    }

    private function makeDto(): WorkFormDto
    {
        $dto = new WorkFormDto();
        $dto->type = WorkType::Fanfiction;
        $dto->title = 'Test Work';
        $dto->sourceType = SourceType::Manual;

        return $dto;
    }

    public function test_multiple_new_types_each_allow_single_entry(): void
    {
        // Two distinct unpersisted MetadataType objects — both have getId() === null,
        // but spl_object_id() gives them different keys, so they don't interfere.
        $typeA = new MetadataType('Fandom', true);
        $typeB = new MetadataType('Character', true);

        $dto = $this->makeDto();
        $dto->metadata = [
            ['metadataType' => $typeA, 'name' => 'Harry Potter', 'link' => null],
            ['metadataType' => $typeB, 'name' => 'Hermione Granger', 'link' => null],
        ];

        // Must not throw — each type has only one entry.
        $work = $this->service->createWork($dto);

        $this->assertCount(2, $work->getMetadata());
    }

    public function test_single_value_type_rejects_second_entry(): void
    {
        $ratingType = new MetadataType('Rating', false);

        $dto = $this->makeDto();
        $dto->metadata = [
            ['metadataType' => $ratingType, 'name' => 'General Audiences', 'link' => null],
            ['metadataType' => $ratingType, 'name' => 'Mature', 'link' => null],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->service->createWork($dto);
    }
}
