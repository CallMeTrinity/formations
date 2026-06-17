<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Enum\Visibility;
use App\Security\Voter\FormationVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FormationController extends AbstractController
{
    #[Route('/formations/{slug}', name: 'app_formation_show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
    ): Response {
        $this->denyAccessUnlessVisible($formation);

        return $this->render('formation/show.html.twig', [
            'formation' => $formation,
        ]);
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
