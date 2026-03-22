<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add show_as_checkboxes column to metadata_types.
 *
 * When true, the work form renders this type as a checkbox group instead of
 * an autocomplete text input. Set to true for Rating, Warning, and Category
 * (small, stable vocabularies) if those rows already exist.
 */
final class Version20260322000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add show_as_checkboxes to metadata_types; enable for Rating, Warning, Category';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE metadata_types ADD COLUMN show_as_checkboxes BOOLEAN NOT NULL DEFAULT 0');
        // Update existing rows for the three small-vocabulary types.
        // If these types don't exist yet (fresh install), the UPDATE is a no-op.
        $this->addSql("UPDATE metadata_types SET show_as_checkboxes = 1 WHERE name IN ('Rating', 'Warning', 'Category')");
    }

    public function down(Schema $schema): void
    {
        // SQLite does not support DROP COLUMN; recreate the table without the column.
        $this->addSql('CREATE TEMPORARY TABLE __temp__metadata_types AS SELECT id, name, multiple_allowed, show_as_dropdown FROM metadata_types');
        $this->addSql('DROP TABLE metadata_types');
        $this->addSql('CREATE TABLE metadata_types (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, multiple_allowed BOOLEAN NOT NULL DEFAULT 1, show_as_dropdown BOOLEAN NOT NULL DEFAULT 0)');
        $this->addSql('INSERT INTO metadata_types (id, name, multiple_allowed, show_as_dropdown) SELECT id, name, multiple_allowed, show_as_dropdown FROM __temp__metadata_types');
        $this->addSql('DROP TABLE __temp__metadata_types');
        $this->addSql('CREATE UNIQUE INDEX uq_metadata_type_name ON metadata_types (name)');
    }
}
