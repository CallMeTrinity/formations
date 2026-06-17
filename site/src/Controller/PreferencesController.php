<?php

namespace App\Controller;

use App\Entity\UserPreferences;
use App\Form\UserPreferencesFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PreferencesController extends AbstractController
{
    #[Route('/profile/preferences', name: 'app_preferences')]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        $preferences = $user->getPreferences();

        if (null === $preferences) {
            $preferences = new UserPreferences();
            $user->setPreferences($preferences);
        }

        $form = $this->createForm(UserPreferencesFormType::class, $preferences);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($preferences); // not useful but more understandable
            $em->flush();
            $this->addFlash('success', 'Vos préférences ont été mises à jour avec succès.');

            return $this->redirectToRoute('app_preferences');
        }

        return $this->render('preferences/edit.html.twig', [
            'preferencesForm' => $form,
        ]);
    }
}
