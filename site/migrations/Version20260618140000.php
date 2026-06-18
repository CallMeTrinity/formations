<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute enrollment.completion_count : nombre de fois où la formation a été
 * terminée (affiché en étoiles sur le tableau de bord). Incrémenté à chaque
 * complétion, y compris après un « recommencer ».
 */
final class Version20260618140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute enrollment.completion_count (nombre de complétions).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enrollment ADD completion_count INT DEFAULT 0 NOT NULL');
        // Backfill : une formation déjà terminée au moins une fois compte pour 1.
        $this->addSql('UPDATE enrollment SET completion_count = 1 WHERE first_completed_at IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enrollment DROP completion_count');
    }
}
