# Conclusion : bonnes pratiques et au-delà

[← Chapitre précédent](11-deploiement-montee-en-charge.md) · [Sommaire](README.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- récapituler le chemin parcouru et ce que tu maîtrises désormais ;
- choisir entre WebSocket et ses alternatives selon le besoin ;
- reconnaître les bonnes pratiques transverses du temps réel ;
- savoir vers quoi t'orienter pour continuer à progresser.

## Le chemin parcouru

Tu es parti d'un constat — HTTP ne sait pas pousser de données — et tu as construit, étape par étape, un
**chat en direct** complet :

- un premier **aller-retour** entre un serveur Node (`ws`) et un client navigateur ;
- la **diffusion** (*broadcast*) d'un message à tous les participants ;
- un **protocole de messages** JSON typé, avec pseudos et horodatage décidés par le serveur ;
- des **salons** pour cloisonner les conversations ;
- la **présence** et l'indicateur **« est en train d'écrire »** ;
- un client **robuste** : reconnexion automatique avec *backoff*, file d'attente, *heartbeat* serveur ;
- la **persistance** SQLite avec historique et reprise sans doublon après coupure ;
- la **sécurité** : `wss://`, authentification, validation, anti-XSS/SQL, `Origin`, *rate limiting* ;
- le **déploiement** derrière nginx et la **montée en charge** avec Redis pub/sub.

Tu ne sais pas seulement « faire un chat » : tu sais bâtir **n'importe quelle fonctionnalité temps
réel**, parce que les mêmes briques (connexion, protocole typé, état partagé côté serveur, robustesse,
persistance, sécurité, scaling) se réutilisent pour des notifications, un jeu, un tableau de bord ou de
l'édition collaborative.

## Quand utiliser WebSocket… et quand ne pas l'utiliser

WebSocket est puissant, mais ce n'est pas la réponse à tout. Choisis l'outil selon le besoin réel :

| Besoin                                              | Meilleur choix                |
| --------------------------------------------------- | ----------------------------- |
| Données ponctuelles à la demande (charger une page) | **HTTP** classique            |
| Le serveur pousse, le client ne fait que recevoir   | **SSE** (Server-Sent Events)  |
| Échanges **bidirectionnels** temps réel             | **WebSocket**                 |
| Latence ultra-faible, perte tolérée (jeu, voix)     | **WebRTC / WebTransport**     |

Quelques repères :

- Si le client n'a **rien à envoyer** par le canal temps réel (un flux de notifications, un cours de
  bourse), **SSE** est plus simple : il fonctionne sur HTTP standard, se reconnecte tout seul, et n'a pas
  besoin de configuration de proxy particulière.
- Si tu as juste besoin de rafraîchir une donnée de temps en temps, un simple appel HTTP périodique peut
  suffire — n'ouvre pas une connexion permanente pour rien.
- WebSocket brille dès qu'il faut du **vrai bidirectionnel** à faible latence : c'est exactement notre
  chat.

> **À retenir** — Le bon réflexe n'est pas « WebSocket partout », mais « quel canal pour quel besoin ».
> Savoir **ne pas** utiliser WebSocket fait partie de la maîtrise du sujet.

## Les bonnes pratiques transverses

Au-delà du chat, retiens ces principes qui reviennent dans tout projet temps réel sérieux :

1. **Un protocole typé.** Un champ `type` sur chaque message, des types ignorés s'ils sont inconnus :
   tu fais évoluer ton application sans rien casser.
2. **Le serveur fait foi.** Identité, horodatage, ordre des messages, autorisations : tout ce qui doit
   être fiable est décidé côté serveur. Le client n'exprime que des intentions.
3. **Ne jamais faire confiance à l'entrée.** Valide le type et borne la taille de chaque champ ;
   neutralise SQL (requêtes paramétrées) et XSS (`textContent`).
4. **La connexion va casser.** Reconnexion automatique, file d'attente, *heartbeat*, reprise par
   curseur : la robustesse n'est pas optionnelle.
