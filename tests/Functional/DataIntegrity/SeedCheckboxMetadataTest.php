<?php

declare(strict_types=1);

namespace App\Tests\Functional\DataIntegrity;

use App\Entity\Metadata;
use App\Entity\MetadataType;
use App\Tests\Functional\AbstractFunctionalTest;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the app:seed-checkbox-metadata console command for correctness
 * and idempotency.
 */
class SeedCheckboxMetadataTest extends AbstractFunctionalTest
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $application = new Application(self::$kernel);
        $command = $application->find('app:seed-checkbox-metadata');
        $this->commandTester = new CommandTester($command);
    }

    public function test_seed_creates_all_types_and_values(): void
    {
        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());

        // Verify the three types exist with correct flags
        $ratingType = $this->em->getRepository(MetadataType::class)->findOneBy(['name' => 'Rating']);
        $this->assertNotNull($ratingType);
        $this->assertFalse($ratingType->isMultipleAllowed());
        $this->assertTrue($ratingType->isShowAsDropdown());
        $this->assertTrue($ratingType->isShowAsCheckboxes());

        $warningType = $this->em->getRepository(MetadataType::class)->findOneBy(['name' => 'Warning']);
        $this->assertNotNull($warningType);
        $this->assertTrue($warningType->isMultipleAllowed());

        $categoryType = $this->em->getRepository(MetadataType::class)->findOneBy(['name' => 'Category']);
        $this->assertNotNull($categoryType);
        $this->assertTrue($categoryType->isMultipleAllowed());

        // Verify value counts
        $ratingValues = $this->em->getRepository(Metadata::class)->findBy(['metadataType' => $ratingType]);
        $this->assertCount(5, $ratingValues);

        $warningValues = $this->em->getRepository(Metadata::class)->findBy(['metadataType' => $warningType]);
        $this->assertCount(6, $warningValues);

        $categoryValues = $this->em->getRepository(Metadata::class)->findBy(['metadataType' => $categoryType]);
        $this->assertCount(6, $categoryValues);
    }

    public function test_seed_is_idempotent(): void
    {
        // Run twice
        $this->commandTester->execute([]);
        $this->assertSame(0, $this->commandTester->getStatusCode());

        $this->commandTester->execute([]);
        $this->assertSame(0, $this->commandTester->getStatusCode());

        // Verify no duplicates
        $types = $this->em->getRepository(MetadataType::class)->findBy(['name' => 'Rating']);
        $this->assertCount(1, $types);

        $ratingType = $types[0];
        $ratingValues = $this->em->getRepository(Metadata::class)->findBy(['metadataType' => $ratingType]);
        $this->assertCount(5, $ratingValues);

        $warningType = $this->em->getRepository(MetadataType::class)->findOneBy(['name' => 'Warning']);
        $warningValues = $this->em->getRepository(Metadata::class)->findBy(['metadataType' => $warningType]);
        $this->assertCount(6, $warningValues);

        $categoryType = $this->em->getRepository(MetadataType::class)->findOneBy(['name' => 'Category']);
        $categoryValues = $this->em->getRepository(Metadata::class)->findBy(['metadataType' => $categoryType]);
        $this->assertCount(6, $categoryValues);
    }

    public function test_seed_preserves_existing_type_when_already_present(): void
    {
        // Pre-create a Rating type without the checkbox flags
        $existingType = new MetadataType('Rating', false);
        $this->em->persist($existingType);
        $this->em->flush();
        $existingId = $existingType->getId();

        $this->commandTester->execute([]);

        // Verify the same type entity is reused (not a new one)
        $types = $this->em->getRepository(MetadataType::class)->findBy(['name' => 'Rating']);
        $this->assertCount(1, $types);
        $this->assertSame($existingId, $types[0]->getId());

        // Values should still be created under the existing type
        $ratingValues = $this->em->getRepository(Metadata::class)->findBy(['metadataType' => $types[0]]);
        $this->assertCount(5, $ratingValues);
    }
}
