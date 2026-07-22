# Moteur d'autorisations et politiques

**Statut :** spécification d'application — ADR-0004 adopté

## Responsabilités

Le moteur centralise l'évaluation des dimensions communes sans retirer au module propriétaire la responsabilité de sa ressource.

Il fournit :

- catalogue des capacités ;
- rôles modèles ;
- attributions, délégations et expirations ;
- évaluation de politiques ;
- approbations et authentification renforcée ;
- audit des décisions ;
- invalidation des autorisations révoquées.

## Requête d'autorisation

Toute évaluation reçoit :

- sujet humain ou technique ;
- organisation active ;
- capacité ;
- ressource et propriétaire ;
- portée demandée ;
- finalité ;
- contexte d'authentification ;
- configuration et politique ;
- corrélation de la transaction.

Une information absente n'est jamais interprétée comme illimitée.

## Contraintes d'implémentation

- Contrôle serveur obligatoire.
- Politique identique pour interface, API et worker.
- Aucune requête métier non filtrée par organisation lorsque l'objet appartient à une organisation.
- Aucun `is_admin` donnant accès global.
- Aucun identifiant d'organisation accepté du client sans validation d'appartenance.
- Aucun worker exécuté sous une identité système universelle lorsque l'action vient d'une personne.
- Aucun export par simple réutilisation d'une capacité de lecture.

## Politique et configuration

Les définitions et modèles sont livrés avec le code ou une migration contrôlée. Leurs paramètres et attributions suivent ADR-0002.

Une nouvelle version de politique est testée sur un échantillon de décisions historiques avant activation afin de détecter les élargissements ou refus inattendus.

## Défense en profondeur

Pour les actifs critiques, PostgreSQL utilise rôles, permissions de schéma, vues ou contraintes adaptées. Ces protections complètent le contrôle applicatif et ne remplacent pas les politiques métier.

## Décision explicable

Le moteur conserve un code de décision non sensible. L'utilisateur reçoit une explication compréhensible ; les détails susceptibles d'aider une attaque restent dans l'audit sécurisé.

## Révocation

Les sessions et jetons portent une version d'autorisation. Après révocation, une différence de version force une nouvelle évaluation. Les tâches en attente vérifient leur autorisation avant l'effet irréversible.