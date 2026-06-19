# Robustesse côté client : reconnexion et heartbeat

[← Chapitre précédent](07-presence-saisie.md) · [Sommaire](README.md) · [Chapitre suivant →](09-historique-persistance.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- comprendre pourquoi une connexion WebSocket saute en conditions réelles ;
- implémenter une **reconnexion automatique** côté client avec *backoff* exponentiel ;
- mettre en file d'attente les messages envoyés pendant une coupure ;
- détecter les connexions mortes côté serveur avec un *heartbeat* `ping`/`pong`.

## Une connexion, ça casse

Jusqu'ici, on a supposé une connexion stable. Dans la vraie vie, elle saute souvent :

- l'utilisateur passe du Wi-Fi à la 4G, ou traverse un tunnel ;
- l'ordinateur se met en veille puis se réveille ;
- un *proxy* ou un pare-feu ferme les connexions inactives au bout de quelques minutes ;
- le serveur redémarre (déploiement).

Quand ça arrive, l'événement `close` se déclenche côté client. Avec notre code actuel, le chat reste
alors muet pour toujours : aucun message ne passe plus, sans que l'utilisateur comprenne pourquoi. Un
chat digne de ce nom **se reconnecte tout seul**.

> **À retenir** — Ne considère jamais une connexion WebSocket comme acquise. La question n'est pas
> *si* elle va sauter, mais *quand*. La robustesse côté client n'est pas une finition optionnelle, c'est
> une partie du travail.

## Reconnexion automatique

L'idée : quand `close` se déclenche, on attend un peu, puis on rouvre une connexion. On encapsule tout
ça dans une petite fonction `connecter` qu'on peut rappeler.

Une erreur de débutant serait de se reconnecter **immédiatement** et en boucle. Si le serveur est tombé,
des milliers de clients qui le martèlent toutes les 10 ms l'achèvent. La bonne pratique est le **backoff
exponentiel** : on attend de plus en plus longtemps entre les tentatives (1 s, 2 s, 4 s, 8 s…), avec un
plafond.

```js
let socket;
let delaiReconnexion = 1000;        // on commence à 1 s
const delaiMax = 30000;             // plafond à 30 s

function connecter() {
  socket = new WebSocket("ws://localhost:8080");

  socket.addEventListener("open", () => {
    console.log("Connecté");
    delaiReconnexion = 1000;        // on remet le délai à zéro après un succès
    // ... (réenvoi de l'identité, voir plus bas)
  });

  socket.addEventListener("message", gererMessage);

  socket.addEventListener("close", () => {
    console.log(`Déconnecté, nouvelle tentative dans ${delaiReconnexion} ms`);
    setTimeout(connecter, delaiReconnexion);
    // backoff : on double le délai pour la prochaine fois, jusqu'au plafond
    delaiReconnexion = Math.min(delaiReconnexion * 2, delaiMax);
  });

  // "error" est suivi d'un "close" : on laisse close gérer la reconnexion.
  socket.addEventListener("error", () => socket.close());
}

connecter();
```

Points clés :

- on **réinitialise** `delaiReconnexion` à 1 s dans `open` : après une reconnexion réussie, on repart
  d'un délai court ;
- on **double** le délai à chaque échec, plafonné à 30 s, pour ne pas surcharger un serveur en
  difficulté ;
- on gère la reconnexion **uniquement dans `close`** : comme `error` est toujours suivi d'un `close`, on
  centralise la logique à un seul endroit.

> **Astuce** — En production, ajoute un peu de hasard au délai (*jitter*) :
> `delai * (0.5 + Math.random())`. Sinon, après une panne serveur, tous les clients se reconnectent
> pile au même instant et créent un pic de charge (l'« effet de troupeau »).

## Rétablir l'état après reconnexion

Se reconnecter ne suffit pas : le serveur, lui, a **tout oublié** de ce client (nouveau `socket`, pas de
pseudo, salon par défaut). Après chaque reconnexion, le client doit **réannoncer son état** : son pseudo
et son salon. C'est pour ça qu'on garde ces informations dans des variables côté client.

```js
let pseudo = "Anonyme";
let salon = "general";

socket.addEventListener("open", () => {
  delaiReconnexion = 1000;
  // On rétablit notre identité auprès du serveur, qui nous a oubliés.
  socket.send(JSON.stringify({ type: "pseudo", pseudo }));
  socket.send(JSON.stringify({ type: "rejoindre", salon }));
});
```

> **À retenir** — Le serveur ne « retrouve » pas un client après reconnexion : c'est une **nouvelle
> connexion** pour lui. Tout l'état nécessaire (pseudo, salon, et plus tard le jeton d'authentification)
> doit être **conservé côté client** et **réenvoyé** à l'ouverture. On verra au chapitre 9 comment
> récupérer aussi les messages manqués.

## Ne pas perdre les messages envoyés hors ligne

Si l'utilisateur tape un message pendant une coupure, `socket.send` échoue (la connexion n'est pas
`OPEN`). Plutôt que de perdre le message, on le met dans une **file d'attente** et on la vide à la
reconnexion.

