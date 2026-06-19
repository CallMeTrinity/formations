# Conclusion et pour aller plus loin

[← Chapitre précédent](12-supervision-maintenance.md) · [Sommaire](README.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- situer tout ce que tu as appris dans une vue d'ensemble cohérente ;
- reconnaître et appliquer les **bonnes pratiques** d'un serveur bien tenu ;
- éviter les **pièges** les plus dangereux de l'administration ;
- choisir tes prochaines étapes pour progresser vers le niveau avancé.

Tu es parti d'une machine vide. Tu sais désormais choisir une distribution, gérer paquets et services,
maîtriser le réseau, exposer un serveur sur Internet, le sécuriser en HTTPS, monter ton propre VPN, et
déployer des services en conteneurs derrière un reverse proxy. Ce chapitre consolide l'ensemble et
t'ouvre la suite.

## Vue d'ensemble du chemin parcouru

Chaque chapitre a posé une brique. Voici comment elles s'empilent :

| Chapitres | Compétence acquise |
| --- | --- |
| 1–2 | Comprendre le rôle de serveur, choisir une distribution |
| 3–4 | Installer des logiciels (`apt`) et gérer des services durables (`systemd`) |
| 5 | Sécuriser l'accès : utilisateurs, `sudo`, SSH par clé |
| 6 | Comprendre le réseau : IP, ports, DNS, NAT, privé contre public |
| 7–8 | Exposer un serveur web sur Internet et le sécuriser (pare-feu, `fail2ban`, HTTPS) |
| 9 | Monter un VPN WireGuard pour accéder à son réseau de l'extérieur |
| 10–11 | Déployer avec Docker, router plusieurs services via un reverse proxy |
| 12 | Surveiller, sauvegarder et maintenir dans la durée |

Le fil conducteur : **comprendre avant d'exposer**. Chaque couche (système, réseau, accès, exposition,
chiffrement) s'appuie sur la précédente. C'est ce qui distingue l'administrateur du suiveur de
tutoriel : tu sais **pourquoi** tu fais chaque geste, donc tu sais te débloquer quand un cas non vu se
présente.

## Les bonnes pratiques d'un serveur bien tenu

Quelques habitudes te feront passer pour quelqu'un qui sait ce qu'il fait — et surtout t'éviteront des
incidents.

- **Le moindre privilège.** Ne travaille pas en `root` ; fais tourner chaque service sous un compte
  dédié peu privilégié. N'ouvre que les ports strictement nécessaires.
- **Réduis la surface exposée.** Tout ce qui n'a pas besoin d'être public ne l'est pas : services sur
  `127.0.0.1` derrière un reverse proxy, administration derrière le VPN.
- **Authentifie par clé, jamais par mot de passe** pour SSH. Combine avec `fail2ban`.
- **Reste à jour.** Les correctifs de sécurité automatiques ferment la plupart des portes.
- **Documente tout.** Ton fichier `notes-homelab.md` et tes configs versionnées avec `git` sont ce qui
  te permet de reconstruire après un incident.
- **Sauvegarde et teste tes sauvegardes.** Une assurance non vérifiée n'en est pas une.
- **Teste avant d'appliquer.** `nginx -t`, `sshd -t`, `certbot --dry-run` : ces vérifications coûtent
  une seconde et évitent les coupures.

> **À retenir** — La sécurité d'un serveur ne tient pas à un outil magique, mais à une discipline :
> moindre privilège, surface réduite, à jour, sauvegardé, documenté. Chacun de ces réflexes est simple ;
> c'est leur **constance** qui protège.

## Les pièges à ne jamais oublier

Certaines erreurs peuvent te couper l'accès ou causer des pertes irréversibles. Garde-les en tête :

- **Se verrouiller dehors.** Activer un pare-feu sans autoriser SSH, ou couper
  `PasswordAuthentication` sans clé fonctionnelle, te coupe d'un serveur distant. Garde **toujours une
  session de secours** ouverte pendant que tu touches à SSH ou au pare-feu.
- **Oublier `enable` ou `daemon-reload`.** Un service qui marche aujourd'hui mais ne revient pas après
  un reboot (`enable` oublié), ou une config modifiée qui n'est pas relue (`daemon-reload` oublié).
