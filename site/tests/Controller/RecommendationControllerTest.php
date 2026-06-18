<?php

namespace App\Tests\Controller;

use App\Entity\Chapter;
use App\Entity\Enrollment;
use App\Entity\Formation;
use App\Entity\Tag;
use App\Entity\User;
use App\Entity\UserPreferences;
use App\Enum\Difficulty;
use App\Enum\Visibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RecommendationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Base propre : progression et inscriptions, puis formations, puis
        // préférences (référencent les tags), enfin les tags.
        foreach ($this->em->getRepository(Enrollment::class)->findAll() as $enrollment) {
            $this->em->remove($enrollment);
        }
        foreach ($this->em->getRepository(Formation::class)->findAll() as $formation) {
            $this->em->remove($formation);
        }
        foreach ($this->em->getRepository(UserPreferences::class)->findAll() as $preferences) {
            $this->em->remove($preferences);
        }
        $this->em->flush();
        foreach ($this->em->getRepository(Tag::class)->findAll() as $tag) {
            $this->em->remove($tag);
        }
        $this->em->flush();
    }

    public function testFallbackListsPublicFormationsForAnonymous(): void
    {
        $this->createFormation('symfony', 'Symfony de zéro');
        $this->createFormation('vim', 'Vim au quotidien');

        $crawler = $this->client->request('GET', '/recommandations');

        self::assertResponseIsSuccessful();
        // Repli : pas de personnalisation possible sans compte.
        self::assertSelectorTextContains('.eyebrow', 'À découvrir');
        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('Symfony de zéro', $body);
        self::assertStringContainsString('Vim au quotidien', $body);
    }

    public function testDraftFormationsAreNotRecommended(): void
    {
        $this->createFormation('public', 'Formation publique', Visibility::PUBLIC);
        $this->createFormation('brouillon', 'Formation brouillon', Visibility::DRAFT);

        $crawler = $this->client->request('GET', '/recommandations');

        self::assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('Formation publique', $body);
        self::assertStringNotContainsString('Formation brouillon', $body);
    }

    public function testPreferredTagsPersonalizeAndRankFirst(): void
    {
        $php = $this->createTag('php', 'PHP');
        $matching = $this->createFormation('symfony', 'Symfony de zéro', Visibility::PUBLIC, [$php]);
        $other = $this->createFormation('vim', 'Vim au quotidien');

        $user = $this->loginUser();
        $this->setPreferredTags($user, [$php]);

        $crawler = $this->client->request('GET', '/recommandations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.eyebrow', 'Recommandé pour toi');

        // La formation qui partage le tag favori passe devant.
        $titles = $crawler->filter('.card h3')->each(fn ($node) => trim($node->text()));
        self::assertSame('Symfony de zéro', $titles[0] ?? null);
        self::assertContains('Vim au quotidien', $titles);
    }

    public function testCompletedFormationIsExcluded(): void
    {
        $php = $this->createTag('php', 'PHP');
        $done = $this->createFormation('symfony', 'Symfony de zéro', Visibility::PUBLIC, [$php]);
        $this->createFormation('vim', 'Vim au quotidien', Visibility::PUBLIC, [$php]);

        $user = $this->loginUser();
        $this->setPreferredTags($user, [$php]);
        $this->completeFormation($user, $done);

        $crawler = $this->client->request('GET', '/recommandations');

        self::assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        self::assertStringNotContainsString('Symfony de zéro', $body);
        self::assertStringContainsString('Vim au quotidien', $body);
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

    private function createTag(string $slug, string $label): Tag
    {
        $tag = (new Tag())->setSlug($slug)->setLabel($label);
        $this->em->persist($tag);
        $this->em->flush();

        return $tag;
    }

    /**
     * @param list<Tag> $tags
     */
    private function createFormation(string $slug, string $title, Visibility $visibility = Visibility::PUBLIC, array $tags = []): Formation
    {
        $formation = (new Formation())
            ->setSlug($slug)
            ->setTitle($title)
            ->setDescription('<p>Description de '.$title.'.</p>')
            ->setVisibility($visibility)
            ->setDifficulty(Difficulty::BEGINNER);

        $formation->addChapter((new Chapter())->setSlug('introduction')->setTitle('Introduction')->setPosition(1));
        foreach ($tags as $tag) {
            $formation->addTag($tag);
        }

        $this->em->persist($formation);
        $this->em->flush();

        return $formation;
    }

    /**
     * @param list<Tag> $tags
     */
    private function setPreferredTags(User $user, array $tags): void
    {
        $preferences = new UserPreferences();
        $user->setPreferences($preferences);
        foreach ($tags as $tag) {
            $preferences->addPreferredTag($tag);
        }
        $this->em->persist($preferences);
        $this->em->flush();
    }

    private function completeFormation(User $user, Formation $formation): void
    {
        $now = new \DateTimeImmutable();
        $enrollment = (new Enrollment())
            ->setUser($user)
            ->setFormation($formation)
            ->setStartedAt($now)
            ->setLastActivityAt($now)
            ->setCompletedAt($now)
            ->setFirstCompletedAt($now)
            ->setCompletionCount(1);

        $this->em->persist($enrollment);
        $this->em->flush();
    }
}
