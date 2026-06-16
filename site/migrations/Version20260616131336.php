<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260616131336 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_preferences DROP FOREIGN KEY `FK_402A6F60A76ED395`');
        $this->addSql('ALTER TABLE user_preferences CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE user_preferences ADD CONSTRAINT FK_402A6F60A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_preferences DROP FOREIGN KEY FK_402A6F60A76ED395');
        $this->addSql('ALTER TABLE user_preferences CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_preferences ADD CONSTRAINT `FK_402A6F60A76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
    }
}