5. **Distingue le volatil du durable.** L'état des connexions se recalcule ; les messages se persistent.
6. **Nettoie toujours.** Tout ce qu'on ouvre ou enregistre (entrée dans un `Set`, `setInterval`,
   abonnement) doit être retiré à la fermeture, sous peine de fuite mémoire.
7. **Pense l'échelle tôt.** Dès qu'il peut y avoir plusieurs instances, l'état partagé sort de la
   mémoire d'un processus pour aller dans un magasin commun (Redis).

## Les alternatives et compléments à connaître

- **Socket.IO** : une bibliothèque très répandue construite **au-dessus** de WebSocket. Elle offre
  d'emblée la reconnexion, les *rooms*, les *acknowledgements* et un repli automatique si WebSocket
  n'est pas disponible. Maintenant que tu comprends le **protocole brut**, tu sauras l'utiliser sans
  effet « boîte noire » — et juger quand son confort vaut sa dépendance.
- **SSE** (*Server-Sent Events*) : le bon outil pour du **push unidirectionnel** simple.
- **WebTransport** : un protocole plus récent (sur HTTP/3) visant la basse latence et le multiplexage ;
  une piste d'avenir pour les cas exigeants.
- **Solutions managées** (Ably, Pusher, Supabase Realtime, etc.) : des services qui gèrent pour toi le
  scaling, la présence et la persistance. Utiles pour aller vite, à mettre en balance avec le contrôle
  et le coût.

## Pour aller plus loin

Quelques directions pour continuer à progresser :

- **Approfondir le scaling** : *sticky sessions* sur le load balancer, dimensionnement, observabilité
  (mesurer le nombre de connexions, la latence, les déconnexions).
- **Enrichir le chat** : messages privés, accusés de lecture, pièces jointes (frames binaires),
  réactions, modération, salons privés avec autorisations fines.
- **Tester le temps réel** : écrire des tests automatisés qui ouvrent de vraies connexions WebSocket et
  vérifient les échanges ; simuler des coupures.
- **Lire les spécifications** : la RFC 6455 (le protocole WebSocket) et la documentation de l'API
  WebSocket du navigateur, que tu peux maintenant aborder sans crainte.

## Résumé

- Tu as construit un **chat temps réel complet**, du premier echo au déploiement multi-instances.
- Tu sais **choisir** entre HTTP, SSE, WebSocket et les alternatives selon le besoin.
- Tu maîtrises les **bonnes pratiques** transverses : protocole typé, serveur qui fait foi, validation,
  robustesse, persistance, nettoyage, scaling.
- Tu connais l'**écosystème** (Socket.IO, SSE, WebTransport, services managés) et tu sais vers quoi
  poursuivre.

## Exercices

### Exercice 1 — Choisir le bon canal

Pour chaque fonctionnalité, indique le canal le plus adapté (HTTP, SSE ou WebSocket) et justifie :
(a) un tableau de bord qui affiche en direct le nombre de commandes du jour ; (b) une messagerie
instantanée entre deux personnes ; (c) l'export d'un rapport mensuel en CSV ; (d) un fil d'actualité qui
se met à jour automatiquement, sans que l'utilisateur publie quoi que ce soit par ce canal.

<details>
<summary>Voir le corrigé</summary>

La question centrale : **a-t-on besoin du bidirectionnel ?** Sinon, SSE ou HTTP suffisent.

- **(a) Tableau de bord en direct** : **SSE**. Le serveur pousse, le client ne fait que recevoir.
  Unidirectionnel, SSE est plus simple que WebSocket.
- **(b) Messagerie instantanée** : **WebSocket**. Chacun envoie et reçoit en temps réel : bidirectionnel
  typique.
- **(c) Export CSV** : **HTTP**. Une requête, une réponse (le fichier). Aucun besoin de connexion
  permanente.
- **(d) Fil d'actualité auto-rafraîchi** : **SSE**. Là encore, le serveur pousse, le client reçoit ; il
  ne publie pas par ce canal.

