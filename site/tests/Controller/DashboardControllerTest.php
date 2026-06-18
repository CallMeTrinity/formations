<?php

namespace App\Tests\Controller;

use App\Entity\Chapter;
use App\Entity\ChapterProgress;
use App\Entity\Enrollment;
use App\Entity\Formation;
use App\Entity\User;
use App\Enum\Visibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Base propre : progression, puis inscriptions, puis formations (contraintes FK).
        foreach ($this->em->getRepository(ChapterProgress::class)->findAll() as $progress) {
            $this->em->remove($progress);
        }
        foreach ($this->em->getRepository(Enrollment::class)->findAll() as $enrollment) {
            $this->em->remove($enrollment);
        }
        foreach ($this->em->getRepository(Formation::class)->findAll() as $formation) {
            $this->em->remove($formation);
        }
        $this->em->flush();
    }

    private function loginUser(): User
    {
        $user = (new User())
            ->setEmail('u'.uniqid().'@test.dev')
            ->setRoles([]);
        $user->setPassword('x');

        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);

        return $user;
    }

    /**
     * Crée une formation à trois chapitres.
     */
    private function createFormation(string $slug): Formation
    {
        $formation = (new Formation())
            ->setSlug($slug)
            ->setTitle('Titre '.$slug)
            ->setDescription('<p>Description.</p>')
            ->setVisibility(Visibility::PUBLIC);

        foreach (['introduction' => 1, 'les-bases' => 2, 'pour-aller-plus-loin' => 3] as $chapterSlug => $position) {
            $formation->addChapter(
                (new Chapter())
                    ->setSlug($chapterSlug)
                    ->setTitle('Chapitre '.$chapterSlug)
                    ->setPosition($position)
            );
        }

        $this->em->persist($formation);
        $this->em->flush();

        return $formation;
    }

    /**
     * Inscrit l'utilisateur et marque les `$completedCount` premiers chapitres terminés.
     */
    private function enroll(User $user, Formation $formation, int $completedCount = 0, ?\DateTimeImmutable $completedAt = null, int $completions = 0): Enrollment
    {
        $now = new \DateTimeImmutable();
        $enrollment = (new Enrollment())
            ->setUser($user)
            ->setFormation($formation)
            ->setStartedAt($now)
            ->setLastActivityAt($now)
            ->setCompletedAt($completedAt)
            ->setCompletionCount($completions);

        $this->em->persist($enrollment);

        $i = 0;
        foreach ($formation->getChapters() as $chapter) {
            if ($i++ >= $completedCount) {
                break;
            }
            $this->em->persist(
                (new ChapterProgress())
                    ->setEnrollment($enrollment)
                    ->setChapter($chapter)
                    ->setCompletedAt($now)
            );
        }
        $this->em->flush();

        return $enrollment;
    }

    public function testDashboardRequiresAuthentication(): void
    {
        $this->client->request('GET', '/mes-formations');

        self::assertResponseRedirects('/login');
    }

    public function testEmptyDashboardInvitesToBrowse(): void
    {
        $this->loginUser();

        $this->client->request('GET', '/mes-formations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.callout', 'Tu ne suis aucune formation');
    }

    public function testInProgressFormationShowsPercentage(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormation('symfony');
        // 1 chapitre sur 3 terminé → 33 %.
        $this->enroll($user, $formation, completedCount: 1);

        $crawler = $this->client->request('GET', '/mes-formations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'En cours');
        self::assertStringContainsString('33%', $crawler->filter('.card')->text());
        self::assertStringContainsString('1 / 3 chapitres', $crawler->filter('.card')->text());
        // Barre de progression à la bonne largeur.
        self::assertStringContainsString('width:33%', (string) $this->client->getResponse()->getContent());
    }

    public function testCompletedFormationListedAsDone(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormation('symfony');
        $this->enroll($user, $formation, completedCount: 3, completedAt: new \DateTimeImmutable(), completions: 1);

        $crawler = $this->client->request('GET', '/mes-formations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Terminées');
        self::assertSelectorExists('.badge--success');
        self::assertStringContainsString('width:100%', (string) $this->client->getResponse()->getContent());
        // Pas de section « En cours » quand tout est terminé.
        self::assertStringNotContainsString('En cours', $crawler->filter('h2')->text());
    }

    public function testNoStarLegendWithoutHistory(): void
    {
        $user = $this->loginUser();
        $this->enroll($user, $this->createFormation('symfony'), completedCount: 1);

        $this->client->request('GET', '/mes-formations');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Une étoile par fois', (string) $this->client->getResponse()->getContent());
        self::assertSelectorNotExists('.badge--warning');
    }

    public function testRestartedFormationKeepsStarsWhileBackInProgress(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormation('symfony');
        // Recommencée : repassée en cours (completedAt null) mais terminée 3 fois par le passé.
        $this->enroll($user, $formation, completedCount: 1, completedAt: null, completions: 3);

        $crawler = $this->client->request('GET', '/mes-formations');

        self::assertResponseIsSuccessful();
        // Apparaît bien dans « En cours » malgré l'historique de complétion.
        self::assertSelectorTextContains('h2', 'En cours');
        // Trois étoiles dans le badge d'historique de la carte.
        $star = $crawler->filter('.card .badge--warning');
        self::assertCount(3, mb_str_split($star->filter('[aria-hidden]')->text()));
        // Légende des étoiles affichée.
        self::assertStringContainsString('Une étoile par fois', $crawler->filter('body')->text());
    }

    public function testCompletedAndInProgressAreSeparated(): void
    {
        $user = $this->loginUser();
        $this->enroll($user, $this->createFormation('symfony'), completedCount: 1);
        $this->enroll($user, $this->createFormation('vim'), completedCount: 3, completedAt: new \DateTimeImmutable());

        $this->client->request('GET', '/mes-formations');

        self::assertResponseIsSuccessful();
        // Une carte par formation, sections distinctes.
        self::assertSelectorExists('.badge--success');
        $headings = $this->client->getCrawler()->filter('h2')->each(fn ($n) => $n->text());
        self::assertContains('En cours (1)', array_map('trim', $headings));
    }
}
