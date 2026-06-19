<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Chapter;
use App\Entity\ChapterProgress;
use App\Entity\Enrollment;
use App\Entity\Formation;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Difficulty;
use App\Enum\Visibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FormationAdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Base propre : progression, inscriptions, formations, puis tags (ordre FK).
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
        foreach ($this->em->getRepository(Tag::class)->findAll() as $tag) {
            $this->em->remove($tag);
        }
        $this->em->flush();
    }

    private function createFormation(string $slug, Visibility $visibility): Formation
    {
        $formation = (new Formation())
            ->setSlug($slug)
            ->setTitle('Titre '.$slug)
            ->setDescription('<p>Description.</p>')
            ->setVisibility($visibility);

        $chapter = (new Chapter())
            ->setSlug('introduction')
            ->setTitle('Introduction')
            ->setPosition(1);
        $formation->addChapter($chapter);

        $this->em->persist($formation);
        $this->em->flush();

        return $formation;
    }

    /**
     * @param list<string> $roles
     */
    private function loginUser(array $roles = []): User
    {
        $user = (new User())
            ->setEmail('u'.uniqid().'@test.dev')
            ->setRoles($roles);
        $user->setPassword('x');

        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);

        return $user;
    }

    // ── Issue 27 : accès réservé + liste ──────────────────────────────────

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }

    public function testRegularUserIsForbidden(): void
    {
        $this->loginUser();

        $this->client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminSeesAllFormationsIncludingDrafts(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $this->createFormation('publique', Visibility::PUBLIC);
        $this->createFormation('cachee', Visibility::DRAFT);

        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Titre publique');
        self::assertSelectorTextContains('body', 'Titre cachee');
        self::assertSelectorExists('a[href="/admin/formations/publique/editer"]');
    }

    // ── Issue 28 : visibilité, effet immédiat ─────────────────────────────

    public function testVisibilityChangeTakesEffectImmediately(): void
    {
        $admin = $this->loginUser(['ROLE_ADMIN']);
        $formation = $this->createFormation('symfony', Visibility::PUBLIC);

        $crawler = $this->client->request('GET', '/admin');
        $form = $crawler->filter('form[action="/admin/formations/symfony/visibilite"]')->form();
        $form['visibility'] = 'draft';
        $this->client->submit($form);

        self::assertResponseRedirects('/admin');

        $this->em->clear();
        $formation = $this->em->getRepository(Formation::class)->findOneBy(['slug' => 'symfony']);
        self::assertSame(Visibility::DRAFT, $formation->getVisibility());

        // Effet immédiat : un utilisateur lambda ne voit plus le brouillon (404).
        $this->loginUser();
        $this->client->request('GET', '/formations/symfony');
        self::assertResponseStatusCodeSame(404);
    }

    public function testVisibilityChangeRejectsInvalidCsrfToken(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $this->createFormation('symfony', Visibility::PUBLIC);

        $this->client->request('POST', '/admin/formations/symfony/visibilite', ['_token' => 'invalide', 'visibility' => 'draft']);

        self::assertResponseStatusCodeSame(403);
    }

    // ── Issue 29 : édition des métadonnées ────────────────────────────────

    public function testEditUpdatesMetadata(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $formation = $this->createFormation('symfony', Visibility::PUBLIC);

        $tag = (new Tag())->setSlug('php')->setLabel('PHP');
        $this->em->persist($tag);
        $this->em->flush();
        $tagId = $tag->getId();

        $crawler = $this->client->request('GET', '/admin/formations/symfony/editer');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="admin_formation[_token]"]')->attr('value');

        $this->client->request('POST', '/admin/formations/symfony/editer', [
            'admin_formation' => [
                'status' => 'done',
                'difficulty' => 'intermediate',
                'estimatedMinutes' => '90',
                'tags' => [$tagId],
                '_token' => $token,
            ],
        ]);

        self::assertResponseRedirects('/admin');

        $this->em->clear();
        $formation = $this->em->getRepository(Formation::class)->findOneBy(['slug' => 'symfony']);
        self::assertSame(Difficulty::INTERMEDIATE, $formation->getDifficulty());
        self::assertSame(90, $formation->getEstimatedMinutes());
        self::assertCount(1, $formation->getTags());
        self::assertSame('php', $formation->getTags()->first()->getSlug());
    }

    // ── Issue 30 : resynchronisation ──────────────────────────────────────

    public function testResyncImportsContentAndReports(): void
    {
        $this->loginUser(['ROLE_ADMIN']);

        // Le service pointe sur tests/fixtures/content en environnement de test.
        $crawler = $this->client->request('GET', '/admin');
        $this->client->submit($crawler->filter('form[action="/admin/resync"]')->form());

        self::assertResponseRedirects('/admin');
        $crawler = $this->client->followRedirect();

        self::assertSelectorTextContains('.toast', 'Synchronisation terminée');
        // Les fixtures contiennent deux formations (alpha, beta).
        self::assertSame(2, $this->em->getRepository(Formation::class)->count([]));
    }

    public function testResyncRejectsInvalidCsrfToken(): void
    {
        $this->loginUser(['ROLE_ADMIN']);

        $this->client->request('POST', '/admin/resync', ['_token' => 'invalide']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testResyncIsForbiddenForRegularUser(): void
    {
        $this->loginUser();

        $this->client->request('POST', '/admin/resync', ['_token' => 'peu-importe']);

        self::assertResponseStatusCodeSame(403);
    }

    // ── Issue 31 : stats inscrits / complétion ────────────────────────────

    public function testIndexShowsEnrollmentAndCompletionStats(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $formation = $this->createFormation('symfony', Visibility::PUBLIC);

        // Deux inscrits, un seul a terminé : 2 inscrits, 50 % de complétion.
        $this->enroll($formation, completed: true);
        $this->enroll($formation, completed: false);

        $crawler = $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        $row = $crawler->filter('tbody tr')->text();
        self::assertStringContainsString('2', $row);
        self::assertStringContainsString('50 %', $row);
    }

    public function testIndexShowsDashForFormationWithoutEnrollments(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $this->createFormation('vide', Visibility::PUBLIC);

        $crawler = $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        // Aucun inscrit : pas de division par zéro, un tiret à la place du taux.
        self::assertStringContainsString('—', $crawler->filter('tbody tr')->text());
    }

    private function enroll(Formation $formation, bool $completed): void
    {
        $user = (new User())
            ->setEmail('e'.uniqid().'@test.dev')
            ->setRoles([]);
        $user->setPassword('x');

        $enrollment = (new Enrollment())
            ->setUser($user)
            ->setFormation($formation)
            ->setLastActivityAt(new \DateTimeImmutable());

        if ($completed) {
            $enrollment->setCompletedAt(new \DateTimeImmutable())
            ->setFirstCompletedAt(new \DateTimeImmutable());
        }

        $this->em->persist($user);
        $this->em->persist($enrollment);
        $this->em->flush();
    }
}
