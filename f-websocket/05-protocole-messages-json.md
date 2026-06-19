# Un protocole de messages applicatif

[← Chapitre précédent](04-chat-de-base-broadcast.md) · [Sommaire](README.md) · [Chapitre suivant →](06-salons.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- concevoir un **protocole de messages** en JSON avec un champ `type` ;
- sérialiser et désérialiser les messages (`JSON.stringify` / `JSON.parse`) des deux côtés ;
- gérer plusieurs types de messages (message de chat, notification système, pseudo) ;
- valider un message reçu pour ne pas faire planter le serveur sur une entrée malformée.

## Pourquoi du texte brut ne suffit plus

Jusqu'ici, on envoie des chaînes de caractères nues. Ça marche pour un echo, mais un vrai chat doit
distinguer plusieurs **sortes** de messages :

- un message écrit par un utilisateur (avec son pseudo et l'heure) ;
- une notification système (« Alice a rejoint le chat ») ;
- bientôt : rejoindre un salon, signaler qu'on écrit, etc.

Avec du texte brut, impossible de faire la différence proprement. La solution standard : échanger des
**objets structurés** sérialisés en **JSON** (*JavaScript Object Notation*, le format texte d'échange de
données du web). Chaque message porte un champ **`type`** qui dit de quoi il s'agit, et des champs
spécifiques selon le type.

```json
{ "type": "chat", "pseudo": "Alice", "texte": "Salut !", "horodatage": 1718800000000 }
{ "type": "systeme", "texte": "Bob a rejoint le chat" }
{ "type": "pseudo", "pseudo": "Alice" }
```

> **À retenir** — Un champ **`type`** sur chaque message est la décision d'architecture la plus
> importante de tout protocole temps réel. Il te permet d'ajouter de nouveaux types de messages sans
> jamais casser les anciens : le récepteur regarde le `type` et sait quoi faire.

## Sérialiser et désérialiser

WebSocket ne transporte que du texte (ou du binaire). On convertit donc nos objets en texte avant
d'envoyer, et inversement à la réception :

- **`JSON.stringify(objet)`** : transforme un objet JavaScript en chaîne JSON. À l'**envoi**.
- **`JSON.parse(chaine)`** : transforme une chaîne JSON en objet JavaScript. À la **réception**.

```js
// Envoi : objet -> texte JSON
socket.send(JSON.stringify({ type: "chat", pseudo: "Alice", texte: "Salut !" }));

// Réception : texte JSON -> objet
const message = JSON.parse(data.toString());
console.log(message.type);   // "chat"
console.log(message.texte);  // "Salut !"
```

> **Attention** — `JSON.parse` **lève une exception** si le texte n'est pas du JSON valide. Un client
> bogué ou malveillant peut envoyer n'importe quoi. Si tu fais `JSON.parse` sans précaution dans
> l'événement `message`, **une seule entrée malformée fait planter ton serveur**. On gère ça plus bas.

## Côté serveur : un protocole avec pseudos

On fait évoluer `server.js`. Chaque connexion mémorise le **pseudo** de son utilisateur. On gère deux
types entrants : `pseudo` (l'utilisateur s'annonce) et `chat` (il envoie un message). Le serveur diffuse
des messages `chat` enrichis (pseudo + horodatage) et des messages `systeme`.

```js
// server.js
import { WebSocketServer, WebSocket } from "ws";

const wss = new WebSocketServer({ port: 8080 });

// Diffuse un OBJET à tous les clients (il s'occupe de la sérialisation).
function diffuser(objet) {
  const texte = JSON.stringify(objet);
  for (const client of wss.clients) {
    if (client.readyState === WebSocket.OPEN) {
      client.send(texte);
    }
  }
}

wss.on("connection", (socket) => {
  socket.pseudo = "Anonyme"; // valeur par défaut, attachée à la connexion

  socket.on("message", (data) => {
    let message;
    try {
      message = JSON.parse(data.toString());
    } catch {
      return; // message non-JSON : on l'ignore au lieu de planter
    }

    // On aiguille selon le type de message.
    switch (message.type) {
      case "pseudo": {
        const ancien = socket.pseudo;
        socket.pseudo = String(message.pseudo ?? "Anonyme").slice(0, 30);
        diffuser({ type: "systeme", texte: `${ancien} est désormais ${socket.pseudo}` });
        break;
      }
      case "chat": {
        diffuser({
          type: "chat",
          pseudo: socket.pseudo,
          texte: String(message.texte ?? ""),
          horodatage: Date.now(),
        });
        break;
      }
      default:
        // type inconnu : on ignore (compatibilité ascendante)
        break;
    }
  });

  socket.on("close", () => {
    diffuser({ type: "systeme", texte: `${socket.pseudo} a quitté le chat` });
  });
});

console.log("Serveur de chat démarré sur ws://localhost:8080");
```

Plusieurs points importants :

- **`socket.pseudo`** : on attache des données métier directement à la connexion. Chaque `socket` a son
  propre pseudo. C'est propre et pratique.
- **C'est le serveur qui décide** du pseudo affiché et de l'horodatage. Ne fais jamais confiance au
  client pour ces champs : un client pourrait se faire passer pour un autre. On reparlera de cette
  règle au chapitre 10.
- **`String(...)` et `.slice(0, 30)`** : on force le type texte et on limite la longueur. C'est la base
  de la validation (chapitre 10 pour aller plus loin).

> **À retenir** — Tout ce qui doit être **fiable** (identité de l'expéditeur, heure, numéro de message)
> est décidé **par le serveur**, jamais recopié depuis le message du client. Le client ne fournit que
> ses *intentions* (« je veux envoyer ce texte »).

## Côté client : afficher selon le type

Le client envoie son pseudo à la connexion, puis des messages `chat`. À la réception, il affiche
différemment selon le `type`. Mets à jour `public/index.html` :

```html
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <title>Chat en direct</title>
  </head>
  <body>
    <h1>Chat en direct</h1>
    <input id="saisie" placeholder="Ton message" />
    <button id="envoyer">Envoyer</button>
    <ul id="messages"></ul>

    <script>
      const socket = new WebSocket("ws://localhost:8080");
      const liste = document.querySelector("#messages");
      const saisie = document.querySelector("#saisie");
      const bouton = document.querySelector("#envoyer");

      socket.addEventListener("open", () => {
        // On choisit un pseudo et on l'annonce au serveur.
        const pseudo = prompt("Ton pseudo ?") || "Anonyme";
        socket.send(JSON.stringify({ type: "pseudo", pseudo }));
      });

      socket.addEventListener("message", (evenement) => {
        let message;
        try {
          message = JSON.parse(evenement.data);
        } catch {
          return; // on ignore un message illisible
        }

        if (message.type === "chat") {
          const heure = new Date(message.horodatage).toLocaleTimeString();
          afficher(`[${heure}] ${message.pseudo} : ${message.texte}`);
        } else if (message.type === "systeme") {
          afficher(`— ${message.texte} —`);
        }
      });

      bouton.addEventListener("click", envoyer);
      saisie.addEventListener("keydown", (e) => {
        if (e.key === "Enter") envoyer(); // envoyer avec la touche Entrée
      });

      function envoyer() {
        const texte = saisie.value.trim();
        if (texte === "") return;
        socket.send(JSON.stringify({ type: "chat", texte }));
        saisie.value = "";
      }

      function afficher(ligne) {
        const li = document.createElement("li");
        li.textContent = ligne;
        liste.appendChild(li);
      }
    </script>
  </body>
</html>
```

Teste avec deux onglets, chacun avec un pseudo différent. Tu vois maintenant qui parle, à quelle heure,
et les notifications système s'affichent en retrait :

```text
— Alice est désormais Alice —
[14:32:05] Alice : salut tout le monde
[14:32:11] Bob : hello !
— Bob a quitté le chat —
```

## Pourquoi un format commun des deux côtés

Client et serveur doivent s'accorder sur **la même structure** de messages. C'est ce qu'on appelle un
**protocole applicatif** : un contrat sur les champs (`type`, `pseudo`, `texte`, `horodatage`) et leur
signification. Si l'un envoie `{ type: "chat" }` et que l'autre attend `{ kind: "message" }`, rien ne
marche.

Une bonne habitude, dès qu'un projet grossit : **documenter ce protocole** (même dans un simple
commentaire ou un fichier `protocole.md`). On listera les types disponibles au fur et à mesure : `chat`,
`systeme`, `pseudo`, puis `rejoindre` (chapitre 6), `presence` et `saisie` (chapitre 7), etc.

## Résumé

- On échange des **objets JSON** plutôt que du texte brut, chacun avec un champ **`type`** qui
  l'identifie.
- À l'envoi : **`JSON.stringify`** ; à la réception : **`JSON.parse`**, toujours protégé par un
  `try/catch` pour ne pas planter sur une entrée invalide.
- Le serveur **aiguille** les messages selon leur `type` (un `switch`) et **ignore** les types inconnus
  (compatibilité ascendante).
- Les champs **fiables** (pseudo affiché, horodatage) sont décidés par le **serveur**, pas recopiés du
  client.
- Client et serveur partagent le même **protocole applicatif** : un contrat sur les champs et leur sens.

## Exercices

### Exercice 1 — Un type « ping applicatif »

Ajoute un type de message `horloge` : quand le client l'envoie (sans autre champ), le serveur lui répond
**à lui seul** (pas en *broadcast*) un message `{ type: "horloge", heure: <horodatage serveur> }`.
Affiche l'heure reçue côté client.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : c'est une réponse ciblée à l'expéditeur, donc on utilise `socket.send` (et non
`diffuser`). Côté serveur, on ajoute un `case` :

```js
case "horloge":
  socket.send(JSON.stringify({ type: "horloge", heure: Date.now() }));
  break;
```

Côté client, on déclenche la demande (par exemple à la connexion) et on gère la réponse :

```js
// envoyer la demande
socket.send(JSON.stringify({ type: "horloge" }));

// dans le gestionnaire message :
if (message.type === "horloge") {
  afficher(`Heure serveur : ${new Date(message.heure).toLocaleTimeString()}`);
}
```

Ce schéma requête ciblée / réponse ciblée resservira pour l'authentification (chapitre 10).

</details>

### Exercice 2 — Robustesse aux messages malformés

Vérifie que ton serveur **ne plante pas** si un client envoie : (a) du texte non-JSON (`bonjour`) ;
(b) un JSON sans champ `type` (`{}`) ; (c) un type inconnu (`{ "type": "dance" }`). Explique ce qui
protège le serveur dans chaque cas.

<details>
<summary>Voir le corrigé</summary>

Le serveur du chapitre résiste déjà aux trois cas, grâce à deux protections :

- **(a) texte non-JSON** : `JSON.parse` lève une exception, mais le `try/catch` la capture et fait
  `return`. Le message est ignoré, le serveur continue.
- **(b) JSON sans `type`** : `message.type` vaut `undefined`. Le `switch` ne trouve aucun `case` et
  tombe dans le `default`, qui ne fait rien.
- **(c) type inconnu** : même chose, `default` ignore le message.

Pour le vérifier sans écrire de client spécial, tu peux utiliser la console du navigateur sur la page du
chat :

```js
socket.send("bonjour");                 // (a)
socket.send(JSON.stringify({}));        // (b)
socket.send(JSON.stringify({ type: "dance" })); // (c)
// Le serveur ne plante pas, les autres clients continuent de discuter.
```

C'est exactement l'état d'esprit défensif qu'on généralisera au chapitre 10 : **ne jamais faire
confiance à l'entrée**.

</details>

## Quiz

**1.** À quoi sert le champ `type` dans chaque message ?
- A. À chiffrer le message
- B. À indiquer la nature du message pour savoir comment le traiter
- C. À compresser le message

**2.** Pourquoi entourer `JSON.parse` d'un `try/catch` à la réception ?
- A. Parce que `JSON.parse` est lent
- B. Parce qu'une entrée non-JSON lève une exception qui ferait planter le serveur
- C. Parce que `JSON.parse` ne fonctionne pas dans Node

**3.** Qui doit décider de l'horodatage et du pseudo affichés ?
- A. Le client, qui les met dans son message
- B. Le serveur, qui ne fait pas confiance aux champs du client
- C. Le navigateur, automatiquement

**4.** Que doit faire le serveur face à un `type` qu'il ne connaît pas ?
- A. Planter pour signaler l'erreur
- B. L'ignorer, pour rester compatible avec d'anciens et de nouveaux clients
- C. Le renvoyer à l'expéditeur tel quel

<details>
<summary>Voir les réponses</summary>

1. **B** — Le `type` identifie la nature du message et permet de l'aiguiller.
2. **B** — `JSON.parse` lève une exception sur une entrée invalide ; le `try/catch` protège le serveur.
3. **B** — Les champs fiables sont décidés par le serveur, jamais recopiés du client.
4. **B** — Ignorer les types inconnus garantit la compatibilité ascendante.

</details>

## Projet fil rouge

Le chat parle maintenant un **vrai langage** : des messages JSON typés, avec pseudo et horodatage
décidés par le serveur, et des notifications système distinctes des messages utilisateur. Le tout
résiste aux entrées malformées. Au chapitre suivant, on exploite ce protocole pour ajouter une
fonctionnalité attendue de tout chat : les **salons**, pour discuter par sujet sans tout mélanger.

---

[← Chapitre précédent](04-chat-de-base-broadcast.md) · [Sommaire](README.md) · [Chapitre suivant →](06-salons.md)
