<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260616130823 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chapter (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, slug VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, formation_id INT DEFAULT NULL, INDEX IDX_F981B52E5200282E (formation_id), UNIQUE INDEX UNIQ_CHAPTER_FORMATION_SLUG (formation_id, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chapter_progress (id INT AUTO_INCREMENT NOT NULL, completed_at DATETIME DEFAULT NULL, enrollment_id INT DEFAULT NULL, chapter_id INT DEFAULT NULL, INDEX IDX_C4189F438F7DB25B (enrollment_id), INDEX IDX_C4189F43579F4768 (chapter_id), UNIQUE INDEX UNIQ_CHAPTER_PROGRESS_ENROLLMENT_CHAPTER (enrollment_id, chapter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE enrollment (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME DEFAULT NULL, last_activity_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, formation_id INT DEFAULT NULL, INDEX IDX_DBDCD7E1A76ED395 (user_id), INDEX IDX_DBDCD7E15200282E (formation_id), UNIQUE INDEX UNIQ_ENROLLMENT_USER_FORMATION (user_id, formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, status VARCHAR(255) DEFAULT \'draft\' NOT NULL, visibility VARCHAR(255) DEFAULT \'draft\' NOT NULL, difficulty VARCHAR(255) DEFAULT NULL, estimated_minutes INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_404021BF989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE formation_tag (formation_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_B4D9D2F75200282E (formation_id), INDEX IDX_B4D9D2F7BAD26311 (tag_id), PRIMARY KEY (formation_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE section (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, type VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, chapter_id INT DEFAULT NULL, INDEX IDX_2D737AEF579F4768 (chapter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_389B783989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, display_name VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_preferences (id INT AUTO_INCREMENT NOT NULL, preferred_difficulty VARCHAR(255) DEFAULT NULL, weekly_goal_minutes INT DEFAULT NULL, user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_402A6F60A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_preferences_tag (user_preferences_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_2FF858DF1748B0EE (user_preferences_id), INDEX IDX_2FF858DFBAD26311 (tag_id), PRIMARY KEY (user_preferences_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chapter ADD CONSTRAINT FK_F981B52E5200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE chapter_progress ADD CONSTRAINT FK_C4189F438F7DB25B FOREIGN KEY (enrollment_id) REFERENCES enrollment (id)');
        $this->addSql('ALTER TABLE chapter_progress ADD CONSTRAINT FK_C4189F43579F4768 FOREIGN KEY (chapter_id) REFERENCES chapter (id)');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_DBDCD7E1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_DBDCD7E15200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE formation_tag ADD CONSTRAINT FK_B4D9D2F75200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formation_tag ADD CONSTRAINT FK_B4D9D2F7BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE section ADD CONSTRAINT FK_2D737AEF579F4768 FOREIGN KEY (chapter_id) REFERENCES chapter (id)');
        $this->addSql('ALTER TABLE user_preferences ADD CONSTRAINT FK_402A6F60A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_preferences_tag ADD CONSTRAINT FK_2FF858DF1748B0EE FOREIGN KEY (user_preferences_id) REFERENCES user_preferences (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_preferences_tag ADD CONSTRAINT FK_2FF858DFBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chapter DROP FOREIGN KEY FK_F981B52E5200282E');
        $this->addSql('ALTER TABLE chapter_progress DROP FOREIGN KEY FK_C4189F438F7DB25B');
        $this->addSql('ALTER TABLE chapter_progress DROP FOREIGN KEY FK_C4189F43579F4768');
        $this->addSql('ALTER TABLE enrollment DROP FOREIGN KEY FK_DBDCD7E1A76ED395');
        $this->addSql('ALTER TABLE enrollment DROP FOREIGN KEY FK_DBDCD7E15200282E');
        $this->addSql('ALTER TABLE formation_tag DROP FOREIGN KEY FK_B4D9D2F75200282E');
        $this->addSql('ALTER TABLE formation_tag DROP FOREIGN KEY FK_B4D9D2F7BAD26311');
        $this->addSql('ALTER TABLE section DROP FOREIGN KEY FK_2D737AEF579F4768');
        $this->addSql('ALTER TABLE user_preferences DROP FOREIGN KEY FK_402A6F60A76ED395');
        $this->addSql('ALTER TABLE user_preferences_tag DROP FOREIGN KEY FK_2FF858DF1748B0EE');
        $this->addSql('ALTER TABLE user_preferences_tag DROP FOREIGN KEY FK_2FF858DFBAD26311');
        $this->addSql('DROP TABLE chapter');
        $this->addSql('DROP TABLE chapter_progress');
        $this->addSql('DROP TABLE enrollment');
        $this->addSql('DROP TABLE formation');
        $this->addSql('DROP TABLE formation_tag');
        $this->addSql('DROP TABLE section');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_preferences');
        $this->addSql('DROP TABLE user_preferences_tag');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
