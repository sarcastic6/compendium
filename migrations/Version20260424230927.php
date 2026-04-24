<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260424230927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__metadata AS SELECT id, name, metadata_type_id FROM metadata');
        $this->addSql('DROP TABLE metadata');
        $this->addSql('CREATE TABLE metadata (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, metadata_type_id INTEGER NOT NULL, CONSTRAINT fk_metadata_type FOREIGN KEY (metadata_type_id) REFERENCES metadata_types (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO metadata (id, name, metadata_type_id) SELECT id, name, metadata_type_id FROM __temp__metadata');
        $this->addSql('DROP TABLE __temp__metadata');
        $this->addSql('CREATE INDEX idx_metadata_type ON metadata (metadata_type_id)');
        $this->addSql('CREATE INDEX idx_metadata_name ON metadata (name)');
        $this->addSql('CREATE UNIQUE INDEX uq_metadata_name_type ON metadata (name, metadata_type_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__metadata_source_links AS SELECT id, metadata_id, source_type, link FROM metadata_source_links');
        $this->addSql('DROP TABLE metadata_source_links');
        $this->addSql('CREATE TABLE metadata_source_links (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, metadata_id INTEGER NOT NULL, source_type VARCHAR(32) NOT NULL, link VARCHAR(1024) NOT NULL, CONSTRAINT fk_msl_metadata FOREIGN KEY (metadata_id) REFERENCES metadata (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO metadata_source_links (id, metadata_id, source_type, link) SELECT id, metadata_id, source_type, link FROM __temp__metadata_source_links');
        $this->addSql('DROP TABLE __temp__metadata_source_links');
        $this->addSql('CREATE INDEX idx_metadata_source_link_metadata ON metadata_source_links (metadata_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_metadata_source_link ON metadata_source_links (metadata_id, source_type)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__reading_entries AS SELECT id, user_id, work_id, status_id, date_started, date_finished, last_read_chapter, review_stars, spice_stars, main_pairing_id, comments, pinned, created_at, updated_at FROM reading_entries');
        $this->addSql('DROP TABLE reading_entries');
        $this->addSql('CREATE TABLE reading_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, work_id INTEGER NOT NULL, status_id INTEGER NOT NULL, date_started DATE DEFAULT NULL, date_finished DATE DEFAULT NULL, last_read_chapter INTEGER DEFAULT NULL, review_stars INTEGER DEFAULT NULL, spice_stars INTEGER DEFAULT NULL, main_pairing_id INTEGER DEFAULT NULL, comments CLOB DEFAULT NULL, pinned BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT fk_re_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT fk_re_work FOREIGN KEY (work_id) REFERENCES works (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT fk_re_status FOREIGN KEY (status_id) REFERENCES statuses (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT fk_re_main_pairing FOREIGN KEY (main_pairing_id) REFERENCES metadata (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO reading_entries (id, user_id, work_id, status_id, date_started, date_finished, last_read_chapter, review_stars, spice_stars, main_pairing_id, comments, pinned, created_at, updated_at) SELECT id, user_id, work_id, status_id, date_started, date_finished, last_read_chapter, review_stars, spice_stars, main_pairing_id, comments, pinned, created_at, updated_at FROM __temp__reading_entries');
        $this->addSql('DROP TABLE __temp__reading_entries');
        $this->addSql('CREATE INDEX IDX_696120F0E47832DE ON reading_entries (main_pairing_id)');
        $this->addSql('CREATE INDEX idx_re_user_date_finished ON reading_entries (user_id, date_finished)');
        $this->addSql('CREATE INDEX idx_re_user_status ON reading_entries (user_id, status_id)');
        $this->addSql('CREATE INDEX idx_re_status ON reading_entries (status_id)');
        $this->addSql('CREATE INDEX idx_re_work ON reading_entries (work_id)');
        $this->addSql('CREATE INDEX idx_re_user ON reading_entries (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__reading_goals AS SELECT id, user_id, year, goal_type, target_value, created_at, updated_at FROM reading_goals');
        $this->addSql('DROP TABLE reading_goals');
        $this->addSql('CREATE TABLE reading_goals (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, year INTEGER NOT NULL, goal_type VARCHAR(32) NOT NULL, target_value INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT fk_rg_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO reading_goals (id, user_id, year, goal_type, target_value, created_at, updated_at) SELECT id, user_id, year, goal_type, target_value, created_at, updated_at FROM __temp__reading_goals');
        $this->addSql('DROP TABLE __temp__reading_goals');
        $this->addSql('CREATE INDEX IDX_AB15DDAAA76ED395 ON reading_goals (user_id)');
        $this->addSql('CREATE INDEX idx_rg_user_year ON reading_goals (user_id, year)');
        $this->addSql('CREATE UNIQUE INDEX uq_rg_user_year_type ON reading_goals (user_id, year, goal_type)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__reset_password_requests AS SELECT id, user_id, selector, hashed_token, requested_at, expires_at FROM reset_password_requests');
        $this->addSql('DROP TABLE reset_password_requests');
        $this->addSql('CREATE TABLE reset_password_requests (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, CONSTRAINT fk_rpr_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO reset_password_requests (id, user_id, selector, hashed_token, requested_at, expires_at) SELECT id, user_id, selector, hashed_token, requested_at, expires_at FROM __temp__reset_password_requests');
        $this->addSql('DROP TABLE __temp__reset_password_requests');
        $this->addSql('CREATE INDEX IDX_16646B41A76ED395 ON reset_password_requests (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__series_source_links AS SELECT id, series_id, source_type, link FROM series_source_links');
        $this->addSql('DROP TABLE series_source_links');
        $this->addSql('CREATE TABLE series_source_links (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, series_id INTEGER NOT NULL, source_type VARCHAR(32) NOT NULL, link VARCHAR(1024) NOT NULL, CONSTRAINT fk_ssl_series FOREIGN KEY (series_id) REFERENCES series (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO series_source_links (id, series_id, source_type, link) SELECT id, series_id, source_type, link FROM __temp__series_source_links');
        $this->addSql('DROP TABLE __temp__series_source_links');
        $this->addSql('CREATE INDEX idx_series_source_link_series ON series_source_links (series_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_series_source_link ON series_source_links (series_id, source_type)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user_achievements AS SELECT id, user_id, achievement_key, unlocked_at, notified_at, created_at FROM user_achievements');
        $this->addSql('DROP TABLE user_achievements');
        $this->addSql('CREATE TABLE user_achievements (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, achievement_key VARCHAR(100) NOT NULL, unlocked_at DATETIME NOT NULL, notified_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT fk_ua_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO user_achievements (id, user_id, achievement_key, unlocked_at, notified_at, created_at) SELECT id, user_id, achievement_key, unlocked_at, notified_at, created_at FROM __temp__user_achievements');
        $this->addSql('DROP TABLE __temp__user_achievements');
        $this->addSql('CREATE INDEX idx_ua_user_notified ON user_achievements (user_id, notified_at)');
        $this->addSql('CREATE INDEX idx_ua_user ON user_achievements (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_ua_user_key ON user_achievements (user_id, achievement_key)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, name, email, password_hash, role, is_disabled, is_mfa_enabled, mfa_secret, mfa_methods, email_auth_code, created_at, updated_at FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, is_disabled BOOLEAN DEFAULT 0 NOT NULL, is_mfa_enabled BOOLEAN DEFAULT 0 NOT NULL, mfa_secret VARCHAR(255) DEFAULT NULL, mfa_methods VARCHAR(255) DEFAULT NULL, email_auth_code VARCHAR(10) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_verified BOOLEAN DEFAULT 0 NOT NULL)');
        $this->addSql('INSERT INTO users (id, name, email, password_hash, role, is_disabled, is_mfa_enabled, mfa_secret, mfa_methods, email_auth_code, created_at, updated_at) SELECT id, name, email, password_hash, role, is_disabled, is_mfa_enabled, mfa_secret, mfa_methods, email_auth_code, created_at, updated_at FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        // Mark all pre-existing users as verified — they registered before email verification existed.
        $this->addSql('UPDATE users SET is_verified = 1');
        $this->addSql('CREATE UNIQUE INDEX uq_user_email ON users (email)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__works AS SELECT id, type, title, summary, series_id, place_in_series, language_id, published_date, last_updated_date, words, chapters, link, source_type, deleted_at, created_at, updated_at, scrape_status FROM works');
        $this->addSql('DROP TABLE works');
        $this->addSql('CREATE TABLE works (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(32) NOT NULL, title VARCHAR(500) NOT NULL, summary CLOB DEFAULT NULL, series_id INTEGER DEFAULT NULL, place_in_series INTEGER DEFAULT NULL, language_id INTEGER DEFAULT NULL, published_date DATE DEFAULT NULL, last_updated_date DATE DEFAULT NULL, words INTEGER DEFAULT NULL, chapters INTEGER DEFAULT NULL, link VARCHAR(1024) DEFAULT NULL, source_type VARCHAR(32) NOT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, scrape_status VARCHAR(32) DEFAULT NULL, CONSTRAINT fk_work_series FOREIGN KEY (series_id) REFERENCES series (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT fk_work_language FOREIGN KEY (language_id) REFERENCES languages (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO works (id, type, title, summary, series_id, place_in_series, language_id, published_date, last_updated_date, words, chapters, link, source_type, deleted_at, created_at, updated_at, scrape_status) SELECT id, type, title, summary, series_id, place_in_series, language_id, published_date, last_updated_date, words, chapters, link, source_type, deleted_at, created_at, updated_at, scrape_status FROM __temp__works');
        $this->addSql('DROP TABLE __temp__works');
        $this->addSql('CREATE INDEX idx_work_language ON works (language_id)');
        $this->addSql('CREATE INDEX idx_work_series ON works (series_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__works_metadata AS SELECT work_id, metadata_id FROM works_metadata');
        $this->addSql('DROP TABLE works_metadata');
        $this->addSql('CREATE TABLE works_metadata (work_id INTEGER NOT NULL, metadata_id INTEGER NOT NULL, PRIMARY KEY (work_id, metadata_id), CONSTRAINT FK_56BD3A7FBB3453DB FOREIGN KEY (work_id) REFERENCES works (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_56BD3A7FDC9EE959 FOREIGN KEY (metadata_id) REFERENCES metadata (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO works_metadata (work_id, metadata_id) SELECT work_id, metadata_id FROM __temp__works_metadata');
        $this->addSql('DROP TABLE __temp__works_metadata');
        $this->addSql('CREATE INDEX IDX_56BD3A7FBB3453DB ON works_metadata (work_id)');
        $this->addSql('CREATE INDEX IDX_56BD3A7FDC9EE959 ON works_metadata (metadata_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('CREATE TEMPORARY TABLE __temp__metadata AS SELECT id, name, metadata_type_id FROM metadata');
        $this->addSql('DROP TABLE metadata');
        $this->addSql('CREATE TABLE metadata (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, metadata_type_id BIGINT NOT NULL, CONSTRAINT FK_4F143414573D5C6B FOREIGN KEY (metadata_type_id) REFERENCES metadata_types (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO metadata (id, name, metadata_type_id) SELECT id, name, metadata_type_id FROM __temp__metadata');
        $this->addSql('DROP TABLE __temp__metadata');
        $this->addSql('CREATE INDEX idx_metadata_name ON metadata (name)');
        $this->addSql('CREATE INDEX idx_metadata_type ON metadata (metadata_type_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_metadata_name_type ON metadata (name, metadata_type_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__metadata_source_links AS SELECT id, source_type, link, metadata_id FROM metadata_source_links');
        $this->addSql('DROP TABLE metadata_source_links');
        $this->addSql('CREATE TABLE metadata_source_links (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, source_type VARCHAR(32) NOT NULL, link VARCHAR(1024) NOT NULL, metadata_id BIGINT NOT NULL, CONSTRAINT FK_DE32DDDC9EE959 FOREIGN KEY (metadata_id) REFERENCES metadata (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO metadata_source_links (id, source_type, link, metadata_id) SELECT id, source_type, link, metadata_id FROM __temp__metadata_source_links');
        $this->addSql('DROP TABLE __temp__metadata_source_links');
        $this->addSql('CREATE INDEX idx_metadata_source_link_metadata ON metadata_source_links (metadata_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_metadata_source_link ON metadata_source_links (metadata_id, source_type)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__reading_entries AS SELECT id, date_started, date_finished, last_read_chapter, review_stars, spice_stars, comments, pinned, created_at, updated_at, user_id, work_id, status_id, main_pairing_id FROM reading_entries');
        $this->addSql('DROP TABLE reading_entries');
        $this->addSql('CREATE TABLE reading_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date_started DATE DEFAULT NULL, date_finished DATE DEFAULT NULL, last_read_chapter INTEGER DEFAULT NULL, review_stars INTEGER DEFAULT NULL, spice_stars INTEGER DEFAULT NULL, comments CLOB DEFAULT NULL, pinned BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id BIGINT NOT NULL, work_id BIGINT NOT NULL, status_id BIGINT NOT NULL, main_pairing_id BIGINT DEFAULT NULL, CONSTRAINT FK_696120F0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_696120F0BB3453DB FOREIGN KEY (work_id) REFERENCES works (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_696120F06BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_696120F0E47832DE FOREIGN KEY (main_pairing_id) REFERENCES metadata (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO reading_entries (id, date_started, date_finished, last_read_chapter, review_stars, spice_stars, comments, pinned, created_at, updated_at, user_id, work_id, status_id, main_pairing_id) SELECT id, date_started, date_finished, last_read_chapter, review_stars, spice_stars, comments, pinned, created_at, updated_at, user_id, work_id, status_id, main_pairing_id FROM __temp__reading_entries');
        $this->addSql('DROP TABLE __temp__reading_entries');
        $this->addSql('CREATE INDEX IDX_696120F0E47832DE ON reading_entries (main_pairing_id)');
        $this->addSql('CREATE INDEX idx_re_user ON reading_entries (user_id)');
        $this->addSql('CREATE INDEX idx_re_work ON reading_entries (work_id)');
        $this->addSql('CREATE INDEX idx_re_status ON reading_entries (status_id)');
        $this->addSql('CREATE INDEX idx_re_user_status ON reading_entries (user_id, status_id)');
        $this->addSql('CREATE INDEX idx_re_user_date_finished ON reading_entries (user_id, date_finished)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__reading_goals AS SELECT id, year, goal_type, target_value, created_at, updated_at, user_id FROM reading_goals');
        $this->addSql('DROP TABLE reading_goals');
        $this->addSql('CREATE TABLE reading_goals (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, year INTEGER NOT NULL, goal_type VARCHAR(32) NOT NULL, target_value INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id BIGINT NOT NULL, CONSTRAINT FK_AB15DDAAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO reading_goals (id, year, goal_type, target_value, created_at, updated_at, user_id) SELECT id, year, goal_type, target_value, created_at, updated_at, user_id FROM __temp__reading_goals');
        $this->addSql('DROP TABLE __temp__reading_goals');
        $this->addSql('CREATE INDEX IDX_AB15DDAAA76ED395 ON reading_goals (user_id)');
        $this->addSql('CREATE INDEX idx_rg_user_year ON reading_goals (user_id, year)');
        $this->addSql('CREATE UNIQUE INDEX uq_rg_user_year_type ON reading_goals (user_id, year, goal_type)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__reset_password_requests AS SELECT id, selector, hashed_token, requested_at, expires_at, user_id FROM reset_password_requests');
        $this->addSql('DROP TABLE reset_password_requests');
        $this->addSql('CREATE TABLE reset_password_requests (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id BIGINT NOT NULL, CONSTRAINT FK_16646B41A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO reset_password_requests (id, selector, hashed_token, requested_at, expires_at, user_id) SELECT id, selector, hashed_token, requested_at, expires_at, user_id FROM __temp__reset_password_requests');
        $this->addSql('DROP TABLE __temp__reset_password_requests');
        $this->addSql('CREATE INDEX IDX_16646B41A76ED395 ON reset_password_requests (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__series_source_links AS SELECT id, source_type, link, series_id FROM series_source_links');
        $this->addSql('DROP TABLE series_source_links');
        $this->addSql('CREATE TABLE series_source_links (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, source_type VARCHAR(32) NOT NULL, link VARCHAR(1024) NOT NULL, series_id BIGINT NOT NULL, CONSTRAINT FK_AC01A9505278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO series_source_links (id, source_type, link, series_id) SELECT id, source_type, link, series_id FROM __temp__series_source_links');
        $this->addSql('DROP TABLE __temp__series_source_links');
        $this->addSql('CREATE INDEX idx_series_source_link_series ON series_source_links (series_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_series_source_link ON series_source_links (series_id, source_type)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user_achievements AS SELECT id, achievement_key, unlocked_at, notified_at, created_at, user_id FROM user_achievements');
        $this->addSql('DROP TABLE user_achievements');
        $this->addSql('CREATE TABLE user_achievements (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, achievement_key VARCHAR(100) NOT NULL, unlocked_at DATETIME NOT NULL, notified_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, user_id BIGINT NOT NULL, CONSTRAINT FK_51EE02FCA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO user_achievements (id, achievement_key, unlocked_at, notified_at, created_at, user_id) SELECT id, achievement_key, unlocked_at, notified_at, created_at, user_id FROM __temp__user_achievements');
        $this->addSql('DROP TABLE __temp__user_achievements');
        $this->addSql('CREATE INDEX idx_ua_user ON user_achievements (user_id)');
        $this->addSql('CREATE INDEX idx_ua_user_notified ON user_achievements (user_id, notified_at)');
        $this->addSql('CREATE UNIQUE INDEX uq_ua_user_key ON user_achievements (user_id, achievement_key)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, name, email, password_hash, role, is_disabled, is_mfa_enabled, mfa_secret, mfa_methods, email_auth_code, created_at, updated_at FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) DEFAULT \'user\' NOT NULL, is_disabled BOOLEAN DEFAULT 0 NOT NULL, is_mfa_enabled BOOLEAN DEFAULT 0 NOT NULL, mfa_secret VARCHAR(255) DEFAULT NULL, mfa_methods VARCHAR(255) DEFAULT NULL, email_auth_code VARCHAR(10) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO users (id, name, email, password_hash, role, is_disabled, is_mfa_enabled, mfa_secret, mfa_methods, email_auth_code, created_at, updated_at) SELECT id, name, email, password_hash, role, is_disabled, is_mfa_enabled, mfa_secret, mfa_methods, email_auth_code, created_at, updated_at FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX uq_user_email ON users (email)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__works AS SELECT id, type, title, summary, place_in_series, published_date, last_updated_date, words, chapters, link, source_type, scrape_status, deleted_at, created_at, updated_at, series_id, language_id FROM works');
        $this->addSql('DROP TABLE works');
        $this->addSql('CREATE TABLE works (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(32) NOT NULL, title VARCHAR(500) NOT NULL, summary CLOB DEFAULT NULL, place_in_series INTEGER DEFAULT NULL, published_date DATE DEFAULT NULL, last_updated_date DATE DEFAULT NULL, words INTEGER DEFAULT NULL, chapters INTEGER DEFAULT NULL, link VARCHAR(1024) DEFAULT NULL, source_type VARCHAR(32) DEFAULT \'Manual\' NOT NULL, scrape_status VARCHAR(32) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, series_id BIGINT DEFAULT NULL, language_id BIGINT DEFAULT NULL, CONSTRAINT FK_F6E502435278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F6E5024382F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO works (id, type, title, summary, place_in_series, published_date, last_updated_date, words, chapters, link, source_type, scrape_status, deleted_at, created_at, updated_at, series_id, language_id) SELECT id, type, title, summary, place_in_series, published_date, last_updated_date, words, chapters, link, source_type, scrape_status, deleted_at, created_at, updated_at, series_id, language_id FROM __temp__works');
        $this->addSql('DROP TABLE __temp__works');
        $this->addSql('CREATE INDEX idx_work_series ON works (series_id)');
        $this->addSql('CREATE INDEX idx_work_language ON works (language_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__works_metadata AS SELECT work_id, metadata_id FROM works_metadata');
        $this->addSql('DROP TABLE works_metadata');
        $this->addSql('CREATE TABLE works_metadata (work_id BIGINT NOT NULL, metadata_id BIGINT NOT NULL, PRIMARY KEY (work_id, metadata_id), CONSTRAINT fk_wm_work FOREIGN KEY (work_id) REFERENCES works (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT fk_wm_metadata FOREIGN KEY (metadata_id) REFERENCES metadata (id) ON UPDATE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO works_metadata (work_id, metadata_id) SELECT work_id, metadata_id FROM __temp__works_metadata');
        $this->addSql('DROP TABLE __temp__works_metadata');
        $this->addSql('CREATE INDEX IDX_56BD3A7FBB3453DB ON works_metadata (work_id)');
        $this->addSql('CREATE INDEX idx_wm_metadata ON works_metadata (metadata_id)');
    }
}
