<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Metadata;
use App\Entity\MetadataType;
use App\Repository\MetadataRepository;
use App\Repository\MetadataTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-checkbox-metadata',
    description: 'Seed the database with AO3 canonical values for Rating, Warning, and Category metadata types.',
)]
class SeedCheckboxMetadataCommand extends Command
{
    /**
     * AO3 canonical vocabularies for checkbox metadata types.
     *
     * Each key is a MetadataType name. The value array contains:
     *   - 'multipleAllowed': whether a work can have multiple values of this type
     *   - 'values': the canonical AO3 values to seed
     *
     * @var array<string, array{multipleAllowed: bool, values: list<string>}>
     */
    private const CHECKBOX_TYPES = [
        'Rating' => [
            'multipleAllowed' => false,
            'values' => [
                'General Audiences',
                'Teen And Up Audiences',
                'Mature',
                'Explicit',
                'Not Rated',
            ],
        ],
        'Warning' => [
            'multipleAllowed' => true,
            'values' => [
                'No Archive Warnings Apply',
                'Graphic Depictions Of Violence',
                'Major Character Death',
                'Rape/Non-Con',
                'Underage Sex',
                'Creator Chose Not To Use Archive Warnings',
            ],
        ],
        'Category' => [
            'multipleAllowed' => true,
            'values' => [
                'F/F',
                'F/M',
                'Gen',
                'M/M',
                'Multi',
                'Other',
            ],
        ],
    ];

    public function __construct(
        private readonly MetadataTypeRepository $metadataTypeRepository,
        private readonly MetadataRepository $metadataRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $typesCreated = 0;
        $typesExisted = 0;
        $valuesCreated = 0;
        $valuesExisted = 0;

        foreach (self::CHECKBOX_TYPES as $typeName => $config) {
            $metadataType = $this->metadataTypeRepository->findOneBy(['name' => $typeName]);

            if ($metadataType === null) {
                $metadataType = new MetadataType($typeName, $config['multipleAllowed']);
                $metadataType->setShowAsDropdown(true);
                $metadataType->setShowAsCheckboxes(true);
                $this->entityManager->persist($metadataType);
                $typesCreated++;
            } else {
                $typesExisted++;
            }

            foreach ($config['values'] as $valueName) {
                $existing = $this->metadataRepository->findOneBy([
                    'name' => $valueName,
                    'metadataType' => $metadataType,
                ]);

                if ($existing === null) {
                    $metadata = new Metadata($valueName, $metadataType);
                    $this->entityManager->persist($metadata);
                    $valuesCreated++;
                } else {
                    $valuesExisted++;
                }
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Seeding complete. Types: %d created, %d already existed. Values: %d created, %d already existed.',
            $typesCreated,
            $typesExisted,
            $valuesCreated,
            $valuesExisted,
        ));

        return Command::SUCCESS;
    }
}
