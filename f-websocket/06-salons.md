# Les salons

[← Chapitre précédent](05-protocole-messages-json.md) · [Sommaire](README.md) · [Chapitre suivant →](07-presence-saisie.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- modéliser des **salons** (*rooms*) pour cloisonner les conversations ;
- diffuser un message **uniquement** aux clients d'un même salon ;
- gérer le fait de **rejoindre** et **changer** de salon ;
- nettoyer correctement quand un client quitte ou se déconnecte.

## Le besoin : ne pas tout mélanger

Pour l'instant, tous les participants partagent une seule grande conversation. Dès qu'un chat sert à
plusieurs sujets ou groupes, il faut des **salons** : des espaces de discussion séparés. Un message
écrit dans `#général` ne doit pas apparaître dans `#dev`.

```text
Salon #général : Alice, Bob          Alice écrit -> Bob seulement
Salon #dev     : Charlie, Diane      Charlie écrit -> Diane seulement
```

L'idée clé : un *broadcast* n'est plus « à tout le monde », mais « à tout le monde **du même salon** ».
On a donc besoin de savoir, pour chaque connexion, **dans quel salon** elle se trouve.

## Modéliser le salon sur la connexion

La façon la plus simple : attacher le salon courant à chaque `socket`, comme on l'a fait pour le pseudo
au chapitre 5. Pour diffuser dans un salon, on parcourt `wss.clients` et on ne garde que ceux dont le
`salon` correspond.

```js
function diffuserDansSalon(salon, objet) {
  const texte = JSON.stringify(objet);
  for (const client of wss.clients) {
    if (client.readyState === WebSocket.OPEN && client.salon === salon) {
      client.send(texte);
    }
  }
}
```

C'est suffisant et lisible tant que le nombre de clients reste raisonnable. On parle des limites et
d'une structure plus efficace à la fin du chapitre.

## Le protocole : rejoindre un salon

On ajoute un type de message `rejoindre` au protocole. Le client annonce le salon qu'il veut rejoindre ;
le serveur l'y place et prévient le salon. On enrichit aussi les messages `chat` pour qu'ils restent
dans le salon de l'expéditeur.

```js
// server.js
import { WebSocketServer, WebSocket } from "ws";

const wss = new WebSocketServer({ port: 8080 });

function diffuserDansSalon(salon, objet) {
  const texte = JSON.stringify(objet);
  for (const client of wss.clients) {
    if (client.readyState === WebSocket.OPEN && client.salon === salon) {
      client.send(texte);
    }
  }
}

wss.on("connection", (socket) => {
  socket.pseudo = "Anonyme";
  socket.salon = "general"; // salon par défaut

  socket.on("message", (data) => {
    let message;
    try {
      message = JSON.parse(data.toString());
    } catch {
      return;
    }

    switch (message.type) {
      case "pseudo":
        socket.pseudo = String(message.pseudo ?? "Anonyme").slice(0, 30);
        break;

      case "rejoindre": {
        const ancien = socket.salon;
        const nouveau = String(message.salon ?? "general").slice(0, 30);
        // On prévient l'ancien salon du départ...
        diffuserDansSalon(ancien, { type: "systeme", texte: `${socket.pseudo} a quitté #${ancien}` });
        socket.salon = nouveau;
        // ...puis le nouveau de l'arrivée.
        diffuserDansSalon(nouveau, { type: "systeme", texte: `${socket.pseudo} a rejoint #${nouveau}` });
        break;
      }

      case "chat":
        diffuserDansSalon(socket.salon, {
          type: "chat",
          salon: socket.salon,
          pseudo: socket.pseudo,
          texte: String(message.texte ?? ""),
          horodatage: Date.now(),
        });
        break;

      default:
        break;
    }
  });

  socket.on("close", () => {
    // On prévient le salon où était le client.
    diffuserDansSalon(socket.salon, { type: "systeme", texte: `${socket.pseudo} a quitté le chat` });
  });
});

console.log("Serveur de chat démarré sur ws://localhost:8080");
```

Remarque l'ordre dans `rejoindre` : on annonce le **départ de l'ancien** salon **avant** de changer
`socket.salon`, puis l'**arrivée dans le nouveau** après. Si tu inverses, les notifications partent dans
le mauvais salon.

> **Attention** — Le moment où tu modifies `socket.salon` est critique. Tout `diffuserDansSalon`
> appelé après le changement vise le nouveau salon ; tout appel avant vise l'ancien. Mélange-les et les
> notifications « X a quitté » / « X a rejoint » se retrouvent dans le mauvais salon.

## Côté client : choisir et changer de salon

On ajoute un champ pour saisir le salon et un bouton pour le rejoindre. À la réception, on n'affiche
plus que ce qui concerne le salon courant (le serveur ne nous envoie déjà que ça, mais on affiche le nom
du salon pour s'y retrouver).

```html
<h1>Chat en direct</h1>
<p>Salon courant : <strong id="salon-actuel">general</strong></p>
<input id="salon" placeholder="Nom du salon" value="general" />
<button id="rejoindre">Rejoindre</button>

