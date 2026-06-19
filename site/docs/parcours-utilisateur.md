# Parcours utilisateur et fonctionnalités

Ce document décrit les flux applicatifs du site : trouver une formation (catalogue, recherche,
filtres), la lire et suivre sa progression, le tableau de bord, les recommandations, le profil et
l'espace admin. Pour les entités sous-jacentes, voir [`modele-de-donnees.md`](modele-de-donnees.md) ;
pour les règles d'accès, [`securite-et-visibilite.md`](securite-et-visibilite.md).

## Carte des routes

| Route | Méthode | Accès | Rôle |
|-------|---------|-------|------|
| `app_home` `/` | GET | public | Accueil + 3 recommandations |
| `app_register` `/register` | GET/POST | anonyme | Inscription |
| `app_login` `/login` | GET/POST | anonyme | Connexion |
| `app_logout` `/logout` | — | connecté | Déconnexion |
| `app_formation_index` `/formations` | GET | public | Catalogue (recherche + filtres) |
| `app_formation_show` `/formations/{slug}` | GET | selon visibilité | Sommaire d'une formation |
| `app_formation_chapter` `/formations/{slug}/{chapterSlug}` | GET | selon visibilité | Lecture d'un chapitre |
| `app_formation_enroll` `…/suivre` | POST | `ROLE_USER` | S'inscrire |
| `app_formation_unenroll` `…/quitter` | POST | `ROLE_USER` | Se désinscrire |
| `app_formation_complete` `…/terminer` | POST | `ROLE_USER` | Marquer la formation terminée |
| `app_formation_restart` `…/recommencer` | POST | `ROLE_USER` | Recommencer une formation terminée |
| `app_formation_chapter_next` `…/{chapterSlug}/suivant` | POST | `ROLE_USER` | Valider + passer au suivant |
| `app_formation_chapter_complete` `…/{chapterSlug}/complete` | POST | `ROLE_USER` | Cocher / décocher un chapitre |
| `app_dashboard` `/mes-formations` | GET | `ROLE_USER` | Tableau de bord |
| `app_recommendations` `/recommandations` | GET | public | Recommandations (liste large) |
| `app_profile` `/profile` | GET/POST | `ROLE_USER` | Profil + mot de passe |
| `app_preferences` `/profile/preferences` | GET/POST | `ROLE_USER` | Préférences de recommandation |
| `app_admin_formations` `/admin` | GET | `ROLE_ADMIN` | Liste admin + stats |
| `app_admin_formation_edit` `/admin/formations/{slug}/editer` | GET/POST | `ROLE_ADMIN` | Métadonnées |
| `app_admin_formation_visibility` `…/visibilite` | POST | `ROLE_ADMIN` | Changer la visibilité |
| `app_admin_resync` `/admin/resync` | POST | `ROLE_ADMIN` | Relancer la sync |

## Catalogue, recherche et filtres

`FormationController::index` rend le catalogue des formations **accessibles à l'appelant** (filtrage
de visibilité, cf. doc dédiée). Trois critères, tous passés en **query string GET** pour rester
partageables et fonctionner sans JavaScript :

- **recherche plein-texte** (`?q=`) : sur le titre, la description et le label des tags
  (`LIKE` insensible à la casse, jokers échappés) ;
- **tags** (`?tags[]=slug`) : une formation portant au moins un des tags sélectionnés ;
- **difficulté** (`?difficulty[]=beginner`) : une des difficultés sélectionnées.

Les critères se combinent en **ET** entre eux, en **OU** à l'intérieur d'un même critère. La
construction de la requête vit dans `FormationRepository::findCatalogue()`. Les tags proposés en
filtre sont limités à ceux des formations visibles par l'appelant.

## Lecture et progression

### Sommaire et lecture

