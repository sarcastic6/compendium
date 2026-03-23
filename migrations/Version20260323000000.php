<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add total_words and is_complete columns to the series table.
 *
 * These are populated from the AO3 series page when a work is imported.
 * Both are nullable because:
 *   - series created before this migration have no scraped values yet
 *   - manually entered series may never have these values
 */
final class Version20260323000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add total_words and is_complete to series';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE series ADD COLUMN total_words INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE series ADD COLUMN is_complete BOOLEAN DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // SQLite does not support DROP COLUMN; recreate the table without the new columns.
        $this->addSql('CREATE TEMPORARY TABLE __temp__series AS SELECT id, name, number_of_parts FROM series');
        $this->addSql('DROP TABLE series');
        $this->addSql('CREATE TABLE series (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, number_of_parts INTEGER DEFAULT NULL)');
        $this->addSql('INSERT INTO series (id, name, number_of_parts) SELECT id, name, number_of_parts FROM __temp__series');
        $this->addSql('DROP TABLE __temp__series');
        $this->addSql('CREATE UNIQUE INDEX uq_series_name ON series (name)');
    }
}