<input id="saisie" placeholder="Ton message" />
<button id="envoyer">Envoyer</button>
<ul id="messages"></ul>

<script>
  const socket = new WebSocket("ws://localhost:8080");
  const liste = document.querySelector("#messages");

  socket.addEventListener("open", () => {
    const pseudo = prompt("Ton pseudo ?") || "Anonyme";
    socket.send(JSON.stringify({ type: "pseudo", pseudo }));
    socket.send(JSON.stringify({ type: "rejoindre", salon: "general" }));
  });

  document.querySelector("#rejoindre").addEventListener("click", () => {
    const salon = document.querySelector("#salon").value.trim() || "general";
    socket.send(JSON.stringify({ type: "rejoindre", salon }));
    document.querySelector("#salon-actuel").textContent = salon;
    liste.innerHTML = ""; // on vide l'affichage en changeant de salon
  });

  document.querySelector("#envoyer").addEventListener("click", () => {
    const champ = document.querySelector("#saisie");
    const texte = champ.value.trim();
    if (texte === "") return;
    socket.send(JSON.stringify({ type: "chat", texte }));
    champ.value = "";
  });

  socket.addEventListener("message", (evenement) => {
    let message;
    try { message = JSON.parse(evenement.data); } catch { return; }

    if (message.type === "chat") {
      const heure = new Date(message.horodatage).toLocaleTimeString();
      afficher(`[${heure}] ${message.pseudo} : ${message.texte}`);
    } else if (message.type === "systeme") {
      afficher(`— ${message.texte} —`);
    }
  });

  function afficher(ligne) {
    const li = document.createElement("li");
    li.textContent = ligne;
    liste.appendChild(li);
  }
</script>
```

Teste avec trois onglets : mets deux clients dans `general` et un dans `dev`. Les messages de `general`
n'apparaissent que chez les deux premiers ; ceux de `dev` restent isolés. Change le salon d'un client et
observe les notifications de départ/arrivée dans les bons salons.

> **Astuce** — Vider l'affichage (`liste.innerHTML = ""`) quand on change de salon évite de mélanger
> visuellement les conversations. L'historique des messages du salon rejoint sera traité au chapitre 9.

## Une structure plus efficace : indexer par salon

Parcourir **tous** les clients pour n'en garder qu'une partie fonctionne, mais devient coûteux avec
beaucoup de connexions : pour diffuser à 5 personnes d'un salon, tu balaies peut-être 10 000 connexions.

La solution courante : tenir un **index des salons**, une `Map` qui associe chaque nom de salon à
l'ensemble de ses connexions. Diffuser dans un salon ne parcourt alors que ses membres.

```js
// Map : nom de salon -> Set de sockets
const salons = new Map();

function ajouterAuSalon(socket, salon) {
  if (!salons.has(salon)) salons.set(salon, new Set());
  salons.get(salon).add(socket);
}

function retirerDuSalon(socket, salon) {
  const membres = salons.get(salon);
  if (!membres) return;
  membres.delete(socket);
  if (membres.size === 0) salons.delete(salon); // on nettoie les salons vides
}

