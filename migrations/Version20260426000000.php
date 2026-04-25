<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Enables WAL (Write-Ahead Logging) mode on SQLite.
 *
 * WAL mode allows concurrent readers alongside a writer and is the
 * recommended journal mode for production SQLite deployments. The setting
 * persists on the database file, so it only needs to be applied once.
 *
 * This migration is a no-op on MySQL and PostgreSQL.
 */
final class Version20260426000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable WAL journal mode on SQLite (no-op on other platforms)';
    }

    public function isTransactional(): bool
    {
        // This migration cannot be run inside a transaction because PRAGMA statements
        // must be executed outside of transactions.
        return false;
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->addSql('PRAGMA journal_mode=WAL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->addSql('PRAGMA journal_mode=DELETE');
        }
    }
}
