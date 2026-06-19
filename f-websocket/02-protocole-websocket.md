# Le protocole WebSocket sous le capot

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-premier-aller-retour.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- décrire le *handshake* qui transforme une connexion HTTP en connexion WebSocket ;
- distinguer `ws://` de `wss://` et savoir lequel utiliser ;
- expliquer ce qu'est une *frame* et comment circulent les messages ;
- comprendre le rôle du mécanisme `ping`/`pong` et le cycle de vie d'une connexion.

Ce chapitre est plus théorique que les autres : on regarde **ce qui se passe vraiment** quand une
connexion WebSocket s'ouvre. Tu n'as rien à taper, mais ces notions t'éviteront de coder « par magie »
et t'aideront à déboguer plus tard. On code dès le chapitre 3.

## Tout commence par une requête HTTP

Une connexion WebSocket ne sort pas de nulle part : elle **démarre comme une requête HTTP normale**,
puis demande à « passer » en WebSocket. Cette négociation s'appelle le *handshake* (« poignée de
main »).

Le navigateur envoie une requête HTTP un peu spéciale, avec des en-têtes qui disent « je veux changer de
protocole » :

```http
GET /chat HTTP/1.1
Host: exemple.com
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==
Sec-WebSocket-Version: 13
```

Les deux en-têtes clés :

- `Upgrade: websocket` — « je veux passer en WebSocket ».
- `Connection: Upgrade` — « cette connexion doit être promue, pas refermée ».

`Sec-WebSocket-Key` est une valeur aléatoire générée par le navigateur. Elle sert à vérifier que le
serveur en face comprend vraiment WebSocket (et n'est pas un serveur HTTP qui répondrait n'importe
quoi).

Si le serveur sait gérer WebSocket, il répond avec un **code 101** :

```http
HTTP/1.1 101 Switching Protocols
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=
```

Le code **101 Switching Protocols** signifie « d'accord, on change de protocole ». Le serveur recalcule
une valeur à partir de la `Sec-WebSocket-Key` reçue et la renvoie dans `Sec-WebSocket-Accept`. Le
navigateur vérifie ce calcul : s'il correspond, le *handshake* est validé.

À partir de cet instant, **ce n'est plus du HTTP**. La même connexion TCP (le tuyau réseau sous-jacent)
reste ouverte, mais on échange désormais des messages WebSocket dans les deux sens.

```text
1. GET ... Upgrade: websocket   ──►   (requête HTTP normale)
2. 101 Switching Protocols      ◄──   (le serveur accepte)
3. ════════ connexion WebSocket ════  (canal permanent, bidirectionnel)
```

> **À retenir** — Le *handshake* WebSocket est une **requête HTTP qui se transforme**. C'est pour ça que
> WebSocket passe par le port 80 (HTTP) ou 443 (HTTPS) et traverse les pare-feux et reverse proxies
> comme du trafic web — à condition de bien les configurer (chapitre 11).

## ws:// et wss:// : les deux schémas d'URL

Comme une page web s'adresse en `http://` ou `https://`, une connexion WebSocket s'adresse avec son
propre schéma d'URL :

| Schéma   | Équivalent HTTP | Chiffré ? | Quand l'utiliser                       |
| -------- | --------------- | --------- | -------------------------------------- |
| `ws://`  | `http://`       | Non       | Développement local uniquement         |
| `wss://` | `https://`      | Oui (TLS) | **Toujours en production**             |

`wss://` est du WebSocket par-dessus **TLS** (*Transport Layer Security*, le chiffrement qui sécurise
HTTPS). Les données sont chiffrées, donc illisibles pour un intermédiaire.

```js
// En développement, sur ta machine :
const socket = new WebSocket("ws://localhost:8080");

// En production, derrière un nom de domaine en HTTPS :
const socket = new WebSocket("wss://chat.exemple.com");
```

> **Attention** — Une page servie en `https://` ne peut **pas** ouvrir une connexion `ws://` (non
> chiffrée) : le navigateur la bloque (contenu mixte). Sur un site en HTTPS, c'est `wss://`
> obligatoire. On met en place `wss://` au chapitre 10.

## Les frames : comment circulent les messages

Une fois la connexion établie, les données ne circulent pas en vrac. Elles sont découpées en **frames**
(« trames ») : de petits paquets avec un en-tête minimal. Tu n'écriras jamais une frame à la main — la
bibliothèque `ws` et le navigateur s'en chargent — mais comprendre l'idée aide.

