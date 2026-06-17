<?php

namespace App\Command;

use App\Repository\FormationRepository;
use App\Service\ChapterParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:formations:sync',
    description: 'Synchronize formations data from markdown to database',
)]
final class FormationsSyncCommand extends Command
{
    private EntityManagerInterface $em;
    private string $formationsContentDir;
    private FormationRepository $formationRepository;
    private ChapterParser $chapterParser;
    public function __construct(
        EntityManagerInterface $em,
        string $formationsContentDir,
        FormationRepository $formationRepository,
        ChapterParser $chapterParser
    )
    {
        $this->em = $em;
        $this->formationsContentDir = $formationsContentDir;
        $this->formationRepository = $formationRepository;
        $this->chapterParser = $chapterParser;
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $finder = new Finder();
        $subFolders = $finder->directories()
            ->in($this->formationsContentDir)
            ->ignoreDotFiles(true)
//            ->ignoreVCSIgnored(true) can't make it work, it gives me the supposedly ignored folders
            ->name('f-*')
            ->depth(0)
            ;

        foreach ($subFolders as $subFolder) {
            dump($subFolder->getPathname());
            $files = $finder->files()->in($subFolder->getPathname())->notName(['README.md', 'CLAUDE.md'])->sortByAccessedTime();
            foreach ($files as $file) {
                $io->writeln($file->getFilename());

            }
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }

    private function readMeParser(string $content)
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $title = '';
        $sections = [];
        $current = null;
        foreach ($lines as $line) {
            if (preg_match('/^#\s+(.+)$/m', $line, $matches)) {
                $title = $matches[1];
            }
        }
        return ['title' => $title, 'sections' => $sections];
    }
}
