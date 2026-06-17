<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617153806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les blocs contenu du README de formation : prerequisites, objectives, project.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE formation ADD prerequisites LONGTEXT DEFAULT NULL, ADD objectives LONGTEXT DEFAULT NULL, ADD project LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE formation DROP prerequisites, DROP objectives, DROP project');
    }
}
