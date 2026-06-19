# Documentation du site

Documentation technique de l'application Symfony (`site/`) qui importe les formations markdown du
dépôt parent pour les consulter, suivre sa progression et recevoir des recommandations.

Pour installer et lancer le site, voir le [`README` du site](../README.md). Pour la partie contenu
(rédaction des formations en markdown), voir les [consignes](../../consignes/) à la racine du dépôt.

## Sommaire

| Document | Ce qu'on y trouve |
|----------|-------------------|
| [`modele-de-donnees.md`](modele-de-donnees.md) | Entités Doctrine, énumérations, relations, invariant de sync. Le point de départ. |
| [`synchronisation-contenu.md`](synchronisation-contenu.md) | La sync markdown → BDD : commande `app:formations:sync`, parsers, rendu HTML, resync admin. |
| [`parcours-utilisateur.md`](parcours-utilisateur.md) | Les flux applicatifs : catalogue et recherche, lecture et progression, tableau de bord, recommandations, espace admin. |
| [`securite-et-visibilite.md`](securite-et-visibilite.md) | Comptes, rôles, firewall, `FormationVoter`, filtrage par visibilité, protection CSRF. |

## Repères rapides

- **Source de vérité du contenu** : le markdown du dépôt parent. La base n'est qu'une projection,
  reconstruite par la sync. Voir l'[invariant de sync](synchronisation-contenu.md#invariant-de-sync).
- **Trois groupes d'entités** : contenu (`Formation` → `Chapter` → `Section`, `Tag`), comptes
  (`User`, `UserPreferences`), progression (`Enrollment` → `ChapterProgress`).
- **Design** : piloté par des tokens exportés de Claude Design, jamais de CSS écrit à la main. Voir
  la section Design du [`README` du site](../README.md#design).
- **Langue** : français, tutoiement côté contenu, aucun emoji. Cette doc suit la même convention.
