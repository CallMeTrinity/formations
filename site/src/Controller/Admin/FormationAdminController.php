<?php

namespace App\Controller\Admin;

use App\Entity\Formation;
use App\Enum\Visibility;
use App\Form\AdminFormationType;
use App\Repository\EnrollmentRepository;
use App\Repository\FormationRepository;
use App\Service\FormationSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace admin des formations : liste, édition des métadonnées, visibilité,
 * et resynchronisation du contenu. Réservé à ROLE_ADMIN (doublé par une règle
 * access_control sur ^/admin).
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class FormationAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Dashboard : la liste de toutes les formations (brouillons compris) avec
     * leur statut éditorial et leur visibilité.
     */
    #[Route('', name: 'app_admin_formations', methods: ['GET'])]
    public function index(FormationRepository $formations, EnrollmentRepository $enrollments): Response
    {
        return $this->render('admin/formation/index.html.twig', [
            'formations' => $formations->findAllForAdmin(),
            'stats' => $enrollments->statsByFormation(),
            'visibilities' => Visibility::cases(),
        ]);
    }

    /**
     * Édition des métadonnées admin (statut, difficulté, durée, tags). Ces champs
     * sont préservés par la sync (cf. FormationSyncService).
     */
    #[Route('/formations/{slug}/editer', name: 'app_admin_formation_edit', methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        Request $request,
    ): Response {
        $form = $this->createForm(AdminFormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', sprintf('Métadonnées de « %s » mises à jour.', $formation->getTitle()));

            return $this->redirectToRoute('app_admin_formations');
        }

        return $this->render('admin/formation/edit.html.twig', [
            'formation' => $formation,
            'form' => $form,
        ]);
    }

    /**
     * Change la visibilité d'une formation. L'effet est immédiat : le
     * FormationVoter et les requêtes de liste relisent la visibilité à chaque
     * accès.
     */
    #[Route('/formations/{slug}/visibilite', name: 'app_admin_formation_visibility', methods: ['POST'])]
    public function visibility(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        Request $request,
    ): Response {
        if (!$this->isCsrfTokenValid('admin_visibility'.$formation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $visibility = Visibility::tryFrom((string) $request->request->get('visibility'));
        if (null !== $visibility) {
            $formation->setVisibility($visibility);
            $this->em->flush();
            $this->addFlash('success', sprintf('« %s » est désormais en %s.', $formation->getTitle(), $visibility->label()));
        }

        return $this->redirectToRoute('app_admin_formations');
    }

    /**
     * Relance la synchronisation markdown → base et affiche un compte-rendu.
     */
    #[Route('/resync', name: 'app_admin_resync', methods: ['POST'])]
    public function resync(Request $request, FormationSyncService $sync): Response
    {
        if (!$this->isCsrfTokenValid('admin_resync', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $report = $sync->sync();

        $this->addFlash('success', sprintf(
            'Synchronisation terminée : %d créée(s), %d mise(s) à jour, %d chapitre(s).',
            $report->created,
            $report->updated,
            $report->chaptersCount,
        ));
        foreach ($report->warnings as $warning) {
            $this->addFlash('error', $warning);
        }

        return $this->redirectToRoute('app_admin_formations');
    }
}
