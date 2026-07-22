# Propriété des données et schémas PostgreSQL

**Statut :** spécification d'application — ADR-0006 adopté

## Carte de propriété

| Schéma | Propriétaire | Données principales | Ne doit pas posséder |
|---|---|---|---|
| identity | Identité et Accès | personnes, comptes, KYC, appareils | gains, campagnes |
| privacy | Consentements et Profil | finalités, consentements, attributs autorisés | documents KYC, Ledger |
| advertising | Publicité | campagnes, diffusion, attention | solde utilisateur |
| subscriptions | Abonnements | offres, cycles, droits | rémunération |
| ledger | Wallet et Ledger | comptes, postings, paiements | qualification publicitaire |
| social_fund | Fonds Social | mandats, vœux, appels | profil publicitaire |
| alerts | Alertes | SOS, dossiers, restitutions | ciblage commercial |
| institutions | Institutions | affiliations, capacités, actions | accès général |
| cards | Cartes Wasplex | cartes, pools, opérations | création autonome de valeur |
| live | Live | sessions, présence, interactions, preuves, modération | solde, budget annonceur, profil publicitaire brut |
| governance | Administration | configurations, autorisations, approbations | modification du Ledger |
| integration | Intégrations | outbox, inbox, sagas, preuves externes | décision métier |
| reporting | Reporting | projections reconstruites | état souverain |

## Dépendances

Une dépendance intermodule est dirigée vers un contrat public. Une table d'un domaine ne référence jamais une colonne interne instable d'un autre domaine.

Les identifiants intermodules sont validés lors de la commande et confirmés par événements. L'absence temporaire d'une projection ne permet aucune création fictive.

## Base partagée

L'instance commune simplifie transactions locales, sauvegardes et coût. Elle ne permet pas :

- modèles ORM traversant librement tous les schémas ;
- cascades intermodules ;
- requêtes de reporting sur les tables sources ;
- scripts administratifs modifiant plusieurs propriétaires ;
- migrations d'un module dans le dossier d'un autre.

## Contrôle architectural

La CI analyse les dépendances, migrations et accès SQL. Une violation de propriétaire bloque la livraison.

Chaque module expose ses identifiants et DTO, pas ses modèles ORM internes.
