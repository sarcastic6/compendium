<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326022853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reading_goals (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, year INTEGER NOT NULL, goal_type VARCHAR(32) NOT NULL, target_value INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_AB15DDAAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_AB15DDAAA76ED395 ON reading_goals (user_id)');
        $this->addSql('CREATE INDEX idx_rg_user_year ON reading_goals (user_id, year)');
        $this->addSql('CREATE UNIQUE INDEX uq_rg_user_year_type ON reading_goals (user_id, year, goal_type)');
        $this->addSql('CREATE TABLE user_achievements (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, achievement_key VARCHAR(100) NOT NULL, unlocked_at DATETIME NOT NULL, notified_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_51EE02FCA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_ua_user ON user_achievements (user_id)');
        $this->addSql('CREATE INDEX idx_ua_user_notified ON user_achievements (user_id, notified_at)');
        $this->addSql('CREATE UNIQUE INDEX uq_ua_user_key ON user_achievements (user_id, achievement_key)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE reading_goals');
        $this->addSql('DROP TABLE user_achievements');
    }
}