`/formations/{slug}` affiche la présentation, les prérequis, les objectifs, le projet et le sommaire
des chapitres. `/formations/{slug}/{chapterSlug}` affiche un chapitre avec ses voisins
précédent / suivant (déduits de l'ordre `position`).

> **Une requête GET ne marque jamais de progression.** C'est volontaire : le prefetch Turbo au survol
> des liens ferait sinon valider des chapitres « en douce ». La complétion passe toujours par une
> action POST explicite (bouton « Suivant » ou case à cocher).

### S'inscrire et progresser

Le cycle de vie d'un `Enrollment` (un par couple utilisateur / formation) :

1. **Suivre** (`enroll`) — crée l'`Enrollment` (`startedAt`, `lastActivityAt`). Garde-fou anti-doublon
   si déjà inscrit.
2. **Valider un chapitre** — deux chemins :
   - **« Suivant »** (`chapterNext`) : marque le chapitre courant terminé (le quitter implique l'avoir
     lu) puis redirige vers le suivant. Seul chemin de complétion « implicite ».
   - **Case à cocher** (`toggleChapterComplete`) : bascule un chapitre terminé / non terminé.
   Dans les deux cas, on crée ou supprime un `ChapterProgress` et on met à jour `lastActivityAt`.
3. **Terminer** (`complete`) — renseigne `completedAt`, **uniquement si tous les chapitres sont
   validés** (idempotent). Incrémente `completionCount` et fixe `firstCompletedAt` à la première fois.
4. **Recommencer** (`restart`) — efface la progression et le `completedAt` du run en cours, mais
   **conserve `firstCompletedAt` et `completionCount`** (la trace d'historique).
5. **Quitter** (`unenroll`) — supprime l'`Enrollment`. Refusé si la formation a déjà été terminée :
   elle reste alors dans l'historique.

> **Run en cours vs historique** — `completedAt` décrit l'état du run actuel (effaçable par
> « Recommencer »). `firstCompletedAt` et `completionCount` décrivent l'historique cumulé, jamais
> effacés. C'est ce qui permet d'afficher des « étoiles » de complétions répétées au tableau de bord.

### Mises à jour sans rechargement (Turbo)

Plusieurs actions (inscription, désinscription, complétion, bascule de chapitre) répondent en **Turbo
Stream** quand le client le supporte, pour mettre à jour les contrôles de la page sans rechargement
(templates `*.stream.html.twig`). Si Turbo n'est pas là, le contrôleur **retombe sur une redirection
classique** : chaque flux reste fonctionnel sans JavaScript. « Recommencer » fait exception et
redirige toujours franchement, pour que tout le sommaire se rafraîchisse.

## Tableau de bord — « Mes formations »

`DashboardController` (`/mes-formations`, `ROLE_USER`) liste les inscriptions de l'utilisateur,
réparties entre **en cours** et **terminées** (selon `completedAt`), avec leur pourcentage
d'avancement. Le calcul (total des chapitres, chapitres validés, tri par activité récente) est fait
en une requête SQL par `EnrollmentRepository::findWithProgressForUser()`. Une légende d'« étoiles »
apparaît dès qu'au moins une formation a été terminée une fois (`completionCount > 0`).

## Recommandations

`RecommendationService` classe les formations recommandées. L'accueil en affiche 3
(`HomeController`), la page dédiée `/recommandations` en affiche 9 (`RecommendationController`) — même
logique, limite différente.

### Candidates

Formations **accessibles** à l'appelant et **non encore terminées** par lui
(`FormationRepository::findRecommendable()`, exclusion des inscriptions existantes).

### Score (mode personnalisé)

Calculé par `RecommendationService::score()`, méthode pure et testable. Trois signaux :

| Signal | Poids | Détail |
|--------|-------|--------|
| **Tags en commun** | `TAG_WEIGHT = 100` par tag | Signal dominant : prime sur tous les autres réunis |
| **Proximité de niveau** | `+6` exact, `+3` adjacent | Écart entre difficulté de la formation et préférence |
| **Popularité** | `+1` par inscrit, plafonné à `POPULARITY_CAP = 5` | Sert surtout à départager |
| **Fraîcheur** | `+3` (≤ 30 j), `+1` (≤ 90 j) | Bonus de récence (`createdAt`) |

Tri : score décroissant, puis popularité, puis fraîcheur, puis titre (ordre stable et déterministe).

### Repli (non personnalisé)

Si l'utilisateur n'a **aucune préférence exploitable** (pas de tag favori ni de niveau choisi —
toujours le cas d'un visiteur anonyme), on bascule sur un repli : les formations **publiques** les
plus populaires et récentes. En mode personnalisé, le périmètre s'ouvre aux visibilités auxquelles
l'utilisateur a droit (beta si connecté, brouillon si admin). `isPersonalizedFor()` indique au
template lequel des deux modes s'applique.

Les préférences qui pilotent ce score (`preferredTags`, `preferredDifficulty`) se règlent sur
`/profile/preferences`.

## Compte : inscription, connexion, profil

- **Inscription** (`RegistrationController`) : crée un `User`, hashe le mot de passe et **connecte
  automatiquement** le nouvel inscrit. Un utilisateur déjà connecté est redirigé vers l'accueil.
- **Connexion / déconnexion** (`SecurityController`) : `form_login` standard avec CSRF et
  « remember me » (cf. doc sécurité).
- **Profil** (`ProfileController`, `/profile`) : deux formulaires sur la même page — éditer le profil
  (`displayName`) et changer le mot de passe. Après changement de mot de passe, l'utilisateur est
  re-connecté (`Security::login`) pour ne pas perdre sa session.
- **Préférences** (`PreferencesController`, `/profile/preferences`) : tags favoris, niveau préféré,
  objectif hebdomadaire. Crée l'entité `UserPreferences` à la volée si elle n'existe pas encore.

## Espace admin

`FormationAdminController`, préfixe `/admin`, réservé à `ROLE_ADMIN` — doublement protégé : attribut
`#[IsGranted('ROLE_ADMIN')]` sur le contrôleur **et** règle `access_control` sur `^/admin`.

| Action | Route | Effet |
|--------|-------|-------|
| **Liste + stats** | `index` | Toutes les formations (brouillons compris), avec statut éditorial, visibilité, et stats d'inscriptions / complétions par formation |
| **Éditer les métadonnées** | `edit` | Formulaire `AdminFormationType` : `status`, `difficulty`, `estimatedMinutes`, `tags`. Ce sont les champs **admin** préservés par la sync |
| **Changer la visibilité** | `visibility` | Accès rapide depuis la liste. Effet immédiat (le voter et les requêtes relisent la visibilité à chaque accès) |
| **Resynchroniser** | `resync` | Relance `FormationSyncService::sync()` et affiche le compte-rendu (cf. [`synchronisation-contenu.md`](synchronisation-contenu.md)) |

Les stats viennent de `EnrollmentRepository::statsByFormation()` : nombre d'inscrits, et nombre de
formations menées à terme au moins une fois (`firstCompletedAt` non nul, donc insensible à un
« Recommencer »).

> Toutes les actions admin qui modifient l'état (visibilité, resync) sont en **POST protégé par un
> jeton CSRF**.