```js
const fileAttente = []; // messages en attente d'envoi

function envoyer(objet) {
  const texte = JSON.stringify(objet);
  if (socket.readyState === WebSocket.OPEN) {
    socket.send(texte);
  } else {
    fileAttente.push(texte); // hors ligne : on garde pour plus tard
  }
}

// À la reconnexion, on vide la file dans l'ordre.
socket.addEventListener("open", () => {
  delaiReconnexion = 1000;
  socket.send(JSON.stringify({ type: "pseudo", pseudo }));
  socket.send(JSON.stringify({ type: "rejoindre", salon }));
  while (fileAttente.length > 0) {
    socket.send(fileAttente.shift());
  }
});
```

On teste facilement : ouvre le chat, **coupe le serveur** (`Ctrl+C` dans son terminal), tape un message
(il part en file d'attente, l'UI peut l'indiquer), **relance le serveur** (`node server.js`). À la
reconnexion, le pseudo et le salon sont rétablis, et le message en attente part.

> **Attention** — Vérifie toujours `socket.readyState === WebSocket.OPEN` avant un `send` côté client.
> Appeler `send` sur une connexion en `CONNECTING` ou `CLOSED` lève une exception. La file d'attente
> transforme ce cas d'erreur en comportement attendu.

## Côté serveur : détecter les connexions mortes (heartbeat)

Symétriquement, le serveur doit repérer les clients qui ont disparu **sans** fermeture propre (câble
arraché, mobile éteint). Pour lui, la connexion reste « ouverte » alors qu'il n'y a plus personne au
bout. Ces connexions zombies consomment de la mémoire et faussent la présence.

La parade est le *heartbeat* vu au chapitre 2 : le serveur envoie un `ping` régulier ; un client vivant
répond automatiquement par un `pong`. La bibliothèque `ws` gère l'envoi du `ping` et la réception du
`pong` pour toi — il te reste à marquer les connexions vivantes et à fermer les autres.

```js
// Marquer chaque connexion comme vivante et écouter ses pong.
wss.on("connection", (socket) => {
  socket.estVivant = true;
  socket.on("pong", () => { socket.estVivant = true; }); // pong reçu : il est vivant
  // ... (reste du code : pseudo, salon, message, close)
});

// Toutes les 30 s, on vérifie chaque connexion.
const intervalle = setInterval(() => {
  for (const socket of wss.clients) {
    if (socket.estVivant === false) {
      socket.terminate(); // pas de pong depuis le dernier tour : on coupe
      continue;
    }
    socket.estVivant = false; // on remet à false...
    socket.ping();            // ...et on envoie un ping ; le pong le repassera à true
  }
}, 30000);

// Important : on arrête l'intervalle si le serveur s'arrête.
wss.on("close", () => clearInterval(intervalle));
```

Le mécanisme, tour par tour :

1. on passe `estVivant` à `false` et on envoie un `ping` ;
2. un client vivant répond `pong`, ce qui repasse `estVivant` à `true` ;
3. au tour suivant, si `estVivant` est resté `false`, c'est qu'aucun `pong` n'est revenu : la connexion
   est morte, on la **`terminate()`** (fermeture immédiate, sans négociation).

`terminate()` déclenche l'événement `close` de cette connexion, donc tout ton nettoyage (retrait du
salon, mise à jour de la présence du chapitre 7) s'exécute normalement. Le *heartbeat* maintient ainsi
la liste des connexions — et la présence — fidèle à la réalité.

> **À retenir** — `socket.close()` ferme proprement (négociation) ; `socket.terminate()` coupe net.
> Pour une connexion qu'on juge morte (pas de `pong`), `terminate()` est le bon choix : inutile de
> négocier avec quelqu'un qui ne répond plus.

## Résumé

- Une connexion WebSocket **saute** en conditions réelles ; le client doit se **reconnecter
  automatiquement**.
- La reconnexion utilise un **backoff exponentiel** (1 s, 2 s, 4 s… plafonné), réinitialisé après un
  succès ; ajoute du *jitter* en production.
