<?php

namespace App\Tests\Controller;

use App\Entity\Chapter;
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
}
