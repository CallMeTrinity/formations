# Historique et persistance

[← Chapitre précédent](08-robustesse-reconnexion.md) · [Sommaire](README.md) · [Chapitre suivant →](10-securite.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- distinguer **état en mémoire** et **persistance** durable ;
- stocker les messages dans une base **SQLite** intégrée à Node ;
- restituer l'**historique** d'un salon à la connexion ou au changement de salon ;
- ne renvoyer que les **messages manqués** après une reconnexion.

## Pourquoi persister

Aujourd'hui, les messages ne vivent que le temps d'être diffusés. Conséquences :

- un nouvel arrivant voit un salon **vide**, même si la discussion bat son plein ;
- au moindre redémarrage du serveur, **tout est perdu** ;
- après une reconnexion (chapitre 8), l'utilisateur a **raté** les messages échangés pendant la coupure.

Il faut donc **persister** les messages : les écrire dans un stockage durable, qui survit aux
redémarrages, et savoir les relire. On distingue deux choses :

- l'**état en mémoire** (les `Map`/`Set` de connexions, la présence) : volatil, recalculé à chaque
  démarrage. C'est normal, il dépend des connexions du moment.
- les **données durables** (les messages échangés) : elles doivent survivre. C'est ce qu'on persiste.

> **À retenir** — Tout n'a pas vocation à être persisté. La **présence** est de l'état volatil (qui est
> connecté maintenant) : inutile de la stocker. Les **messages**, eux, sont des données durables. Sache
> distinguer les deux avant de choisir où ranger une information.

## SQLite, sans rien installer

On utilise **SQLite** : une base de données stockée dans un simple fichier, sans serveur à lancer.
Node.js l'intègre désormais nativement via le module `node:sqlite`. C'est idéal pour apprendre et pour
de petits déploiements.

> **Astuce** — Le module `node:sqlite` est intégré aux versions récentes de Node (20.x avec un drapeau,
> stable à partir de Node 22+). Si ta version l'expose en expérimental, lance
> `node --experimental-sqlite server.js`. En cas de souci, l'alternative `npm install better-sqlite3`
> offre la même API synchrone ; adapte juste l'import.

On crée la base et une table `messages` au démarrage du serveur :

```js
import { DatabaseSync } from "node:sqlite";

// Ouvre (ou crée) le fichier de base de données.
const db = new DatabaseSync("chat.db");

// Crée la table si elle n'existe pas encore.
db.exec(`
  CREATE TABLE IF NOT EXISTS messages (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    salon      TEXT    NOT NULL,
    pseudo     TEXT    NOT NULL,
    texte      TEXT    NOT NULL,
    horodatage INTEGER NOT NULL
  )
`);
```

Chaque ligne est un message. La colonne `id` (auto-incrémentée) donne un **numéro croissant unique** à
chaque message : on s'en servira pour savoir « à partir d'où » renvoyer les messages manqués.

## Enregistrer chaque message

On prépare deux requêtes réutilisables (insertion et lecture) et on insère chaque message de chat avant
de le diffuser. On utilise des **requêtes paramétrées** (les `?`) : jamais de concaténation de chaînes
dans une requête SQL, sous peine d'**injection SQL** (on y revient au chapitre 10).

```js
// Requêtes préparées une fois, réutilisées à chaque appel.
const insererMessage = db.prepare(
  "INSERT INTO messages (salon, pseudo, texte, horodatage) VALUES (?, ?, ?, ?)"
);
const lireHistorique = db.prepare(
  "SELECT id, pseudo, texte, horodatage FROM messages WHERE salon = ? ORDER BY id DESC LIMIT ?"
);
const lireDepuis = db.prepare(
  "SELECT id, salon, pseudo, texte, horodatage FROM messages WHERE salon = ? AND id > ? ORDER BY id ASC"
);

// Dans le traitement d'un message "chat" :
case "chat": {
  const texte = String(message.texte ?? "").slice(0, 1000);
  if (texte === "") break;
  const horodatage = Date.now();

  // 1. On persiste, et on récupère l'id attribué.
  const resultat = insererMessage.run(socket.salon, socket.pseudo, texte, horodatage);
  const id = resultat.lastInsertRowid;

  // 2. On diffuse, en incluant l'id (utile pour la reprise après coupure).
  diffuserDansSalon(socket.salon, {
    type: "chat", id, salon: socket.salon,
    pseudo: socket.pseudo, texte, horodatage,
  });
  break;
}
```

L'ordre **persister puis diffuser** garantit qu'on n'annonce jamais un message qu'on n'a pas réussi à
enregistrer.

## Restituer l'historique à la connexion

Quand un client rejoint un salon, on lui envoie les **derniers** messages de ce salon (par exemple les
50 plus récents), à lui seul. On a écrit la requête `lireHistorique` qui prend les N derniers par `id`
décroissant ; on les remet dans l'ordre chronologique avant d'envoyer.

```js
function envoyerHistorique(socket, salon, limite = 50) {
  // Les N plus récents (id DESC), qu'on remet dans l'ordre chronologique.
  const recents = lireHistorique.all(salon, limite).reverse();
  socket.send(JSON.stringify({ type: "historique", salon, messages: recents }));
}
```

On l'appelle quand le client rejoint un salon (dans le `case "rejoindre"`, après l'avoir ajouté), et
pour le salon par défaut à la connexion :

