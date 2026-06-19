# Introduction : le temps réel sur le web

[Sommaire](README.md) · [Chapitre suivant →](02-protocole-websocket.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer pourquoi le web « classique » (HTTP) ne suffit pas pour le temps réel ;
- citer les techniques utilisées avant WebSocket (*polling*, *long-polling*, SSE) et leurs limites ;
- dire ce qu'apporte WebSocket et quand l'utiliser ;
- installer l'environnement et créer le squelette du projet fil rouge (le chat).

## Le web classique : le client demande, le serveur répond

Quand tu ouvres une page web, il se passe toujours la même chose : ton navigateur envoie une
**requête** au serveur, le serveur renvoie une **réponse**, et la connexion se referme. C'est le modèle
**requête/réponse** du protocole *HTTP* (*HyperText Transfer Protocol*, le protocole du web).

```text
Navigateur  ──── requête ───►  Serveur
Navigateur  ◄─── réponse ────  Serveur
            (connexion fermée)
```

Ce modèle a une conséquence simple mais lourde : **le serveur ne peut jamais parler en premier**. Il ne
répond que lorsqu'on lui demande quelque chose. Tant que le navigateur ne redemande rien, le serveur
reste muet, même s'il a une information toute fraîche à transmettre.

Pour une page d'article de blog, aucun problème : tu charges la page, tu la lis, c'est fini. Mais
imagine un **chat**. Quelqu'un t'écrit un message. Comment ton navigateur l'apprend-il ? Avec HTTP seul,
il ne l'apprend pas — sauf s'il redemande au serveur « du nouveau ? ». Et c'est exactement le bricolage
qu'on a dû inventer avant WebSocket.

## Les bricolages d'avant : polling, long-polling, SSE

### Le polling (interrogation régulière)

L'idée la plus simple : le client redemande au serveur, à intervalle régulier, s'il y a du nouveau.
C'est le *polling* (« interroger en boucle »).

```js
// Polling : on demande au serveur toutes les 2 secondes s'il y a des messages.
setInterval(async () => {
  const reponse = await fetch("/api/messages");
  const messages = await reponse.json();
  afficher(messages);
}, 2000);
```

Ça marche, mais c'est mauvais sur tous les plans :

- **Lent** : si un message arrive juste après une requête, l'utilisateur attend jusqu'à 2 secondes.
- **Gaspilleur** : la plupart des requêtes ne ramènent rien. Tu envoies des requêtes pour rien, 24h/24,
  pour chaque client connecté.
- **Insoluble** : si tu réduis l'intervalle pour gagner en réactivité, tu exploses la charge serveur.

### Le long-polling

Variante plus maligne : le client envoie une requête, et le serveur **ne répond pas tout de suite**. Il
garde la requête ouverte et n'y répond qu'au moment où il a quelque chose à dire (un nouveau message).
Dès qu'il a répondu, le client en renvoie une autre aussitôt.

C'est plus réactif que le polling, mais ça reste un détournement de HTTP : une requête = une seule
réponse, puis il faut tout recommencer. La communication reste **à sens unique** (le client demande,
le serveur répond) et chaque échange recrée une requête.

### Les Server-Sent Events (SSE)

Les *Server-Sent Events* (« événements envoyés par le serveur ») sont un vrai standard : le serveur
garde une connexion ouverte et **pousse** des messages vers le client quand il veut. C'est efficace et
simple. Mais SSE a une limite de conception : c'est **unidirectionnel**, du serveur vers le client
seulement. Le client ne peut pas envoyer de données par ce canal ; il doit utiliser des requêtes HTTP
classiques à côté.

Pour un chat, ça coince : on veut **les deux sens** sur un seul canal — recevoir les messages des
autres *et* envoyer les siens.

> **À retenir** — Toutes ces techniques contournent une limite de HTTP : le serveur ne peut pas parler
> spontanément, et la communication n'est pas vraiment bidirectionnelle. WebSocket supprime la limite
> au lieu de la contourner.

## Ce qu'apporte WebSocket

**WebSocket** est un protocole qui établit une **connexion permanente et bidirectionnelle** entre le
client et le serveur. Une fois la connexion ouverte :

- elle **reste ouverte** (pas de réouverture à chaque message) ;
- **les deux côtés peuvent envoyer** des messages à tout moment, dans les deux sens ;
- chaque message est **léger** (pas de réenvoi des en-têtes HTTP à chaque fois).

```text
            handshake (une fois)
Navigateur  ◄══════════════════►  Serveur
            connexion permanente
Navigateur  ───── message ─────►  Serveur   (le client envoie)
Navigateur  ◄──── message ──────  Serveur   (le serveur pousse, quand il veut)
Navigateur  ◄──── message ──────  Serveur
```

C'est exactement ce qu'il faut pour un chat, mais aussi pour : notifications instantanées, jeux en
ligne, édition collaborative (type Google Docs), tableaux de bord temps réel, suivi de livraison,
cours de bourse, etc.

WebSocket n'est pas magique pour autant. Il a un coût (une connexion ouverte par client, qu'il faut
gérer) et n'est pas adapté à tout. On verra au [chapitre 12](12-conclusion.md) quand préférer SSE ou de
simples requêtes HTTP. Pour l'instant, retiens la règle : **dès qu'il faut du bidirectionnel temps réel,
WebSocket est le bon outil.**

