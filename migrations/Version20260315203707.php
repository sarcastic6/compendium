<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315203707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed default reading statuses';
    }

    public function up(Schema $schema): void
    {
        $statuses = [
            ['name' => 'TBR',       'is_finished' => 0],
            ['name' => 'Reading',   'is_finished' => 0],
            ['name' => 'On Hold',   'is_finished' => 0],
            ['name' => 'Completed', 'is_finished' => 1],
            ['name' => 'DNF',       'is_finished' => 1],
        ];

        foreach ($statuses as $status) {
            $this->addSql(
                'INSERT INTO statuses (name, is_finished) VALUES (:name, :is_finished)',
                $status,
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM statuses WHERE name IN ('TBR', 'Reading', 'On Hold', 'Completed', 'DNF')");
    }
}
