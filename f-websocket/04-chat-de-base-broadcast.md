# Le chat de base : diffuser à tous

[← Chapitre précédent](03-premier-aller-retour.md) · [Sommaire](README.md) · [Chapitre suivant →](05-protocole-messages-json.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- diffuser un message reçu à **tous** les clients connectés (*broadcast*) ;
- parcourir l'ensemble des connexions avec `wss.clients` ;
- vérifier l'état d'une connexion avant d'y envoyer un message ;
- transformer l'echo du chapitre 3 en un vrai chat multi-utilisateurs.

## D'un echo à un chat

Au chapitre 3, le serveur renvoyait le message à son seul expéditeur. Un chat fait l'inverse : quand
quelqu'un écrit, **tout le monde** doit voir le message. C'est ça, le *broadcast* (« diffusion ») : un
message reçu d'un client est renvoyé à tous les clients connectés.

```text
            ┌──────────► Alice
Bob ──msg──►│ Serveur ──► Bob
            └──────────► Charlie
```

La bibliothèque `ws` garde la liste de toutes les connexions ouvertes dans **`wss.clients`**. C'est un
*Set* (un ensemble) qu'on peut parcourir. Pour diffuser, il suffit de parcourir cet ensemble et
d'envoyer le message à chaque connexion.

## Diffuser à tout le monde

On modifie `server.js`. Au lieu de répondre à `socket`, on parcourt `wss.clients` :

```js
// server.js
import { WebSocketServer } from "ws";

const wss = new WebSocketServer({ port: 8080 });

wss.on("connection", (socket) => {
  console.log("Un client s'est connecté");

  socket.on("message", (data) => {
    const texte = data.toString();

    // On diffuse le message à TOUS les clients connectés.
    for (const client of wss.clients) {
      client.send(texte);
    }
  });

  socket.on("close", () => {
    console.log("Un client s'est déconnecté");
  });
});

console.log("Serveur de chat démarré sur ws://localhost:8080");
```

Teste-le : ouvre **deux onglets** sur `http://localhost:3000`. Écris dans l'un, le message apparaît dans
les deux. Tu as un chat. Ouvre un troisième onglet : il reçoit aussi. C'est toute la puissance du
*broadcast* — le serveur pousse spontanément vers chaque client.

> **Astuce** — Pour tester un chat, ouvre plusieurs onglets, ou mieux, deux navigateurs différents
> côte à côte. Tu visualises immédiatement le temps réel : un message tapé à gauche surgit à droite.

## Vérifier l'état avant d'envoyer

Le code ci-dessus a un défaut subtil. Entre le moment où un client apparaît dans `wss.clients` et le
moment où on lui envoie, sa connexion peut être **en train de se fermer**. Envoyer sur une connexion qui
n'est pas `OPEN` peut lever une erreur et faire planter la boucle — donc empêcher les autres clients de
recevoir le message.

On vérifie donc l'état de chaque connexion avant d'envoyer. La bibliothèque `ws` expose les constantes
d'état (`OPEN`, `CONNECTING`, etc.) et chaque socket a un `readyState` (vu au chapitre 2) :

```js
import { WebSocketServer, WebSocket } from "ws";

const wss = new WebSocketServer({ port: 8080 });

wss.on("connection", (socket) => {
  socket.on("message", (data) => {
    const texte = data.toString();

    for (const client of wss.clients) {
      // On n'envoie qu'aux connexions réellement ouvertes.
      if (client.readyState === WebSocket.OPEN) {
        client.send(texte);
      }
    }
  });
});
```

> **À retenir** — Avant tout `send` dans une boucle de diffusion, vérifie
> `client.readyState === WebSocket.OPEN`. C'est une protection systématique contre les connexions
> mortes ou en cours de fermeture, et ça évite qu'une connexion défaillante casse la diffusion pour les
> autres.

## Faut-il renvoyer le message à l'expéditeur ?

Dans notre boucle, l'expéditeur reçoit **aussi** son propre message (il fait partie de `wss.clients`).
Deux écoles :

- **Le renvoyer** (ce qu'on fait) : l'expéditeur voit son message s'afficher exactement comme les
  autres, une fois confirmé par le serveur. Simple et cohérent.
- **L'exclure** : on affiche le message localement dès l'envoi côté client, et on diffuse seulement aux
  *autres*. Plus réactif en apparence, mais plus délicat (risque de doublons, d'ordre incohérent).

Pour exclure l'expéditeur, on compare chaque client au `socket` courant :

```js
for (const client of wss.clients) {
  // "client !== socket" : on saute l'expéditeur.
  if (client !== socket && client.readyState === WebSocket.OPEN) {
    client.send(texte);
  }
}
```

On garde l'approche **« renvoyer à tous »** dans le projet : c'est plus simple, et l'affichage reste
cohérent car c'est toujours le serveur qui fait foi sur l'ordre des messages. On affinera quand on
ajoutera les pseudos (chapitre 5) et l'historique (chapitre 9).

## Un mot sur l'isolation des clients

Remarque un point important d'architecture : la **liste des clients** (`wss.clients`) vit au niveau du
**serveur**, pas d'une connexion. Chaque `socket` ne connaît que lui-même ; c'est le serveur qui
orchestre la diffusion vers l'ensemble. Cette idée — un état partagé côté serveur, des connexions
individuelles qui s'y réfèrent — est au cœur de tout ce qui suit : salons (chapitre 6), présence
(chapitre 7), historique (chapitre 9).

> **Attention** — Ne stocke jamais « la liste des autres clients » dans chaque connexion. Tu finirais
> avec des copies incohérentes. L'état partagé (qui est connecté, quels salons existent…) appartient au
> serveur, en un seul exemplaire.

## Résumé

- Un **chat** diffuse chaque message reçu à **tous** les clients : c'est le *broadcast*.
- `ws` maintient l'ensemble des connexions dans **`wss.clients`** ; on le parcourt pour diffuser.
- Avant chaque `send`, vérifier **`client.readyState === WebSocket.OPEN`** pour ignorer les connexions
  non ouvertes.
- On peut **inclure ou exclure** l'expéditeur ; inclure est plus simple et garde un affichage cohérent.
- L'**état partagé** (liste des clients) appartient au **serveur**, pas aux connexions individuelles.

## Exercices

### Exercice 1 — Annoncer les arrivées et départs

Fais en sorte que, lorsqu'un client se connecte, le serveur diffuse à tout le monde le message
« Un participant a rejoint le chat », et « Un participant a quitté le chat » lorsqu'il se déconnecte.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : on a besoin de diffuser depuis plusieurs endroits (message reçu, connexion, déconnexion).
On extrait donc une fonction `diffuser` réutilisable, puis on l'appelle dans `connection` et `close`.

```js
import { WebSocketServer, WebSocket } from "ws";

const wss = new WebSocketServer({ port: 8080 });

function diffuser(texte) {
  for (const client of wss.clients) {
    if (client.readyState === WebSocket.OPEN) {
      client.send(texte);
    }
  }
}

wss.on("connection", (socket) => {
  diffuser("Un participant a rejoint le chat");

  socket.on("message", (data) => {
    diffuser(data.toString());
  });

  socket.on("close", () => {
    diffuser("Un participant a quitté le chat");
  });
});
```

Teste avec deux onglets : ouvre le second, le premier affiche l'arrivée ; ferme-le, le premier affiche
le départ.

</details>

### Exercice 2 — Compter les connectés

À chaque connexion et déconnexion, diffuse à tout le monde le nombre de participants actuellement
connectés (par exemple « 3 participants en ligne »).

<details>
<summary>Voir le corrigé</summary>

**Démarche** : le nombre de connectés, c'est tout simplement la taille de `wss.clients`, accessible via
`wss.clients.size` (c'est un *Set*). Attention au moment de la déconnexion : l'événement `close` se
déclenche, mais le client peut encore figurer dans le *Set* à cet instant. Pour un compte fiable, on
diffuse juste après, ou on filtre sur l'état `OPEN`.

```js
function compterEnLigne() {
  let n = 0;
  for (const client of wss.clients) {
    if (client.readyState === WebSocket.OPEN) n++;
  }
  return n;
}

wss.on("connection", (socket) => {
  diffuser(`${compterEnLigne()} participants en ligne`);

  socket.on("close", () => {
    // À ce stade, ce socket n'est plus OPEN : compterEnLigne() ne le compte pas.
    diffuser(`${compterEnLigne()} participants en ligne`);
  });
});
```

On voit déjà poindre la notion de **présence**, qu'on traitera proprement au chapitre 7.

</details>

## Quiz

**1.** Où `ws` stocke-t-il l'ensemble des connexions ouvertes ?
- A. `socket.clients`
- B. `wss.clients`
- C. `WebSocket.all`

**2.** Pourquoi vérifier `client.readyState === WebSocket.OPEN` avant d'envoyer ?
- A. Pour chiffrer le message
- B. Pour éviter d'envoyer sur une connexion non ouverte et casser la diffusion
- C. Pour accélérer l'envoi

**3.** Qu'est-ce que le *broadcast* dans un chat ?
- A. Renvoyer le message uniquement à son expéditeur
- B. Diffuser le message à tous les clients connectés
- C. Stocker le message dans une base de données

**4.** Où doit vivre l'état partagé (liste des connectés) ?
- A. Dupliqué dans chaque connexion client
- B. Au niveau du serveur, en un seul exemplaire
- C. Dans le navigateur de chaque utilisateur

<details>
<summary>Voir les réponses</summary>

1. **B** — `wss.clients` est le *Set* des connexions, au niveau du serveur.
2. **B** — Envoyer sur une connexion non `OPEN` peut lever une erreur et interrompre la boucle de
   diffusion.
3. **B** — Diffuser à tous les clients connectés, c'est la définition du *broadcast*.
4. **B** — L'état partagé appartient au serveur, en un seul exemplaire, pour rester cohérent.

</details>

## Projet fil rouge

Le chat fonctionne vraiment : un message écrit par n'importe qui apparaît chez tout le monde, en temps
réel. Avec les exercices, tu sais aussi annoncer les arrivées/départs et compter les connectés. Mais
pour l'instant, tout est du texte brut : on ne sait pas **qui** a écrit quoi, ni distinguer un message
d'une notification système. Au chapitre suivant, on structure les échanges avec un vrai **protocole de
messages en JSON** et on introduit les **pseudos**.

---

[← Chapitre précédent](03-premier-aller-retour.md) · [Sommaire](README.md) · [Chapitre suivant →](05-protocole-messages-json.md)
