<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds CHECK constraints to reading_entries.review_stars and spice_stars.
 *
 * The original migration omitted these constraints. This migration recreates
 * the table (SQLite requires full table recreation to add CHECK constraints)
 * with:
 *   - review_stars CHECK (review_stars BETWEEN 1 AND 5)
 *   - spice_stars  CHECK (spice_stars  BETWEEN 0 AND 5)
 *
 * spice_stars now allows 0 ("ice cold" — no spice), distinct from NULL (not rated).
 */
final class Version20260317000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CHECK constraints to review_stars (1-5) and spice_stars (0-5); allow spice_stars = 0 (ice cold)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__reading_entries AS SELECT id, date_started, date_finished, last_read_chapter, review_stars, spice_stars, comments, starred, created_at, updated_at, user_id, work_id, status_id, main_pairing_id FROM reading_entries');
        $this->addSql('DROP TABLE reading_entries');
        $this->addSql('CREATE TABLE reading_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date_started DATE DEFAULT NULL, date_finished DATE DEFAULT NULL, last_read_chapter INTEGER DEFAULT NULL, review_stars INTEGER DEFAULT NULL CHECK (review_stars BETWEEN 1 AND 5), spice_stars INTEGER DEFAULT NULL CHECK (spice_stars BETWEEN 0 AND 5), comments CLOB DEFAULT NULL, starred BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INTEGER NOT NULL, work_id INTEGER NOT NULL, status_id INTEGER NOT NULL, main_pairing_id INTEGER DEFAULT NULL, CONSTRAINT FK_696120F0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_696120F0BB3453DB FOREIGN KEY (work_id) REFERENCES works (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_696120F06BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_696120F0E47832DE FOREIGN KEY (main_pairing_id) REFERENCES metadata (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO reading_entries (id, date_started, date_finished, last_read_chapter, review_stars, spice_stars, comments, starred, created_at, updated_at, user_id, work_id, status_id, main_pairing_id) SELECT id, date_started, date_finished, last_read_chapter, review_stars, spice_stars, comments, starred, created_at, updated_at, user_id, work_id, status_id, main_pairing_id FROM __temp__reading_entries');
        $this->addSql('DROP TABLE __temp__reading_entries');
        $this->addSql('CREATE INDEX IDX_696120F0E47832DE ON reading_entries (main_pairing_id)');
        $this->addSql('CREATE INDEX idx_re_user ON reading_entries (user_id)');
        $this->addSql('CREATE INDEX idx_re_work ON reading_entries (work_id)');
        $this->addSql('CREATE INDEX idx_re_status ON reading_entries (status_id)');
        $this->addSql('CREATE INDEX idx_re_user_status ON reading_entries (user_id, status_id)');
        $this->addSql('CREATE INDEX idx_re_user_date_finished ON reading_entries (user_id, date_finished)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__reading_entries AS SELECT id, date_started, date_finished, last_read_chapter, review_stars, spice_stars, comments, starred, created_at, updated_at, user_id, work_id, status_id, main_pairing_id FROM reading_entries');
        $this->addSql('DROP TABLE reading_entries');
        $this->addSql('CREATE TABLE reading_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date_started DATE DEFAULT NULL, date_finished DATE DEFAULT NULL, last_read_chapter INTEGER DEFAULT NULL, review_stars INTEGER DEFAULT NULL, spice_stars INTEGER DEFAULT NULL, comments CLOB DEFAULT NULL, starred BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INTEGER NOT NULL, work_id INTEGER NOT NULL, status_id INTEGER NOT NULL, main_pairing_id INTEGER DEFAULT NULL, CONSTRAINT FK_696120F0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_696120F0BB3453DB FOREIGN KEY (work_id) REFERENCES works (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_696120F06BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_696120F0E47832DE FOREIGN KEY (main_pairing_id) REFERENCES metadata (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO reading_entries (id, date_started, date_finished, last_read_chapter, review_stars, spice_stars, comments, starred, created_at, updated_at, user_id, work_id, status_id, main_pairing_id) SELECT id, date_started, date_finished, last_read_chapter, review_stars, spice_stars, comments, starred, created_at, updated_at, user_id, work_id, status_id, main_pairing_id FROM __temp__reading_entries');
        $this->addSql('DROP TABLE __temp__reading_entries');
        $this->addSql('CREATE INDEX IDX_696120F0E47832DE ON reading_entries (main_pairing_id)');
        $this->addSql('CREATE INDEX idx_re_user ON reading_entries (user_id)');
        $this->addSql('CREATE INDEX idx_re_work ON reading_entries (work_id)');
        $this->addSql('CREATE INDEX idx_re_status ON reading_entries (status_id)');
        $this->addSql('CREATE INDEX idx_re_user_status ON reading_entries (user_id, status_id)');
        $this->addSql('CREATE INDEX idx_re_user_date_finished ON reading_entries (user_id, date_finished)');
    }
}
