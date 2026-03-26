<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename starred column to pinned on reading_entries and works';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reading_entries RENAME COLUMN starred TO pinned');
        $this->addSql('ALTER TABLE works RENAME COLUMN starred TO pinned');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reading_entries RENAME COLUMN pinned TO starred');
        $this->addSql('ALTER TABLE works RENAME COLUMN pinned TO starred');
    }
}
