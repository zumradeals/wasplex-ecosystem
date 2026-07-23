# Registre technique initial Wasplex

**Statut :** validé et actualisé à P001-B
**Date :** 2026-07-23
**Sources normatives :** ADR-0001 et ADR-0009

## Composants

| Composant | Version ou état | Source | Rôle | Statut | Règle de mise à jour |
|---|---|---|---|---|---|
| Système d'exploitation | Ubuntu Server 24.04.4 LTS (noble) | Dépôts Ubuntu officiels (noble, noble-updates, noble-security) | Système hôte du VPS | Installé et actif | Mises à jour de sécurité automatiques via unattended-upgrades ; montées de version majeures décidées séparément |
| Architecture processeur | x86_64 (amd64) | Matériel VPS Contabo | Cible de compilation et de binaires natifs | Vérifiée | Fixe pour la durée de vie de l'instance |
| PHP | 8.3.6 (cli, NTS) | Dépôt Ubuntu noble-updates / noble-security | Runtime applicatif backend | Installé et actif | Suit les correctifs de sécurité Ubuntu 24.04 ; montée de version mineure/majeure via décision distincte |
| Composer | 2.7.1 | Dépôt Ubuntu noble (universe) | Gestionnaire de dépendances PHP | Installé | Verrouillage des dépendances applicatives par composer.lock pendant P001-B |
| PostgreSQL | 16.14 (serveur), 16.14 (client) | Dépôt Ubuntu noble-updates / noble-security | Source transactionnelle principale de Wasplex | Installé, cluster `main` en ligne, rôle applicatif et base Wasplex créés localement ; aucun secret documenté dans ce registre | Toute montée majeure suit ADR-0009 et une procédure testée de migration et restauration |
| Node.js | v24.18.0 | Node officiel via NVM 0.40.6 | Runtime JavaScript pour outillage et build front-end | Installé | Version confirmée à ce jour ; alignement avec les besoins front-end à P001-B |
| npm | 11.16.0 | Fourni avec Node.js | Gestionnaire de paquets JavaScript | Installé | Suit Node.js ; verrouillage des dépendances front-end pendant P001-B |
| Laravel | Installé, v13.21.1 | Composer (apps/platform/composer.lock) | Framework applicatif backend du monolithe modulaire | Créé | Contrainte `^13.0` ; version exacte verrouillée par composer.lock |
| React | Installé, v19.2.8 | npm (apps/platform/package-lock.json) | Bibliothèque d'interface utilisateur front-end | Créé | Version exacte verrouillée par package-lock.json |
| Inertia | Installé — inertiajs/inertia-laravel v3.1.1 (PHP), @inertiajs/react v3.6.1 (React) | Composer et npm (apps/platform) | Liaison Web Laravel ↔ React | Créé | Ne remplace ni n'interdit les contrats API prévus par ADR-0007 ; versions verrouillées par composer.lock et package-lock.json |
| TypeScript | Installé, v5.9.3 | npm (apps/platform/package-lock.json) | Typage statique du code front-end | Créé | Version exacte verrouillée par package-lock.json |
| Vite | Installé, v8.1.5 | npm (apps/platform/package-lock.json) | Outil de build et serveur de développement front-end | Créé | Version exacte verrouillée par package-lock.json |
| Serveur Web | Nginx 1.24.0 | Dépôt Ubuntu noble-updates / noble-security | Serveur web frontal du VPS | Installé, aucune configuration Wasplex encore créée | Configuration Wasplex traitée dans une mission distincte |
| Stratégie de conteneur | Absente | Aucun constructeur OCI installé à ce stade | Empaquetage reproductible des artefacts applicatifs | Non créée, différée conformément à ADR-0009 | Docker ou constructeur OCI équivalent requis avant la première chaîne d'artefacts conforme à ADR-0009 ; Kubernetes exclu au lancement |
| Stockage objet | Absent | Aucun service configuré à ce stade | Stockage des médias et preuves binaires | Non créé, différé conformément à ADR-0009 | Obligatoire avant médias ou preuves réels ; compatible S3 ; les objets binaires ne seront pas stockés dans PostgreSQL |
| Cache et files | Pilotes Laravel initiaux sur PostgreSQL (`database`) | config/cache.php et config/queue.php (apps/platform) | Cache applicatif et traitement asynchrone des tâches | Créés | Pilotes initiaux verrouillés sur PostgreSQL ; Redis reste conditionnel à un besoin mesuré de concurrence, disponibilité ou volume |

## Précisions obligatoires

- Laravel 13 et PHP 8.3 constituent le socle initial.
- PostgreSQL 16 est la base transactionnelle principale de Wasplex.
- MySQL n'appartient pas au socle Wasplex et n'est pas installé.
- Aucune donnée Wasplex ne doit être écrite dans MySQL.
- Un artefact OCI reproductible est requis avant la première chaîne de livraison.
- Kubernetes reste exclu au lancement.
- Redis reste conditionnel.
- Le stockage objet compatible S3 précède toute preuve ou tout média réel.
- Les versions front-end exactes sont verrouillées par composer.lock et package-lock.json depuis P001-B.
- Aucune valeur commerciale ou économique ne figure dans ce registre.
