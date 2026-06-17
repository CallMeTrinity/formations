<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->userRepository = $em->getRepository(User::class);

        // Repart d'une base propre.
        foreach ($this->userRepository->findAll() as $user) {
            $em->remove($user);
        }
        $em->flush();
    }

    public function testRegisterCreatesUserAndRedirectsToHome(): void
    {
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Créer mon compte', [
            'registration_form[email]' => 'nouveau@example.com',
            'registration_form[displayName]' => 'Nouveau',
            'registration_form[plainPassword]' => 'motdepasse123',
        ]);

        self::assertResponseRedirects('/');

        $user = $this->userRepository->findOneBy(['email' => 'nouveau@example.com']);
        self::assertNotNull($user);
        self::assertSame('Nouveau', $user->getDisplayName());
        self::assertNotEmpty($user->getPassword());

        // Le mot de passe est bien hashé, jamais stocké en clair.
        self::assertNotSame('motdepasse123', $user->getPassword());
    }

    public function testRegisterRejectsShortPassword(): void
    {
        $this->client->request('GET', '/register');

        $this->client->submitForm('Créer mon compte', [
            'registration_form[email]' => 'court@example.com',
            'registration_form[plainPassword]' => 'court',
        ]);

        self::assertResponseIsUnprocessable();
        self::assertNull($this->userRepository->findOneBy(['email' => 'court@example.com']));
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $passwordHasher = static::getContainer()->get('security.user_password_hasher');
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $existing = (new User())->setEmail('deja@example.com');
        $existing->setPassword($passwordHasher->hashPassword($existing, 'password'));
        $em->persist($existing);
        $em->flush();

        $this->client->request('GET', '/register');
        $this->client->submitForm('Créer mon compte', [
            'registration_form[email]' => 'deja@example.com',
            'registration_form[plainPassword]' => 'motdepasse123',
        ]);

        self::assertResponseIsUnprocessable();
        self::assertCount(1, $this->userRepository->findBy(['email' => 'deja@example.com']));
    }
}
