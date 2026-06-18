<?php

namespace App\Tests\Command;

use App\Command\FormationsSyncCommand;
use App\Entity\Chapter;
use App\Entity\Formation;
use App\Entity\Section;
use App\Enum\Difficulty;
use App\Enum\Visibility;
use App\Repository\FormationRepository;
use App\Service\ChapterParser;
use App\Service\FormationSyncService;
use App\Service\ReadmeParser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class FormationsSyncCommandTest extends KernelTestCase
{
    private const string FIXTURES_DIR = __DIR__.'/../fixtures/content';

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine.orm.entity_manager');

        // Repart d'un schéma vierge pour que le test soit indépendant.
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testFirstRunCreatesFormationsChaptersAndSections(): void
    {
        $this->runSync();

        // Le dossier "notes" (sans README) n'est pas importé.
        self::assertSame(2, $this->entityCount(Formation::class));
        self::assertSame(3, $this->entityCount(Chapter::class)); // alpha: 2, beta: 1
        self::assertSame(6, $this->entityCount(Section::class)); // alpha: 3 + 2, beta: 1

        $alpha = $this->formationRepository()->findOneBy(['slug' => 'alpha']);
        self::assertNotNull($alpha);
        self::assertSame('Formation Alpha', $alpha->getTitle());
        // La description est convertie en HTML à la sync (markdown inline), comme les sections.
        self::assertSame(
            "<p>Une formation de test pour vérifier la synchronisation.\nSa description tient sur deux lignes.</p>\n",
            $alpha->getDescription(),
        );
        self::assertCount(2, $alpha->getChapters());
    }

    public function testImportsReadmeContentBlocks(): void
    {
        $this->runSync();

        $alpha = $this->formationRepository()->findOneBy(['slug' => 'alpha']);
        self::assertNotNull($alpha);

        // Les blocs canoniques du README sont importés en HTML.
        self::assertSame("<p>Aucun.</p>\n", $alpha->getPrerequisites());
        self::assertSame(
            "<ul>\n<li>Compétence un.</li>\n<li>Compétence deux.</li>\n</ul>\n",
            $alpha->getObjectives(),
        );
        self::assertSame(
            "<p>Construire un petit projet de test au fil des chapitres.</p>\n",
            $alpha->getProject(),
        );

        // Le « Plan de la formation » n'est jamais persisté (doublon des chapitres),
        // et la navigation de pied (« Commencer par… ») est retirée.
        self::assertStringNotContainsString('Plan de la formation', (string) $alpha->getProject());
        self::assertStringNotContainsString('Commencer par', (string) $alpha->getProject());

        // beta n'a qu'un bloc Prérequis : objectifs et projet restent nuls.
        $beta = $this->formationRepository()->findOneBy(['slug' => 'beta']);
        self::assertNotNull($beta);
        self::assertSame("<p>Aucun.</p>\n", $beta->getPrerequisites());
        self::assertNull($beta->getObjectives());
        self::assertNull($beta->getProject());
    }

    public function testSecondRunIsIdempotent(): void
    {
        $this->runSync();
        $this->em->clear();
        $this->runSync();

        // Relancer ne duplique rien.
        self::assertSame(2, $this->entityCount(Formation::class));
        self::assertSame(3, $this->entityCount(Chapter::class));
        self::assertSame(6, $this->entityCount(Section::class));
    }

    public function testPreservesAdminFields(): void
    {
        $this->runSync();

        // Un admin règle les champs hors contenu.
        $alpha = $this->formationRepository()->findOneBy(['slug' => 'alpha']);
        self::assertNotNull($alpha);
        $alpha
            ->setVisibility(Visibility::PUBLIC)
            ->setDifficulty(Difficulty::INTERMEDIATE)
            ->setEstimatedMinutes(120);
        $this->em->flush();
        $this->em->clear();

        // Nouvelle synchro du contenu.
        $this->runSync();

        $alpha = $this->formationRepository()->findOneBy(['slug' => 'alpha']);
        self::assertNotNull($alpha);
        // Les réglages admin sont préservés…
        self::assertSame(Visibility::PUBLIC, $alpha->getVisibility());
        self::assertSame(Difficulty::INTERMEDIATE, $alpha->getDifficulty());
        self::assertSame(120, $alpha->getEstimatedMinutes());
        // …et le contenu reste à jour.
        self::assertSame('Formation Alpha', $alpha->getTitle());
    }

    private function runSync(): void
    {
        $service = new FormationSyncService(
            $this->em,
            self::FIXTURES_DIR,
            $this->formationRepository(),
            self::getContainer()->get(ChapterParser::class),
            self::getContainer()->get(ReadmeParser::class),
        );

        $tester = new CommandTester(new FormationsSyncCommand($service));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
    }

    private function formationRepository(): FormationRepository
    {
        return $this->em->getRepository(Formation::class);
    }

    /**
     * @param class-string $entityClass
     */
    private function entityCount(string $entityClass): int
    {
        return $this->em->getRepository($entityClass)->count([]);
    }
}
