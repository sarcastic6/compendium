<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316004322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE metadata_source_links (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, source_type VARCHAR(32) NOT NULL, link VARCHAR(1024) NOT NULL, metadata_id INTEGER NOT NULL, CONSTRAINT FK_DE32DDDC9EE959 FOREIGN KEY (metadata_id) REFERENCES metadata (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_metadata_source_link_metadata ON metadata_source_links (metadata_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_metadata_source_link ON metadata_source_links (metadata_id, source_type)');
        $this->addSql('CREATE TABLE series_source_links (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, source_type VARCHAR(32) NOT NULL, link VARCHAR(1024) NOT NULL, series_id INTEGER NOT NULL, CONSTRAINT FK_AC01A9505278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_series_source_link_series ON series_source_links (series_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_series_source_link ON series_source_links (series_id, source_type)');
        $this->addSql('DROP TABLE authors');
        $this->addSql('DROP TABLE work_authors');
        $this->addSql('CREATE TEMPORARY TABLE __temp__metadata AS SELECT id, name, metadata_type_id FROM metadata');
        $this->addSql('DROP TABLE metadata');
        $this->addSql('CREATE TABLE metadata (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, metadata_type_id INTEGER NOT NULL, CONSTRAINT FK_4F143414573D5C6B FOREIGN KEY (metadata_type_id) REFERENCES metadata_types (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO metadata (id, name, metadata_type_id) SELECT id, name, metadata_type_id FROM __temp__metadata');
        $this->addSql('DROP TABLE __temp__metadata');
        $this->addSql('CREATE UNIQUE INDEX uq_metadata_name_type ON metadata (name, metadata_type_id)');
        $this->addSql('CREATE INDEX idx_metadata_type ON metadata (metadata_type_id)');
        $this->addSql('CREATE INDEX idx_metadata_name ON metadata (name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__series AS SELECT id, name, number_of_parts FROM series');
        $this->addSql('DROP TABLE series');
        $this->addSql('CREATE TABLE series (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, number_of_parts INTEGER DEFAULT NULL)');
        $this->addSql('INSERT INTO series (id, name, number_of_parts) SELECT id, name, number_of_parts FROM __temp__series');
        $this->addSql('DROP TABLE __temp__series');
        $this->addSql('CREATE UNIQUE INDEX uq_series_name ON series (name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE authors (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL COLLATE "BINARY", link VARCHAR(1024) DEFAULT NULL COLLATE "BINARY")');
        $this->addSql('CREATE UNIQUE INDEX uq_author_name ON authors (name)');
        $this->addSql('CREATE TABLE work_authors (work_id INTEGER NOT NULL, author_id INTEGER NOT NULL, PRIMARY KEY (work_id, author_id), CONSTRAINT FK_5BC79273BB3453DB FOREIGN KEY (work_id) REFERENCES works (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5BC79273F675F31B FOREIGN KEY (author_id) REFERENCES authors (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_5BC79273F675F31B ON work_authors (author_id)');
        $this->addSql('CREATE INDEX IDX_5BC79273BB3453DB ON work_authors (work_id)');
        $this->addSql('DROP TABLE metadata_source_links');
        $this->addSql('DROP TABLE series_source_links');
        $this->addSql('ALTER TABLE metadata ADD COLUMN link VARCHAR(1024) DEFAULT NULL');
        $this->addSql('ALTER TABLE series ADD COLUMN link VARCHAR(1024) DEFAULT NULL');
    }
}
