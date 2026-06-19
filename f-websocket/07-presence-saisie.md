# Présence et indicateur de saisie

[← Chapitre précédent](06-salons.md) · [Sommaire](README.md) · [Chapitre suivant →](08-robustesse-reconnexion.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- maintenir et diffuser la **présence** : la liste des participants d'un salon ;
- mettre à jour cette liste à chaque connexion, déconnexion ou changement de salon ;
- implémenter l'indicateur **« est en train d'écrire »** côté serveur et client ;
- gérer l'expiration de cet indicateur avec un *timer* côté client.

## La présence : qui est là ?

La **présence**, c'est la liste des personnes actuellement dans un salon. C'est une attente standard
d'un chat : on veut voir qui peut nous lire. Techniquement, le serveur connaît déjà cette information —
il sait, pour chaque salon, quelles connexions s'y trouvent (chapitre 6). Il suffit de **calculer la
liste des pseudos d'un salon** et de la **diffuser** à ses membres dès qu'elle change.

On part de la structure `Map` du chapitre 6 (`salons : nom -> Set de sockets`). On ajoute une fonction
qui calcule la liste des pseudos d'un salon et l'envoie à tous ses membres :

```js
function diffuserPresence(salon) {
  const membres = salons.get(salon);
  if (!membres) return;

  // On collecte les pseudos des connexions ouvertes du salon.
  const pseudos = [];
  for (const client of membres) {
    if (client.readyState === WebSocket.OPEN) pseudos.push(client.pseudo);
  }

  diffuserDansSalon(salon, { type: "presence", salon, participants: pseudos });
}
```

On ajoute un type de message **`presence`** au protocole : `{ type, salon, participants: [...] }`. Le
serveur l'émet à chaque changement ; le client n'a qu'à afficher la liste reçue.

> **À retenir** — La présence est une **donnée dérivée** : ne la stocke pas séparément, recalcule-la à
> partir de la source de vérité (les membres du salon). Une liste de présence maintenue « à la main » en
> parallèle finit toujours par se désynchroniser de la réalité.

## Quand mettre à jour la présence

La liste change à trois moments. Il faut appeler `diffuserPresence` à chacun :

1. **un client rejoint** un salon → présence du nouveau salon (et de l'ancien s'il vient d'ailleurs) ;
2. **un client se déconnecte** → présence de son salon, **après** l'avoir retiré ;
3. **un client change de pseudo** → présence de son salon (le nom affiché change).

Voici le serveur consolidé du chapitre, avec la présence branchée au bon endroit. On reprend la
structure `Map` du chapitre 6 :

```js
// server.js
import { WebSocketServer, WebSocket } from "ws";

const wss = new WebSocketServer({ port: 8080 });
const salons = new Map(); // nom -> Set de sockets

function ajouterAuSalon(socket, salon) {
  if (!salons.has(salon)) salons.set(salon, new Set());
  salons.get(salon).add(socket);
}
function retirerDuSalon(socket, salon) {
  const membres = salons.get(salon);
  if (!membres) return;
  membres.delete(socket);
  if (membres.size === 0) salons.delete(salon);
}
function diffuserDansSalon(salon, objet) {
  const membres = salons.get(salon);
  if (!membres) return;
  const texte = JSON.stringify(objet);
  for (const client of membres) {
    if (client.readyState === WebSocket.OPEN) client.send(texte);
  }
}
function diffuserPresence(salon) {
  const membres = salons.get(salon);
  if (!membres) return;
  const pseudos = [];
  for (const client of membres) {
    if (client.readyState === WebSocket.OPEN) pseudos.push(client.pseudo);
  }
  diffuserDansSalon(salon, { type: "presence", salon, participants: pseudos });
}

wss.on("connection", (socket) => {
  socket.pseudo = "Anonyme";
  socket.salon = "general";
  ajouterAuSalon(socket, socket.salon);
  diffuserPresence(socket.salon);

  socket.on("message", (data) => {
    let message;
    try { message = JSON.parse(data.toString()); } catch { return; }

    switch (message.type) {
      case "pseudo":
        socket.pseudo = String(message.pseudo ?? "Anonyme").slice(0, 30);
        diffuserPresence(socket.salon); // le nom affiché change
        break;

      case "rejoindre": {
        const nouveau = String(message.salon ?? "general").slice(0, 30);
        if (nouveau === socket.salon) break;
        const ancien = socket.salon;
        retirerDuSalon(socket, ancien);
        diffuserDansSalon(ancien, { type: "systeme", texte: `${socket.pseudo} a quitté #${ancien}` });
        diffuserPresence(ancien);
        socket.salon = nouveau;
        ajouterAuSalon(socket, nouveau);
        diffuserDansSalon(nouveau, { type: "systeme", texte: `${socket.pseudo} a rejoint #${nouveau}` });
        diffuserPresence(nouveau);
        break;
      }

      case "chat":
        diffuserDansSalon(socket.salon, {
          type: "chat", salon: socket.salon, pseudo: socket.pseudo,
          texte: String(message.texte ?? ""), horodatage: Date.now(),
        });
        break;

      case "saisie":
        // On prévient les AUTRES membres que ce client tape (voir plus bas).
        diffuserSaisie(socket);
        break;

      default:
        break;
    }
  });

  socket.on("close", () => {
    const salon = socket.salon;
    retirerDuSalon(socket, salon);
    diffuserDansSalon(salon, { type: "systeme", texte: `${socket.pseudo} a quitté le chat` });
    diffuserPresence(salon); // après retrait
  });
});
```

> **Attention** — À la déconnexion, retire le client de son salon **avant** de diffuser la présence,
> sinon il apparaîtra encore dans la liste. L'ordre « modifier l'état, puis diffuser l'état » est une
> règle générale du temps réel.

## L'indicateur « est en train d'écrire »

C'est le petit « Alice est en train d'écrire… » qui apparaît sous la conversation. Le principe :

- quand un utilisateur tape dans le champ, son client envoie un message `saisie` au serveur ;
- le serveur prévient les **autres** membres du salon (pas l'expéditeur) ;
- chez les autres, l'indicateur s'affiche, puis **disparaît tout seul** après un court délai sans
  nouvelle frappe.

Côté serveur, la diffusion (référencée plus haut sous `diffuserSaisie`) exclut l'expéditeur :

```js
function diffuserSaisie(expediteur) {
  const membres = salons.get(expediteur.salon);
  if (!membres) return;
  const texte = JSON.stringify({ type: "saisie", pseudo: expediteur.pseudo });
  for (const client of membres) {
    // On exclut l'expéditeur : inutile de lui dire qu'il écrit.
    if (client !== expediteur && client.readyState === WebSocket.OPEN) {
      client.send(texte);
    }
  }
}
```

Côté client, deux choses : envoyer `saisie` quand on tape (sans en envoyer un par caractère), et gérer
l'expiration de l'indicateur reçu.

### Envoyer « je tape » sans spammer

Si on envoyait un message `saisie` à chaque touche, on inonderait le serveur. On limite la fréquence
avec un *throttle* (« limiteur de débit ») simple : on n'envoie au plus qu'un `saisie` toutes les 2
secondes.

```js
let dernierEnvoiSaisie = 0;
const champ = document.querySelector("#saisie");

