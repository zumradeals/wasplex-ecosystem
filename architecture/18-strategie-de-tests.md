# Stratégie de tests

**Statut :** spécification proposée — ADR-0008

## Matrice minimale par changement

| Changement | Unitaires | PostgreSQL | Contrats | E2E | Sécurité | Résilience |
|---|---:|---:|---:|---:|---:|---:|
| Règle métier | oui | selon persistance | si exposée | parcours critique | selon risque | selon état |
| Ledger/paiement | oui renforcé | oui | oui | oui ciblé | oui | oui |
| Autorisation | oui | oui | oui | oui négatif | oui renforcé | révocation |
| API/webhook | oui | oui | oui | ciblé | oui | timeout/rejeu |
| UI ordinaire | composants | non | non | ciblé | basique | réseau faible |
| Migration | non | oui renforcé | compatibilité | smoke | données | interruption |
| Configuration C1 | propriétés | oui | événement | simulation | séparation | retour |

## Structure

Les tests suivent les modules. Aucun dossier global ne devient propriétaire des règles de tous les domaines.

Les helpers partagés fournissent temps contrôlé, UUID, montants, identités, organisations, idempotence et prestataires simulés, sans masquer les assertions métier.

## Temps

Les tests ne dépendent pas de l'horloge réelle. Ils contrôlent expiration, fuseau, changement de jour, cycle d'abonnement, clôture, délai de webhook et conservation.

## Concurrence

Les tests critiques exécutent réellement plusieurs transactions PostgreSQL. Une boucle séquentielle ne prouve pas l'absence de course.

## Aléas

Les dépendances simulées peuvent répondre tard, dupliquer, contredire, échouer puis réussir. Le hasard est reproductible par graine enregistrée.

## Nommage

Le nom d'un test décrit règle, contexte et résultat. Les tests Q0/Q1 citent l'exigence ou invariant couvert.