<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename metadata type 'Pairing' to 'Relationships'.
 *
 * 'Pairing' was the original internal name for work relationship metadata (e.g. AO3 "Relationships").
 * It was renamed to 'Relationships' to avoid confusion with the reading entry-level 'Main Pairing'
 * field, which tracks the user's personal focus pairing for a specific read — a different concept.
 *
 * This is a data migration only — no schema change.
 */
final class Version20260320230224 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Rename metadata type 'Pairing' to 'Relationships'";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE metadata_types SET name = 'Relationships' WHERE name = 'Pairing'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE metadata_types SET name = 'Pairing' WHERE name = 'Relationships'");
    }
}
