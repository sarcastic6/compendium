<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\MetadataType;
use App\Repository\MetadataRepository;
use App\Repository\MetadataTypeRepository;
use App\Repository\SeriesRepository;
use App\Service\WorkService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WorkService::resolveCheckboxPreselections().
 *
 * Verifies correct partitioning of DTO metadata into checkbox-type
 * preselections vs. autocomplete entries.
 */
class WorkServiceCheckboxPreselectionTest extends TestCase
{
    private WorkService $service;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $metadataRepo = $this->createStub(MetadataRepository::class);
        $metadataTypeRepo = $this->createStub(MetadataTypeRepository::class);
        $seriesRepo = $this->createStub(SeriesRepository::class);

        $this->service = new WorkService($em, $metadataRepo, $metadataTypeRepo, $seriesRepo);
    }

    private function createMetadataTypeWithId(string $name, int $id): MetadataType
    {
        $type = new MetadataType($name);
        $reflection = new \ReflectionProperty(MetadataType::class, 'id');
        $reflection->setValue($type, $id);

        return $type;
    }

    public function test_extracts_checkbox_type_names(): void
    {
        $ratingType = $this->createMetadataTypeWithId('Rating', 1);
        $fandomType = $this->createMetadataTypeWithId('Fandom', 2);

        $dtoMetadata = [
            ['metadataType' => $ratingType, 'name' => 'Mature', 'link' => null],
            ['metadataType' => $fandomType, 'name' => 'Harry Potter', 'link' => null],
            ['metadataType' => $ratingType, 'name' => 'Explicit', 'link' => null],
        ];

        $result = $this->service->resolveCheckboxPreselections($dtoMetadata, [$ratingType]);

        $this->assertArrayHasKey(1, $result);
        $this->assertSame(['Mature', 'Explicit'], $result[1]);
        $this->assertArrayNotHasKey(2, $result);
    }

    public function test_returns_empty_when_no_checkbox_types_match(): void
    {
        $fandomType = $this->createMetadataTypeWithId('Fandom', 2);

        $dtoMetadata = [
            ['metadataType' => $fandomType, 'name' => 'Harry Potter', 'link' => null],
        ];

        $result = $this->service->resolveCheckboxPreselections($dtoMetadata, []);

        $this->assertSame([], $result);
    }

    public function test_skips_entries_with_empty_names(): void
    {
        $ratingType = $this->createMetadataTypeWithId('Rating', 1);

        $dtoMetadata = [
            ['metadataType' => $ratingType, 'name' => '', 'link' => null],
            ['metadataType' => $ratingType, 'name' => '   ', 'link' => null],
            ['metadataType' => $ratingType, 'name' => 'Mature', 'link' => null],
        ];

        $result = $this->service->resolveCheckboxPreselections($dtoMetadata, [$ratingType]);

        $this->assertSame(['Mature'], $result[1]);
    }

    public function test_skips_entries_without_metadata_type(): void
    {
        $ratingType = $this->createMetadataTypeWithId('Rating', 1);

        $dtoMetadata = [
            ['metadataType' => null, 'name' => 'Orphan', 'link' => null],
            ['name' => 'No Type Key', 'link' => null],
            ['metadataType' => $ratingType, 'name' => 'Mature', 'link' => null],
        ];

        $result = $this->service->resolveCheckboxPreselections($dtoMetadata, [$ratingType]);

        $this->assertSame(['Mature'], $result[1]);
    }

    public function test_handles_multiple_checkbox_types(): void
    {
        $ratingType = $this->createMetadataTypeWithId('Rating', 1);
        $warningType = $this->createMetadataTypeWithId('Warning', 3);
        $fandomType = $this->createMetadataTypeWithId('Fandom', 2);

        $dtoMetadata = [
            ['metadataType' => $ratingType, 'name' => 'Mature', 'link' => null],
            ['metadataType' => $warningType, 'name' => 'Major Character Death', 'link' => null],
            ['metadataType' => $fandomType, 'name' => 'Harry Potter', 'link' => null],
            ['metadataType' => $warningType, 'name' => 'Underage Sex', 'link' => null],
        ];

        $result = $this->service->resolveCheckboxPreselections($dtoMetadata, [$ratingType, $warningType]);

        $this->assertSame(['Mature'], $result[1]);
        $this->assertSame(['Major Character Death', 'Underage Sex'], $result[3]);
        $this->assertArrayNotHasKey(2, $result);
    }

    public function test_does_not_mutate_input_array(): void
    {
        $ratingType = $this->createMetadataTypeWithId('Rating', 1);
        $fandomType = $this->createMetadataTypeWithId('Fandom', 2);

        $dtoMetadata = [
            ['metadataType' => $ratingType, 'name' => 'Mature', 'link' => null],
            ['metadataType' => $fandomType, 'name' => 'Harry Potter', 'link' => null],
        ];

        $originalCount = count($dtoMetadata);

        $this->service->resolveCheckboxPreselections($dtoMetadata, [$ratingType]);

        $this->assertCount($originalCount, $dtoMetadata);
    }
}
