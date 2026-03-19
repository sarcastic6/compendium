<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replaces the single is_finished boolean on statuses with two distinct flags:
 *
 *   has_been_started — true when the user has actually begun reading this work
 *                      (Reading, On Hold, Completed, DNF). False for TBR.
 *                      Used to determine which entries contribute to word count stats.
 *
 *   counts_as_read   — true only when the status represents a successfully
 *                      completed read (typically only 'Completed').
 *                      Used for trend charts, finished count, finish rate,
 *                      and the Read Count column in rankings.
 *
 * Data migration logic:
 *   - has_been_started defaults to 1 (true) for all rows; set to 0 only for 'TBR'
 *   - counts_as_read defaults to 0 (false) for all rows; set to 1 only for 'Completed'
 *   - All other statuses (Reading, On Hold, DNF, user-created) keep the defaults
 *     and can be adjusted via the admin UI after migration
 */
final class Version20260319000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace is_finished on statuses with has_been_started and counts_as_read';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Add the two new columns with safe defaults
        $this->addSql('ALTER TABLE statuses ADD COLUMN has_been_started BOOLEAN DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE statuses ADD COLUMN counts_as_read BOOLEAN DEFAULT 0 NOT NULL');

        // Step 2: Populate from known seed data
        // TBR has not been started (user hasn't read it yet)
        $this->addSql("UPDATE statuses SET has_been_started = 0 WHERE name = 'TBR'");
        // Only Completed counts as a fully read work
        $this->addSql("UPDATE statuses SET counts_as_read = 1 WHERE name = 'Completed'");

        // Step 3: Recreate the table without is_finished (SQLite does not support
        // DROP COLUMN reliably across all versions; use the temp-table approach)
        $this->addSql('CREATE TEMPORARY TABLE __temp__statuses AS SELECT id, name, has_been_started, counts_as_read FROM statuses');
        $this->addSql('DROP TABLE statuses');
        $this->addSql('CREATE TABLE statuses (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, has_been_started BOOLEAN DEFAULT 1 NOT NULL, counts_as_read BOOLEAN DEFAULT 0 NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uq_status_name ON statuses (name)');
        $this->addSql('INSERT INTO statuses (id, name, has_been_started, counts_as_read) SELECT id, name, has_been_started, counts_as_read FROM __temp__statuses');
        $this->addSql('DROP TABLE __temp__statuses');
    }

    public function down(Schema $schema): void
    {
        // Restore is_finished as best approximation: counts_as_read = true → was finished.
        // NOTE: DNF entries had is_finished = true originally but counts_as_read = false,
        // so this rollback is lossy for DNF statuses.
        $this->addSql('CREATE TEMPORARY TABLE __temp__statuses AS SELECT id, name, counts_as_read FROM statuses');
        $this->addSql('DROP TABLE statuses');
        $this->addSql('CREATE TABLE statuses (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, is_finished BOOLEAN DEFAULT 0 NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uq_status_name ON statuses (name)');
        $this->addSql('INSERT INTO statuses (id, name, is_finished) SELECT id, name, counts_as_read FROM __temp__statuses');
        $this->addSql('DROP TABLE __temp__statuses');
    }
}
