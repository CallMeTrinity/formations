<?php

namespace App\DataFixtures;

use App\Entity\Enrollment;
use App\Entity\Formation;
use App\Entity\Tag;
use App\Entity\User;
use App\Entity\UserPreferences;
use App\Enum\Difficulty;
use App\Enum\Visibility;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de démo pour travailler le catalogue (filtres) et les
 * recommandations sans dépendre du contenu réel.
 *
 * Ces formations sont volontairement SANS chapitres : leurs slugs ne
 * correspondent à aucune formation markdown, donc `app:formations:sync` ne les
 * touche jamais et ne leur ajoute pas de contenu. On ne renseigne que les champs
 * qui pilotent les filtres et le score de reco : visibility, difficulty, tags,
 * estimatedMinutes, createdAt (fraîcheur) et la popularité (via enrollments).
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
        'docker' => 'Docker',
        'devops' => 'DevOps',
        'git' => 'Git',
        'python' => 'Python',
        'typescript' => 'TypeScript',
        'react' => 'React',
        'sql' => 'SQL',
        'css' => 'CSS',
        'testing' => 'Tests',
        'api' => 'API',
        'ia' => 'IA',
        'algo' => 'Algorithmes',
        'go' => 'Go',
        'rust' => 'Rust',
    ];

    /**
     * Formations de démo. Chaque entrée :
     *   [slug, titre, difficulty|null, visibility, estimatedMinutes, [tags], ageInDays].
     *
     * L'âge (en jours) décale createdAt pour exercer le bonus de fraîcheur de la
     * reco : <= 30 j = récent, <= 90 j = moyen, au-delà = neutre.
     *
     * @var list<array{string, string, Difficulty|null, Visibility, int, list<string>, int}>
     */
    private const FORMATIONS = [
        ['docker-pour-les-devs', 'Docker pour les développeurs', Difficulty::BEGINNER, Visibility::PUBLIC, 180, ['docker', 'devops'], 5],
        ['git-en-profondeur', 'Git en profondeur', Difficulty::INTERMEDIATE, Visibility::PUBLIC, 240, ['git'], 12],
        ['python-pour-debutants', 'Python pour débutants', Difficulty::BEGINNER, Visibility::PUBLIC, 300, ['python'], 40],
        ['typescript-de-zero', 'TypeScript de zéro', Difficulty::BEGINNER, Visibility::PUBLIC, 260, ['typescript', 'javascript'], 8],
        ['react-fondamentaux', 'React : les fondamentaux', Difficulty::INTERMEDIATE, Visibility::PUBLIC, 320, ['react', 'javascript'], 60],
        ['postgresql-et-sql', 'PostgreSQL et SQL', Difficulty::INTERMEDIATE, Visibility::PUBLIC, 280, ['sql'], 100],
        ['css-moderne', 'CSS moderne : Grid, Flexbox, oklch', Difficulty::BEGINNER, Visibility::PUBLIC, 200, ['css'], 20],
        ['tests-automatises-php', 'Tests automatisés en PHP', Difficulty::INTERMEDIATE, Visibility::BETA, 220, ['php', 'testing'], 15],
        ['api-rest-avec-symfony', 'Concevoir une API REST avec Symfony', Difficulty::INTERMEDIATE, Visibility::PUBLIC, 360, ['symfony', 'php', 'api'], 3],
        ['symfony-temps-reel', 'Symfony temps réel avec Mercure', Difficulty::ADVANCED, Visibility::BETA, 300, ['symfony', 'php'], 25],
        ['algorithmes-essentiels', 'Algorithmes essentiels', Difficulty::ADVANCED, Visibility::PUBLIC, 400, ['algo'], 200],
        ['ia-pour-developpeurs-demo', 'IA pour les développeurs', Difficulty::INTERMEDIATE, Visibility::DRAFT, 280, ['ia', 'python'], 2],
        ['go-pour-le-backend', 'Go pour le backend', Difficulty::INTERMEDIATE, Visibility::PUBLIC, 300, ['go'], 50],
        ['rust-les-bases', 'Rust : les bases', Difficulty::ADVANCED, Visibility::DRAFT, 360, ['rust'], 70],
        ['introduction-au-devops', 'Introduction au DevOps', null, Visibility::PUBLIC, 150, ['devops', 'docker'], 130],
    ];

    /**
     * Popularité : slug de formation => nombre d'apprenants « fantômes » inscrits.
     * Sert à exercer le tri par popularité (départage et repli de la reco).
     *
     * @var array<string, int>
     */
    private const POPULARITY = [
        'api-rest-avec-symfony' => 4,
        'docker-pour-les-devs' => 3,
        'react-fondamentaux' => 2,
        'python-pour-debutants' => 1,
        'css-moderne' => 2,
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

        // Apprenants « fantômes » : servent uniquement à gonfler la popularité de
        // certaines formations sans polluer le compte de démo.
        $learners = [];
        for ($i = 1; $i <= 4; ++$i) {
            $learner = new User();
            $learner->setEmail(sprintf('learner%d@formations.test', $i));
            $learner->setDisplayName(sprintf('Apprenant %d', $i));
            $learner->setPassword($this->passwordHasher->hashPassword($learner, 'learner'));
            $manager->persist($learner);
            $learners[] = $learner;
        }

        $formations = [];
        foreach (self::FORMATIONS as [$slug, $title, $difficulty, $visibility, $minutes, $tagSlugs, $ageInDays]) {
            $formation = new Formation();
            $formation->setSlug($slug);
            $formation->setTitle($title);
            $formation->setDescription(sprintf('Formation de démo « %s » (sans contenu, pour tester filtres et recommandations).', $title));
            $formation->setVisibility($visibility);
            $formation->setEstimatedMinutes($minutes);
            if (null !== $difficulty) {
                $formation->setDifficulty($difficulty);
            }
            foreach ($tagSlugs as $tagSlug) {
                $formation->addTag($tags[$tagSlug]);
            }
            $manager->persist($formation);
            $formations[$slug] = [$formation, $ageInDays];
        }

        // Inscriptions pour la popularité (apprenants fantômes, non terminées :
        // elles restent donc recommandables pour le compte de démo).
        $now = new \DateTimeImmutable();
        foreach (self::POPULARITY as $slug => $count) {
            [$formation] = $formations[$slug];
            for ($i = 0; $i < $count; ++$i) {
                $enrollment = new Enrollment();
                $enrollment->setUser($learners[$i % \count($learners)]);
                $enrollment->setFormation($formation);
                $enrollment->setStartedAt($now->modify(sprintf('-%d days', $i + 1)));
                $enrollment->setLastActivityAt($now->modify(sprintf('-%d days', $i)));
                $manager->persist($enrollment);
            }
        }

        $manager->flush();

        // Deuxième passe : createdAt est figé par @PrePersist au moment du flush.
        // On le réécrit ici pour étaler la fraîcheur ; @PreUpdate ne touche que
        // updatedAt, donc cette valeur est conservée.
        foreach ($formations as [$formation, $ageInDays]) {
            $formation->setCreatedAt($now->modify(sprintf('-%d days', $ageInDays)));
        }

        $manager->flush();
    }
}
