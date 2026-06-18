<?php

namespace App\Tests\Controller;

use App\Entity\Chapter;
use App\Entity\ChapterProgress;
use App\Entity\Enrollment;
use App\Entity\Formation;
use App\Entity\Section;
use App\Entity\User;
use App\Enum\SectionType;
use App\Enum\Visibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FormationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Repart d'une base propre (le contenu vient normalement de la sync).
        // Ordre imposé par les contraintes FK : progression, puis inscriptions, puis formations.
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

    /**
     * Crée une formation avec un chapitre, contournant la sync.
     */
    private function createFormation(string $slug, Visibility $visibility): Formation
    {
        $formation = (new Formation())
            ->setSlug($slug)
            ->setTitle('Titre '.$slug)
            ->setDescription('<p>Description de la formation.</p>')
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
     * Crée et connecte un utilisateur avec les rôles fournis.
     *
     * @param list<string> $roles
     */
    private function loginUser(array $roles = []): User
    {
        $user = (new User())
            ->setEmail('u'.uniqid().'@test.dev')
            ->setRoles($roles);
        // Mot de passe non nullable ; loginUser() n'en vérifie pas la valeur.
        $user->setPassword('x');

        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);

        return $user;
    }

    public function testShowDisplaysPresentationAndChapterPlan(): void
    {
        $this->createFormation('symfony', Visibility::PUBLIC);

        $this->client->request('GET', '/formations/symfony');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Titre symfony');
        self::assertSelectorTextContains('h2', 'Plan des chapitres');
        self::assertSelectorTextContains('ol', 'Introduction');
    }

    public function testBetaFormationIsAccessibleWhenLoggedIn(): void
    {
        $this->loginUser();
        $this->createFormation('vim', Visibility::BETA);

        $this->client->request('GET', '/formations/vim');

        self::assertResponseIsSuccessful();
    }

    public function testBetaFormationIsDeniedForAnonymous(): void
    {
        $this->createFormation('vim', Visibility::BETA);

        $this->client->request('GET', '/formations/vim');

        // 403 transformé en redirection vers le login par l'entry point form_login.
        self::assertResponseRedirects('/login');
    }

    public function testDraftFormationReturns404ForAnonymous(): void
    {
        $this->createFormation('cachee', Visibility::DRAFT);

        $this->client->request('GET', '/formations/cachee');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDraftFormationReturns404ForLoggedUser(): void
    {
        $this->loginUser();
        $this->createFormation('cachee', Visibility::DRAFT);

        $this->client->request('GET', '/formations/cachee');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDraftFormationIsAccessibleForAdmin(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $this->createFormation('cachee', Visibility::DRAFT);

        $this->client->request('GET', '/formations/cachee');

        self::assertResponseIsSuccessful();
    }

    public function testUnknownSlugReturns404(): void
    {
        $this->client->request('GET', '/formations/inconnue');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Crée une formation à plusieurs chapitres, chacun avec une section de contenu.
     */
    private function createFormationWithChapters(string $slug, Visibility $visibility): Formation
    {
        $formation = (new Formation())
            ->setSlug($slug)
            ->setTitle('Titre '.$slug)
            ->setDescription('<p>Description.</p>')
            ->setVisibility($visibility);

        foreach (['introduction' => 1, 'les-bases' => 2, 'pour-aller-plus-loin' => 3] as $chapterSlug => $position) {
            $chapter = (new Chapter())
                ->setSlug($chapterSlug)
                ->setTitle('Chapitre '.$chapterSlug)
                ->setPosition($position);

            $section = new Section();
            $section->setType(SectionType::CONTENT)
                ->setTitle('Contenu')
                ->setPosition(1)
                ->setContent('<p>Texte du chapitre '.$chapterSlug.'.</p><details><summary>Corrigé</summary><p>La réponse.</p></details>');
            $chapter->addSection($section);

            $formation->addChapter($chapter);
        }

        $this->em->persist($formation);
        $this->em->flush();

        return $formation;
    }

    public function testChapterRendersContentAndPreservesDetails(): void
    {
        $this->createFormationWithChapters('symfony', Visibility::PUBLIC);

        $this->client->request('GET', '/formations/symfony/les-bases');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Chapitre les-bases');
        self::assertSelectorTextContains('.prose-chapter', 'Texte du chapitre les-bases');
        // Les blocs <details> (corrigés/quiz) sont conservés tels quels.
        self::assertSelectorExists('.prose-chapter details summary');
    }

    public function testChapterShowsPreviousAndNextNavigation(): void
    {
        $this->createFormationWithChapters('symfony', Visibility::PUBLIC);

        $this->client->request('GET', '/formations/symfony/les-bases');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[rel="prev"][href="/formations/symfony/introduction"]');
        self::assertSelectorExists('a[rel="next"][href="/formations/symfony/pour-aller-plus-loin"]');
    }

    public function testFirstChapterHasNoPreviousLink(): void
    {
        $this->createFormationWithChapters('symfony', Visibility::PUBLIC);

        $this->client->request('GET', '/formations/symfony/introduction');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[rel="prev"]');
        self::assertSelectorExists('a[rel="next"][href="/formations/symfony/les-bases"]');
    }

    public function testLastChapterHasNoNextLink(): void
    {
        $this->createFormationWithChapters('symfony', Visibility::PUBLIC);

        $this->client->request('GET', '/formations/symfony/pour-aller-plus-loin');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[rel="next"]');
        self::assertSelectorExists('a[rel="prev"][href="/formations/symfony/les-bases"]');
    }

    public function testUnknownChapterReturns404(): void
    {
        $this->createFormationWithChapters('symfony', Visibility::PUBLIC);

        $this->client->request('GET', '/formations/symfony/inconnu');

        self::assertResponseStatusCodeSame(404);
    }

    public function testChapterOfDraftFormationReturns404(): void
    {
        $this->createFormationWithChapters('cachee', Visibility::DRAFT);

        $this->client->request('GET', '/formations/cachee/introduction');

        self::assertResponseStatusCodeSame(404);
    }

    public function testChapterOfBetaFormationIsDeniedForAnonymous(): void
    {
        $this->createFormationWithChapters('vim', Visibility::BETA);

        $this->client->request('GET', '/formations/vim/introduction');

        self::assertResponseRedirects('/login');
    }

    public function testEnrollCreatesEnrollment(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormation('symfony', Visibility::PUBLIC);

        $this->client->request('GET', '/formations/symfony');
        $this->client->submitForm('Suivre cette formation');

        self::assertResponseRedirects('/formations/symfony');

        $enrollment = $this->em->getRepository(Enrollment::class)
            ->findOneByUserAndFormation($user, $formation);
        self::assertNotNull($enrollment);
        self::assertNotNull($enrollment->getStartedAt());
        self::assertNotNull($enrollment->getLastActivityAt());
    }

    public function testEnrollIsIdempotentWhenAlreadyEnrolled(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormation('symfony', Visibility::PUBLIC);

        // Premier suivi via le formulaire (jeton CSRF valide, lié à la session).
        $crawler = $this->client->request('GET', '/formations/symfony');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->submitForm('Suivre cette formation');

        // Rejoue la même action « suivre » : le garde-fou évite le doublon.
        $this->client->request('POST', '/formations/symfony/suivre', ['_token' => $token]);

        self::assertResponseRedirects('/formations/symfony');
        self::assertSame(1, $this->em->getRepository(Enrollment::class)
            ->count(['user' => $user, 'formation' => $formation]));
    }

    public function testUnenrollRemovesEnrollment(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormation('symfony', Visibility::PUBLIC);

        // Suivre, puis quitter, chacun via son formulaire rendu.
        $this->client->request('GET', '/formations/symfony');
        $this->client->submitForm('Suivre cette formation');
        $this->client->request('GET', '/formations/symfony');
        $this->client->submitForm('Quitter cette formation');

        self::assertResponseRedirects('/formations/symfony');
        self::assertNull($this->em->getRepository(Enrollment::class)
            ->findOneByUserAndFormation($user, $formation));
    }

    public function testUnenrollWhenNotEnrolledIsHarmless(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormation('symfony', Visibility::PUBLIC);

        // Suivre pour obtenir un jeton « quitter » valide, quitter, puis rejouer « quitter ».
        $this->client->request('GET', '/formations/symfony');
        $this->client->submitForm('Suivre cette formation');
        $crawler = $this->client->request('GET', '/formations/symfony');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->submitForm('Quitter cette formation');

        $this->client->request('POST', '/formations/symfony/quitter', ['_token' => $token]);

        self::assertResponseRedirects('/formations/symfony');
        self::assertSame(0, $this->em->getRepository(Enrollment::class)
            ->count(['user' => $user, 'formation' => $formation]));
    }

    public function testEnrollRequiresAuthentication(): void
    {
        $this->createFormation('symfony', Visibility::PUBLIC);

        // Anonyme : refus par le firewall avant même le contrôleur, peu importe le jeton.
        $this->client->request('POST', '/formations/symfony/suivre', ['_token' => 'peu-importe']);

        self::assertResponseRedirects('/login');
    }

    public function testEnrollRejectsInvalidCsrfToken(): void
    {
        $this->loginUser();
        $this->createFormation('symfony', Visibility::PUBLIC);

        $this->client->request('POST', '/formations/symfony/suivre', ['_token' => 'invalide']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Inscrit l'utilisateur, avec une dernière activité volontairement ancienne
     * pour pouvoir vérifier qu'une action la rafraîchit.
     */
    private function enroll(User $user, Formation $formation): Enrollment
    {
        $past = new \DateTimeImmutable('2000-01-01');
        $enrollment = (new Enrollment())
            ->setUser($user)
            ->setFormation($formation)
            ->setStartedAt($past)
            ->setLastActivityAt($past);

        $this->em->persist($enrollment);
        $this->em->flush();

        return $enrollment;
    }

    /**
     * Soumet le formulaire « terminer / annuler » de la page chapitre (jeton CSRF
     * inclus), indépendamment du libellé du bouton. Sans Turbo, l'action redirige :
     * on suit la redirection pour retomber sur une page complète.
     */
    private function submitChapterCompleteForm(string $path): void
    {
        $crawler = $this->client->request('GET', $path);
        $this->client->submit($crawler->filter('#chapter-complete form')->form());
        $this->client->followRedirect();
    }

    public function testMarkChapterCompleteCreatesProgressAndRefreshesActivity(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $this->enroll($user, $formation);

        $this->submitChapterCompleteForm('/formations/symfony/introduction');

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $enrollment = $this->em->getRepository(Enrollment::class)->findOneByUserAndFormation($user, $formation);
        self::assertNotNull($enrollment);
        self::assertSame(1, $this->em->getRepository(ChapterProgress::class)->count(['enrollment' => $enrollment]));
        self::assertGreaterThan(new \DateTimeImmutable('2000-01-02'), $enrollment->getLastActivityAt());
    }

    public function testMarkChapterCompleteTogglesOff(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $enrollment = $this->enroll($user, $formation);

        // Marquer puis annuler : on retombe à zéro progression.
        $this->submitChapterCompleteForm('/formations/symfony/introduction');
        $this->submitChapterCompleteForm('/formations/symfony/introduction');

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->em->getRepository(ChapterProgress::class)->count(['enrollment' => $enrollment]));
    }

    public function testMarkChapterCompleteWhenNotEnrolledIsHarmless(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $this->enroll($user, $formation);

        // Récupère un jeton valide via le formulaire rendu, puis désinscrit l'utilisateur.
        $crawler = $this->client->request('GET', '/formations/symfony/introduction');
        $token = $crawler->filter('#chapter-complete form input[name="_token"]')->attr('value');

        // Le kernel a redémarré : on repart d'un em frais pour écrire en base.
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->remove($em->getRepository(Enrollment::class)->findOneByUserAndFormation($user, $formation));
        $em->flush();

        $this->client->request('POST', '/formations/symfony/introduction/complete', ['_token' => $token]);

        self::assertResponseRedirects('/formations/symfony');
        self::assertSame(0, static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(ChapterProgress::class)->count([]));
    }

    public function testMarkChapterCompleteRejectsInvalidCsrfToken(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $this->enroll($user, $formation);

        $this->client->request('POST', '/formations/symfony/introduction/complete', ['_token' => 'invalide']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testMarkChapterCompleteRequiresAuthentication(): void
    {
        $this->createFormationWithChapters('symfony', Visibility::PUBLIC);

        $this->client->request('POST', '/formations/symfony/introduction/complete', ['_token' => 'peu-importe']);

        self::assertResponseRedirects('/login');
    }

    public function testVisitingNextChapterMarksPreviousCompleted(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $this->enroll($user, $formation);

        // Aller au 2e chapitre marque automatiquement le 1er comme terminé (mais pas le courant).
        $this->client->request('GET', '/formations/symfony/les-bases');
        self::assertResponseIsSuccessful();

        self::assertSame(['introduction'], $this->completedChapterSlugs($user, 'symfony'));
    }

    /**
     * Slugs des chapitres terminés pour une formation, scoping par l'inscription
     * (robuste aux éventuels chapitres orphelins laissés par d'autres tests).
     *
     * @return list<string>
     */
    private function completedChapterSlugs(User $user, string $formationSlug): array
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $formation = $em->getRepository(Formation::class)->findOneBy(['slug' => $formationSlug]);
        $enrollment = $em->getRepository(Enrollment::class)->findOneByUserAndFormation($user, $formation);
        $progress = $em->getRepository(ChapterProgress::class);

        $slugs = [];
        foreach ($formation->getChapters() as $chapter) {
            if (null !== $progress->findOneByEnrollmentAndChapter($enrollment, $chapter)) {
                $slugs[] = $chapter->getSlug();
            }
        }

        return $slugs;
    }

    public function testVisitingFirstChapterMarksNothing(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $this->enroll($user, $formation);

        $this->client->request('GET', '/formations/symfony/introduction');

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $enrollment = $em->getRepository(Enrollment::class)->findOneByUserAndFormation($user, $formation);
        self::assertSame(0, $em->getRepository(ChapterProgress::class)->count(['enrollment' => $enrollment]));
    }

    public function testSummaryMarksCompletedChapters(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $this->enroll($user, $formation);

        // Visiter le 2e chapitre termine le 1er, qui doit apparaître « Terminé » au sommaire.
        $this->client->request('GET', '/formations/symfony/les-bases');
        $this->client->request('GET', '/formations/symfony');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('ol li:first-child', 'Terminé');
    }

    public function testEnrollViaTurboReturnsStream(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormation('symfony', Visibility::PUBLIC);

        $crawler = $this->client->request('GET', '/formations/symfony');
        $token = $crawler->filter('.enroll-control input[name="_token"]')->first()->attr('value');

        // Requête « Turbo » : on simule l'en-tête Accept envoyé par Turbo.
        $this->client->request(
            'POST',
            '/formations/symfony/suivre',
            ['_token' => $token],
            [],
            ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html'],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/vnd.turbo-stream.html', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('<turbo-stream', (string) $this->client->getResponse()->getContent());
        self::assertNotNull(static::getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(Enrollment::class)->findOneByUserAndFormation($user, $formation));
    }

    /**
     * Marque tous les chapitres d'une formation comme terminés pour l'inscription.
     */
    private function completeAllChapters(Enrollment $enrollment, Formation $formation): void
    {
        $now = new \DateTimeImmutable();
        foreach ($formation->getChapters() as $chapter) {
            $this->em->persist(
                (new ChapterProgress())
                    ->setEnrollment($enrollment)
                    ->setChapter($chapter)
                    ->setCompletedAt($now)
            );
        }
        $this->em->flush();
    }

    public function testCompleteButtonHiddenWhileChaptersRemain(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $this->enroll($user, $formation);

        $this->client->request('GET', '/formations/symfony');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action="/formations/symfony/terminer"]');
    }

    public function testCompleteButtonAppearsWhenAllChaptersDone(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $enrollment = $this->enroll($user, $formation);
        $this->completeAllChapters($enrollment, $formation);

        $this->client->request('GET', '/formations/symfony');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/formations/symfony/terminer"]');
    }

    public function testCompleteFormationSetsCompletedAt(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $enrollment = $this->enroll($user, $formation);
        $this->completeAllChapters($enrollment, $formation);

        $crawler = $this->client->request('GET', '/formations/symfony');
        $this->client->submit($crawler->filter('form[action="/formations/symfony/terminer"]')->first()->form());

        self::assertResponseRedirects('/formations/symfony');

        $this->em->clear();
        $enrollment = $this->em->getRepository(Enrollment::class)->findOneByUserAndFormation($user, $formation);
        self::assertNotNull($enrollment->getCompletedAt());
        self::assertGreaterThan(new \DateTimeImmutable('2000-01-02'), $enrollment->getLastActivityAt());
    }

    public function testCompleteFormationRejectedWhenChaptersIncomplete(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $enrollment = $this->enroll($user, $formation);
        $this->completeAllChapters($enrollment, $formation);

        // Jeton valide récupéré quand tout est terminé...
        $crawler = $this->client->request('GET', '/formations/symfony');
        $token = $crawler->filter('form[action="/formations/symfony/terminer"] input[name="_token"]')->first()->attr('value');

        // ... mais on retire une progression : la formation n'est plus complétable.
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $formation = $em->getRepository(Formation::class)->findOneBy(['slug' => 'symfony']);
        $enrollment = $em->getRepository(Enrollment::class)->findOneByUserAndFormation($user, $formation);
        $progress = $em->getRepository(ChapterProgress::class)->findOneBy(['enrollment' => $enrollment]);
        $em->remove($progress);
        $em->flush();

        $this->client->request('POST', '/formations/symfony/terminer', ['_token' => $token]);

        self::assertResponseRedirects('/formations/symfony');
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $enrollment = $em->getRepository(Enrollment::class)->findOneByUserAndFormation($user, $formation);
        self::assertNull($enrollment->getCompletedAt());
    }

    public function testCompleteFormationRejectsInvalidCsrfToken(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $this->enroll($user, $formation);

        $this->client->request('POST', '/formations/symfony/terminer', ['_token' => 'invalide']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCompleteFormationRequiresAuthentication(): void
    {
        $this->createFormationWithChapters('symfony', Visibility::PUBLIC);

        $this->client->request('POST', '/formations/symfony/terminer', ['_token' => 'peu-importe']);

        self::assertResponseRedirects('/login');
    }

    public function testCompleteFormationViaTurboReturnsStream(): void
    {
        $user = $this->loginUser();
        $formation = $this->createFormationWithChapters('symfony', Visibility::PUBLIC);
        $enrollment = $this->enroll($user, $formation);
        $this->completeAllChapters($enrollment, $formation);

        $crawler = $this->client->request('GET', '/formations/symfony');
        $token = $crawler->filter('form[action="/formations/symfony/terminer"] input[name="_token"]')->first()->attr('value');

        $this->client->request(
            'POST',
            '/formations/symfony/terminer',
            ['_token' => $token],
            [],
            ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html'],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/vnd.turbo-stream.html', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('<turbo-stream', (string) $this->client->getResponse()->getContent());

        $enrollment = static::getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(Enrollment::class)->findOneByUserAndFormation($user, $formation);
        self::assertNotNull($enrollment->getCompletedAt());
    }
}