```js
wss.on("connection", (socket) => {
  socket.pseudo = "Anonyme";
  socket.salon = "general";
  ajouterAuSalon(socket, socket.salon);
  envoyerHistorique(socket, socket.salon); // le nouvel arrivant voit le passé récent
  diffuserPresence(socket.salon);
  // ...
});
```

Côté client, on gère le nouveau type `historique` : on l'affiche **avant** les messages temps réel qui
suivront.

```js
if (message.type === "historique") {
  liste.innerHTML = ""; // on repart propre (utile au changement de salon)
  for (const m of message.messages) afficherMessage(m);
  dernierIdRecu = message.messages.length
    ? message.messages[message.messages.length - 1].id
    : dernierIdRecu;
} else if (message.type === "chat") {
  afficherMessage(message);
  dernierIdRecu = message.id; // on retient le dernier id vu
}
```

> **Attention** — `lireHistorique` trie par `id DESC` pour prendre les plus **récents**, puis on
> `.reverse()` pour les afficher du plus ancien au plus récent. Si tu oublies le `reverse`, l'historique
> s'affiche à l'envers. Trier directement `ASC` avec `LIMIT` te donnerait les plus **anciens**, pas les
> derniers : ce n'est pas ce qu'on veut.

## Ne renvoyer que les messages manqués

Après une **reconnexion** (chapitre 8), renvoyer tout l'historique afficherait des doublons. Mieux : le
client dit « j'ai déjà reçu jusqu'au message numéro X », et le serveur ne renvoie que ce qui a suivi.
C'est tout l'intérêt de l'`id` croissant.

On ajoute un type `reprendre` : le client envoie le dernier `id` qu'il a vu pour son salon ; le serveur
renvoie uniquement les messages d'`id` supérieur.

```js
// Serveur :
case "reprendre": {
  const depuis = Number(message.dernierId) || 0;
  const manques = lireDepuis.all(socket.salon, depuis); // id > depuis, ordre chronologique
  socket.send(JSON.stringify({ type: "rattrapage", salon: socket.salon, messages: manques }));
  break;
}
```

```js
// Client, à la reconnexion (dans le gestionnaire "open") :
socket.addEventListener("open", () => {
  delaiReconnexion = 1000;
  socket.send(JSON.stringify({ type: "pseudo", pseudo }));
  socket.send(JSON.stringify({ type: "rejoindre", salon }));
  // On demande seulement ce qu'on a manqué depuis le dernier message vu.
  socket.send(JSON.stringify({ type: "reprendre", dernierId: dernierIdRecu }));
});

// ... et au traitement du rattrapage :
if (message.type === "rattrapage") {
  for (const m of message.messages) {
    afficherMessage(m);
    dernierIdRecu = m.id;
  }
}
```

Ainsi, un utilisateur qui perd le réseau 5 minutes retrouve, à la reconnexion, **exactement** les
messages qu'il a manqués, sans doublon et dans l'ordre. C'est le confort qui distingue un chat amateur
d'un chat fiable.

> **À retenir** — Un identifiant **monotone croissant** (l'`id` SQLite) est la clé de la reprise après
> coupure : « donne-moi tout ce qui est arrivé après le numéro X » est une requête simple et sans
> ambiguïté. Beaucoup de systèmes temps réel reposent sur ce principe de curseur.

## Résumé

- On distingue l'**état volatil** (connexions, présence, recalculés au démarrage) des **données
  durables** (les messages), qu'il faut **persister**.
- **SQLite** (`node:sqlite`) stocke les messages dans un simple fichier, sans serveur à lancer.
- On utilise des **requêtes paramétrées** (`?`), jamais de concaténation, pour éviter l'injection SQL.
- On **persiste avant de diffuser**, et on inclut l'`id` du message dans la diffusion.
- À la connexion / au changement de salon, on **restitue l'historique récent** (type `historique`).
- Après reconnexion, on ne renvoie que les **messages manqués** grâce à l'`id` croissant (types
  `reprendre` / `rattrapage`).