> **Attention** — WebSocket ne remplace pas HTTP. Une application réelle utilise les deux : HTTP pour
> charger la page, les images, les requêtes classiques ; WebSocket pour le flux temps réel. D'ailleurs,
> une connexion WebSocket **commence** par une requête HTTP (on verra ça au chapitre 2).

## Mise en place de l'environnement

On va construire le chat avec **Node.js** côté serveur et l'**API WebSocket native du navigateur** côté
client. Node.js est un environnement qui exécute du JavaScript en dehors du navigateur ; c'est ce qui te
permet d'écrire un serveur en JavaScript.

### Vérifier Node.js

Ouvre un terminal et vérifie ta version :

```bash
node --version
# Sortie attendue : v20.x.x ou plus récent
```

Si la commande échoue ou affiche une version inférieure à 20, installe Node.js depuis
[nodejs.org](https://nodejs.org) (version *LTS*, *Long Term Support*). L'installeur fournit aussi `npm`,
le gestionnaire de paquets de Node, qu'on utilisera pour ajouter des bibliothèques.

```bash
npm --version
# Sortie attendue : 10.x.x ou plus récent
```

### Créer le squelette du projet

On crée un dossier pour le projet et on l'initialise comme projet Node :

```bash
mkdir chat-direct
cd chat-direct
npm init -y          # crée un package.json par défaut
```

Le fichier `package.json` est la carte d'identité du projet : il liste ses dépendances et ses scripts.
On va dire à Node qu'on utilise la syntaxe **moderne des modules** (`import`/`export` plutôt que
`require`). Ouvre `package.json` et ajoute la ligne `"type": "module"` :

```json
{
  "name": "chat-direct",
  "version": "1.0.0",
  "type": "module",
  "main": "index.js"
}
```

> **Astuce** — `"type": "module"` te permet d'écrire `import { WebSocketServer } from "ws"` plutôt que
> l'ancienne syntaxe `const { WebSocketServer } = require("ws")`. On utilisera la syntaxe moderne dans
> toute la formation.

On crée enfin l'arborescence de départ. Pour l'instant, deux fichiers vides suffisent : le serveur et
la page du client.

```bash
touch server.js          # le serveur WebSocket (Node)
mkdir public
touch public/index.html  # la page du chat (navigateur)
```

On ne code rien tout de suite : le but de ce chapitre est de comprendre **pourquoi** WebSocket existe et
de poser le terrain. On écrira le premier serveur et le premier client au
[chapitre 3](03-premier-aller-retour.md), une fois le protocole compris.

## Résumé

- HTTP suit un modèle **requête/réponse** : le serveur ne parle jamais en premier, ce qui empêche le
  temps réel naturel.
- Avant WebSocket, on contournait ça avec le **polling** (gaspilleur), le **long-polling** (un seul
  échange à la fois) et les **SSE** (efficaces mais unidirectionnels).
- **WebSocket** ouvre une connexion **permanente et bidirectionnelle** : les deux côtés s'envoient des
  messages quand ils veulent, sur un canal léger.
- C'est l'outil des fonctionnalités **temps réel bidirectionnelles** : chat, notifications, jeux,
  collaboration.
- WebSocket **complète** HTTP, il ne le remplace pas.
- Notre projet utilisera **Node.js** + la bibliothèque `ws` côté serveur, et l'**API WebSocket native**
  côté navigateur.

## Exercices

### Exercice 1 — Repérer les cas d'usage

