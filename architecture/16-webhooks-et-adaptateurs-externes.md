# Webhooks et adaptateurs externes

**Statut :** spécification d'application — ADR-0007 adopté

## Entrée

Le contrôleur de webhook :

1. limite la taille ;
2. conserve le corps brut protégé ;
3. vérifie l'identité et la signature ;
4. contrôle horodatage et rejeu ;
5. valide la version ;
6. écrit dans l'inbox ;
7. accuse réception ;
8. laisse le worker exécuter le contrat métier.

Il ne crédite, ne débite et ne clôture rien directement.

## Sortie

Le dispatcher :

1. sélectionne un événement outbox autorisé ;
2. construit un payload minimal ;
3. signe le corps ;
4. transmet avec délai court ;
5. conserve résultat et tentative ;
6. reprend si l'erreur est temporaire ;
7. suspend et alerte en cas d'échec durable.

## SSRF

Les destinations utilisent HTTPS, résolution contrôlée et listes de réseaux interdites. Les redirections sont limitées ou refusées. Une validation initiale n'autorise pas une modification silencieuse de destination.

## Adaptateur anticorruption

Chaque prestataire possède :

- DTO propres ;
- traduction des états ;
- gestion des erreurs ;
- idempotence disponible ;
- preuves externes ;
- limites ;
- sandbox ;
- tableau de santé ;
- procédure de sortie.

Le domaine métier ne dépend jamais directement du SDK d'un prestataire.

## Rotation

Deux secrets ou clés peuvent coexister pendant une courte période de rotation. Chaque message indique l'identifiant de clé sans exposer le secret.