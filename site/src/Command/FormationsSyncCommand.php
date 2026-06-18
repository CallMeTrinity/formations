<?php

namespace App\Command;

use App\Service\FormationSyncService;
use League\CommonMark\Exception\CommonMarkException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:formations:sync',
    description: 'Importe le contenu markdown du dépôt parent en base (upsert idempotent par slug).',
)]
final class FormationsSyncCommand extends Command
{
    public function __construct(private readonly FormationSyncService $sync)
    {
        parent::__construct();
    }

    /**
     * @throws CommonMarkException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $report = $this->sync->sync();

        foreach ($report->warnings as $warning) {
            $io->warning($warning);
        }

        $io->success(sprintf(
            '%d formation(s) créée(s), %d mise(s) à jour, %d chapitre(s) synchronisé(s).',
            $report->created,
            $report->updated,
            $report->chaptersCount,
        ));

        return Command::SUCCESS;
    }
}
