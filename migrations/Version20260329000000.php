<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed data for statuses and metadata_types.
 *
 * Kept in a separate migration from Version20260328000000 (schema creation)
 * because in Doctrine Migrations 3.x, addSql() statements execute *before*
 * the Schema API DDL. Mixing DDL and DML in the same migration causes
 * "no such table" errors on a clean install.
 *
 * All inserts are idempotent (WHERE NOT EXISTS), so this migration is safe
 * to run against an existing database that already has seed data.
 */
final class Version20260329000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed statuses and metadata_types reference data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO statuses (name, has_been_started, counts_as_read, is_active) SELECT 'TBR', 0, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM statuses WHERE name = 'TBR')");
        $this->addSql("INSERT INTO statuses (name, has_been_started, counts_as_read, is_active) SELECT 'Reading', 1, 0, 1 WHERE NOT EXISTS (SELECT 1 FROM statuses WHERE name = 'Reading')");
        $this->addSql("INSERT INTO statuses (name, has_been_started, counts_as_read, is_active) SELECT 'On Hold', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM statuses WHERE name = 'On Hold')");
        $this->addSql("INSERT INTO statuses (name, has_been_started, counts_as_read, is_active) SELECT 'Completed', 1, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM statuses WHERE name = 'Completed')");
        $this->addSql("INSERT INTO statuses (name, has_been_started, counts_as_read, is_active) SELECT 'DNF', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM statuses WHERE name = 'DNF')");

        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes) SELECT 'Author', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Author')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes) SELECT 'Fandom', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Fandom')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes) SELECT 'Relationships', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Relationships')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes) SELECT 'Character', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Character')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes) SELECT 'Tag', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Tag')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes) SELECT 'Rating', 0, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Rating')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes) SELECT 'Warning', 1, 1, 1 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Warning')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes) SELECT 'Category', 1, 1, 1 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Category')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM statuses WHERE name IN ('TBR', 'Reading', 'On Hold', 'Completed', 'DNF')");
        $this->addSql("DELETE FROM metadata_types WHERE name IN ('Author', 'Fandom', 'Relationships', 'Character', 'Tag', 'Rating', 'Warning', 'Category')");
    }
}
