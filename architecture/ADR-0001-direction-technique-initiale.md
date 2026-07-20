# ADR-0001 — Direction technique initiale

- **État :** proposé
- **Date :** 2026-07-20
- **Décideur final :** fondateur de Wasplex

## Contexte

Le marché initial est africain, majoritairement mobile, sensible au coût des données et aux performances des téléphones. Les fondateurs souhaitent limiter les coûts et conserver une architecture compréhensible par une équipe peu spécialisée. Supabase est explicitement exclu.

## Décision proposée

Construire d'abord un monolithe web modulaire :

- PHP avec Laravel ;
- PostgreSQL ;
- React et TypeScript ;
- Inertia.js pour relier Laravel et React sans API séparée pour l'interface web ;
- interface mobile-first pour les utilisateurs ;
- interfaces desktop responsives pour annonceurs, administration et institutions ;
- PWA pour l'installation web ;
- application Android traitée comme une phase distincte.

## Contraintes

- un dépôt applicatif principal ;
- une base PostgreSQL principale ;
- aucune architecture microservices au lancement ;
- Redis non obligatoire au lancement ;
- traitements différés possibles avec la file en base de Laravel ;
- transactions, idempotence, audit et tests obligatoires pour toute opération de valeur ;
- modules métier séparés dans le code même s'ils partagent le déploiement.

## Conséquences favorables

- coût d'exploitation réduit ;
- déploiement et diagnostic simplifiés ;
- une seule équipe et une seule chaîne technique ;
- évolution ultérieure possible vers une API ou une application Android.

## Risques

- couplage si les frontières métier ne sont pas respectées ;
- performances vidéo à traiter séparément du serveur PHP ;
- nécessité de discipline pour empêcher les écritures financières directes depuis les contrôleurs.

## Questions avant adoption

- hébergeur et budget mensuel cible ;
- volumes de membres, campagnes et vidéos ;
- stratégie de stockage/CDN ;
- fonctionnement attendu sous mauvaise connexion ;
- fournisseur d'authentification SMS ;
- prestataires Mobile Money ;
- exigences réglementaires par pays.
