<?php

declare(strict_types=1);

namespace App\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Event\ConnectionEventArgs;

/**
 * Enables SQLite foreign key enforcement on every new connection.
 *
 * SQLite ignores FK constraints by default. Without this listener, ON DELETE RESTRICT
 * and other FK constraints have no effect, making data integrity tests unreliable.
 */
class SqliteForeignKeyListener
{
    public function postConnect(ConnectionEventArgs $event): void
    {
        $connection = $event->getConnection();

        if (!($connection->getDriver() instanceof AbstractSQLiteDriver)) {
            return;
        }

        $connection->executeStatement('PRAGMA foreign_keys = ON');
    }
}