champ.addEventListener("input", () => {
  const maintenant = Date.now();
  if (maintenant - dernierEnvoiSaisie > 2000) { // au plus 1 envoi / 2 s
    socket.send(JSON.stringify({ type: "saisie" }));
    dernierEnvoiSaisie = maintenant;
  }
});
```

### Afficher et faire expirer l'indicateur

Quand on reçoit un `saisie`, on affiche l'indicateur et on (re)lance un *timer* : s'il n'arrive plus de
`saisie` du même utilisateur pendant 3 secondes, on efface. Recevoir un message `chat` de cette personne
efface aussi l'indicateur (elle a fini d'écrire).

```js
const zoneSaisie = document.querySelector("#en-train"); // un <p> vide sous la liste
const timersSaisie = new Map(); // pseudo -> timer

function montrerSaisie(pseudo) {
  // On (ré)arme le timer d'expiration pour ce pseudo.
  if (timersSaisie.has(pseudo)) clearTimeout(timersSaisie.get(pseudo));
  timersSaisie.set(pseudo, setTimeout(() => {
    timersSaisie.delete(pseudo);
    rafraichirSaisie();
  }, 3000));
  rafraichirSaisie();
}

function effacerSaisie(pseudo) {
  if (timersSaisie.has(pseudo)) clearTimeout(timersSaisie.get(pseudo));
  timersSaisie.delete(pseudo);
  rafraichirSaisie();
}

function rafraichirSaisie() {
  const gens = [...timersSaisie.keys()];
  if (gens.length === 0) zoneSaisie.textContent = "";
  else if (gens.length === 1) zoneSaisie.textContent = `${gens[0]} est en train d'écrire…`;
  else zoneSaisie.textContent = `${gens.join(", ")} sont en train d'écrire…`;
}

