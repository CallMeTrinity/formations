# Premier aller-retour : serveur et client

[← Chapitre précédent](02-protocole-websocket.md) · [Sommaire](README.md) · [Chapitre suivant →](04-chat-de-base-broadcast.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- installer la bibliothèque `ws` et écrire un serveur WebSocket minimal en Node.js ;
- écrire un client WebSocket dans le navigateur avec l'API native ;
- servir une page HTML et établir un premier aller-retour (un *echo*) ;
- lire les événements `open`, `message`, `close` côté client et `connection`, `message` côté serveur.

## Installer la bibliothèque ws

Node.js ne fournit pas de serveur WebSocket clé en main, mais la bibliothèque **`ws`** est la référence :
légère, rapide, et de loin la plus utilisée. On l'installe depuis le dossier du projet (`chat-direct/`,
créé au chapitre 1) :

```bash
npm install ws
```

Cette commande télécharge `ws` dans `node_modules/` et l'ajoute aux dépendances de `package.json`. Le
côté **client**, lui, n'a rien à installer : l'objet `WebSocket` est intégré à tous les navigateurs.

## Le serveur le plus simple possible

On va d'abord écrire un serveur qui renvoie en écho tout ce qu'il reçoit. C'est le « Hello, World » de
WebSocket. Ouvre `server.js` :

```js
// server.js
import { WebSocketServer } from "ws";

// On crée un serveur WebSocket qui écoute sur le port 8080.
const wss = new WebSocketServer({ port: 8080 });

// "connection" se déclenche à chaque nouveau client qui se connecte.
// "socket" représente CE client précis (sa connexion individuelle).
wss.on("connection", (socket) => {
  console.log("Un client s'est connecté");

  // "message" se déclenche quand ce client nous envoie un message.
  socket.on("message", (data) => {
    const texte = data.toString(); // "data" est un Buffer : on le convertit en texte
    console.log("Reçu :", texte);

    // On renvoie le message à l'expéditeur (echo).
    socket.send(`echo : ${texte}`);
  });

  // "close" se déclenche quand ce client se déconnecte.
  socket.on("close", () => {
    console.log("Un client s'est déconnecté");
  });
});

console.log("Serveur WebSocket démarré sur ws://localhost:8080");
```

Trois objets à bien distinguer :

- `wss` (le **serveur**) : il accepte les connexions. Son événement `connection` se déclenche une fois
  par client.