- **Exposer sans sécuriser.** Mettre un service en ligne avant le pare-feu, les mises à jour et le
  HTTPS, c'est ouvrir une porte aux robots qui scannent en permanence.
- **Le disque plein.** La panne silencieuse par excellence : surveille `df -h`, borne tes journaux,
  nettoie Docker.
- **La sauvegarde fantôme.** Une sauvegarde locale, jamais testée : inutile le jour du pépin.
- **`sudo` à l'aveugle.** Comprends une commande avant de la lancer en `root`, surtout copiée
  d'Internet.

> **À retenir** — Le réflexe de sécurité le plus précieux est gratuit : avant une action sur SSH ou le
> pare-feu d'un serveur distant, demande-toi « si ça se passe mal, comment je reprends la main ? ».
> Garde toujours une porte de secours.

## Comment continuer à progresser

Tu as le niveau intermédiaire en administration. Pour aller vers l'avancé, voici des pistes concrètes,
par ordre d'utilité.

- **L'infrastructure en code.** Tu as documenté ton serveur à la main ; l'étape suivante est de le
  décrire en code reproductible avec des outils comme **Ansible** (configuration), pour reconstruire un
  serveur entier d'une commande.
- **L'orchestration de conteneurs.** Quand un seul serveur ne suffit plus, **Docker Swarm** puis
  **Kubernetes** répartissent les conteneurs sur plusieurs machines.
- **La supervision avancée.** Des outils comme **Prometheus** et **Grafana** transforment la
  surveillance manuelle du chapitre 12 en tableaux de bord et alertes automatiques.
- **Le réseau en profondeur.** VLAN, routage, segmentation : pour isoler tes services les uns des
  autres. Et l'IPv6, que tu croiseras de plus en plus.