- Après reconnexion, le serveur a **tout oublié** : le client **réannonce** son pseudo et son salon
  (état conservé côté client).
- Les messages tapés **hors ligne** sont mis en **file d'attente** et envoyés à la reconnexion ;
  vérifie toujours `readyState === OPEN` avant `send`.
- Côté serveur, un **heartbeat** `ping`/`pong` détecte les connexions mortes et les `terminate()`, ce
  qui garde la présence exacte.

## Exercices

### Exercice 1 — Indiquer l'état de connexion à l'utilisateur

Affiche un bandeau « Reconnexion en cours… » quand la connexion est perdue, et fais-le disparaître à la
reconnexion. Utilise les événements `open` et `close`.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : on a déjà des gestionnaires `open` et `close` ; il suffit d'y piloter l'affichage d'un
élément de statut.

```html
<div id="statut"></div>
```

```js
const statut = document.querySelector("#statut");

socket.addEventListener("open",  () => { statut.textContent = ""; });
socket.addEventListener("close", () => { statut.textContent = "Reconnexion en cours…"; });
```

Informer l'utilisateur de l'état réseau est un élément d'expérience à part entière : il comprend
pourquoi ses messages partent en file d'attente plutôt que de croire à un bug.

</details>

### Exercice 2 — Comprendre le backoff

Avec un délai initial de 1 s, un doublement à chaque échec et un plafond de 30 s, écris la suite des
délais d'attente pour les 8 premières tentatives ratées. Pourquoi plafonner ?

<details>
<summary>Voir le corrigé</summary>

**Démarche** : on part de 1000 ms et on double, en s'arrêtant au plafond de 30 000 ms.

```text
Tentative : 1     2     3     4      5      6      7      8
Délai     : 1000  2000  4000  8000   16000  30000  30000  30000  (ms)
```

À la 6ᵉ tentative, le doublement (32 000) dépasse le plafond : on reste à 30 000.

**Pourquoi plafonner ?** Sans plafond, le délai exploserait (1 minute, puis 2, 4…). L'utilisateur
attendrait des minutes avant une nouvelle tentative alors que le réseau est peut-être déjà revenu. Le
plafond garantit qu'on retente au moins toutes les 30 s, tout en évitant de marteler un serveur en
panne. Le *jitter* évoqué dans le chapitre éviterait en plus que tous les clients retentent au même
instant.

</details>

## Quiz

**1.** Pourquoi utiliser un *backoff exponentiel* pour la reconnexion ?
- A. Pour reconnecter le plus vite possible, en boucle serrée
- B. Pour ne pas marteler un serveur en panne en espaçant les tentatives
- C. Parce que `setTimeout` l'impose

**2.** Que doit faire le client juste après une reconnexion réussie ?
- A. Rien, le serveur restaure tout
- B. Réannoncer son pseudo et son salon, car le serveur l'a oublié
- C. Recharger la page

**3.** À quoi sert la file d'attente côté client ?
- A. À chiffrer les messages
- B. À ne pas perdre les messages tapés pendant une coupure
- C. À limiter la fréquence d'envoi

**4.** Dans le heartbeat serveur, que fait `terminate()` ?
- A. Ferme la connexion proprement avec négociation
- B. Coupe net une connexion jugée morte (pas de pong reçu)
- C. Envoie un message d'erreur au client

<details>
<summary>Voir les réponses</summary>

1. **B** — Le backoff espace les tentatives pour ne pas achever un serveur déjà en difficulté.
2. **B** — Le serveur traite une reconnexion comme une nouvelle connexion ; le client doit réannoncer
   son état.
3. **B** — La file d'attente conserve les messages hors ligne pour les envoyer à la reconnexion.
4. **B** — `terminate()` coupe immédiatement une connexion morte, sans négociation.

</details>

## Projet fil rouge

Le chat est maintenant **robuste** : il survit aux coupures, se reconnecte tout seul avec un backoff
raisonnable, réannonce l'identité de l'utilisateur, n'oublie pas les messages tapés hors ligne, et le
serveur élimine les connexions mortes pour garder une présence exacte. Reste un manque : à la connexion,
un nouvel arrivant voit un chat **vide**, et après une reconnexion il a raté des messages. Le chapitre
suivant ajoute la **persistance** de l'historique pour restituer les derniers messages.

---

[← Chapitre précédent](07-presence-saisie.md) · [Sommaire](README.md) · [Chapitre suivant →](09-historique-persistance.md)
