# Sécurité d'une application temps réel

[← Chapitre précédent](09-historique-persistance.md) · [Sommaire](README.md) · [Chapitre suivant →](11-deploiement-montee-en-charge.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- chiffrer les échanges avec **`wss://`** (WebSocket sur TLS) ;
- **authentifier** un client à la connexion via un jeton ;
- **valider** systématiquement les messages reçus côté serveur ;
- vérifier l'**`Origin`** des connexions et limiter le débit (*rate limiting*) contre les abus.

## WebSocket n'est pas sécurisé par défaut

Un chat ouvert sur Internet est exposé à tout : usurpation d'identité, messages géants, injection,
spam, connexions depuis n'importe quel site. La règle de base, déjà vue au chapitre 5 : **ne jamais
faire confiance au client**. Ce chapitre généralise ce principe en quatre piliers : chiffrer,
authentifier, valider, limiter.

> **À retenir** — La sécurité n'est pas une couche qu'on ajoute à la fin. Mais comme on a construit le
> protocole proprement (champ `type`, validation des champs, décisions côté serveur), on a déjà posé de
> bonnes fondations. Ce chapitre les renforce.

## 1. Chiffrer : passer en wss://

En `ws://`, tout circule **en clair** : un intermédiaire sur le réseau (Wi-Fi public, proxy) peut lire
et modifier les messages. En production, c'est **`wss://`** obligatoire : WebSocket par-dessus TLS, le
même chiffrement que HTTPS.

En pratique, on ne gère presque jamais le certificat TLS dans Node directement : on place un **reverse
proxy** (nginx) devant, qui termine le TLS et transmet en clair au serveur Node sur la machine locale.
On détaille cette mise en place au [chapitre 11](11-deploiement-montee-en-charge.md). Ce qu'il faut
retenir ici :

- le **client** se connecte en `wss://chat.exemple.com` ;
- le **reverse proxy** déchiffre et transmet à `ws://localhost:8080` (trafic local, non exposé) ;
- une page servie en **HTTPS** ne peut ouvrir que du **`wss://`** (rappel du chapitre 2).

```js
// Côté client : on choisit le schéma selon celui de la page.
const schema = location.protocol === "https:" ? "wss" : "ws";
const socket = new WebSocket(`${schema}://${location.host}/ws`);
```

Ce petit réflexe (déduire `wss`/`ws` du protocole de la page) évite l'erreur de contenu mixte et marche
aussi bien en local qu'en production.

## 2. Authentifier le client

Notre chat laisse n'importe qui choisir n'importe quel pseudo : rien n'empêche de se faire passer pour
un autre. Pour une vraie application, on **authentifie** la connexion. Le schéma le plus courant avec
WebSocket :

1. l'utilisateur se connecte normalement (formulaire HTTP classique) et reçoit un **jeton** (par
   exemple un *JWT*, un jeton signé que le serveur peut vérifier) ;
2. à l'ouverture du WebSocket, le client **présente ce jeton** ;
3. le serveur le **vérifie** : s'il est invalide, il **ferme** la connexion ; sinon, il associe
   l'identité réelle à la connexion.

Comment transmettre le jeton ? L'API WebSocket du navigateur ne permet pas d'ajouter d'en-tête HTTP
personnalisé. Deux options pratiques :

- **dans l'URL** en paramètre de requête : `wss://.../ws?token=...` (simple, mais le jeton peut finir
  dans les journaux du serveur) ;
- **premier message** après l'ouverture : un message `auth` contenant le jeton, le serveur n'autorise
  rien d'autre tant qu'il n'est pas validé (plus propre).

On met en place la seconde approche : tant que la connexion n'est pas authentifiée, on ignore tout sauf
`auth`.