- **La sécurité offensive et défensive.** Comprendre comment on attaque un serveur pour mieux le
  défendre (durcissement, audit, détection d'intrusion).
- **Une autre famille de distribution.** Monte une machine sous Fedora ou Arch pour vérifier que tu
  maîtrises les **concepts** au-delà des commandes `apt`.

> **Astuce** — Le réflexe le plus important n'est pas de tout savoir, mais de savoir **chercher et se
> débloquer** : `man`, `--help`, la documentation officielle de chaque projet, les journaux
> (`journalctl`, `docker logs`). Un bon administrateur est avant tout quelqu'un qui lit les messages
> d'erreur et formule clairement son problème.

## Résumé

- Tu maîtrises l'administration d'un serveur Linux : distribution, paquets, services, accès, réseau,
  exposition, chiffrement, VPN, conteneurs, reverse proxy, maintenance.
- Le fil conducteur : **comprendre chaque couche avant de l'exposer**, ce qui te rend autonome face aux
  cas non vus.
- Bonnes pratiques : moindre privilège, surface réduite, authentification par clé, à jour, documenté,
  sauvegardé et testé.
- Pièges majeurs : se verrouiller dehors, oublier `enable`/`daemon-reload`, exposer sans sécuriser,
  disque plein, sauvegarde non testée, `sudo` à l'aveugle.
- Pour progresser : infrastructure en code (Ansible), orchestration (Kubernetes), supervision
  (Prometheus/Grafana), réseau avancé, sécurité.

## Exercices

### Exercice 1 — L'audit de ton propre serveur

Sans regarder le corrigé, passe ton homelab en revue : pour chacun des points suivants, vérifie d'une
commande qu'il est en ordre. (a) SSH n'accepte que les clés ; (b) le pare-feu n'ouvre que le
nécessaire ; (c) aucun service en échec ; (d) le disque n'est pas saturé ; (e) le certificat HTTPS se
renouvellera.

<details>
<summary>Voir le corrigé</summary>

La démarche : un audit, c'est une checklist de vérifications concrètes.

```bash
sudo grep -E "PasswordAuthentication|PermitRootLogin" /etc/ssh/sshd_config   # (a)
sudo ufw status verbose          # (b) seuls SSH, 80, 443, 51820/udp ?
systemctl --failed               # (c) rien, idéalement
df -h                            # (d) Use% raisonnable sur /
sudo certbot renew --dry-run     # (e) le renouvellement réussit en simulation
```

Cet enchaînement réutilise des compétences de presque tous les chapitres. Le faire régulièrement est
exactement la posture d'un administrateur : vérifier plutôt que supposer.

</details>

### Exercice 2 — Reprendre la main

Tu modifies à distance la config de ton pare-feu sur un VPS et, par erreur, tu coupes ta propre
session SSH. Tu n'as plus accès. Qu'aurais-tu dû faire avant, et comment reprends-tu la main
maintenant ?

<details>
<summary>Voir le corrigé</summary>

La démarche : prévention (porte de secours) et récupération (console hébergeur).

**Avant** : garder une **seconde session SSH ouverte** pendant la manipulation, pour annuler le
changement si la première se coupe. C'est la règle d'or dès qu'on touche à SSH ou au pare-feu à
distance.

**Maintenant** : la plupart des hébergeurs de VPS fournissent une **console de secours** (accès
« KVM », « VNC » ou « rescue ») dans leur interface web, qui te connecte à la machine **hors** du
réseau SSH. Tu t'y connectes, tu corriges la règle de pare-feu (`sudo ufw allow OpenSSH`), et tu
reprends la main par SSH.

</details>

## Quiz

**1.** Quel principe résume le mieux la sécurité d'un serveur ?
- A. Installer le plus d'outils de sécurité possible
- B. Le moindre privilège et la surface d'exposition réduite, appliqués avec constance
- C. Changer souvent de distribution

**2.** Avant de modifier le pare-feu ou SSH sur un serveur distant, quel réflexe adopter ?
- A. Garder une porte de secours (session ouverte ou console de l'hébergeur)
- B. Redémarrer le serveur
- C. Désactiver le pare-feu

**3.** Pourquoi placer l'administration derrière le VPN plutôt que sur un sous-domaine public ?
- A. Pour aller plus vite
- B. Pour la rendre invisible d'Internet, accessible seulement par les pairs du tunnel
- C. Parce que le reverse proxy l'exige

**4.** Quelle est la prochaine étape logique pour rendre ton serveur reproductible « en code » ?
- A. Tout réinstaller à la main plus vite
- B. Décrire sa configuration avec un outil comme Ansible
- C. Supprimer la documentation

<details>
<summary>Voir les réponses</summary>

1. **B** — Moindre privilège et surface réduite, tenus dans la durée : c'est le cœur de la sécurité.
2. **A** — Une porte de secours évite de se verrouiller dehors lors d'une fausse manœuvre.
3. **B** — Le VPN rend l'administration invisible d'Internet, joignable seulement par le tunnel.
4. **B** — L'infrastructure en code (Ansible) reconstruit un serveur de façon reproductible.

</details>

## Projet fil rouge

Jalon final : **ton homelab est complet — fais-en le tour et approprie-le toi.**

Tu disposes maintenant d'un serveur réel :

- une base saine (distribution LTS, à jour, services systemd) ;
- un accès verrouillé (compte dédié, SSH par clé, pare-feu, `fail2ban`) ;
- un ou plusieurs services exposés en **HTTPS** derrière un **reverse proxy**, par leur nom de
  domaine ;
- un **VPN WireGuard** pour rejoindre ton réseau et administrer en privé, de n'importe où ;
- des **sauvegardes** automatiques et testées, et une **routine de maintenance**.

Pour clore le projet, offre-lui une dernière amélioration de ton choix, par ordre d'ambition :

- **Consolidation** : passe l'audit de l'exercice 1 et corrige le moindre point faible.
- **Un nouveau service** : héberge quelque chose qui te sert vraiment (un cloud de fichiers, un
  gestionnaire de mots de passe, un agrégateur de flux), en suivant la routine du chapitre 11.
- **La reproductibilité** : commence à décrire ton installation en code (Ansible), pour pouvoir la
  reconstruire d'une commande.

Tu disposes d'un serveur que tu comprends de bout en bout, **et** de la méthode pour en monter
d'autres. C'est la définition du niveau intermédiaire visé par cette formation : autonome, capable de
te débloquer seul, conscient des bonnes pratiques et des pièges.

Bravo d'être allé au bout. Tu n'utilises plus seulement Linux : tu l'administres.

---

[← Chapitre précédent](12-supervision-maintenance.md) · [Sommaire](README.md)