Chaque frame indique notamment :

- son **type** (on parle d'*opcode*) : message texte, message binaire, fermeture, `ping`, `pong` ;
- si c'est la **dernière** frame du message (un gros message peut être découpé en plusieurs frames) ;
- la **taille** des données ;
- les données elles-mêmes.

Les deux types qui t'intéressent au quotidien :

- **frame texte** : transporte du texte UTF-8. C'est ce qu'on utilisera pour envoyer du JSON.
- **frame binaire** : transporte des octets bruts (images, fichiers, données compactes).

Côté code, c'est transparent : tu envoies une chaîne de caractères, tu en reçois une. L'essentiel à
retenir : **WebSocket transporte des messages, pas un flux continu**. Quand tu envoies un message, le
destinataire le reçoit comme **un seul message entier**, jamais coupé en morceaux arbitraires — le
protocole se charge du réassemblage.

> **À retenir** — Contrairement à TCP brut (un flux d'octets sans frontières), WebSocket préserve les
> **limites des messages** : un `send` = un message reçu de l'autre côté. Tu n'as pas à recoller les
> morceaux toi-même.

## Garder la connexion en vie : ping et pong

Une connexion WebSocket est censée rester ouverte longtemps. Problème : comment savoir si l'autre côté
est toujours là ? Un client peut disparaître brutalement (Wi-Fi coupé, onglet fermé de force, tunnel)
sans prévenir. La connexion reste alors « ouverte » en apparence, mais elle est morte.

Le protocole prévoit deux frames de contrôle pour ça :

- **`ping`** : « tu es toujours là ? »
- **`pong`** : la réponse automatique à un `ping`.

Le mécanisme : un côté (souvent le serveur) envoie un `ping` régulièrement ; s'il reçoit le `pong` en
retour, la connexion est vivante ; sinon, après un délai, il considère le client perdu et ferme la
connexion de son côté.

```text
Serveur  ──── ping ───►  Client
Serveur  ◄─── pong ────  Client     (tout va bien)

Serveur  ──── ping ───►  Client
Serveur       ...        (pas de pong : client perdu, on ferme)
```

On mettra en place ce *heartbeat* (« battement de cœur ») concrètement au
[chapitre 8](08-robustesse-reconnexion.md), pour détecter les connexions mortes et déclencher la
reconnexion automatique côté client.

## Le cycle de vie d'une connexion

Du début à la fin, une connexion WebSocket passe par quatre grandes phases. Côté navigateur, l'objet
`WebSocket` expose une propriété `readyState` qui reflète cette phase :

| Phase            | `readyState` | Ce qui se passe                                    |
| ---------------- | ------------ | -------------------------------------------------- |
| `CONNECTING` (0) | en cours     | Le *handshake* est en cours.                       |
| `OPEN` (1)       | ouverte      | La connexion est établie ; on peut envoyer/recevoir. |
| `CLOSING` (2)    | en fermeture | Une fermeture a été demandée.                      |
| `CLOSED` (3)     | fermée       | La connexion est terminée.                         |

Côté client, ces phases déclenchent des **événements** que tu écouteras dans ton code :

- `open` — la connexion est prête (phase `OPEN`).
- `message` — un message vient d'arriver.
- `error` — une erreur est survenue.
- `close` — la connexion s'est fermée (avec un **code de fermeture** indiquant pourquoi).

C'est le squelette de tout client WebSocket, et c'est exactement ce qu'on va coder au chapitre suivant :

```js
// Aperçu de ce qu'on écrira au chapitre 3 (ne pas exécuter encore).
const socket = new WebSocket("ws://localhost:8080");

socket.addEventListener("open",    () => console.log("connecté"));
socket.addEventListener("message", (e) => console.log("reçu :", e.data));
socket.addEventListener("close",   () => console.log("déconnecté"));
socket.addEventListener("error",   () => console.log("erreur"));
```

> **Astuce** — Quand un comportement WebSocket te paraît étrange, reviens toujours au `readyState` : on
> oublie souvent qu'on essaie d'envoyer un message alors que la connexion est encore en `CONNECTING` ou
> déjà `CLOSED`. C'est l'une des erreurs les plus fréquentes.

## Résumé

- Une connexion WebSocket **commence par une requête HTTP** avec les en-têtes `Upgrade: websocket` et
  `Connection: Upgrade` ; le serveur répond **101 Switching Protocols**.
- Après ce *handshake*, la même connexion sert à échanger des messages **dans les deux sens**, ce n'est
  plus du HTTP.
- On adresse une connexion en **`ws://`** (non chiffré, dev) ou **`wss://`** (chiffré par TLS,
  production).
- Les messages circulent en **frames** (texte ou binaire) ; le protocole **préserve les limites des
  messages** : un envoi = un message reçu entier.
- Les frames **`ping`/`pong`** servent à vérifier qu'une connexion est toujours vivante (*heartbeat*).
- Une connexion passe par les états **CONNECTING → OPEN → CLOSING → CLOSED**, exposés via `readyState`
  et les événements `open`, `message`, `error`, `close`.

## Exercices

### Exercice 1 — Lire un handshake

On te montre le début d'un échange réseau. Dis s'il s'agit d'un *handshake* WebSocket valide, et
identifie la ligne qui le prouve.

```http
GET /ws HTTP/1.1
Host: chat.exemple.com
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Version: 13
Sec-WebSocket-Key: x3JJHMbDL1EzLkh9GBhXDw==
```

```http
HTTP/1.1 101 Switching Protocols
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Accept: HSmrc0sMlYUkAGmm5OPpG2HaGWk=
```

<details>
<summary>Voir le corrigé</summary>

Oui, c'est un *handshake* WebSocket valide. Côté requête, `Upgrade: websocket` et `Connection: Upgrade`
indiquent la demande de passage en WebSocket. **La ligne décisive est la réponse `HTTP/1.1 101
Switching Protocols`** : c'est le code 101 qui confirme que le serveur accepte de changer de protocole.
La présence cohérente de `Sec-WebSocket-Accept` (calculé à partir de la `Sec-WebSocket-Key`) confirme
que le serveur parle bien WebSocket.

</details>

### Exercice 2 — Choisir le bon schéma

Pour chacun de ces contextes, indique s'il faut `ws://` ou `wss://` et pourquoi : (a) tu testes ton chat
sur `http://localhost:8080` pendant le développement ; (b) ton chat est déployé sur
`https://chat.monsite.fr` ; (c) une page chargée en HTTPS tente d'ouvrir `ws://chat.monsite.fr`.

<details>
<summary>Voir le corrigé</summary>

- **(a) Développement local** : `ws://` convient. Pas de TLS en local, le trafic ne quitte pas ta
  machine.
- **(b) Production en HTTPS** : `wss://` obligatoire. Les données doivent être chiffrées, et c'est la
  seule option acceptée depuis une page HTTPS.
- **(c) Page HTTPS ouvrant `ws://`** : **bloqué par le navigateur**. C'est du contenu mixte (une page
  sécurisée ouvrant une connexion non sécurisée). Il faut `wss://`.

</details>

## Quiz

**1.** Quel code de statut HTTP confirme qu'un serveur accepte de passer en WebSocket ?
- A. 200 OK
- B. 101 Switching Protocols
- C. 426 Upgrade Required

**2.** Que garantit WebSocket à propos des messages ?
- A. Les messages peuvent arriver coupés en morceaux qu'il faut recoller
- B. Un message envoyé est reçu comme un seul message entier
- C. Les messages sont toujours du texte, jamais du binaire

**3.** À quoi servent les frames `ping`/`pong` ?
- A. À chiffrer les données
- B. À vérifier qu'une connexion est toujours vivante
- C. À découper les gros messages

**4.** Quel `readyState` indique qu'on peut envoyer un message ?
- A. CONNECTING
- B. OPEN
- C. CLOSED

<details>
<summary>Voir les réponses</summary>

1. **B** — 101 Switching Protocols valide le changement de protocole.
2. **B** — WebSocket préserve les limites des messages : un envoi = un message reçu entier.
3. **B** — `ping`/`pong` est le mécanisme de *heartbeat* qui détecte les connexions mortes.
4. **B** — Seul l'état OPEN permet d'échanger des messages.

</details>

## Projet fil rouge

Pas de code ce chapitre, mais un acquis essentiel pour le projet : tu sais maintenant **ce qui se passe
quand ton chat se connecte**. Quand on lancera le serveur au chapitre 3, tu sauras reconnaître le
*handshake* (la requête `Upgrade` puis le `101`) et tu comprendras pourquoi on écoute les événements
`open`, `message`, `close` et `error`. Ces fondations resserviront à chaque chapitre, en particulier
pour le *heartbeat* (chapitre 8) et le passage en `wss://` (chapitre 10).

---

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-premier-aller-retour.md)
