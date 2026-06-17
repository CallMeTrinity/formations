<?php

namespace App\DataFixtures;

use App\Entity\Tag;
use App\Entity\User;
use App\Entity\UserPreferences;
use App\Enum\Difficulty;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de démo : un admin, un utilisateur, quelques tags.
 *
 * Ne touche jamais au contenu pédagogique (Formation / Chapter / Section),
 * qui reste alimenté par `app:formations:sync` depuis le markdown.
 */
class AppFixtures extends Fixture
{
    /**
     * @var array<string, string>
     */
    private const TAGS = [
        'linux' => 'Linux',
        'bash' => 'Bash',
        'php' => 'PHP',
        'symfony' => 'Symfony',
        'javascript' => 'JavaScript',
        'vim' => 'Vim',
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $tags = [];
        foreach (self::TAGS as $slug => $label) {
            $tag = new Tag();
            $tag->setSlug($slug);
            $tag->setLabel($label);
            $manager->persist($tag);
            $tags[$slug] = $tag;
        }

        $admin = new User();
        $admin->setEmail('admin@formations.test');
        $admin->setDisplayName('Admin Démo');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin'));
        $manager->persist($admin);

        $user = new User();
        $user->setEmail('user@formations.test');
        $user->setDisplayName('Utilisateur Démo');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'user'));
        $manager->persist($user);

        $preferences = new UserPreferences();
        $preferences->setUser($user);
        $preferences->setPreferredDifficulty(Difficulty::BEGINNER);
        $preferences->setWeeklyGoalMinutes(120);
        $preferences->addPreferredTag($tags['symfony']);
        $preferences->addPreferredTag($tags['php']);
        $manager->persist($preferences);

        $manager->flush();
    }
}