Pour chacune des fonctionnalités suivantes, dis si WebSocket est pertinent ou si HTTP classique suffit,
et pourquoi : (a) afficher la fiche d'un produit ; (b) un fil de discussion en direct ; (c) un compteur
de spectateurs d'un live qui se met à jour tout seul ; (d) télécharger une facture en PDF.

<details>
<summary>Voir le corrigé</summary>

La question à se poser : **le serveur a-t-il besoin de pousser des données spontanément, dans les deux
sens ?**

- **(a) Fiche produit** : HTTP suffit. C'est une requête ponctuelle, le contenu ne change pas en
  permanence. Pas besoin de connexion permanente.
- **(b) Discussion en direct** : WebSocket. C'est le cas typique : chacun envoie des messages et reçoit
  ceux des autres, en temps réel, dans les deux sens.
- **(c) Compteur de spectateurs** : ici c'est unidirectionnel (le serveur pousse, le client ne fait que
  recevoir). **SSE** suffirait, mais WebSocket fonctionne aussi. Le polling serait gaspilleur.
- **(d) Télécharger un PDF** : HTTP. Une requête, une réponse, c'est exactement le modèle requête/réponse.

</details>

### Exercice 2 — Mesurer le coût du polling

Un chat a 1 000 utilisateurs connectés. Chaque client fait du polling toutes les 2 secondes. Combien de
requêtes le serveur reçoit-il par minute ? Et combien d'entre elles sont « utiles » si, en moyenne, il
n'y a qu'un nouveau message toutes les 10 secondes pour l'ensemble du chat ?

<details>
<summary>Voir le corrigé</summary>

**Démarche** : on compte les requêtes, puis on estime celles qui ramènent vraiment quelque chose.

- Chaque client : 1 requête toutes les 2 s = 30 requêtes/minute.
- Pour 1 000 clients : 30 × 1 000 = **30 000 requêtes/minute**.
- Messages réellement nouveaux : 1 toutes les 10 s = 6/minute.

Sur 30 000 requêtes par minute, **6 au plus** apportent une nouveauté. Plus de 99,9 % du trafic est du
pur gaspillage : connexions ouvertes, en-têtes HTTP transmis, serveur sollicité — pour rien. C'est tout
le problème que WebSocket résout, avec une seule connexion ouverte par client et un message envoyé
uniquement quand il y a vraiment quelque chose à dire.

</details>

## Quiz

**1.** Pourquoi HTTP classique est-il mal adapté à un chat ?
- A. HTTP est trop lent pour transporter du texte
- B. Avec HTTP, le serveur ne peut pas envoyer de données sans qu'on les lui demande
- C. HTTP ne sait pas transmettre de JSON

**2.** Quelle est la principale limite des *Server-Sent Events* (SSE) pour un chat ?
- A. Ils ne fonctionnent que dans Chrome
- B. Ils sont unidirectionnels (serveur vers client seulement)
- C. Ils ferment la connexion après chaque message

**3.** Qu'est-ce qui caractérise une connexion WebSocket ?
- A. Elle est permanente et bidirectionnelle
- B. Elle se rouvre à chaque message envoyé
- C. Elle remplace complètement HTTP dans une application

**4.** Pourquoi le *polling* est-il un mauvais choix à grande échelle ?
- A. Il ne fonctionne pas derrière un pare-feu
- B. Il génère énormément de requêtes inutiles qui chargent le serveur
- C. Il ne permet pas d'envoyer du JSON

<details>
<summary>Voir les réponses</summary>

1. **B** — Le modèle requête/réponse interdit au serveur de parler en premier.
2. **B** — SSE ne va que du serveur vers le client ; le client doit utiliser HTTP à côté pour envoyer.
3. **A** — C'est sa définition : une connexion ouverte en continu, utilisable dans les deux sens.
4. **B** — La grande majorité des requêtes ne ramènent rien et saturent inutilement le serveur.

</details>

## Projet fil rouge

Premier jalon : l'environnement est prêt. Tu as :

- vérifié **Node.js 20+** et **npm** ;
- créé le dossier `chat-direct/` initialisé avec `npm init -y` et `"type": "module"` ;
- créé les fichiers de départ `server.js` et `public/index.html` (encore vides).

Au chapitre suivant, on plonge dans le **protocole** WebSocket pour comprendre ce qui se passe quand
une connexion s'établit. On écrira le premier code fonctionnel au chapitre 3.

---

[Sommaire](README.md) · [Chapitre suivant →](02-protocole-websocket.md)