Retiens que WebSocket n'est justifié que pour **(b)** : le seul cas réellement bidirectionnel.

</details>

### Exercice 2 — Repérer les bonnes pratiques dans le projet

Reprends le chat construit pendant la formation et retrouve, dans le code, **un exemple concret** pour
chacun de ces principes : (1) le serveur fait foi ; (2) ne jamais faire confiance à l'entrée ;
(3) la connexion va casser ; (4) nettoyer toujours.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : on relie chaque principe à une décision précise prise dans les chapitres.

1. **Le serveur fait foi** : dans le `case "chat"` (chapitre 5), `pseudo` et `horodatage` sont fixés par
   le serveur (`socket.pseudo`, `Date.now()`), jamais recopiés du message client.
2. **Ne jamais faire confiance à l'entrée** : le `try/catch` autour de `JSON.parse`, les `String(...)` /
   `.slice(...)`, la validation par expression régulière du nom de salon, les requêtes SQL paramétrées
   (chapitre 9) et l'usage de `textContent` plutôt qu'`innerHTML` (chapitre 10).
3. **La connexion va casser** : la fonction `connecter` avec *backoff* exponentiel et la file d'attente
   côté client, plus le *heartbeat* `ping`/`pong` côté serveur (chapitre 8).
4. **Nettoyer toujours** : `retirerDuSalon` dans l'événement `close` (chapitre 6), `clearInterval` du
   heartbeat et du *rate limiting* à la fermeture (chapitres 8 et 10).

Si tu sais retrouver chacun de ces exemples dans ton code, c'est que les principes sont devenus des
réflexes — l'objectif de la formation est atteint.

</details>

## Quiz

**1.** Pour du push **unidirectionnel** simple (serveur vers client), quel canal privilégier ?
- A. WebSocket
- B. SSE (Server-Sent Events)
- C. Polling HTTP

**2.** Qu'apporte Socket.IO par rapport à WebSocket brut ?
- A. Un protocole totalement différent et incompatible
- B. Reconnexion, rooms, accusés et repli intégrés, au-dessus de WebSocket
- C. Le chiffrement, que WebSocket ne permet pas

**3.** Quelle affirmation résume le mieux la bonne pratique « le serveur fait foi » ?
- A. Le client décide de son pseudo et de l'heure des messages
- B. Tout ce qui doit être fiable est décidé côté serveur ; le client exprime des intentions
- C. Le serveur ne valide jamais les entrées pour gagner du temps

**4.** Pourquoi « savoir ne pas utiliser WebSocket » fait-il partie de la maîtrise ?
- A. Parce que WebSocket est obsolète
- B. Parce qu'on choisit le canal selon le besoin (HTTP/SSE/WebSocket), pas par défaut
- C. Parce que WebSocket ne marche pas en production

<details>
<summary>Voir les réponses</summary>

1. **B** — SSE est plus simple pour un push unidirectionnel.
2. **B** — Socket.IO ajoute du confort (reconnexion, rooms, repli) au-dessus de WebSocket.
3. **B** — Les données fiables sont décidées par le serveur ; le client n'exprime que des intentions.
4. **B** — La maîtrise, c'est choisir le bon canal selon le besoin, pas mettre WebSocket partout.

</details>

## Projet fil rouge

C'est terminé : ton chat en direct est **complet**. Il gère plusieurs salons, la présence et la saisie,
survit aux coupures, persiste son historique, se défend contre les abus et se déploie derrière nginx
avec une voie claire vers la montée en charge. Surtout, tu repars avec une **méthode** réutilisable pour
toute fonctionnalité temps réel. Pour continuer, choisis une extension de l'exercice « pour aller plus
loin » (messages privés, accusés de lecture, tests automatisés) et applique les mêmes principes — tu as
désormais tout ce qu'il faut.

Félicitations, et bon temps réel.

---

[← Chapitre précédent](11-deploiement-montee-en-charge.md) · [Sommaire](README.md)