- `socket` (la **connexion d'un client**) : c'est l'objet pour parler à **un** client précis. Tu en as
  un par client connecté.
- `data` (un **message reçu**) : `ws` te le donne sous forme de *Buffer* (une suite d'octets). Pour du
  texte, on appelle `.toString()`.

Lance le serveur :

```bash
node server.js
# Sortie attendue :
# Serveur WebSocket démarré sur ws://localhost:8080
```

Laisse ce terminal ouvert : le serveur tourne tant que tu ne fais pas `Ctrl+C`.

> **Attention** — `data.toString()` est indispensable. Si tu oublies, tu manipules un `Buffer` et non
> une chaîne, ce qui provoque des bugs sournois (concaténations bizarres, comparaisons qui échouent).
> On verra au chapitre 5 comment passer proprement au JSON.

## Le client dans le navigateur

Le client est une simple page HTML avec un peu de JavaScript. Ouvre `public/index.html` :

```html
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <title>Chat en direct</title>
  </head>
  <body>
    <h1>Premier aller-retour</h1>
    <input id="saisie" placeholder="Ton message" />
    <button id="envoyer">Envoyer</button>
    <ul id="messages"></ul>

    <script>
      // On ouvre la connexion vers le serveur.
      const socket = new WebSocket("ws://localhost:8080");

      const liste = document.querySelector("#messages");
      const saisie = document.querySelector("#saisie");
      const bouton = document.querySelector("#envoyer");

      // "open" : la connexion est prête.
      socket.addEventListener("open", () => {
        ajouter("Connecté au serveur");
      });

      // "message" : le serveur nous a envoyé quelque chose.
      socket.addEventListener("message", (evenement) => {
        ajouter(evenement.data);
      });

      // "close" : la connexion s'est fermée.
      socket.addEventListener("close", () => {
        ajouter("Déconnecté");
      });

      // Envoi : au clic, on envoie le contenu de la saisie.
      bouton.addEventListener("click", () => {
        const texte = saisie.value;
        if (texte === "") return;
        socket.send(texte); // on envoie au serveur
        saisie.value = "";
      });

      // Petite fonction utilitaire pour afficher une ligne.
      function ajouter(texte) {
        const li = document.createElement("li");
        li.textContent = texte;
        liste.appendChild(li);
      }
    </script>
  </body>
</html>
```

Côté client, retiens les correspondances avec le serveur :

- `new WebSocket(url)` lance le *handshake* (chapitre 2) ;
- `socket.send(texte)` envoie un message au serveur ;
- l'événement `message` donne le message reçu dans `evenement.data`.

## Servir la page

Tu pourrais ouvrir `index.html` directement par double-clic, mais c'est une mauvaise habitude :
l'adresse serait alors `file://...`, ce qui pose des problèmes dès qu'on ajoute des fonctionnalités. On
sert plutôt la page par un vrai serveur HTTP. Le plus simple, sans rien installer :

```bash
# Dans un SECOND terminal, depuis le dossier public/ :
cd public
npx http-server -p 3000
# Ouvre ensuite http://localhost:3000 dans ton navigateur
```

`npx` exécute un paquet sans l'installer durablement. `http-server` est un petit serveur de fichiers
statiques. Tu as maintenant **deux serveurs** :

- le **serveur WebSocket** sur le port 8080 (`node server.js`, premier terminal) ;
- le **serveur de fichiers** sur le port 3000 (`http-server`, second terminal), qui sert la page.

```text
Navigateur ──HTTP──►  http-server (3000)   : charge la page index.html
Navigateur ──WS───►   server.js   (8080)   : connexion WebSocket temps réel
```

> **Astuce** — Garde toujours la **console du navigateur** ouverte (touche F12, onglet *Console*)
> pendant que tu développes du WebSocket. Les erreurs de connexion (mauvais port, serveur éteint,
> contenu mixte) s'y affichent et te font gagner un temps fou.

## L'aller-retour en action

Ouvre `http://localhost:3000`. Tu devrais voir « Connecté au serveur ». Tape un message, clique sur
**Envoyer**, et observe :

```text
Dans le navigateur :
  Connecté au serveur
  echo : bonjour

Dans le terminal du serveur :
  Un client s'est connecté
  Reçu : bonjour
```

Décortiquons ce qui vient de se passer, dans l'ordre :

1. le navigateur a ouvert la connexion (`new WebSocket`), le *handshake* a réussi → événement `open` ;
2. au clic, le client a appelé `socket.send("bonjour")` ;
3. le serveur a reçu le message (`socket.on("message", ...)`), l'a affiché et a renvoyé `echo : bonjour`
   avec `socket.send(...)` ;
4. le client a reçu cette réponse (`message`) et l'a affichée.

C'est **le premier aller-retour bidirectionnel** : le client a parlé, le serveur a répondu spontanément
sur la même connexion. Tout le reste de la formation enrichit ce squelette.

> **Attention** — Si rien ne s'affiche, vérifie dans l'ordre : le serveur `node server.js` tourne-t-il ?
> Le port est-il bien `8080` des deux côtés ? La console du navigateur montre-t-elle une erreur de
> connexion ? Un oubli classique : avoir fermé le terminal du serveur.

## Résumé

- Côté serveur, on installe **`ws`** (`npm install ws`) et on crée un `WebSocketServer({ port })`.
- L'événement **`connection`** donne un `socket` par client ; `socket.on("message", ...)` reçoit ses
  messages ; `socket.send(...)` lui répond.
- Les messages reçus côté serveur sont des **Buffers** : convertis-les avec `.toString()`.
- Côté client, **`new WebSocket(url)`** ouvre la connexion ; on écoute `open`, `message`, `close` ; on
  envoie avec `socket.send(...)`.
- On sert la page par un **vrai serveur HTTP** (port 3000) ; le WebSocket est sur un **autre port**
  (8080).
- L'**echo** réalisé est le premier aller-retour bidirectionnel : la base de tout le projet.

## Exercices

### Exercice 1 — Echo en majuscules

Modifie le serveur pour qu'il renvoie le message en majuscules au lieu de le préfixer par `echo :`.
Vérifie dans le navigateur que « bonjour » revient « BONJOUR ».

<details>
<summary>Voir le corrigé</summary>

Il suffit de transformer la chaîne avant de la renvoyer, avec `toUpperCase()` :

```js
socket.on("message", (data) => {
  const texte = data.toString();
  socket.send(texte.toUpperCase()); // on renvoie en majuscules
});
```

Pense à relancer le serveur (`Ctrl+C` puis `node server.js`) : Node ne recharge pas le code tout seul.

</details>

### Exercice 2 — Compter les messages

Fais en sorte que le serveur compte combien de messages **chaque client** a envoyés depuis sa
connexion, et renvoie ce numéro : `message n°1 : bonjour`, `message n°2 : ça va ?`, etc. Le compteur
doit repartir de zéro pour chaque nouveau client.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : le compteur doit être propre à chaque client. On le déclare donc **dans** le
*callback* `connection` (une variable par connexion), et non au niveau global.

```js
wss.on("connection", (socket) => {
  let compteur = 0; // propre à CE client

  socket.on("message", (data) => {
    compteur++;
    const texte = data.toString();
    socket.send(`message n°${compteur} : ${texte}`);
  });
});
```

Si on avait déclaré `compteur` en dehors du `connection`, il serait partagé par tous les clients : c'est
justement le genre de variable partagée qui resservira au chapitre 4 pour diffuser à tout le monde.

</details>

## Quiz

**1.** Quel objet représente la connexion d'**un seul** client côté serveur ?
- A. `wss` (le WebSocketServer)
- B. le `socket` reçu dans l'événement `connection`
- C. l'objet `WebSocket` du navigateur

**2.** Pourquoi appelle-t-on `.toString()` sur les données reçues côté serveur ?
- A. Parce que `ws` livre les messages sous forme de Buffer (octets)
- B. Parce que sinon le message est chiffré
- C. Parce que `send` n'accepte que des nombres

**3.** Pourquoi sert-on la page HTML par un serveur HTTP plutôt qu'en l'ouvrant par double-clic ?
- A. Pour que le WebSocket aille plus vite
- B. Pour éviter le schéma `file://` qui pose problème dès qu'on ajoute des fonctionnalités
- C. Parce que les navigateurs interdisent le HTML local

**4.** Dans cet exemple, combien de serveurs tournent en même temps ?
- A. Un seul, qui fait tout
- B. Deux : le serveur de fichiers (3000) et le serveur WebSocket (8080)
- C. Trois

<details>
<summary>Voir les réponses</summary>

1. **B** — Le `socket` de l'événement `connection` représente une connexion client unique ; `wss` est
   le serveur global.
2. **A** — `ws` fournit les messages sous forme de Buffer ; `.toString()` les convertit en texte.
3. **B** — Servir la page évite `file://` et ses limitations, et reproduit un contexte réaliste.
4. **B** — Un serveur HTTP pour la page (3000) et un serveur WebSocket pour le temps réel (8080).

</details>

## Projet fil rouge

Le squelette technique du chat est en place et **fonctionne** : un client navigateur ouvre une
connexion vers un serveur Node, lui envoie un message, et reçoit une réponse en temps réel. Pour
l'instant, le serveur ne répond qu'à l'expéditeur (un echo). Au chapitre suivant, on franchit l'étape
qui fait d'un echo un vrai chat : **diffuser chaque message à tous les clients connectés** (*broadcast*).

---

[← Chapitre précédent](02-protocole-websocket.md) · [Sommaire](README.md) · [Chapitre suivant →](04-chat-de-base-broadcast.md)
