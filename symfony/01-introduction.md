# Introduction : qu'est-ce que Symfony, installation, premier projet

[Sommaire](README.md) · [Chapitre suivant →](02-anatomie-projet.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est un **framework** et pourquoi Symfony en est un incontournable ;
- comprendre le **modèle MVC** sur lequel repose une application web ;
- installer les trois outils indispensables : **PHP 8.4**, **Composer** et l'**outil Symfony** ;
- créer ton premier projet et le faire tourner sur ton ordinateur ;
- ouvrir la page d'accueil par défaut dans ton navigateur.

## Qu'est-ce que Symfony

Imagine que tu doives construire un site web qui répond à des adresses (URL), affiche des pages,
enregistre des données dans une base, gère des comptes utilisateurs et des formulaires. Tout cela
demande énormément de code répétitif et délicat : router les URL vers le bon morceau de programme,
sécuriser les mots de passe, valider les formulaires, parler à la base de données… Ce code-là, on
l'appelle la **plomberie** : indispensable, mais sans valeur propre pour ton projet.

Un **framework** (« cadre de travail ») est une boîte à outils qui fournit cette plomberie une fois
pour toutes, avec une **structure** et des **conventions**. Tu ne réinventes plus la roue : tu
branches ton code métier dans un cadre éprouvé. **Symfony** est le framework PHP de référence pour
cela. Concrètement, il t'apporte :

- un **routeur** qui dirige chaque URL vers le bon bout de code ;
- un moteur de **templates** (Twig) pour générer le HTML proprement ;
- une couche **base de données** (Doctrine) pour stocker et récupérer des données ;
- des outils de **formulaires**, de **sécurité**, de **tests**, et bien plus.

> **À retenir** — Symfony ne fait pas ton travail à ta place : il t'enlève le travail répétitif pour
> que tu te concentres sur ce qui rend ton application unique.

Symfony est partout : de nombreux sites et API d'entreprise tournent dessus, et d'autres grands
projets (Laravel, Drupal, parties de Magento) réutilisent ses **composants**. Apprendre Symfony,
c'est apprendre une compétence durable et très demandée.

### Le modèle MVC

Symfony, comme la plupart des frameworks web, organise le code selon le patron **MVC**, pour
*Model-View-Controller* (Modèle-Vue-Contrôleur). C'est une façon de séparer les responsabilités :

- le **Modèle** (*Model*) représente les **données** et les règles métier (par exemple : un article
  de blog, et le fait qu'un article a un titre obligatoire) ;
- la **Vue** (*View*) est l'**affichage** : le HTML envoyé au navigateur ;
- le **Contrôleur** (*Controller*) est le **chef d'orchestre** : il reçoit la requête, demande les
  données au modèle, et choisit la vue qui produira la réponse.

Le cycle d'une page web dans Symfony tient en une phrase : une **requête** arrive sur une **URL**, le
**routeur** la dirige vers un **contrôleur**, qui prépare des **données** et rend une **vue**,
laquelle devient la **réponse** envoyée au navigateur.

```text
Requête  ──►  Routeur  ──►  Contrôleur  ──►  Vue (Twig)  ──►  Réponse
(URL)                          │
                               ▼
                            Modèle (données)
```

On reviendra en détail sur chaque maillon. Pour l'instant, retiens ce trajet : c'est la colonne
vertébrale de tout ce que tu vas faire.

## Installer les outils

Pour développer avec Symfony 8, il te faut trois choses. On les installe une par une.

### 1. PHP 8.4 ou supérieur

**PHP** est le langage dans lequel Symfony est écrit (et dans lequel tu vas coder). Symfony 8 exige
**PHP 8.4 minimum** : il s'appuie sur des fonctionnalités récentes du langage. Vérifie d'abord si tu
as déjà une version suffisante :

```bash
$ php --version
PHP 8.4.3 (cli) (built: ...)
```

Si la commande est introuvable ou affiche une version inférieure à 8.4, installe-la :

- **Linux (Debian/Ubuntu)** : via le dépôt PHP courant, par exemple
  `sudo apt install php8.4-cli php8.4-xml php8.4-mbstring`.
- **macOS** : avec [Homebrew](https://brew.sh), `brew install php`.
- **Windows** : le plus simple est d'installer PHP via le gestionnaire de paquets
  [Scoop](https://scoop.sh) (`scoop install php`) ou de télécharger PHP depuis le site officiel.

Symfony a besoin de quelques **extensions** PHP (Ctype, iconv, PCRE, Session, SimpleXML, Tokenizer),
presque toujours présentes par défaut. On vérifiera tout ça automatiquement plus bas.

### 2. Composer

**Composer** est le **gestionnaire de dépendances** de PHP : c'est lui qui télécharge Symfony et les
bibliothèques dont ton projet a besoin, dans les bonnes versions. C'est l'équivalent de `npm` pour
Node.js. Installe-le depuis [getcomposer.org](https://getcomposer.org/download/), puis vérifie :

```bash
$ composer --version
Composer version 2.8.0 ...
```

### 3. L'outil Symfony (Symfony CLI)

La **Symfony CLI** (*Command Line Interface*, « interface en ligne de commande ») est un outil
pratique qui simplifie la création de projets, intègre un serveur web de développement, et vérifie
ton environnement. Installe-le :

```bash
# Linux et macOS
$ curl -sS https://get.symfony.com/cli/installer | bash
```

Sous **Windows**, installe-le via Scoop (`scoop install symfony-cli`) ou télécharge l'exécutable
depuis [symfony.com/download](https://symfony.com/download). Vérifie ensuite :

```bash
$ symfony version
Symfony CLI version 5.x.x ...
```

> **Attention** — Ne confonds pas les numéros de version. La **Symfony CLI** (l'outil, version 5.x)
> est différente du **framework Symfony** (version 8) que tu vas installer dans ton projet. Ce sont
> deux logiciels distincts.

### Vérifier que tout est prêt

La Symfony CLI sait contrôler que ta machine remplit toutes les conditions. Lance :

```bash
$ symfony check:requirements
```

Tu obtiens un rapport ligne par ligne. S'il se termine par un message du type « Your system is ready
to run Symfony projects », tu peux continuer. Sinon, il t'indique précisément ce qui manque (une
extension, une version de PHP) : corrige avant d'aller plus loin.

## Créer ton premier projet

Place-toi dans le dossier où tu ranges tes projets, puis crée une nouvelle application web. On la
nomme `blog` : c'est le projet fil rouge de toute la formation.

```bash
$ symfony new blog --webapp
```

Décortiquons cette commande :

- `symfony new` crée un nouveau projet à partir d'un squelette.
- `blog` est le nom du projet (et du dossier créé).
- `--webapp` demande la version **complète**, avec tous les paquets utiles à une application web
  (Twig, formulaires, base de données, sécurité…). Sans cette option, tu obtiendrais un squelette
  minimal auquel il faudrait ajouter chaque brique à la main.

Par défaut, `symfony new` installe la **dernière version stable** de Symfony — au moment de cette
formation, Symfony 8. La commande télécharge tout (cela prend une à deux minutes) et initialise même
un dépôt Git pour toi.

> **Astuce** — Pour figer explicitement la version, tu peux écrire
> `symfony new blog --webapp --version=8.0`. Pour partir d'un squelette minimal (utile quand tu sais
> exactement ce dont tu as besoin), retire `--webapp`.

Entre ensuite dans le dossier du projet :

```bash
$ cd blog
```

## Lancer le serveur local

Pour voir ton site dans un navigateur, il faut un **serveur web** qui exécute PHP. La Symfony CLI en
fournit un, conçu pour le développement. Lance-le :

```bash
$ symfony server:start
```

Le terminal reste occupé par le serveur et affiche les requêtes au fur et à mesure. Il t'indique
l'adresse, en général :

```text
[OK] Web server listening
     https://127.0.0.1:8000
```

Ouvre cette adresse (`https://127.0.0.1:8000`) dans ton navigateur : tu vois la **page d'accueil par
défaut** de Symfony, avec le message de bienvenue et le numéro de version. Bravo, ton application
tourne.

> **Astuce** — Pour lancer le serveur **en arrière-plan** et récupérer ton terminal, utilise
> `symfony serve -d` (`-d` pour *daemon*). Tu l'arrêteras avec `symfony server:stop`.

Pour stopper un serveur lancé au premier plan, reviens dans son terminal et appuie sur `Ctrl` + `C`.

> **Attention** — L'adresse est en **https** avec un certificat local. Au premier lancement, ton
> navigateur peut afficher un avertissement de sécurité : c'est normal en développement. La commande
> `symfony server:ca:install` installe le certificat local pour faire disparaître l'avertissement.

## Résumé

- Un **framework** fournit la plomberie et une structure ; **Symfony** est le framework PHP de
  référence.
- Une application Symfony suit le modèle **MVC** : **Requête → Routeur → Contrôleur → Vue →
  Réponse**, le contrôleur s'appuyant sur le **modèle** pour les données.
- Trois outils à installer : **PHP 8.4+**, **Composer** (dépendances) et la **Symfony CLI** (outil +
  serveur de dev). `symfony check:requirements` vérifie que tout est prêt.
- On crée un projet web complet avec `symfony new blog --webapp`, et on le lance avec
  `symfony server:start` (ou `symfony serve -d` en arrière-plan).
- La page d'accueil par défaut s'ouvre sur `https://127.0.0.1:8000`.

## Exercices

### Exercice 1 — Vérifier son environnement

Sans encore créer de projet, vérifie que ta machine est prête pour Symfony 8 : affiche la version de
PHP, celle de Composer, celle de la Symfony CLI, puis lance le contrôle complet des prérequis.

<details>
<summary>Voir le corrigé</summary>

La démarche : on interroge chaque outil installé, puis on lance le vérificateur intégré.

```bash
$ php --version          # doit afficher 8.4 ou plus
$ composer --version
$ symfony version
$ symfony check:requirements
```

Si `symfony check:requirements` se termine par « Your system is ready to run Symfony projects », tout
est bon. Sinon, lis les lignes en erreur : elles disent exactement quoi corriger (souvent une
extension PHP manquante ou une version trop ancienne).

</details>

### Exercice 2 — Créer et lancer un projet jetable

Crée un projet de test nommé `essai` (en version complète), lance son serveur, ouvre la page
d'accueil dans ton navigateur, puis arrête le serveur.

<details>
<summary>Voir le corrigé</summary>

La démarche : `symfony new` pour créer, `cd` pour entrer, `serve` pour lancer.

```bash
$ symfony new essai --webapp
$ cd essai
$ symfony serve -d
```

Ouvre `https://127.0.0.1:8000` : la page de bienvenue Symfony s'affiche. Pour arrêter le serveur
lancé en arrière-plan :

```bash
$ symfony server:stop
```

Tu peux ensuite supprimer le dossier `essai` : c'était juste pour t'entraîner. Le vrai projet de la
formation est `blog`.

</details>

## Quiz

**1.** À quoi sert un framework comme Symfony ?
- A. À écrire le code métier à ta place automatiquement
- B. À fournir la plomberie et une structure pour que tu te concentres sur ton code métier
- C. À remplacer le langage PHP

**2.** Dans le modèle MVC, quel élément reçoit la requête et orchestre la réponse ?
- A. La vue
- B. Le modèle
- C. Le contrôleur

**3.** Quelle version minimale de PHP exige Symfony 8 ?
- A. PHP 7.4
- B. PHP 8.0
- C. PHP 8.4

**4.** Quelle commande crée un nouveau projet web complet nommé `blog` ?
- A. `composer blog --webapp`
- B. `symfony new blog --webapp`
- C. `php new blog`

<details>
<summary>Voir les réponses</summary>

1. **B** — Le framework prend en charge la plomberie répétitive ; ton code métier reste à ta charge.
2. **C** — Le contrôleur reçoit la requête, sollicite le modèle et choisit la vue.
3. **C** — Symfony 8 requiert PHP 8.4 ou supérieur.
4. **B** — `symfony new` crée le projet ; `--webapp` installe la version complète.

</details>

## Projet fil rouge

C'est le démarrage du blog que tu vas construire pendant toute la formation.

1. Crée le projet : `symfony new blog --webapp`.
2. Entre dedans : `cd blog`.
3. Lance le serveur et ouvre `https://127.0.0.1:8000` pour vérifier que la page de bienvenue
   s'affiche.

Garde ce projet : on l'enrichira à chaque chapitre. Au chapitre suivant, on va explorer l'**intérieur
du dossier `blog`** pour comprendre où va chaque type de code.

---

[Sommaire](README.md) · [Chapitre suivant →](02-anatomie-projet.md)
