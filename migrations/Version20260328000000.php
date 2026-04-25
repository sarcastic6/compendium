<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Consolidated baseline migration.
 *
 * Replaces all previous migrations, which used SQLite-specific DDL syntax
 * (INTEGER PRIMARY KEY AUTOINCREMENT, CLOB, temp-table column-removal pattern).
 *
 * Uses the Doctrine Schema API exclusively for DDL so that Doctrine generates
 * platform-appropriate SQL at runtime. This migration runs identically on
 * SQLite, MySQL, and PostgreSQL without modification.
 *
 * Seed data (statuses, metadata types) uses portable INSERT … SELECT … WHERE
 * NOT EXISTS so the migration is safe on both fresh installs and existing
 * databases where seed data was created before this migration existed.
 */
final class Version20260328000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consolidated baseline schema — portable across SQLite, MySQL, and PostgreSQL';
    }

    public function up(Schema $schema): void
    {
        // ----------------------------------------------------------------
        // Root tables (no foreign key dependencies)
        // ----------------------------------------------------------------

        $languages = $schema->createTable('languages');
        $languages->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $languages->addColumn('name', Types::STRING, ['length' => 100, 'notnull' => true]);
        $languages->setPrimaryKey(['id']);
        $languages->addUniqueIndex(['name'], 'uq_language_name');

        $series = $schema->createTable('series');
        $series->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $series->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
        $series->addColumn('number_of_parts', Types::INTEGER, ['notnull' => false]);
        $series->addColumn('total_words', Types::INTEGER, ['notnull' => false]);
        $series->addColumn('is_complete', Types::BOOLEAN, ['notnull' => false]);
        $series->setPrimaryKey(['id']);
        $series->addUniqueIndex(['name'], 'uq_series_name');

        $statuses = $schema->createTable('statuses');
        $statuses->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $statuses->addColumn('name', Types::STRING, ['length' => 100, 'notnull' => true]);
        $statuses->addColumn('has_been_started', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
        $statuses->addColumn('counts_as_read', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $statuses->addColumn('is_active', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $statuses->setPrimaryKey(['id']);
        $statuses->addUniqueIndex(['name'], 'uq_status_name');

        $metadataTypes = $schema->createTable('metadata_types');
        $metadataTypes->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $metadataTypes->addColumn('name', Types::STRING, ['length' => 100, 'notnull' => true]);
        $metadataTypes->addColumn('multiple_allowed', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
        $metadataTypes->addColumn('show_as_dropdown', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $metadataTypes->addColumn('show_as_checkboxes', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $metadataTypes->setPrimaryKey(['id']);
        $metadataTypes->addUniqueIndex(['name'], 'uq_metadata_type_name');

        $users = $schema->createTable('users');
        $users->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $users->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
        $users->addColumn('email', Types::STRING, ['length' => 255, 'notnull' => true]);
        $users->addColumn('password_hash', Types::STRING, ['length' => 255, 'notnull' => true]);
        $users->addColumn('role', Types::STRING, ['length' => 20, 'notnull' => true, 'default' => 'user']);
        $users->addColumn('is_disabled', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $users->addColumn('is_mfa_enabled', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $users->addColumn('mfa_secret', Types::STRING, ['length' => 255, 'notnull' => false]);
        $users->addColumn('mfa_methods', Types::STRING, ['length' => 255, 'notnull' => false]);
        $users->addColumn('email_auth_code', Types::STRING, ['length' => 10, 'notnull' => false]);
        $users->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $users->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $users->setPrimaryKey(['id']);
        $users->addUniqueIndex(['email'], 'uq_user_email');

        // ----------------------------------------------------------------
        // Tables that depend on root tables
        // ----------------------------------------------------------------

        $works = $schema->createTable('works');
        $works->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $works->addColumn('type', Types::STRING, ['length' => 32, 'notnull' => true]);
        $works->addColumn('title', Types::STRING, ['length' => 500, 'notnull' => true]);
        $works->addColumn('summary', Types::TEXT, ['notnull' => false]);
        $works->addColumn('series_id', Types::BIGINT, ['notnull' => false]);
        $works->addColumn('place_in_series', Types::INTEGER, ['notnull' => false]);
        $works->addColumn('language_id', Types::BIGINT, ['notnull' => false]);
        $works->addColumn('published_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
        $works->addColumn('last_updated_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
        $works->addColumn('words', Types::INTEGER, ['notnull' => false]);
        $works->addColumn('chapters', Types::INTEGER, ['notnull' => false]);
        $works->addColumn('link', Types::STRING, ['length' => 1024, 'notnull' => false]);
        $works->addColumn('source_type', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'Manual']);
        $works->addColumn('deleted_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $works->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $works->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $works->setPrimaryKey(['id']);
        $works->addIndex(['series_id'], 'idx_work_series');
        $works->addIndex(['language_id'], 'idx_work_language');
        $works->addForeignKeyConstraint('series', ['series_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_work_series');
        $works->addForeignKeyConstraint('languages', ['language_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_work_language');

        $metadata = $schema->createTable('metadata');
        $metadata->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $metadata->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
        $metadata->addColumn('metadata_type_id', Types::BIGINT, ['notnull' => true]);
        $metadata->setPrimaryKey(['id']);
        $metadata->addUniqueIndex(['name', 'metadata_type_id'], 'uq_metadata_name_type');
        $metadata->addIndex(['name'], 'idx_metadata_name');
        $metadata->addIndex(['metadata_type_id'], 'idx_metadata_type');
        $metadata->addForeignKeyConstraint('metadata_types', ['metadata_type_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_metadata_type');

        $seriesSourceLinks = $schema->createTable('series_source_links');
        $seriesSourceLinks->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $seriesSourceLinks->addColumn('series_id', Types::BIGINT, ['notnull' => true]);
        $seriesSourceLinks->addColumn('source_type', Types::STRING, ['length' => 32, 'notnull' => true]);
        $seriesSourceLinks->addColumn('link', Types::STRING, ['length' => 1024, 'notnull' => true]);
        $seriesSourceLinks->setPrimaryKey(['id']);
        $seriesSourceLinks->addUniqueIndex(['series_id', 'source_type'], 'uq_series_source_link');
        $seriesSourceLinks->addIndex(['series_id'], 'idx_series_source_link_series');
        $seriesSourceLinks->addForeignKeyConstraint('series', ['series_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_ssl_series');

        $metadataSourceLinks = $schema->createTable('metadata_source_links');
        $metadataSourceLinks->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $metadataSourceLinks->addColumn('metadata_id', Types::BIGINT, ['notnull' => true]);
        $metadataSourceLinks->addColumn('source_type', Types::STRING, ['length' => 32, 'notnull' => true]);
        $metadataSourceLinks->addColumn('link', Types::STRING, ['length' => 1024, 'notnull' => true]);
        $metadataSourceLinks->setPrimaryKey(['id']);
        $metadataSourceLinks->addUniqueIndex(['metadata_id', 'source_type'], 'uq_metadata_source_link');
        $metadataSourceLinks->addIndex(['metadata_id'], 'idx_metadata_source_link_metadata');
        $metadataSourceLinks->addForeignKeyConstraint('metadata', ['metadata_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_msl_metadata');

        // ----------------------------------------------------------------
        // Junction table (works ↔ metadata, managed by Doctrine ManyToMany)
        // ----------------------------------------------------------------

        $worksMetadata = $schema->createTable('works_metadata');
        $worksMetadata->addColumn('work_id', Types::BIGINT, ['notnull' => true]);
        $worksMetadata->addColumn('metadata_id', Types::BIGINT, ['notnull' => true]);
        $worksMetadata->setPrimaryKey(['work_id', 'metadata_id']);
        $worksMetadata->addIndex(['metadata_id'], 'idx_wm_metadata');
        $worksMetadata->addForeignKeyConstraint('works', ['work_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_wm_work');
        $worksMetadata->addForeignKeyConstraint('metadata', ['metadata_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_wm_metadata');

        // ----------------------------------------------------------------
        // Tables that depend on multiple root tables
        // ----------------------------------------------------------------

        $readingEntries = $schema->createTable('reading_entries');
        $readingEntries->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $readingEntries->addColumn('user_id', Types::BIGINT, ['notnull' => true]);
        $readingEntries->addColumn('work_id', Types::BIGINT, ['notnull' => true]);
        $readingEntries->addColumn('status_id', Types::BIGINT, ['notnull' => true]);
        $readingEntries->addColumn('date_started', Types::DATE_IMMUTABLE, ['notnull' => false]);
        $readingEntries->addColumn('date_finished', Types::DATE_IMMUTABLE, ['notnull' => false]);
        $readingEntries->addColumn('last_read_chapter', Types::INTEGER, ['notnull' => false]);
        // review_stars (1–5) and spice_stars (0–5, where 0 = no spice) are validated at
        // application level only — DBAL does not support CHECK constraints portably.
        $readingEntries->addColumn('review_stars', Types::INTEGER, ['notnull' => false]);
        $readingEntries->addColumn('spice_stars', Types::INTEGER, ['notnull' => false]);
        // main_pairing_id must reference metadata with type='Relationships'.
        // Enforced at application level — a type-scoped FK is not expressible in SQL.
        $readingEntries->addColumn('main_pairing_id', Types::BIGINT, ['notnull' => false]);
        $readingEntries->addColumn('comments', Types::TEXT, ['notnull' => false]);
        $readingEntries->addColumn('pinned', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $readingEntries->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $readingEntries->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $readingEntries->setPrimaryKey(['id']);
        $readingEntries->addIndex(['user_id'], 'idx_re_user');
        $readingEntries->addIndex(['work_id'], 'idx_re_work');
        $readingEntries->addIndex(['status_id'], 'idx_re_status');
        $readingEntries->addIndex(['user_id', 'status_id'], 'idx_re_user_status');
        $readingEntries->addIndex(['user_id', 'date_finished'], 'idx_re_user_date_finished');
        $readingEntries->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_re_user');
        $readingEntries->addForeignKeyConstraint('works', ['work_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_re_work');
        $readingEntries->addForeignKeyConstraint('statuses', ['status_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_re_status');
        $readingEntries->addForeignKeyConstraint('metadata', ['main_pairing_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_re_main_pairing');

        // symfonycasts/reset-password-bundle columns come from ResetPasswordRequestTrait
        $resetPasswordRequests = $schema->createTable('reset_password_requests');
        $resetPasswordRequests->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $resetPasswordRequests->addColumn('user_id', Types::BIGINT, ['notnull' => true]);
        $resetPasswordRequests->addColumn('selector', Types::STRING, ['length' => 20, 'notnull' => true]);
        $resetPasswordRequests->addColumn('hashed_token', Types::STRING, ['length' => 100, 'notnull' => true]);
        $resetPasswordRequests->addColumn('requested_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $resetPasswordRequests->addColumn('expires_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $resetPasswordRequests->setPrimaryKey(['id']);
        $resetPasswordRequests->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_rpr_user');

        $readingGoals = $schema->createTable('reading_goals');
        $readingGoals->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $readingGoals->addColumn('user_id', Types::BIGINT, ['notnull' => true]);
        $readingGoals->addColumn('year', Types::INTEGER, ['notnull' => true]);
        $readingGoals->addColumn('goal_type', Types::STRING, ['length' => 32, 'notnull' => true]);
        $readingGoals->addColumn('target_value', Types::INTEGER, ['notnull' => true]);
        $readingGoals->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $readingGoals->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $readingGoals->setPrimaryKey(['id']);
        $readingGoals->addUniqueIndex(['user_id', 'year', 'goal_type'], 'uq_rg_user_year_type');
        $readingGoals->addIndex(['user_id', 'year'], 'idx_rg_user_year');
        $readingGoals->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_rg_user');

        $userAchievements = $schema->createTable('user_achievements');
        $userAchievements->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $userAchievements->addColumn('user_id', Types::BIGINT, ['notnull' => true]);
        $userAchievements->addColumn('achievement_key', Types::STRING, ['length' => 100, 'notnull' => true]);
        $userAchievements->addColumn('unlocked_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $userAchievements->addColumn('notified_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $userAchievements->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $userAchievements->setPrimaryKey(['id']);
        $userAchievements->addUniqueIndex(['user_id', 'achievement_key'], 'uq_ua_user_key');
        $userAchievements->addIndex(['user_id'], 'idx_ua_user');
        $userAchievements->addIndex(['user_id', 'notified_at'], 'idx_ua_user_notified');
        $userAchievements->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_ua_user');

        // Seed data lives in Version20260329000000 to guarantee it runs after
        // the Schema API DDL above has been committed. In Doctrine Migrations 3.x,
        // addSql() statements execute before the schema-diff DDL, so mixing DDL
        // and DML in the same migration causes "no such table" errors on a clean install.
    }

    public function down(Schema $schema): void
    {
        // Drop in reverse dependency order so foreign key constraints are satisfied
        $schema->dropTable('user_achievements');
        $schema->dropTable('reading_goals');
        $schema->dropTable('reset_password_requests');
        $schema->dropTable('reading_entries');
        $schema->dropTable('works_metadata');
        $schema->dropTable('metadata_source_links');
        $schema->dropTable('series_source_links');
        $schema->dropTable('metadata');
        $schema->dropTable('works');
        $schema->dropTable('users');
        $schema->dropTable('metadata_types');
        $schema->dropTable('statuses');
        $schema->dropTable('series');
        $schema->dropTable('languages');
    }
}
