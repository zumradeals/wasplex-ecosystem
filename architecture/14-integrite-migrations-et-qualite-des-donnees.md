# Intégrité, migrations et qualité des données

**Statut :** spécification d'application — ADR-0006 adopté

## Invariants

Les invariants d'un seul module sont protégés au plus près des données. Les invariants intermodules utilisent commandes, événements et contrôles de cohérence ; aucun trigger ne modifie silencieusement un autre domaine.

## Qualité

Chaque donnée importante possède des règles de :

- complétude ;
- validité ;
- unicité ;
- cohérence ;
- fraîcheur ;
- origine ;
- exactitude contestable.

Un tableau de bord de qualité ne corrige jamais automatiquement une valeur économique ou une identité sensible.

## Migration

Toute migration reçoit :

- propriétaire ;
- risque ;
- volumes estimés ;
- compatibilité ascendante et descendante ;
- durée et verrouillage attendus ;
- stratégie de reprise ;
- contrôles et totaux ;
- période d'observation ;
- preuve de clôture.

Les migrations importantes sont testées sur une copie représentative dépourvue de données personnelles inutiles.

## Import et reprise de l'ancien Wasplex

Aucune donnée de l'ancien dépôt ou déploiement n'est importée directement.

Chaque import futur exige :

- inventaire et légitimité ;
- correspondance vers le nouveau modèle ;
- déduplication contrôlée ;
- validation des consentements ;
- rapprochement financier ;
- quarantaine des incohérences ;
- rapport d'import ;
- possibilité de ne pas importer.

L'ancien schéma n'impose aucune structure au nouvel écosystème.

## Réparation

Une réparation crée une trace, conserve l'ancienne valeur lorsque nécessaire et utilise les mécanismes du module. Ledger emploie contre-écriture ; identité emploie une décision de correction ; les projections sont reconstruites.

La modification SQL directe en production n'est jamais le mécanisme normal de réparation.