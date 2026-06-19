# Linux avancé : administration, réseau et auto-hébergement

Tu sais te déplacer au terminal et écrire des scripts Bash. Cette formation t'apprend l'étape
suivante : **administrer** une machine Linux et en faire un **serveur** que tu contrôles de bout en
bout. Tu vas comprendre comment Linux est organisé en familles de *distributions*, comment installer
et faire tourner des services durables, comment fonctionne vraiment le réseau, et comment **exposer
ton propre serveur sur Internet** de façon sécurisée.

Le fil conducteur est concret : à la fin, tu héberges toi-même un service accessible depuis n'importe
où via un nom de domaine en HTTPS, et tu rejoins ton réseau personnel « comme si tu étais chez toi »
grâce à **ton propre VPN**. C'est exactement ce que font les administrateurs système et les passionnés
d'auto-hébergement — et c'est à ta portée.

## Prérequis

- Être à l'aise au terminal : se déplacer dans le système de fichiers, manipuler des fichiers, lire
  un message d'erreur, et écrire un script Bash simple. La formation [Linux & Bash](../f-linux-bash/)
  couvre exactement ces bases ; si tu la maîtrises, tu es prêt.
- Savoir utiliser `sudo`, les redirections (`>`, `|`) et un éditeur en terminal (`nano` suffit).

Côté matériel : une machine sous Linux que tu peux « casser » sans risque. L'idéal est une **machine
virtuelle** (VM) sur ton ordinateur, ou un **serveur privé virtuel** (VPS) loué quelques euros par
mois. Le [chapitre 1](01-introduction.md) explique comment mettre en place cet environnement.

## Ce que tu sauras faire à la fin

À la fin de cette formation, tu seras au niveau intermédiaire en administration Linux : plus
autonome que la moitié des gens qui gèrent un serveur. Concrètement, tu sauras :

- **choisir une distribution** Linux en connaissance de cause et comprendre à quelle famille elle
  appartient (Debian/Ubuntu, Red Hat/Fedora, Arch…) ;
- **installer, mettre à jour et compiler** des logiciels avec un gestionnaire de paquets ;
- **gérer des services** durables avec `systemd` et lire leurs journaux avec `journalctl` ;
- sécuriser les **accès** : utilisateurs, droits fins, connexion SSH par clé ;
- comprendre le **réseau** sous Linux : adresses IP, ports, DNS, NAT, privé contre public ;
- **déployer un serveur web et l'exposer sur Internet**, que ton serveur soit chez toi (derrière ta
  box) ou sur un VPS, avec un **nom de domaine** ;
- **sécuriser l'exposition** : pare-feu, `fail2ban`, et **HTTPS** automatique avec Let's Encrypt ;
- monter **ton propre VPN avec WireGuard** pour accéder à ton réseau privé depuis l'extérieur ;
- empaqueter et déployer des services avec **Docker**, et en héberger plusieurs derrière un **reverse
  proxy** ;
- **surveiller, sauvegarder et maintenir** ton serveur dans la durée.

## Plan de la formation

1. [De l'utilisateur à l'administrateur](01-introduction.md)
2. [Les distributions Linux](02-distributions.md)
3. [Gestion des paquets et des dépôts](03-paquets.md)
4. [`systemd` : services, journaux et timers](04-systemd.md)
5. [Utilisateurs, droits et accès SSH](05-utilisateurs-et-ssh.md)
6. [Le réseau sous Linux](06-reseau.md)
7. [Déployer et exposer son premier serveur](07-serveur-web.md)
8. [Sécuriser l'exposition : pare-feu et HTTPS](08-securite-exposition.md)
9. [Son propre VPN avec WireGuard](09-vpn-wireguard.md)
10. [Déployer avec Docker](10-docker.md)
11. [Reverse proxy et plusieurs services](11-reverse-proxy.md)
12. [Surveillance, sauvegardes et maintenance](12-supervision-maintenance.md)
13. [Conclusion et pour aller plus loin](13-conclusion.md)

## Projet fil rouge

Tout au long de la formation, tu montes pas à pas ton propre **homelab auto-hébergé** : un serveur
que tu administres entièrement et qui rend un service réel.

- Tu **mets en place** la machine (VM ou VPS) et tu la sécurises.
- Tu **installes des services** proprement avec `systemd`, puis avec Docker.
- Tu **exposes un site web** sur Internet, accessible par un **nom de domaine** en **HTTPS**.
- Tu **protèges** le tout : pare-feu, `fail2ban`, mises à jour automatiques.
- Tu montes un **VPN WireGuard** pour que l'administration de ton serveur ne soit joignable que par
  toi, où que tu sois — « comme depuis chez toi ».
- Tu **supervises et sauvegardes** ton installation pour qu'elle dure.

À la fin, tu disposes d'un serveur réel, sécurisé et accessible, que tu comprends de bout en bout —
et de la méthode pour en monter d'autres.

---

Commencer par le [chapitre 1 →](01-introduction.md).
