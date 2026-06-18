<?php

namespace App\Service;

use App\Entity\Formation;
use App\Entity\User;
use App\Entity\UserPreferences;
use App\Enum\Difficulty;
use App\Enum\Visibility;
use App\Repository\EnrollmentRepository;
use App\Repository\FormationRepository;

/**
 * Calcule des recommandations de formations pour un utilisateur.
 *
 * Principe : on part des formations accessibles que l'utilisateur n'a jamais
 * terminées, puis on les classe par un score combinant trois signaux — les tags
 * en commun avec ses préférences (signal dominant), la proximité de niveau, et
 * un bonus de fraîcheur/popularité qui sert surtout à départager.
 *
 * Sans préférences exploitables (visiteur anonyme ou compte sans tag favori ni
 * niveau choisi), on bascule sur un repli : les formations publiques les plus
 * populaires et récentes (cf. issue #25).
 */
final class RecommendationService
{
    /** Poids d'un tag en commun : signal dominant, il prime sur tous les autres réunis. */
    public const TAG_WEIGHT = 100;
    /** Bonus quand le niveau de la formation correspond exactement à la préférence. */
    public const LEVEL_EXACT_BONUS = 6;
    /** Bonus quand le niveau est adjacent (un cran d'écart). */
    public const LEVEL_NEAR_BONUS = 3;
    /** Plafond de la contribution « popularité » (nb d'inscrits), pour ne pas écraser les tags. */
    public const POPULARITY_CAP = 5;
    /** Bonus de fraîcheur pour une formation récente, puis pour une formation d'âge moyen. */
    public const FRESHNESS_RECENT_BONUS = 3;
    public const FRESHNESS_RECENT_DAYS = 30;
    public const FRESHNESS_MEDIUM_BONUS = 1;
    public const FRESHNESS_MEDIUM_DAYS = 90;

    public function __construct(
        private readonly FormationRepository $formations,
        private readonly EnrollmentRepository $enrollments,
    ) {
    }

    /**
     * Recommandations classées pour l'utilisateur (ou repli si pas de préférences).
     *
     * @return list<Formation>
     */
    public function recommendFor(?User $user, bool $isAuthenticated, bool $isAdmin, int $limit = 3): array
    {
        $preferences = $this->usablePreferences($user);
        $personalized = null !== $preferences;

        // Repli (issue #25) : on se limite au public ; en mode personnalisé on
        // ouvre aux visibilités auxquelles l'utilisateur a droit.
        $visibilities = [Visibility::PUBLIC];
        if ($personalized) {
            if ($isAuthenticated) {
                $visibilities[] = Visibility::BETA;
            }
            if ($isAdmin) {
                $visibilities[] = Visibility::DRAFT;
            }
        }

        $excludedIds = null !== $user ? $this->enrollments->findEnrolledUserIds($user) : [];
        $candidates = $this->formations->findRecommendable($visibilities, $excludedIds);

        if ([] === $candidates) {
            return [];
        }

        $counts = $this->enrollments->countByFormation();
        $now = new \DateTimeImmutable();

        $scored = [];
        foreach ($candidates as $formation) {
            $popularity = $counts[$formation->getId()] ?? 0;
            $scored[] = [
                'formation' => $formation,
                'score' => $this->score($formation, $preferences, $popularity, $now),
                'popularity' => $popularity,
                'createdAt' => $formation->getCreatedAt(),
            ];
        }

        // Score décroissant, puis popularité, puis fraîcheur ; titre en dernier
        // recours pour un ordre stable et déterministe.
        usort($scored, static fn (array $a, array $b): int => [$b['score'], $b['popularity'], $b['createdAt']] <=> [$a['score'], $a['popularity'], $a['createdAt']]
            ?: strcmp((string) $a['formation']->getTitle(), (string) $b['formation']->getTitle()));

        return array_map(
            static fn (array $row): Formation => $row['formation'],
            \array_slice($scored, 0, max(0, $limit)),
        );
    }

    /**
     * Score d'une formation. Méthode pure (testable isolément) : tags communs
     * (dominant) + proximité de niveau + bonus fraîcheur/popularité (départage).
     */
    public function score(Formation $formation, ?UserPreferences $preferences, int $enrollmentCount, \DateTimeImmutable $now): int
    {
        $score = 0;

        if (null !== $preferences) {
            $score += self::TAG_WEIGHT * $this->commonTagCount($formation, $preferences);
            $score += $this->levelBonus($formation->getDifficulty(), $preferences->getPreferredDifficulty());
        }

        $score += min(max(0, $enrollmentCount), self::POPULARITY_CAP);
        $score += $this->freshnessBonus($formation->getCreatedAt(), $now);

        return $score;
    }

    /**
     * Préférences exploitables pour personnaliser : au moins un tag favori ou un
     * niveau choisi. Renvoie null sinon, ce qui déclenche le repli.
     */
    public function usablePreferences(?User $user): ?UserPreferences
    {
        $preferences = $user?->getPreferences();
        if (null === $preferences) {
            return null;
        }

        if ($preferences->getPreferredTags()->isEmpty() && null === $preferences->getPreferredDifficulty()) {
            return null;
        }

        return $preferences;
    }

    /**
     * Les recommandations seront-elles personnalisées (vs repli) pour cet utilisateur ?
     */
    public function isPersonalizedFor(?User $user): bool
    {
        return null !== $this->usablePreferences($user);
    }

    /**
     * Nombre de tags partagés entre la formation et les préférences. Comparaison
     * par slug (unique) pour rester correct même sur des entités non persistées.
     */
    private function commonTagCount(Formation $formation, UserPreferences $preferences): int
    {
        $preferredSlugs = [];
        foreach ($preferences->getPreferredTags() as $tag) {
            $preferredSlugs[(string) $tag->getSlug()] = true;
        }

        $common = 0;
        foreach ($formation->getTags() as $tag) {
            if (isset($preferredSlugs[(string) $tag->getSlug()])) {
                ++$common;
            }
        }

        return $common;
    }

    private function levelBonus(?Difficulty $formationLevel, ?Difficulty $preferredLevel): int
    {
        if (null === $formationLevel || null === $preferredLevel) {
            return 0;
        }

        return match (abs(self::levelOrdinal($formationLevel) - self::levelOrdinal($preferredLevel))) {
            0 => self::LEVEL_EXACT_BONUS,
            1 => self::LEVEL_NEAR_BONUS,
            default => 0,
        };
    }

    private static function levelOrdinal(Difficulty $difficulty): int
    {
        return match ($difficulty) {
            Difficulty::BEGINNER => 0,
            Difficulty::INTERMEDIATE => 1,
            Difficulty::ADVANCED => 2,
        };
    }

    private function freshnessBonus(?\DateTimeImmutable $createdAt, \DateTimeImmutable $now): int
    {
        if (null === $createdAt) {
            return 0;
        }

        $days = (int) $createdAt->diff($now)->days;
        if ($days <= self::FRESHNESS_RECENT_DAYS) {
            return self::FRESHNESS_RECENT_BONUS;
        }
        if ($days <= self::FRESHNESS_MEDIUM_DAYS) {
            return self::FRESHNESS_MEDIUM_BONUS;
        }

        return 0;
    }
}