## Exercices

### Exercice 1 — Limiter et purger l'historique

(a) Modifie `envoyerHistorique` pour n'envoyer que les **20** derniers messages. (b) Propose une requête
SQL qui supprime les messages de plus de 30 jours, pour ne pas laisser la base grossir indéfiniment.

<details>
<summary>Voir le corrigé</summary>

**(a)** La limite est déjà un paramètre : il suffit de l'appeler avec `20`, ou de changer la valeur par
défaut.

```js
envoyerHistorique(socket, socket.salon, 20);
```

**(b)** On supprime les messages dont l'horodatage est plus vieux que « maintenant moins 30 jours ». On
calcule le seuil en millisecondes et on utilise une requête paramétrée :

```js
const purger = db.prepare("DELETE FROM messages WHERE horodatage < ?");
const seuil = Date.now() - 30 * 24 * 60 * 60 * 1000; // il y a 30 jours
purger.run(seuil);
```

On lancerait cette purge périodiquement (par exemple une fois par jour avec `setInterval`). Garder une
base bornée fait partie de l'exploitation d'un service temps réel.

</details>

### Exercice 2 — Pourquoi un id plutôt qu'un horodatage pour la reprise ?

On pourrait demander « tous les messages depuis tel horodatage » au lieu d'« depuis tel id ». Donne deux
raisons qui rendent l'`id` croissant plus fiable pour la reprise après coupure.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : on compare les garanties offertes par chaque approche.

1. **Unicité.** Deux messages peuvent avoir le **même horodatage** à la milliseconde près (deux envois
   simultanés). Filtrer « `horodatage > X` » risque alors d'oublier ou de dupliquer un message à la
   frontière. L'`id` est **strictement unique et croissant** : « `id > X` » est sans ambiguïté.
2. **Indépendance de l'horloge.** L'horodatage dépend de l'horloge système, qui peut reculer (ajustement
   NTP, changement d'heure, décalage entre machines). L'`id` auto-incrémenté ne recule jamais : il
   reflète l'**ordre d'insertion** réel, ce qui est exactement ce qu'on veut pour « la suite ».

C'est pourquoi les systèmes temps réel sérieux utilisent un curseur monotone (id, *offset*, numéro de
séquence) plutôt qu'un timestamp pour la reprise.

</details>

## Quiz

**1.** Que faut-il persister dans notre chat ?
- A. La présence et les connexions
- B. Les messages échangés
- C. Le `readyState` de chaque socket

**2.** Pourquoi utiliser des requêtes paramétrées (`?`) en SQL ?
- A. Pour aller plus vite uniquement
- B. Pour éviter l'injection SQL (ne jamais concaténer l'entrée utilisateur)
- C. Parce que SQLite refuse les chaînes

**3.** Dans quel ordre traiter un nouveau message de chat ?
- A. Diffuser puis persister
- B. Persister puis diffuser
- C. Peu importe l'ordre

**4.** Comment éviter d'afficher des doublons après une reconnexion ?
- A. Renvoyer tout l'historique à chaque reconnexion
- B. Ne renvoyer que les messages d'`id` supérieur au dernier id reçu
- C. Vider la base à chaque reconnexion

<details>
<summary>Voir les réponses</summary>

1. **B** — Les messages sont les données durables ; la présence est volatile.
2. **B** — Les requêtes paramétrées préviennent l'injection SQL.
3. **B** — On persiste avant de diffuser, pour ne jamais annoncer un message non enregistré.
4. **B** — Le curseur `id` permet de ne renvoyer que ce qui a été manqué.

</details>

## Projet fil rouge

Le chat a maintenant une **mémoire** : les messages survivent aux redémarrages, un nouvel arrivant voit
l'historique récent de son salon, et après une coupure l'utilisateur récupère pile les messages manqués,
sans doublon. Le chat est fonctionnellement complet. Avant de le déployer, il faut le **sécuriser** :
chiffrement, authentification, validation des entrées, *rate limiting*. C'est l'objet du chapitre
suivant.

---

[← Chapitre précédent](08-robustesse-reconnexion.md) · [Sommaire](README.md) · [Chapitre suivant →](10-securite.md)
