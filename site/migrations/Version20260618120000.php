<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute enrollment.first_completed_at : trace permanente de la première
 * complétion (conservée même après un « recommencer »).
 */
final class Version20260618120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute enrollment.first_completed_at (historique de complétion).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enrollment ADD first_completed_at DATETIME DEFAULT NULL');
        // Backfill : les formations déjà terminées gardent leur date comme première complétion.
        $this->addSql('UPDATE enrollment SET first_completed_at = completed_at WHERE completed_at IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enrollment DROP first_completed_at');
    }
}
