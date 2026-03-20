<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320220853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE metadata_types ADD COLUMN show_as_dropdown BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__metadata_types AS SELECT id, name, multiple_allowed FROM metadata_types');
        $this->addSql('DROP TABLE metadata_types');
        $this->addSql('CREATE TABLE metadata_types (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, multiple_allowed BOOLEAN DEFAULT 1 NOT NULL)');
        $this->addSql('INSERT INTO metadata_types (id, name, multiple_allowed) SELECT id, name, multiple_allowed FROM __temp__metadata_types');
        $this->addSql('DROP TABLE __temp__metadata_types');
        $this->addSql('CREATE UNIQUE INDEX uq_metadata_type_name ON metadata_types (name)');
    }
}
