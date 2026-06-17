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
}