```js
import { verifierJeton } from "./auth.js"; // ta fonction de vérification (ex. JWT)

wss.on("connection", (socket) => {
  socket.authentifie = false;

  socket.on("message", (data) => {
    let message;
    try { message = JSON.parse(data.toString()); } catch { return; }

    // Tant qu'on n'est pas authentifié, seul "auth" est accepté.
    if (!socket.authentifie) {
      if (message.type !== "auth") return;
      const identite = verifierJeton(message.token); // renvoie l'utilisateur ou null
      if (!identite) {
        socket.close(4001, "authentification refusée"); // code de fermeture applicatif
        return;
      }
      socket.authentifie = true;
      socket.pseudo = identite.pseudo; // pseudo issu du compte, pas choisi librement
      socket.salon = "general";
      ajouterAuSalon(socket, socket.salon);
      envoyerHistorique(socket, socket.salon);
      diffuserPresence(socket.salon);
      return;
    }

    // À partir d'ici, le client est authentifié : on traite normalement.
    switch (message.type) {
      // ... chat, rejoindre, saisie, reprendre (chapitres précédents)
    }
  });
});
```

Remarque le **code de fermeture** `4001` : la plage 4000–4999 est réservée aux codes **applicatifs**, à
toi de la documenter. Le client peut lire ce code dans l'événement `close` pour réagir (ne pas
retenter une reconnexion si l'authentification est refusée, par exemple).

> **Attention** — Authentifier **à la connexion** ne suffit pas si tes jetons expirent. Pour une
> session longue, prévois de revérifier périodiquement la validité du jeton, ou de fermer la connexion
> à son expiration. Une connexion ouverte ne doit pas devenir un passe-droit éternel.

## 3. Valider chaque message

On a commencé au chapitre 5 (`try/catch` sur `JSON.parse`, `String(...)`, `.slice(...)`). On systématise.
Pour **chaque** type de message, on vérifie que les champs attendus sont présents et du bon type, et on
**borne** les tailles. Une entrée invalide est **ignorée** (ou la connexion fermée si c'est clairement
malveillant), jamais traitée à l'aveugle.

```js
function texteValide(valeur, maxLongueur) {
  return typeof valeur === "string" && valeur.length > 0 && valeur.length <= maxLongueur;
}

case "chat": {
  // On refuse tout ce qui n'est pas un texte non vide et raisonnable.
  if (!texteValide(message.texte, 1000)) return;
  const texte = message.texte;
  // ... persistance + diffusion
  break;
}

case "rejoindre": {
  // Un nom de salon : lettres, chiffres, tirets, longueur bornée.
  if (typeof message.salon !== "string" || !/^[a-z0-9-]{1,30}$/.test(message.salon)) return;
  // ...
  break;
}
```

Deux points de vigilance particuliers :

- **Injection SQL** : on l'a déjà neutralisée au chapitre 9 avec les requêtes paramétrées (`?`). Ne
  jamais construire une requête par concaténation de chaînes venant du client.
- **Injection HTML / XSS** : côté client, n'insère jamais de message reçu avec `innerHTML`. Utilise
  toujours `textContent` (ce qu'on fait depuis le début), qui traite le contenu comme du **texte**, pas
  du HTML. Sinon, un message contenant `<script>` s'exécuterait chez les autres.

```js
// DANGEREUX : un message piégé peut exécuter du code chez les autres.
li.innerHTML = message.texte;        // à NE PAS faire

// SÛR : le contenu est affiché tel quel, jamais interprété.
li.textContent = message.texte;      // bonne pratique
```

> **À retenir** — Deux injections menacent un chat : **SQL** (côté serveur, neutralisée par les
> requêtes paramétrées) et **XSS** (côté client, neutralisée par `textContent`). Ce sont des réflexes,
> pas des options.

## 4. Vérifier l'Origin et limiter le débit

### Vérifier l'Origin

Par défaut, **n'importe quel site web** peut ouvrir une connexion WebSocket vers ton serveur depuis le
navigateur d'un de tes utilisateurs (il n'y a pas de protection équivalente au CORS pour WebSocket). Un
site malveillant pourrait ainsi détourner une session. On vérifie donc l'en-tête **`Origin`** envoyé au
*handshake* et on refuse les origines inconnues.

