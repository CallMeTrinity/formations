<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\RecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RecommendationController extends AbstractController
{
    /**
     * Page dédiée « Recommandations » : la même logique que la section d'accueil,
     * mais avec une liste plus large. Personnalisée selon les préférences, ou
     * repli sur les formations publiques populaires/récentes (issues #24, #25).
     */
    #[Route('/recommandations', name: 'app_recommendations', methods: ['GET'])]
    public function index(RecommendationService $recommendations): Response
    {
        $user = $this->getUser() instanceof User ? $this->getUser() : null;

        return $this->render('recommendation/index.html.twig', [
            'recommendations' => $recommendations->recommendFor(
                $user,
                $this->isGranted('ROLE_USER'),
                $this->isGranted('ROLE_ADMIN'),
                9,
            ),
            'personalized' => $recommendations->isPersonalizedFor($user),
        ]);
    }
}
