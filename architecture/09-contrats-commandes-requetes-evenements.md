# Contrats : commandes, requêtes et événements

**Statut :** spécification proposée — ADR-0005

## Commande

Une commande modifie potentiellement un seul module propriétaire. Son contrat contient :

- nom et version ;
- identifiant de commande ;
- clé d'idempotence ;
- sujet, organisation et finalité ;
- ressource et portée ;
- configuration ;
- données typées ;
- date limite éventuelle ;
- corrélation et cause.

Le résultat distingue accepté, exécuté, refusé et en attente. « Accepté » ne signifie pas « exécuté ».

## Requête

Une requête ne produit aucun effet métier. Son résultat contient source, fraîcheur, portée appliquée, masquage et pagination.

Les contrôles d'accès s'appliquent avant et après récupération des données afin d'éviter l'exposition de champs ou totaux interdits.

## Événement

L'événement est un document immuable au passé. Son schéma possède une version majeure. Il ne contient que les données nécessaires aux consommateurs autorisés.

Les noms évitent les ambiguïtés : `RetraitTransmis` et `RetraitPaye` sont deux faits différents.

## Catalogue

Chaque module maintient un catalogue public interne comprenant :

- commandes reçues ;
- requêtes fournies ;
- événements publiés ;
- événements consommés ;
- erreurs métier ;
- politique de compatibilité ;
- propriétaire du contrat.

Les classes, tables et services internes absents de ce catalogue ne sont pas appelables par un autre module.

## Compatibilité

Les tests de contrat sont exécutés pour le producteur et chaque consommateur. Une livraison est bloquée si elle publie une version non supportée ou modifie le sens d'un champ existant.

## Unités

Montants, pourcentages, durées, dates, fuseaux, territoires et statuts utilisent des types explicites. Aucun contrat financier ne transporte un nombre flottant ou un montant sans devise.