```js
const originesAutorisees = new Set([
  "https://chat.exemple.com",
  "http://localhost:3000", // pour le développement
]);

const wss = new WebSocketServer({
  port: 8080,
  verifyClient: (info) => {
    // info.origin = l'en-tête Origin du handshake.
    return originesAutorisees.has(info.origin);
  },
});
```

`verifyClient` est appelé **avant** d'accepter la connexion : une origine non listée est refusée dès le
*handshake*.

### Limiter le débit (rate limiting)

Sans limite, un client peut envoyer des milliers de messages par seconde et saturer le serveur ou
inonder les autres. On limite le **débit** par connexion : par exemple, au plus 5 messages par 2
secondes. Au-delà, on ignore (ou on avertit, ou on déconnecte les récidivistes).

```js
wss.on("connection", (socket) => {
  socket.compteurMessages = 0;
  // Toutes les 2 s, on remet le compteur à zéro.
  const reset = setInterval(() => { socket.compteurMessages = 0; }, 2000);
  socket.on("close", () => clearInterval(reset)); // ne pas oublier de nettoyer

  socket.on("message", (data) => {
    socket.compteurMessages++;
    if (socket.compteurMessages > 5) return; // au-delà de 5 / 2 s : on ignore
    // ... traitement normal
  });
});
```

> **Attention** — N'oublie pas de `clearInterval` à la fermeture de la connexion. Chaque `setInterval`
> oublié est une fuite : un timer qui tourne pour une connexion morte. C'est la même discipline de
> nettoyage que pour les salons (chapitre 6) et le heartbeat (chapitre 8).

## Récapitulatif des quatre piliers

| Pilier            | Menace                          | Parade                                       |
| ----------------- | ------------------------------- | -------------------------------------------- |
| Chiffrer          | Écoute / altération réseau      | `wss://` (TLS via reverse proxy)             |
| Authentifier      | Usurpation d'identité           | Jeton vérifié à la connexion, identité serveur |
| Valider           | Entrées malformées, XSS, SQL    | Vérif. des champs, requêtes paramétrées, `textContent` |
| Limiter           | Spam, déni de service           | `verifyClient` (Origin) + *rate limiting*    |

## Résumé

- **Chiffrer** : `wss://` en production (TLS, généralement via un reverse proxy) ; déduis le schéma du
  protocole de la page.
- **Authentifier** : vérifie un **jeton** à la connexion, n'autorise rien tant que ce n'est pas validé,
  et dérive l'identité (pseudo) du compte — pas du choix du client.
- **Valider** : contrôle le **type** et **borne la taille** de chaque champ ; neutralise l'**injection
  SQL** (requêtes paramétrées) et le **XSS** (`textContent`, jamais `innerHTML`).
- **Limiter** : vérifie l'**`Origin`** au *handshake* (`verifyClient`) et applique un **rate limiting**
  par connexion ; nettoie les timers à la fermeture.
- Principe transverse : **ne jamais faire confiance au client**.

## Exercices

### Exercice 1 — Choisir entre ignorer, avertir, déconnecter

Pour chacune de ces situations, dis si le serveur devrait **ignorer** le message, **avertir** le client,
ou **fermer** la connexion : (a) un message non-JSON ; (b) un jeton d'authentification invalide ;
(c) un 6ᵉ message en moins de 2 secondes ; (d) un nom de salon contenant des caractères interdits.

<details>
<summary>Voir le corrigé</summary>

Il n'y a pas de réponse unique, mais une logique : plus l'acte est clairement malveillant ou bloquant,
plus la réponse est ferme.

- **(a) Non-JSON** : **ignorer**. C'est souvent un bug client bénin ; inutile de couper.
- **(b) Jeton invalide** : **fermer** (code applicatif, ex. 4001). Sans authentification, le client ne
  doit rien pouvoir faire ; le laisser connecté n'a pas de sens.
