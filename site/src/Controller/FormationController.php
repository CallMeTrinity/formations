<?php

namespace App\Controller;

use App\Entity\Chapter;
use App\Entity\ChapterProgress;
use App\Entity\Enrollment;
use App\Entity\Formation;
use App\Entity\User;
use App\Enum\Visibility;
use App\Repository\ChapterProgressRepository;
use App\Repository\EnrollmentRepository;
use App\Security\Voter\FormationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Turbo\TurboBundle;

final class FormationController extends AbstractController
{
    public function __construct(
        private readonly EnrollmentRepository $enrollments,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/formations/{slug}', name: 'app_formation_show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        ChapterProgressRepository $chapterProgress,
    ): Response {
        $this->denyAccessUnlessVisible($formation);

        $enrollment = null;
        $completedChapterIds = [];
        $user = $this->getUser();
        if ($user instanceof User) {
            $enrollment = $this->enrollments->findOneByUserAndFormation($user, $formation);
            if (null !== $enrollment) {
                $completedChapterIds = $chapterProgress->findCompletedChapterIds($enrollment);
            }
        }

        return $this->render('formation/show.html.twig', [
            'formation' => $formation,
            'enrollment' => $enrollment,
            'completedChapterIds' => $completedChapterIds,
            'allChaptersCompleted' => $this->allChaptersCompleted($formation, $completedChapterIds),
        ]);
    }

