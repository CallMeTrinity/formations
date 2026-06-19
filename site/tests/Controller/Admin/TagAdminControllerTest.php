<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Chapter;
use App\Entity\Formation;
use App\Entity\Tag;
use App\Entity\User;
use App\Entity\UserPreferences;
use App\Enum\Visibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TagAdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        foreach ($this->em->getRepository(Formation::class)->findAll() as $formation) {
            $this->em->remove($formation);
        }
        // Préférences avant tags : elles portent le côté propriétaire de la
        // relation, leur suppression vide les lignes de jointure préférences→tag.
        foreach ($this->em->getRepository(UserPreferences::class)->findAll() as $preferences) {
            $this->em->remove($preferences);
        }
        $this->em->flush();
        foreach ($this->em->getRepository(Tag::class)->findAll() as $tag) {
            $this->em->remove($tag);
        }
        $this->em->flush();
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

    private function createFormation(string $slug = 'symfony'): Formation
    {
        $formation = (new Formation())
            ->setSlug($slug)
            ->setTitle('Titre '.$slug)
            ->setDescription('<p>Description.</p>')
            ->setVisibility(Visibility::PUBLIC);
        $formation->addChapter((new Chapter())->setSlug('introduction')->setTitle('Introduction')->setPosition(1));

        $this->em->persist($formation);
        $this->em->flush();

        return $formation;
    }

    private function createTag(string $slug, string $label): Tag
    {
        $tag = (new Tag())->setSlug($slug)->setLabel($label);
        $this->em->persist($tag);
        $this->em->flush();

        return $tag;
    }

    /**
     * Jeton CSRF d'un formulaire de la frame, lu sur la page d'édition.
     */
    private function tokenFor(string $action): string
    {
        $crawler = $this->client->request('GET', '/admin/formations/symfony/editer');

        return $crawler->filter('form[action="'.$action.'"] input[name="_token"]')->attr('value');
    }

    public function testSelectingTagsPersistsImmediately(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $this->createFormation();
        $php = $this->createTag('php', 'PHP');
        $vue = $this->createTag('vue', 'Vue');

        $token = $this->tokenFor('/admin/formations/symfony/tags');

        $this->client->request('POST', '/admin/formations/symfony/tags', [
            '_token' => $token,
            'tags' => [(string) $php->getId(), (string) $vue->getId()],
        ]);

        self::assertResponseIsSuccessful();
        // La frame ré-affichée contient bien les deux puces cochées.
        self::assertSelectorExists('turbo-frame#formation-tags-'.$this->formationId());

        $this->em->clear();
        $formation = $this->em->getRepository(Formation::class)->findOneBy(['slug' => 'symfony']);
        self::assertCount(2, $formation->getTags());
    }

    public function testDeselectingTagsRemovesThem(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $formation = $this->createFormation();
        $php = $this->createTag('php', 'PHP');
        $formation->addTag($php);
        $this->em->flush();

        $token = $this->tokenFor('/admin/formations/symfony/tags');

        // Aucune case cochée : la sélection devient vide.
        $this->client->request('POST', '/admin/formations/symfony/tags', ['_token' => $token]);

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $formation = $this->em->getRepository(Formation::class)->findOneBy(['slug' => 'symfony']);
        self::assertCount(0, $formation->getTags());
    }

    public function testCreateTagCreatesAndAttachesIt(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $this->createFormation();

        $token = $this->tokenFor('/admin/formations/symfony/tags/create');

        $this->client->request('POST', '/admin/formations/symfony/tags/create', [
            '_token' => $token,
            'label' => 'Vue JS',
        ]);

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $tag = $this->em->getRepository(Tag::class)->findOneBy(['slug' => 'vue-js']);
        self::assertNotNull($tag);
        self::assertSame('Vue JS', $tag->getLabel());

        $formation = $this->em->getRepository(Formation::class)->findOneBy(['slug' => 'symfony']);
        self::assertCount(1, $formation->getTags());
        self::assertSame('vue-js', $formation->getTags()->first()->getSlug());
    }

    public function testCreateExistingTagAttachesItWithoutDuplicate(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $this->createFormation();
        $this->createTag('php', 'PHP');

        $token = $this->tokenFor('/admin/formations/symfony/tags/create');

        $this->client->request('POST', '/admin/formations/symfony/tags/create', [
            '_token' => $token,
            'label' => 'PHP', // → même slug que l'existant
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('turbo-frame', 'existait déjà');

        $this->em->clear();
        self::assertSame(1, $this->em->getRepository(Tag::class)->count([]));
        $formation = $this->em->getRepository(Formation::class)->findOneBy(['slug' => 'symfony']);
        self::assertCount(1, $formation->getTags());
    }

    public function testDeleteRemovesTagFromFormationsAndPreferences(): void
    {
        $admin = $this->loginUser(['ROLE_ADMIN']);
        $formation = $this->createFormation();
        $php = $this->createTag('php', 'PHP');
        $formation->addTag($php);

        // Le tag est aussi dans les préférences de l'admin : la suppression doit
        // nettoyer les deux côtés sans violer de contrainte.
        $preferences = new UserPreferences();
        $preferences->addPreferredTag($php);
        $admin->setPreferences($preferences);
        $this->em->persist($preferences);
        $this->em->flush();
        $id = $php->getId();

        $crawler = $this->client->request('GET', '/admin/formations/symfony/editer');
        $deleteToken = $crawler->filter('input[name="_delete_token"]')->attr('value');

        $this->client->request('POST', '/admin/formations/symfony/tags/'.$id.'/delete', [
            '_delete_token' => $deleteToken,
        ]);

        self::assertResponseIsSuccessful();

        $this->em->clear();
        self::assertNull($this->em->getRepository(Tag::class)->find($id));
        $formation = $this->em->getRepository(Formation::class)->findOneBy(['slug' => 'symfony']);
        self::assertCount(0, $formation->getTags());
        $user = $this->em->getRepository(User::class)->find($admin->getId());
        self::assertCount(0, $user->getPreferences()->getPreferredTags());
    }

    public function testDeleteRejectsInvalidCsrfToken(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $this->createFormation();
        $php = $this->createTag('php', 'PHP');

        $this->client->request('POST', '/admin/formations/symfony/tags/'.$php->getId().'/delete', [
            '_delete_token' => 'invalide',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->em->getRepository(Tag::class)->count([]));
    }

    public function testCreateRejectsBlankLabel(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $this->createFormation();

        $token = $this->tokenFor('/admin/formations/symfony/tags/create');

        $this->client->request('POST', '/admin/formations/symfony/tags/create', [
            '_token' => $token,
            'label' => '   ',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->em->getRepository(Tag::class)->count([]));
    }

    public function testUpdateRejectsInvalidCsrfToken(): void
    {
        $this->loginUser(['ROLE_ADMIN']);
        $this->createFormation();

        $this->client->request('POST', '/admin/formations/symfony/tags', ['_token' => 'invalide']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testForbiddenForRegularUser(): void
    {
        $this->createFormation();
        $this->loginUser();

        $this->client->request('POST', '/admin/formations/symfony/tags', ['_token' => 'peu-importe']);

        self::assertResponseStatusCodeSame(403);
    }

    private function formationId(): int
    {
        return $this->em->getRepository(Formation::class)->findOneBy(['slug' => 'symfony'])->getId();
    }
}
