<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove pinned column from works table — pinning is reading-entry-only';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE works DROP COLUMN pinned');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE works ADD COLUMN pinned BOOLEAN DEFAULT 0 NOT NULL');
    }
}
