<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds is_active flag to statuses.
 *
 * When true, reading entries with this status float to the top of the list
 * when sorting by completion date descending — regardless of whether they
 * have a date_finished value. Intended for actively-in-progress statuses
 * (e.g. Reading) so they remain visible and easy to update.
 *
 * DNF and On Hold default to false. The admin can toggle On Hold via the
 * admin UI based on personal preference.
 *
 * Data migration logic:
 *   - is_active defaults to 0 (false) for all rows
 *   - Set to 1 only for 'Reading'
 *   - All other statuses keep the default and can be adjusted via the admin UI
 */
final class Version20260327000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_active flag to statuses — controls floating to top when sorting by date';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE statuses ADD COLUMN is_active BOOLEAN DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE statuses SET is_active = 1 WHERE name = 'Reading'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__statuses AS SELECT id, name, has_been_started, counts_as_read FROM statuses');
        $this->addSql('DROP TABLE statuses');
        $this->addSql('CREATE TABLE statuses (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, has_been_started BOOLEAN DEFAULT 1 NOT NULL, counts_as_read BOOLEAN DEFAULT 0 NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uq_status_name ON statuses (name)');
        $this->addSql('INSERT INTO statuses (id, name, has_been_started, counts_as_read) SELECT id, name, has_been_started, counts_as_read FROM __temp__statuses');
        $this->addSql('DROP TABLE __temp__statuses');
    }
}
