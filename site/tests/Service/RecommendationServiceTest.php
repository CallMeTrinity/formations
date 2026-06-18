<?php

namespace App\Tests\Service;

use App\Entity\Formation;
use App\Entity\Tag;
use App\Entity\UserPreferences;
use App\Enum\Difficulty;
use App\Repository\EnrollmentRepository;
use App\Repository\FormationRepository;
use App\Service\RecommendationService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du scoring (issue #23). On teste la méthode pure `score()`
 * isolément : les dépendances (repositories) sont mockées et jamais sollicitées.
 */
class RecommendationServiceTest extends TestCase
{
    private RecommendationService $service;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        // `score()` est pure : les repositories ne sont jamais sollicités, de
        // simples stubs suffisent (et évitent les notices « mock sans attente »).
        $this->service = new RecommendationService(
            $this->createStub(FormationRepository::class),
            $this->createStub(EnrollmentRepository::class),
        );
        $this->now = new \DateTimeImmutable('2026-06-18');
    }

    public function testWithoutPreferencesScoresOnPopularityAndFreshnessOnly(): void
    {
        // Créée aujourd'hui (fraîcheur récente +3) et suivie par 2 inscrits (+2).
        $formation = $this->formation(createdAt: $this->now);

        self::assertSame(
            RecommendationService::FRESHNESS_RECENT_BONUS + 2,
            $this->service->score($formation, null, 2, $this->now),
        );
    }

    public function testCommonTagsDriveTheScore(): void
    {
        $formation = $this->formation(['php', 'symfony', 'doctrine'], createdAt: $this->old());
        $preferences = $this->preferences(['php', 'symfony']);

        // 2 tags communs, pas de niveau, pas de popularité, formation ancienne.
        self::assertSame(2 * RecommendationService::TAG_WEIGHT, $this->service->score($formation, $preferences, 0, $this->now));
    }

    public function testExactLevelBeatsAdjacentLevel(): void
    {
        $preferences = $this->preferences([], Difficulty::INTERMEDIATE);

        $exact = $this->formation([], Difficulty::INTERMEDIATE, $this->old());
        $near = $this->formation([], Difficulty::BEGINNER, $this->old());
        $far = $this->formation([], Difficulty::ADVANCED, $this->old());

        self::assertSame(RecommendationService::LEVEL_EXACT_BONUS, $this->service->score($exact, $preferences, 0, $this->now));
        self::assertSame(RecommendationService::LEVEL_NEAR_BONUS, $this->service->score($near, $preferences, 0, $this->now));
        // ADVANCED vs INTERMEDIATE : distance 1, donc bonus de proximité (et non zéro).
        self::assertSame(RecommendationService::LEVEL_NEAR_BONUS, $this->service->score($far, $preferences, 0, $this->now));
    }

    public function testPopularityIsCapped(): void
    {
        $formation = $this->formation(createdAt: $this->old());

        self::assertSame(RecommendationService::POPULARITY_CAP, $this->service->score($formation, null, 999, $this->now));
        self::assertSame(3, $this->service->score($formation, null, 3, $this->now));
    }

    public function testRecentFormationsScoreHigherThanOldOnes(): void
    {
        $recent = $this->formation(createdAt: $this->now->modify('-10 days'));
        $medium = $this->formation(createdAt: $this->now->modify('-60 days'));
        $old = $this->formation(createdAt: $this->now->modify('-200 days'));

        self::assertSame(RecommendationService::FRESHNESS_RECENT_BONUS, $this->service->score($recent, null, 0, $this->now));
        self::assertSame(RecommendationService::FRESHNESS_MEDIUM_BONUS, $this->service->score($medium, null, 0, $this->now));
        self::assertSame(0, $this->service->score($old, null, 0, $this->now));
    }

    public function testTagsOutweighLevelPopularityAndFreshnessCombined(): void
    {
        $preferences = $this->preferences(['php'], Difficulty::INTERMEDIATE);

        // Un seul tag commun, rien d'autre.
        $tagged = $this->formation(['php'], Difficulty::ADVANCED, $this->old());
        // Aucun tag, mais niveau exact + popularité max + fraîcheur récente.
        $loaded = $this->formation([], Difficulty::INTERMEDIATE, $this->now);

        $taggedScore = $this->service->score($tagged, $preferences, 0, $this->now);
        $loadedScore = $this->service->score($loaded, $preferences, 999, $this->now);

        self::assertGreaterThan($loadedScore, $taggedScore);
    }

    private function old(): \DateTimeImmutable
    {
        return $this->now->modify('-1 year');
    }

    private function tag(string $slug): Tag
    {
        return (new Tag())->setSlug($slug)->setLabel(ucfirst($slug));
    }

    /**
     * @param list<string> $tagSlugs
     */
    private function formation(array $tagSlugs = [], ?Difficulty $difficulty = null, ?\DateTimeImmutable $createdAt = null): Formation
    {
        $formation = (new Formation())
            ->setSlug('formation')
            ->setTitle('Formation')
            ->setDescription('Description.');

        foreach ($tagSlugs as $slug) {
            $formation->addTag($this->tag($slug));
        }
        if (null !== $difficulty) {
            $formation->setDifficulty($difficulty);
        }
        if (null !== $createdAt) {
            $formation->setCreatedAt($createdAt);
        }

        return $formation;
    }

    /**
     * @param list<string> $tagSlugs
     */
    private function preferences(array $tagSlugs = [], ?Difficulty $difficulty = null): UserPreferences
    {
        $preferences = new UserPreferences();
        foreach ($tagSlugs as $slug) {
            $preferences->addPreferredTag($this->tag($slug));
        }
        if (null !== $difficulty) {
            $preferences->setPreferredDifficulty($difficulty);
        }

        return $preferences;
    }
}
