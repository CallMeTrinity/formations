<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Enum\Visibility;
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
        // Garde minimale en attendant le FormationVoter (#18) : un brouillon n'est pas consultable.
        if (Visibility::DRAFT === $formation->getVisibility()) {
            throw $this->createNotFoundException();
        }

        return $this->render('formation/show.html.twig', [
            'formation' => $formation,
        ]);
    }

    #[Route('/formations/{slug}/{chapterSlug}', name: 'app_formation_chapter', methods: ['GET'])]
    public function chapter(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        string $chapterSlug,
    ): Response {
        // Même garde minimale que show() en attendant le FormationVoter (#18).
        if (Visibility::DRAFT === $formation->getVisibility()) {
            throw $this->createNotFoundException();
        }

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
}