// Dans le gestionnaire de messages :
socket.addEventListener("message", (evenement) => {
  let message;
  try { message = JSON.parse(evenement.data); } catch { return; }

  if (message.type === "chat") {
    effacerSaisie(message.pseudo); // il a fini d'écrire
    afficherMessage(message);
  } else if (message.type === "saisie") {
    montrerSaisie(message.pseudo);
  } else if (message.type === "presence") {
    afficherPresence(message.participants);
  } else if (message.type === "systeme") {
    afficherSysteme(message.texte);
  }
});
```

> **À retenir** — L'indicateur de saisie doit **expirer tout seul** côté client. Si le réseau coupe au
> mauvais moment, tu ne recevras peut-être jamais le « il a arrêté ». Un *timer* qui efface après
> quelques secondes garantit que l'indicateur ne reste pas bloqué à l'écran.

## Présence et saisie : deux usages du même principe

Remarque que présence et saisie reposent sur la **même mécanique** que tout le reste : un type de
message dédié, diffusé dans le bon salon, traité côté client selon son `type`. Tu n'apprends pas une
nouvelle technologie à chaque fonctionnalité — tu **réutilises** le protocole. C'est le signe d'une
architecture saine.

## Résumé

- La **présence** est la liste des participants d'un salon ; on la **recalcule** depuis les membres du
  salon et on la diffuse via un message `presence` à chaque changement.
- Mets à jour la présence à la **connexion**, à la **déconnexion** (après retrait), au **changement de
  salon** et au **changement de pseudo**.
- Règle d'or du temps réel : **modifier l'état, puis diffuser l'état**.
- L'indicateur **« est en train d'écrire »** : le client envoie `saisie` (avec un *throttle*), le
  serveur prévient les autres membres, le client affiche puis **fait expirer** l'indicateur via un
  *timer*.
- Présence et saisie réutilisent le **même protocole** typé : pas de nouvelle techno, juste de nouveaux
  types de messages.

## Exercices

### Exercice 1 — Compteur de participants

À partir de la présence, affiche en haut du salon le nombre de participants (« 4 personnes dans
#general »). Tu ne dois ajouter aucun nouveau message au protocole.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : le message `presence` contient déjà `participants`. Le compteur est simplement sa
longueur — inutile d'ajouter un type de message.

```js
function afficherPresence(participants) {
  document.querySelector("#liste-presence").textContent = participants.join(", ");
  document.querySelector("#compteur").textContent =
    `${participants.length} personne(s) dans le salon`;
}
```

C'est l'intérêt d'une donnée dérivée bien conçue : une seule source (`participants`) alimente plusieurs
affichages.

</details>

### Exercice 2 — Éviter un indicateur fantôme à la déconnexion

Si Alice tape puis ferme son onglet sans envoyer de message, son « Alice est en train d'écrire… » peut
rester affiché chez les autres jusqu'à expiration du timer. Propose deux façons de régler ça
proprement.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : deux angles, idéalement combinés.

1. **Côté client (déjà en place)** : le *timer* d'expiration de 3 s efface l'indicateur même si on ne
   reçoit jamais d'arrêt. C'est le filet de sécurité minimal.
2. **Côté serveur (mieux)** : quand un client se déconnecte (`close`), envoyer aux autres membres du
   salon un message d'arrêt de saisie pour ce pseudo, afin d'effacer l'indicateur **immédiatement** :

```js
socket.on("close", () => {
  const salon = socket.salon;
  retirerDuSalon(socket, salon);
  // Effacer tout de suite l'éventuel indicateur de saisie de ce client.
  diffuserDansSalon(salon, { type: "saisie-stop", pseudo: socket.pseudo });
  diffuserDansSalon(salon, { type: "systeme", texte: `${socket.pseudo} a quitté le chat` });
  diffuserPresence(salon);
});
```

Côté client, `saisie-stop` appelle `effacerSaisie(message.pseudo)`. On combine ainsi un **filet de
sécurité** (timer) et un **signal explicite** (serveur) — un bon réflexe pour tout indicateur temps
réel.

</details>

## Quiz

**1.** Comment le serveur connaît-il la liste des participants d'un salon ?
- A. Le client la lui envoie
- B. Il la recalcule à partir des membres du salon (donnée dérivée)
- C. Elle est stockée dans le navigateur

**2.** À quel moment NE faut-il PAS oublier de diffuser la présence ?
- A. Uniquement à la connexion
- B. À la connexion, à la déconnexion, au changement de salon et de pseudo
- C. À chaque message de chat

**3.** Pourquoi limiter (*throttle*) l'envoi des messages `saisie` ?
- A. Pour ne pas inonder le serveur avec un message par caractère tapé
- B. Pour chiffrer la saisie
- C. Parce que `send` ne marche qu'une fois par seconde

**4.** Pourquoi l'indicateur de saisie doit-il expirer tout seul côté client ?
- A. Pour économiser de la mémoire serveur
- B. Parce qu'on peut ne jamais recevoir le signal d'arrêt (coupure réseau)
- C. Parce que le protocole l'interdit autrement

<details>
<summary>Voir les réponses</summary>

1. **B** — La présence est une donnée dérivée, recalculée depuis les membres du salon.
2. **B** — Toute modification de la composition ou de l'affichage du salon doit rediffuser la présence.
3. **A** — Sans *throttle*, on enverrait un message par frappe, ce qui saturerait le serveur.
4. **B** — Le signal d'arrêt peut ne jamais arriver ; un timer garantit que l'indicateur disparaît.

</details>

## Projet fil rouge

Le chat affiche maintenant **qui est présent** dans chaque salon et signale **qui est en train
d'écrire**, avec une expiration propre de l'indicateur. Toutes ces fonctionnalités réutilisent le même
protocole typé. Jusqu'ici, on a supposé que la connexion tient. Or, sur le vrai web, elle saute :
Wi-Fi qui coupe, mise en veille, tunnel. Le chapitre suivant rend le client **robuste** : détecter une
coupure et se **reconnecter automatiquement**.

---

[← Chapitre précédent](06-salons.md) · [Sommaire](README.md) · [Chapitre suivant →](08-robustesse-reconnexion.md)
