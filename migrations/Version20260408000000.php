<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds scrape_status column to works table.
 *
 * - null   — work was not queued for scraping (manual entry, no URL)
 * - pending — scrape job dispatched, not yet processed
 * - complete — scrape succeeded
 * - failed — scrape failed permanently; user can retry via "Refresh from source"
 */
final class Version20260408000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add scrape_status column to works table for background scrape tracking';
    }

    public function up(Schema $schema): void
    {
        $works = $schema->getTable('works');
        $works->addColumn('scrape_status', Types::STRING, ['length' => 32, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $works = $schema->getTable('works');
        $works->dropColumn('scrape_status');
    }
}