    /**
     * Suivre une formation : crée un Enrollment pour l'utilisateur courant.
     */
    #[Route('/formations/{slug}/suivre', name: 'app_formation_enroll', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enroll(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        Request $request,
    ): Response {
        $this->denyAccessUnlessVisible($formation);

        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('enroll'.$formation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // Garde-fou : déjà inscrit, on ne crée pas de doublon (cf. contrainte unique).
        $existing = $this->enrollments->findOneByUserAndFormation($user, $formation);
        if (null !== $existing) {
            if (null !== $stream = $this->enrollStream($request, $formation, $existing)) {
                return $stream;
            }
            $this->addFlash('info', 'Tu suis déjà cette formation.');

            return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
        }

        $now = new \DateTimeImmutable();
        $enrollment = (new Enrollment())
            ->setUser($user)
            ->setFormation($formation)
            ->setStartedAt($now)
            ->setLastActivityAt($now);

        $this->em->persist($enrollment);
        $this->em->flush();

        if (null !== $stream = $this->enrollStream($request, $formation, $enrollment)) {
            return $stream;
        }
        $this->addFlash('success', 'Tu suis maintenant cette formation.');

        return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
    }

    /**
     * Quitter une formation : supprime l'Enrollment de l'utilisateur courant.
     */
    #[Route('/formations/{slug}/quitter', name: 'app_formation_unenroll', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function unenroll(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        Request $request,
    ): Response {
        $this->denyAccessUnlessVisible($formation);

        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('unenroll'.$formation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $enrollment = $this->enrollments->findOneByUserAndFormation($user, $formation);

        // Garde-fou : pas inscrit, rien à faire.
        if (null === $enrollment) {
            if (null !== $stream = $this->enrollStream($request, $formation, null)) {
                return $stream;
            }
            $this->addFlash('info', 'Tu ne suis pas cette formation.');

            return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
        }

        $this->em->remove($enrollment);
        $this->em->flush();

        if (null !== $stream = $this->enrollStream($request, $formation, null)) {
            return $stream;
        }
        $this->addFlash('success', 'Tu as quitté cette formation.');

        return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
    }

    /**
     * Terminer une formation : renseigne completedAt sur l'Enrollment.
     * N'est possible qu'une fois tous les chapitres terminés.
     */
    #[Route('/formations/{slug}/terminer', name: 'app_formation_complete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function complete(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        Request $request,
        ChapterProgressRepository $chapterProgress,
    ): Response {
        $this->denyAccessUnlessVisible($formation);

        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('complete_formation'.$formation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $enrollment = $this->enrollments->findOneByUserAndFormation($user, $formation);

        // Garde-fou : pas inscrit, rien à terminer.
        if (null === $enrollment) {
            $this->addFlash('info', 'Tu ne suis pas cette formation.');

            return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
        }

        // Garde-fou : on ne valide que si tous les chapitres sont terminés (idempotent).
        if (null === $enrollment->getCompletedAt()
            && $this->allChaptersCompleted($formation, $chapterProgress->findCompletedChapterIds($enrollment))) {
            $now = new \DateTimeImmutable();
            $enrollment->setCompletedAt($now);
            $enrollment->setLastActivityAt($now);
            $this->em->flush();
        }

        if (null !== $stream = $this->enrollStream($request, $formation, $enrollment)) {
            return $stream;
        }
        $this->addFlash('success', 'Bravo, tu as terminé cette formation.');

        return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
    }

    #[Route('/formations/{slug}/{chapterSlug}', name: 'app_formation_chapter', methods: ['GET'])]
    public function chapter(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        string $chapterSlug,
        ChapterProgressRepository $chapterProgress,
    ): Response {
        $this->denyAccessUnlessVisible($formation);

        $user = $this->getUser();

        // Liste à plat ordonnée par position (cf. OrderBy sur Formation::$chapters)
        // pour retrouver le chapitre courant et ses voisins précédent / suivant.
        $chapters = $formation->getChapters()->getValues();
        $index = null;
        foreach ($chapters as $i => $chapter) {
            if ($chapter->getSlug() === $chapterSlug) {
                $index = $i;
                break;
            }
        }

        if (null === $index) {
            throw $this->createNotFoundException();
        }

        $enrollment = null;
        $completed = false;
        if ($user instanceof User) {
            $enrollment = $this->enrollments->findOneByUserAndFormation($user, $formation);
            if (null !== $enrollment) {
                // Atteindre un chapitre marque le précédent comme terminé (lecture linéaire).
                if ($index > 0
                    && $this->markChapterCompleted($chapterProgress, $enrollment, $chapters[$index - 1], new \DateTimeImmutable())) {
                    $enrollment->setLastActivityAt(new \DateTimeImmutable());
                    $this->em->flush();
                }

                $completed = null !== $chapterProgress->findOneByEnrollmentAndChapter($enrollment, $chapters[$index]);
            }
        }

        return $this->render('formation/chapter.html.twig', [
            'formation' => $formation,
            'enrollment' => $enrollment,
            'chapter' => $chapters[$index],
            'previous' => $chapters[$index - 1] ?? null,
            'next' => $chapters[$index + 1] ?? null,
            'completed' => $completed,
        ]);
    }

    #[Route('/formations/{slug}/{chapterSlug}/complete', name: 'app_formation_chapter_complete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggleChapterComplete(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        string $chapterSlug,
        Request $request,
        ChapterProgressRepository $chapterProgress,
    ): Response {
        $this->denyAccessUnlessVisible($formation);

        /** @var User $user */
        $user = $this->getUser();

        $chapter = null;
        foreach ($formation->getChapters() as $c) {
            if ($c->getSlug() === $chapterSlug) {
                $chapter = $c;
                break;
            }
        }

        if (null === $chapter) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('complete'.$chapter->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $enrollment = $this->enrollments->findOneByUserAndFormation($user, $formation);

        // Garde-fou : on ne suit pas la formation, rien à marquer.
        if (null === $enrollment) {
            $this->addFlash('info', 'Tu ne suis pas cette formation.');

            return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
        }

        $now = new \DateTimeImmutable();
        $progress = $chapterProgress->findOneByEnrollmentAndChapter($enrollment, $chapter);

        // Bascule : si déjà terminé on annule (suppression), sinon on marque.
        if (null !== $progress) {
            $this->em->remove($progress);
            $completed = false;
        } else {
            $this->markChapterCompleted($chapterProgress, $enrollment, $chapter, $now);
            $completed = true;
        }

        $enrollment->setLastActivityAt($now);
        $this->em->flush();

        // Réponse Turbo Stream sans rechargement, ou redirection si Turbo n'est pas là.
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('formation/chapter_complete.stream.html.twig', [
                'formation' => $formation,
                'chapter' => $chapter,
                'completed' => $completed,
            ]);
        }

        return $this->redirectToRoute('app_formation_chapter', [
            'slug' => $formation->getSlug(),
            'chapterSlug' => $chapter->getSlug(),
        ]);
    }

    /**
     * Marque un chapitre comme terminé de façon idempotente.
     * Ne flush pas : l'appelant décide quand persister. Retourne true si une
     * progression a été créée, false si elle existait déjà.
     */
    private function markChapterCompleted(ChapterProgressRepository $chapterProgress, Enrollment $enrollment, Chapter $chapter, \DateTimeImmutable $now): bool
    {
        if (null !== $chapterProgress->findOneByEnrollmentAndChapter($enrollment, $chapter)) {
            return false;
        }

        $this->em->persist(
            (new ChapterProgress())
                ->setEnrollment($enrollment)
                ->setChapter($chapter)
                ->setCompletedAt($now)
        );

        return true;
    }

    /**
     * Tous les chapitres de la formation sont-ils terminés ? Faux s'il n'y a
     * aucun chapitre (rien à terminer).
     *
     * @param list<int> $completedChapterIds
     */
    private function allChaptersCompleted(Formation $formation, array $completedChapterIds): bool
    {
        $total = $formation->getChapters()->count();

        return $total > 0 && \count($completedChapterIds) >= $total;
    }

    /**
     * Réponse Turbo Stream qui met à jour les contrôles « suivre / quitter »
     * (haut et bas de page), ou null si la requête n'est pas une requête Turbo
     * Stream (l'appelant retombe alors sur une redirection classique).
     */
    private function enrollStream(Request $request, Formation $formation, ?Enrollment $enrollment): ?Response
    {
        if (TurboBundle::STREAM_FORMAT !== $request->getPreferredFormat()) {
            return null;
        }

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('formation/enroll.stream.html.twig', [
            'formation' => $formation,
            'enrollment' => $enrollment,
        ]);
    }

    /**
     * Refuse l'accès à une formation non visible par l'utilisateur courant.
     *
     * Un brouillon renvoie 404 pour ne pas révéler son existence ; les autres
     * refus renvoient 403 (qu'un visiteur anonyme verra comme une redirection
     * vers le login via l'entry point form_login).
     */
    private function denyAccessUnlessVisible(Formation $formation): void
    {
        if ($this->isGranted(FormationVoter::VIEW, $formation)) {
            return;
        }

        if (Visibility::DRAFT === $formation->getVisibility()) {
            throw $this->createNotFoundException();
        }

        throw $this->createAccessDeniedException();
    }
}
