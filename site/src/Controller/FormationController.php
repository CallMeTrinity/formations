<?php

namespace App\Controller;

use App\Entity\Enrollment;
use App\Entity\Formation;
use App\Entity\User;
use App\Enum\Visibility;
use App\Repository\EnrollmentRepository;
use App\Security\Voter\FormationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    ): Response {
        $this->denyAccessUnlessVisible($formation);

        $enrollment = null;
        $user = $this->getUser();
        if ($user instanceof User) {
            $enrollment = $this->enrollments->findOneByUserAndFormation($user, $formation);
        }

        return $this->render('formation/show.html.twig', [
            'formation' => $formation,
            'enrollment' => $enrollment,
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
        if (null !== $this->enrollments->findOneByUserAndFormation($user, $formation)) {
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
            $this->addFlash('info', 'Tu ne suis pas cette formation.');

            return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
        }

        $this->em->remove($enrollment);
        $this->em->flush();

        $this->addFlash('success', 'Tu as quitté cette formation.');

        return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
    }

    #[Route('/formations/{slug}/{chapterSlug}', name: 'app_formation_chapter', methods: ['GET'])]
    public function chapter(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        string $chapterSlug,
    ): Response {
        $this->denyAccessUnlessVisible($formation);

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

        return $this->render('formation/chapter.html.twig', [
            'formation' => $formation,
            'chapter' => $chapters[$index],
            'previous' => $chapters[$index - 1] ?? null,
            'next' => $chapters[$index + 1] ?? null,
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