function diffuserDansSalon(salon, objet) {
  const membres = salons.get(salon);
  if (!membres) return;
  const texte = JSON.stringify(objet);
  for (const client of membres) {
    if (client.readyState === WebSocket.OPEN) client.send(texte);
  }
}
```

Avec cette structure, rejoindre un salon devient : `retirerDuSalon(socket, socket.salon)`, changer
`socket.salon`, puis `ajouterAuSalon(socket, socket.salon)`. **N'oublie jamais** de retirer le client de
son salon dans l'événement `close`, sinon des connexions mortes s'accumulent dans la `Map` (une fuite
mémoire).

> **À retenir** — Pour quelques dizaines de connexions, parcourir `wss.clients` suffit. Dès que ça
> grossit, **indexe par salon** avec une `Map`. Et dans les deux cas, **nettoie** à la déconnexion :
> tout ce qu'on ajoute (à un Set, une Map) doit être retiré au `close`.

## Résumé

- Un **salon** cloisonne les conversations : un message n'est diffusé qu'aux clients du **même salon**.
- Approche simple : attacher `socket.salon` à chaque connexion et filtrer dans la boucle de diffusion.
- Le type de message **`rejoindre`** fait changer de salon ; l'**ordre** des notifications
  départ/arrivée par rapport au changement de `socket.salon` est crucial.
- Pour passer à l'échelle, **indexer les salons** dans une `Map` (`salon -> Set de sockets`) évite de
  balayer toutes les connexions.
- Quel que soit le choix, **nettoyer à la déconnexion** (`close`) pour éviter les fuites mémoire.

## Exercices

### Exercice 1 — Lister les salons actifs

Ajoute un type de message `salons` : quand un client l'envoie, le serveur lui répond (à lui seul) la
liste des salons qui ont au moins un participant. Utilise la structure `Map` de la fin du chapitre.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : avec la `Map` `salons`, les noms de salons actifs sont simplement ses clés. On les
renvoie à l'expéditeur seul (`socket.send`), pas en *broadcast*.

```js
case "salons": {
  const liste = [...salons.keys()]; // tous les salons non vides
  socket.send(JSON.stringify({ type: "salons", salons: liste }));
  break;
}
```

Comme `retirerDuSalon` supprime les salons devenus vides, la liste ne contient que des salons réellement
actifs. Côté client, tu affiches `message.salons` quand `message.type === "salons"`.

</details>

### Exercice 2 — Empêcher de rejoindre un salon où l'on est déjà

Modifie le traitement de `rejoindre` pour ne rien faire si le client demande à rejoindre le salon où il
se trouve déjà (pas de notification inutile « X a quitté » puis « X a rejoint » le même salon).

<details>
<summary>Voir le corrigé</summary>

**Démarche** : on compare le salon demandé au salon courant et on sort tôt si c'est le même.

```js
case "rejoindre": {
  const nouveau = String(message.salon ?? "general").slice(0, 30);
  if (nouveau === socket.salon) break; // déjà dans ce salon : on ne fait rien

  diffuserDansSalon(socket.salon, { type: "systeme", texte: `${socket.pseudo} a quitté #${socket.salon}` });
  socket.salon = nouveau;
  diffuserDansSalon(nouveau, { type: "systeme", texte: `${socket.pseudo} a rejoint #${nouveau}` });
  break;
}
```

Ce genre de garde (« sortir tôt si l'action n'a pas de sens ») évite du bruit et des bugs. On en mettra
d'autres au chapitre 10 (validation).

</details>

## Quiz

**1.** Qu'est-ce qu'un salon dans un chat ?
- A. Un serveur WebSocket séparé
- B. Un espace de discussion qui cloisonne les messages à ses seuls membres
- C. Un type de message JSON

**2.** Avec l'approche simple, comment diffuse-t-on dans un salon ?
- A. En envoyant à `wss.clients` sans filtre
- B. En parcourant `wss.clients` et en ne gardant que ceux dont `socket.salon` correspond
- C. En ouvrant un nouveau port par salon

**3.** Pourquoi l'ordre des opérations dans `rejoindre` est-il important ?
- A. Pour que les notifications partent dans le bon salon (avant/après le changement de `socket.salon`)
- B. Parce que `JSON.stringify` est lent
- C. Pour économiser de la mémoire

**4.** Quel est l'intérêt d'indexer les salons dans une `Map` ?
- A. Chiffrer les messages
- B. Diffuser sans balayer toutes les connexions, ce qui passe mieux à l'échelle
- C. Éviter d'utiliser `JSON.parse`

<details>
<summary>Voir les réponses</summary>

1. **B** — Un salon cloisonne les messages à ses membres.
2. **B** — On filtre `wss.clients` sur `socket.salon`.
3. **A** — Selon qu'on diffuse avant ou après le changement de `socket.salon`, la notification vise
   l'ancien ou le nouveau salon.
4. **B** — La `Map` évite de parcourir toutes les connexions et améliore la montée en charge.

</details>

## Projet fil rouge

Le chat gère désormais plusieurs **salons** : les conversations sont cloisonnées, on rejoint et on
change de salon, et les notifications arrivent au bon endroit. Tu as aussi vu comment **indexer** les
salons pour tenir la charge. Il manque encore une information attendue de tout chat : savoir **qui est
présent** dans le salon. C'est l'objet du chapitre suivant : la **présence** et l'indicateur « est en
train d'écrire ».

---

[← Chapitre précédent](05-protocole-messages-json.md) · [Sommaire](README.md) · [Chapitre suivant →](07-presence-saisie.md)
