# Cycle de vie, confidentialité et stockage

**Statut :** spécification d'application — ADR-0006 adopté

## Registre des données

Chaque donnée D2 à D4 figure dans un registre indiquant :

- finalité et base de traitement ;
- personnes concernées ;
- source ;
- destinataires ;
- pays de traitement ;
- chiffrement ;
- durée ;
- droits applicables ;
- procédure d'incident ;
- propriétaire.

## Fin de vie

La fin de vie est une opération orchestrée et auditée. Elle couvre base principale, projections, index de recherche, stockage objet, caches et systèmes externes sous contrôle.

Les sauvegardes ne sont pas réécrites une par une. Un journal protégé de suppressions est rejoué après restauration avant toute réouverture.

## Objets

Un pipeline d'importation :

1. limite taille et format ;
2. calcule l'empreinte ;
3. place l'objet en quarantaine ;
4. détecte son type réel ;
5. analyse les menaces ;
6. chiffre et classe ;
7. publie seulement après validation.

Une miniature ou copie compressée hérite de la classification et de la rétention de l'original.

## Accès

Les URLs signées sont courtes, liées à une ressource et générées après ADR-0004. Elles ne sont jamais enregistrées comme identifiant permanent.

Les téléchargements D3/D4 produisent une preuve d'accès.

## Analytique

Les besoins analytiques utilisent projections minimisées, agrégées ou anonymisées. Une équipe analytique ne reçoit pas une copie complète de production par défaut.

L'anonymisation est testée contre la réidentification raisonnable ; la pseudonymisation reste une donnée personnelle.