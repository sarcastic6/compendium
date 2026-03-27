<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seeds the default statuses and metadata types.
 *
 * Uses WHERE NOT EXISTS for each INSERT so the migration is safe to run on
 * both fresh installs (where nothing exists yet) and existing installs (where
 * data was created manually at runtime before this seed existed).
 *
 * Also corrects a misconfiguration on the Rating metadata type:
 *   - multiple_allowed was incorrectly set to 1 (a work has one AO3 rating)
 *   - show_as_checkboxes was incorrectly set to 1 (single-value type; checkboxes make no sense)
 *
 * Default statuses:
 *   TBR       — not started, not read, not active
 *   Reading   — started, not read, active (floats to top of list)
 *   On Hold   — started, not read, not active
 *   Completed — started, counts as read, not active
 *   DNF       — started, not read, not active
 *
 * Default metadata types:
 *   Author, Fandom, Relationships, Character, Tag — multi-value, free-text filter, text input on form
 *   Rating    — single-value, dropdown filter, dropdown on form
 *   Warning   — multi-value, dropdown filter, checkboxes on form
 *   Category  — multi-value, dropdown filter, checkboxes on form
 */
final class Version20260327010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed default statuses and metadata types; fix Rating multiple_allowed and show_as_checkboxes';
    }

    public function up(Schema $schema): void
    {
        // Statuses
        $this->addSql("INSERT INTO statuses (name, has_been_started, counts_as_read, is_active)
            SELECT 'TBR', 0, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM statuses WHERE name = 'TBR')");
        $this->addSql("INSERT INTO statuses (name, has_been_started, counts_as_read, is_active)
            SELECT 'Reading', 1, 0, 1 WHERE NOT EXISTS (SELECT 1 FROM statuses WHERE name = 'Reading')");
        $this->addSql("INSERT INTO statuses (name, has_been_started, counts_as_read, is_active)
            SELECT 'On Hold', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM statuses WHERE name = 'On Hold')");
        $this->addSql("INSERT INTO statuses (name, has_been_started, counts_as_read, is_active)
            SELECT 'Completed', 1, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM statuses WHERE name = 'Completed')");
        $this->addSql("INSERT INTO statuses (name, has_been_started, counts_as_read, is_active)
            SELECT 'DNF', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM statuses WHERE name = 'DNF')");

        // Metadata types
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes)
            SELECT 'Author', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Author')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes)
            SELECT 'Fandom', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Fandom')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes)
            SELECT 'Relationships', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Relationships')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes)
            SELECT 'Character', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Character')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes)
            SELECT 'Tag', 1, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Tag')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes)
            SELECT 'Rating', 0, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Rating')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes)
            SELECT 'Warning', 1, 1, 1 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Warning')");
        $this->addSql("INSERT INTO metadata_types (name, multiple_allowed, show_as_dropdown, show_as_checkboxes)
            SELECT 'Category', 1, 1, 1 WHERE NOT EXISTS (SELECT 1 FROM metadata_types WHERE name = 'Category')");

        // Fix Rating misconfiguration on existing installs
        $this->addSql("UPDATE metadata_types SET multiple_allowed = 0, show_as_checkboxes = 0 WHERE name = 'Rating'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM statuses WHERE name IN ('TBR', 'Reading', 'On Hold', 'Completed', 'DNF')");
        $this->addSql("DELETE FROM metadata_types WHERE name IN ('Author', 'Fandom', 'Relationships', 'Character', 'Tag', 'Rating', 'Warning', 'Category')");
    }
}
