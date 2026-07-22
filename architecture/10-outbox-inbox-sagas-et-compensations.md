# Outbox, inbox, sagas et compensations

**Statut :** spécification d'application — ADR-0005 adopté

## Outbox

L'événement et l'état source sont enregistrés dans la même transaction PostgreSQL. Le dispatcher utilise verrouillage avec saut des lignes déjà prises, lots limités et reprise progressive.

L'état de livraison n'altère jamais le contenu de l'événement.

## Inbox

Chaque consommateur durable déduplique avant l'effet. La marque de traitement et l'état consommateur sont atomiques.

Une inbox peut conserver résultat, nombre de tentatives et version du handler pour faciliter l'audit et la reprise.

## Quarantaine

Un message rejoint la quarantaine après erreur définitive, contrat inconnu ou tentatives épuisées. La quarantaine est visible dans l'administration opérationnelle, sans afficher les secrets du payload.

Les actions possibles sont corriger la cause, reprendre l'original, compenser ou clôturer avec justification. « Supprimer » n'est pas une résolution.

## Saga

Une saga est une machine d'états persistante. Chaque transition exige l'état précédent attendu. Les délais créent des événements de timeout idempotents.

Une saga bloquée possède :

- étape ;
- valeur éventuellement engagée ;
- prochain délai ;
- responsable ;
- options sûres ;
- trace complète.

## Compensation

La compensation est définie avant mise en production de tout parcours à effets multiples. L'équipe identifie chaque point réversible, irréversible et soumis à intervention humaine.

Une compensation financière utilise exclusivement ADR-0003.

## Sobriété

Le pilote utilise PostgreSQL et les workers Laravel pour outbox, inbox et sagas. Aucun Kafka, RabbitMQ ou orchestrateur distribué n'est requis au lancement.

Un broker externe ne sera introduit qu'après mesure d'un besoin de débit, isolation ou disponibilité que PostgreSQL ne satisfait plus.