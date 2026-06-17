<?php

namespace App\Tests\Controller;

use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Difficulty;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PreferencesControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $this->userRepository = $em->getRepository(User::class);
        $this->passwordHasher = $container->get('security.user_password_hasher');

        // Repart d'une base propre : les users d'abord (cascade sur leurs préférences),
        // puis les tags.
        foreach ($this->userRepository->findAll() as $user) {
            $em->remove($user);
        }
        foreach ($em->getRepository(Tag::class)->findAll() as $tag) {
            $em->remove($tag);
        }
        $em->flush();
    }

    private function createUser(string $email, string $plainPassword): User
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $user = (new User())->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createTag(string $slug, string $label): Tag
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $tag = (new Tag())->setSlug($slug)->setLabel($label);
        $em->persist($tag);
        $em->flush();

        return $tag;
    }

    public function testPreferencesRequiresAuthentication(): void
    {
        $this->client->request('GET', '/profile/preferences');

        // L'access_control ^/profile renvoie un anonyme vers le login.
        self::assertResponseRedirects('/login');
    }

    public function testPreferencesPageIsAccessibleWhenLoggedIn(): void
    {
        $user = $this->createUser('prefs@example.com', 'motdepasse123');
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile/preferences');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Préférences');
    }

    public function testSavePreferencesCreatesThemForANewUser(): void
    {
        $tag = $this->createTag('symfony', 'Symfony');
        $user = $this->createUser('prefs@example.com', 'motdepasse123');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/profile/preferences');
        $form = $crawler->selectButton('Enregistrer mes préférences')->form();
        $form['user_preferences_form[preferredDifficulty]'] = Difficulty::INTERMEDIATE->value;
        $form['user_preferences_form[weeklyGoalMinutes]'] = '120';
        // Coche le seul thème disponible (index 0 → la Tag créée ci-dessus).
        $form['user_preferences_form[preferredTags][0]']->tick();
        $this->client->submit($form);

        self::assertResponseRedirects('/profile/preferences');

        // Vide l'identity map pour relire l'état réel en base, et non l'objet
        // mutué en mémoire par le contrôleur.
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();
        $updated = $em->getRepository(User::class)->findOneBy(['email' => 'prefs@example.com']);
        $preferences = $updated->getPreferences();

        self::assertNotNull($preferences);
        self::assertSame(Difficulty::INTERMEDIATE, $preferences->getPreferredDifficulty());
        self::assertSame(120, $preferences->getWeeklyGoalMinutes());
        self::assertCount(1, $preferences->getPreferredTags());
        self::assertSame($tag->getId(), $preferences->getPreferredTags()->first()->getId());
    }

    public function testRejectsOutOfRangeWeeklyGoal(): void
    {
        $user = $this->createUser('prefs@example.com', 'motdepasse123');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/profile/preferences');
        $form = $crawler->selectButton('Enregistrer mes préférences')->form();
        $form['user_preferences_form[weeklyGoalMinutes]'] = '99999'; // > 10080

        $this->client->submit($form);

        // Formulaire invalide : on reste sur la page (422), rien n'est enregistré.
        self::assertResponseIsUnprocessable();

        // Rien ne doit avoir été persisté : on relit l'état réel en base.
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();
        $updated = $em->getRepository(User::class)->findOneBy(['email' => 'prefs@example.com']);
        self::assertNull($updated->getPreferences());
    }
}
