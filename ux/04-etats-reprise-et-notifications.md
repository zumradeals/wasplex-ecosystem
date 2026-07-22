# États, reprise et notifications

**Statut :** spécification proposée — UX-0001

## Gabarit d'état

Chaque état important affiche :

- libellé ;
- explication ;
- date ;
- référence ;
- montant ou portée ;
- preuve disponible ;
- prochaine étape ;
- action sûre ;
- assistance ou recours.

## Résultat inconnu

Composant obligatoire pour paiement, transmission et intégration externe :

> Nous n'avons pas encore la preuve du résultat. Aucune nouvelle tentative n'est nécessaire. La valeur reste protégée pendant la vérification.

Le texte exact s'adapte au domaine sans transformer l'inconnu en échec.

## Reprise

Les brouillons portent date et appareil. Une reprise sur autre appareil exige synchronisation et authentification adaptées.

Un brouillon sensible expiré n'est pas rouvert avec des données obsolètes sans avertissement.

## Notifications

Une notification possède :

- catégorie ;
- importance ;
- objet ;
- texte minimal ;
- lien profond ;
- date ;
- état lu/non lu ;
- expiration ;
- canaux ;
- preuve de livraison lorsqu'elle existe.

La suppression d'une notification ne supprime pas l'événement métier.

## Préférences

Les utilisateurs contrôlent les communications facultatives. Les messages contractuels, sécurité, finance et alertes critiques suivent leur base propre et ne sont pas assimilés à du marketing.