- **(c) 6ᵉ message en 2 s** : **ignorer** le message excédentaire (et éventuellement **avertir**). Si le
  client persiste (par ex. dépasse largement et répétitivement), on peut **fermer**.
- **(d) Salon invalide** : **ignorer** la demande de changement de salon. C'est probablement un bug ;
  on n'a pas à couper toute la session pour ça.

L'idée : **ignorer** pour le bruit bénin, **fermer** pour l'absence d'autorisation, et garder la
déconnexion pour les abus répétés.

</details>

### Exercice 2 — Repérer une faille XSS

Un développeur a « amélioré » l'affichage des messages pour gérer des liens cliquables :

```js
li.innerHTML = message.texte.replace(/\n/g, "<br>");
```

Explique la faille et corrige-la sans réintroduire de risque.

<details>
<summary>Voir le corrigé</summary>

**La faille** : `innerHTML` **interprète** la chaîne comme du HTML. Un utilisateur peut envoyer
`<img src=x onerror="...code malveillant...">` ou `<script>...</script>` : ce code s'exécutera dans le
navigateur de **tous** les autres participants (faille XSS). C'est l'une des erreurs les plus courantes
et les plus graves d'une application web.

**La correction** : ne jamais passer du contenu utilisateur à `innerHTML`. Pour gérer les retours à la
ligne tout en restant sûr, on met le texte avec `textContent` et on laisse le CSS gérer les sauts de
ligne :

```js
li.textContent = message.texte;
li.style.whiteSpace = "pre-wrap"; // respecte les retours à la ligne, sans interpréter de HTML
```

Si un jour tu dois vraiment générer du HTML à partir d'entrée utilisateur (liens, mise en forme), il
faut **échapper** ou **assainir** le contenu avec une bibliothèque dédiée (par ex. DOMPurify), jamais le
faire à la main.

</details>

## Quiz

**1.** Pourquoi utiliser `wss://` plutôt que `ws://` en production ?
- A. C'est plus rapide
- B. Les échanges sont chiffrés (TLS), illisibles pour un intermédiaire
- C. `ws://` ne fonctionne pas sur Internet

**2.** Pourquoi le pseudo doit-il venir du compte authentifié et non du choix libre du client ?
- A. Pour éviter l'usurpation d'identité
- B. Pour gagner de la mémoire
- C. Parce que `JSON.parse` l'exige

**3.** Comment éviter une faille XSS à l'affichage d'un message ?
- A. Utiliser `innerHTML`
- B. Utiliser `textContent` (jamais `innerHTML` avec du contenu utilisateur)
- C. Chiffrer le message

**4.** À quoi sert `verifyClient` / la vérification de l'`Origin` ?
- A. À refuser les connexions venant d'origines non autorisées
- B. À compresser les messages
- C. À authentifier l'utilisateur

<details>
<summary>Voir les réponses</summary>

1. **B** — `wss://` chiffre les échanges via TLS.
2. **A** — Dériver le pseudo du compte empêche un client de se faire passer pour un autre.
3. **B** — `textContent` affiche le contenu comme du texte et neutralise le HTML injecté.
4. **A** — La vérification d'`Origin` refuse les connexions provenant de sites non autorisés.

</details>

## Projet fil rouge

Le chat est désormais **défendu** : échanges chiffrables en `wss://`, authentification à la connexion
avec identité dérivée du compte, validation systématique des entrées (anti-SQL, anti-XSS), contrôle de
l'`Origin` et *rate limiting*. Il est prêt à affronter Internet. Reste l'étape finale : le **mettre en
ligne** et comprendre comment il **tient la charge** quand les utilisateurs affluent. C'est l'objet du
chapitre suivant.

---

[← Chapitre précédent](09-historique-persistance.md) · [Sommaire](README.md) · [Chapitre suivant →](11-deploiement-montee-en-charge.md)
