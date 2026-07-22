# Registre technique initial Wasplex

**Statut :** proposition d'exploitation soumise à validation P001-A-R
**Date :** 2026-07-22
**Sources normatives :** ADR-0001 et ADR-0009

## Composants

| Composant | Version ou état | Source | Rôle | Statut | Règle de mise à jour |
|---|---|---|---|---|---|
| Système d'exploitation | Ubuntu Server 24.04.4 LTS (noble) | Dépôts Ubuntu officiels (noble, noble-updates, noble-security) | Système hôte du VPS | Installé et actif | Mises à jour de sécurité automatiques via unattended-upgrades ; montées de version majeures décidées séparément |
| Architecture processeur | x86_64 (amd64) | Matériel VPS Contabo | Cible de compilation et de binaires natifs | Vérifiée | Fixe pour la durée de vie de l'instance |
| PHP | 8.3.6 (cli, NTS) | Dépôt Ubuntu noble-updates / noble-security | Runtime applicatif backend | Installé et actif | Suit les correctifs de sécurité Ubuntu 24.04 ; montée de version mineure/majeure via décision distincte |
| Composer | 2.7.1 | Dépôt Ubuntu noble (universe) | Gestionnaire de dépendances PHP | Installé | Verrouillage des dépendances applicatives par composer.lock pendant P001-B |
| PostgreSQL | 16.14 (serveur), 16.14 (client) | Dépôt Ubuntu noble-updates / noble-security | Source transactionnelle principale de Wasplex | Installé, cluster `main` en ligne, aucune base ni rôle Wasplex créé | Toute montée majeure suit ADR-0009 et une procédure testée de migration et restauration |
| Node.js | v24.18.0 | Node officiel via NVM 0.40.6 | Runtime JavaScript pour outillage et build front-end | Installé | Version confirmée à ce jour ; alignement avec les besoins front-end à P001-B |
| npm | 11.16.0 | Fourni avec Node.js | Gestionnaire de paquets JavaScript | Installé | Suit Node.js ; verrouillage des dépendances front-end pendant P001-B |
| Laravel | Non installé — cible Laravel 13 | À installer via Composer pendant P001-B | Framework applicatif backend du monolithe modulaire | Non créé | Contrainte future `^13.0` ; version exacte verrouillée par composer.lock pendant P001-B |
| React | Non installé | À installer via npm pendant P001-B | Bibliothèque d'interface utilisateur front-end | Non créé | Version exacte verrouillée pendant P001-B |
| Inertia | Non installé | À installer pendant P001-B | Liaison Web Laravel ↔ React | Non créé | Ne remplace ni n'interdit les contrats API prévus par ADR-0007 ; version verrouillée pendant P001-B |
| TypeScript | Non installé | À installer via npm pendant P001-B | Typage statique du code front-end | Non créé | Version exacte verrouillée pendant P001-B |
| Vite | Non installé | À installer via npm pendant P001-B | Outil de build et serveur de développement front-end | Non créé | Version exacte verrouillée pendant P001-B |
| Serveur Web | Nginx 1.24.0 | Dépôt Ubuntu noble-updates / noble-security | Serveur web frontal du VPS | Installé et actif, aucune configuration Wasplex créée | Configuration Wasplex traitée dans une mission distincte |
| Stratégie de conteneur | Absente | Aucun constructeur OCI installé à ce stade | Empaquetage reproductible des artefacts applicatifs | Non créée | Docker ou constructeur OCI équivalent requis avant la première chaîne d'artefacts conforme à ADR-0009 ; Kubernetes exclu au lancement |
| Stockage objet | Absent | Aucun service configuré à ce stade | Stockage des médias et preuves binaires | Non créé | Obligatoire avant médias ou preuves réels ; compatible S3 ; les objets binaires ne seront pas stockés dans PostgreSQL |
| Cache et files | Non choisis | Aucun pilote installé à ce stade | Cache applicatif et traitement asynchrone des tâches | Non créés | Pilotes Laravel initiaux choisis et verrouillés pendant P001-B ; Redis reste conditionnel à un besoin mesuré de concurrence, disponibilité ou volume |

## Précisions obligatoires

- Laravel 13 et PHP 8.3 constituent le socle initial.
- PostgreSQL 16 est la base transactionnelle principale de Wasplex.
- MySQL n'appartient pas au socle Wasplex et n'est pas installé.
- Aucune donnée Wasplex ne doit être écrite dans MySQL.
- Un artefact OCI reproductible est requis avant la première chaîne de livraison.
- Kubernetes reste exclu au lancement.
- Redis reste conditionnel.
- Le stockage objet compatible S3 précède toute preuve ou tout média réel.
- Les versions front-end exactes seront verrouillées pendant P001-B.
- Aucune valeur commerciale ou économique ne figure dans ce registre.
