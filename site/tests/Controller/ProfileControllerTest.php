<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileControllerTest extends WebTestCase
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

        // Repart d'une base propre.
        foreach ($this->userRepository->findAll() as $user) {
            $em->remove($user);
        }
        $em->flush();
    }

    /**
     * Crée et persiste un utilisateur avec le mot de passe en clair donné.
     */
    private function createUser(string $email, string $plainPassword, ?string $displayName = null): User
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $user = (new User())->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        if (null !== $displayName) {
            $user->setDisplayName($displayName);
        }

        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testProfileRequiresAuthentication(): void
    {
        $this->client->request('GET', '/profile');

        // L'access_control ^/profile renvoie un anonyme vers le login.
        self::assertResponseRedirects('/login');
    }

    public function testProfilePageIsAccessibleWhenLoggedIn(): void
    {
        $user = $this->createUser('profil@example.com', 'motdepasse123', 'Antonin');
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Profil');
    }

    public function testUpdateDisplayName(): void
    {
        $user = $this->createUser('profil@example.com', 'motdepasse123', 'Antonin');
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile');
        $this->client->submitForm('Enregistrer', [
            'profile_form[displayName]' => 'Nouveau Nom',
        ]);

        self::assertResponseRedirects('/profile');

        $updated = $this->userRepository->findOneBy(['email' => 'profil@example.com']);
        self::assertSame('Nouveau Nom', $updated->getDisplayName());
    }

    public function testClearDisplayName(): void
    {
        $user = $this->createUser('profil@example.com', 'motdepasse123', 'Antonin');
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile');
        $this->client->submitForm('Enregistrer', [
            'profile_form[displayName]' => '',
        ]);

        self::assertResponseRedirects('/profile');

        $updated = $this->userRepository->findOneBy(['email' => 'profil@example.com']);
        self::assertEmpty($updated->getDisplayName());
    }

    public function testChangePasswordWithCorrectCurrentPassword(): void
    {
        $user = $this->createUser('profil@example.com', 'ancien-mdp-123');
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile');
        $this->client->submitForm('Changer le mot de passe', [
            'change_password_form[currentPassword]' => 'ancien-mdp-123',
            'change_password_form[newPassword][first]' => 'nouveau-mdp-456',
            'change_password_form[newPassword][second]' => 'nouveau-mdp-456',
        ]);

        self::assertResponseRedirects('/profile');

        // Le nouveau mot de passe est bien actif en base.
        $updated = $this->userRepository->findOneBy(['email' => 'profil@example.com']);
        self::assertTrue($this->passwordHasher->isPasswordValid($updated, 'nouveau-mdp-456'));
        self::assertFalse($this->passwordHasher->isPasswordValid($updated, 'ancien-mdp-123'));
    }

    public function testChangePasswordRejectsWrongCurrentPassword(): void
    {
        $user = $this->createUser('profil@example.com', 'ancien-mdp-123');
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile');
        $this->client->submitForm('Changer le mot de passe', [
            'change_password_form[currentPassword]' => 'mauvais-mdp',
            'change_password_form[newPassword][first]' => 'nouveau-mdp-456',
            'change_password_form[newPassword][second]' => 'nouveau-mdp-456',
        ]);

        // Formulaire invalide : on reste sur la page (422), mot de passe inchangé.
        self::assertResponseIsUnprocessable();

        $updated = $this->userRepository->findOneBy(['email' => 'profil@example.com']);
        self::assertTrue($this->passwordHasher->isPasswordValid($updated, 'ancien-mdp-123'));
    }

    public function testChangePasswordRejectsMismatchedConfirmation(): void
    {
        $user = $this->createUser('profil@example.com', 'ancien-mdp-123');
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile');
        $this->client->submitForm('Changer le mot de passe', [
            'change_password_form[currentPassword]' => 'ancien-mdp-123',
            'change_password_form[newPassword][first]' => 'nouveau-mdp-456',
            'change_password_form[newPassword][second]' => 'pas-pareil-789',
        ]);

        self::assertResponseIsUnprocessable();

        $updated = $this->userRepository->findOneBy(['email' => 'profil@example.com']);
        self::assertTrue($this->passwordHasher->isPasswordValid($updated, 'ancien-mdp-123'));
    }
}
