<?php

namespace App\Controller\Admin;

use App\Entity\Formation;
use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Gestion des tags d'une formation depuis l'espace admin. La sélection et la
 * création se font dans une turbo frame : effet immédiat, sans passer par le
 * bouton « Enregistrer » des métadonnées. Réservé à ROLE_ADMIN.
 */
#[Route('/admin/formations/{slug}/tags')]
#[IsGranted('ROLE_ADMIN')]
final class TagAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TagRepository $tags,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Enregistre la sélection de tags de la formation à partir des cases cochées
     * et renvoie la frame ré-affichée.
     */
    #[Route('', name: 'app_admin_formation_tags', methods: ['POST'])]
    public function update(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        Request $request,
    ): Response {
        if (!$this->isCsrfTokenValid('admin_tags'.$formation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $ids = array_map('intval', $request->request->all('tags'));
        $selected = [] === $ids ? [] : $this->tags->findBy(['id' => $ids]);

        foreach ($formation->getTags() as $current) {
            if (!\in_array($current, $selected, true)) {
                $formation->removeTag($current);
            }
        }
        foreach ($selected as $tag) {
            $formation->addTag($tag);
        }

        $this->em->flush();

        return $this->renderFrame($formation);
    }

    /**
     * Crée un tag (s'il n'existe pas déjà, dédoublonné par slug) et l'ajoute à la
     * formation. Si un tag équivalent existe, on l'ajoute simplement plutôt que
     * d'en créer un doublon. Renvoie la frame ré-affichée.
     */
    #[Route('/create', name: 'app_admin_formation_tag_create', methods: ['POST'])]
    public function create(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        Request $request,
    ): Response {
        if (!$this->isCsrfTokenValid('admin_tag_create', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $label = trim((string) $request->request->get('label'));
        $message = null;
        $error = null;

        if ('' === $label) {
            $error = 'Donne un libellé au tag.';
        } else {
            $slug = $this->slugger->slug($label)->lower()->toString();

            if ('' === $slug) {
                $error = 'Ce libellé ne donne pas de tag valide.';
            } else {
                $existing = $this->tags->findOneBy(['slug' => $slug]);

                if (null !== $existing) {
                    $formation->addTag($existing);
                    $message = sprintf('« %s » existait déjà : ajouté à la formation.', $existing->getLabel());
                } else {
                    $tag = (new Tag())->setLabel($label)->setSlug($slug);
                    $this->em->persist($tag);
                    $formation->addTag($tag);
                    $message = sprintf('Tag « %s » créé et ajouté.', $label);
                }

                $this->em->flush();
            }
        }

        return $this->renderFrame($formation, $message, $error);
    }

    /**
     * Supprime un tag définitivement : il est retiré de toutes les formations et
     * de toutes les préférences utilisateur (nettoyage du côté propriétaire des
     * relations avant suppression), puis la frame est ré-affichée.
     */
    #[Route('/{id}/delete', name: 'app_admin_formation_tag_delete', methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['slug' => 'slug'])] Formation $formation,
        Tag $tag,
        Request $request,
    ): Response {
        if (!$this->isCsrfTokenValid('admin_tag_delete', (string) $request->request->get('_delete_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $label = (string) $tag->getLabel();

        foreach ($tag->getFormations() as $linked) {
            $linked->removeTag($tag);
        }
        foreach ($tag->getUserPreferences() as $preferences) {
            $preferences->removePreferredTag($tag);
        }

        $this->em->remove($tag);
        $this->em->flush();

        return $this->renderFrame($formation, sprintf('Tag « %s » supprimé.', $label));
    }

    private function renderFrame(Formation $formation, ?string $message = null, ?string $error = null): Response
    {
        return $this->render('admin/formation/_tags_frame.html.twig', [
            'formation' => $formation,
            'all_tags' => $this->tags->findAllOrdered(),
            'message' => $message,
            'error' => $error,
        ]);
    }
}
