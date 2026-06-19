<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ChapterProgressRepository;
use App\Repository\EnrollmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    /**
     * Tableau de bord « Mes formations » : les inscriptions de l'utilisateur,
     * réparties entre en cours et terminées, avec leur pourcentage d'avancement.
     */
    #[Route('/mes-formations', name: 'app_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(EnrollmentRepository $enrollments, ChapterProgressRepository $chapterProgress): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $inProgress = [];
        $completed = [];
        // Vrai dès qu'une formation a été terminée au moins une fois (affiche la légende des étoiles).
        $hasHistory = false;

        foreach ($enrollments->findWithProgressForUser($user) as $row) {
            $enrollment = $row['enrollment'];
            $total = $row['chaptersCount'];

            if ($enrollment->getCompletionCount() > 0) {
                $hasHistory = true;
            }

            $item = [
                'enrollment' => $enrollment,
                'formation' => $enrollment->getFormation(),
                'chaptersCount' => $total,
                'completedCount' => $row['completedCount'],
                // Pourcentage entier ; 0 si la formation n'a pas (encore) de chapitres.
                'percent' => $total > 0 ? (int) round($row['completedCount'] / $total * 100) : 0,
            ];

            // Détection de complétion : une formation terminée porte un completedAt.
            if (null !== $enrollment->getCompletedAt()) {
                $completed[] = $item;
            } else {
                $inProgress[] = $item;
            }
        }

        // Objectif hebdomadaire : minutes accomplies depuis lundi 00:00, comparées à
        // l'objectif fixé dans les préférences. Widget masqué tant qu'aucun objectif.
        $weeklyGoal = $user->getPreferences()?->getWeeklyGoalMinutes();
        $weeklyProgress = null;
        if (null !== $weeklyGoal && $weeklyGoal > 0) {
            $doneMinutes = $chapterProgress->sumMinutesCompletedSince($user, new \DateTimeImmutable('monday this week'));
            $weeklyProgress = [
                'goalMinutes' => $weeklyGoal,
                'doneMinutes' => $doneMinutes,
                'percent' => min(100, (int) round($doneMinutes / $weeklyGoal * 100)),
            ];
        }

        return $this->render('dashboard/index.html.twig', [
            'inProgress' => $inProgress,
            'completed' => $completed,
            'hasHistory' => $hasHistory,
            'weeklyProgress' => $weeklyProgress,
        ]);
    }
}
