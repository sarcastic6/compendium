<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260314175806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE authors (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, link VARCHAR(1024) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uq_author_name ON authors (name)');
        $this->addSql('CREATE TABLE languages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, link VARCHAR(1024) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uq_language_name ON languages (name)');
        $this->addSql('CREATE TABLE metadata (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, link VARCHAR(1024) DEFAULT NULL, metadata_type_id INTEGER NOT NULL, CONSTRAINT FK_4F143414573D5C6B FOREIGN KEY (metadata_type_id) REFERENCES metadata_types (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_metadata_name ON metadata (name)');
        $this->addSql('CREATE INDEX idx_metadata_type ON metadata (metadata_type_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_metadata_name_type ON metadata (name, metadata_type_id)');
        $this->addSql('CREATE TABLE metadata_types (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, multiple_allowed BOOLEAN DEFAULT 1 NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uq_metadata_type_name ON metadata_types (name)');
        $this->addSql('CREATE TABLE reading_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date_started DATE DEFAULT NULL, date_finished DATE DEFAULT NULL, last_read_chapter INTEGER DEFAULT NULL, review_stars INTEGER DEFAULT NULL, spice_stars INTEGER DEFAULT NULL, comments CLOB DEFAULT NULL, starred BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INTEGER NOT NULL, work_id INTEGER NOT NULL, status_id INTEGER NOT NULL, main_pairing_id INTEGER DEFAULT NULL, CONSTRAINT FK_696120F0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_696120F0BB3453DB FOREIGN KEY (work_id) REFERENCES works (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_696120F06BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_696120F0E47832DE FOREIGN KEY (main_pairing_id) REFERENCES metadata (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_696120F0E47832DE ON reading_entries (main_pairing_id)');
        $this->addSql('CREATE INDEX idx_re_user ON reading_entries (user_id)');
        $this->addSql('CREATE INDEX idx_re_work ON reading_entries (work_id)');
        $this->addSql('CREATE INDEX idx_re_status ON reading_entries (status_id)');
        $this->addSql('CREATE INDEX idx_re_user_status ON reading_entries (user_id, status_id)');
        $this->addSql('CREATE INDEX idx_re_user_date_finished ON reading_entries (user_id, date_finished)');
        $this->addSql('CREATE TABLE reset_password_requests (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_16646B41A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_16646B41A76ED395 ON reset_password_requests (user_id)');
        $this->addSql('CREATE TABLE series (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, link VARCHAR(1024) DEFAULT NULL, number_of_parts INTEGER DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uq_series_name ON series (name)');
        $this->addSql('CREATE TABLE statuses (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, is_finished BOOLEAN DEFAULT 0 NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uq_status_name ON statuses (name)');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, is_disabled BOOLEAN DEFAULT 0 NOT NULL, is_mfa_enabled BOOLEAN DEFAULT 0 NOT NULL, mfa_secret VARCHAR(255) DEFAULT NULL, mfa_methods VARCHAR(255) DEFAULT NULL, email_auth_code VARCHAR(10) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uq_user_email ON users (email)');
        $this->addSql('CREATE TABLE works (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(32) NOT NULL, title VARCHAR(500) NOT NULL, summary CLOB DEFAULT NULL, place_in_series INTEGER DEFAULT NULL, published_date DATE DEFAULT NULL, last_updated_date DATE DEFAULT NULL, words INTEGER DEFAULT NULL, chapters INTEGER DEFAULT NULL, link VARCHAR(1024) DEFAULT NULL, source_type VARCHAR(32) NOT NULL, starred BOOLEAN DEFAULT 0 NOT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, series_id INTEGER DEFAULT NULL, language_id INTEGER DEFAULT NULL, CONSTRAINT FK_F6E502435278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F6E5024382F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_work_series ON works (series_id)');
        $this->addSql('CREATE INDEX idx_work_language ON works (language_id)');
        $this->addSql('CREATE TABLE work_authors (work_id INTEGER NOT NULL, author_id INTEGER NOT NULL, PRIMARY KEY (work_id, author_id), CONSTRAINT FK_5BC79273BB3453DB FOREIGN KEY (work_id) REFERENCES works (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5BC79273F675F31B FOREIGN KEY (author_id) REFERENCES authors (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_5BC79273BB3453DB ON work_authors (work_id)');
        $this->addSql('CREATE INDEX IDX_5BC79273F675F31B ON work_authors (author_id)');
        $this->addSql('CREATE TABLE works_metadata (work_id INTEGER NOT NULL, metadata_id INTEGER NOT NULL, PRIMARY KEY (work_id, metadata_id), CONSTRAINT FK_56BD3A7FBB3453DB FOREIGN KEY (work_id) REFERENCES works (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_56BD3A7FDC9EE959 FOREIGN KEY (metadata_id) REFERENCES metadata (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_56BD3A7FBB3453DB ON works_metadata (work_id)');
        $this->addSql('CREATE INDEX IDX_56BD3A7FDC9EE959 ON works_metadata (metadata_id)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE authors');
        $this->addSql('DROP TABLE languages');
        $this->addSql('DROP TABLE metadata');
        $this->addSql('DROP TABLE metadata_types');
        $this->addSql('DROP TABLE reading_entries');
        $this->addSql('DROP TABLE reset_password_requests');
        $this->addSql('DROP TABLE series');
        $this->addSql('DROP TABLE statuses');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE works');
        $this->addSql('DROP TABLE work_authors');
        $this->addSql('DROP TABLE works_metadata');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
