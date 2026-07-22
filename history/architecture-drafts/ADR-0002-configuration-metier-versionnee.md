# ADR-0002 — Configuration métier administrable et versionnée — brouillon historique non normatif

- **État :** proposé
- **Date :** 2026-07-21
- **Source :** clarification du fondateur 03

## Contexte

Le fondateur exige que les abonnements, quotas, ventilations et autres paramètres métier ne soient pas codés en dur et puissent être gérés depuis l'administration.

## Décision proposée

Les paramètres métier variables sont stockés dans un registre de configuration administrable, typé, versionné et audité.

Chaque paramètre possède au minimum :

- une clé stable ;
- un type ;
- une unité ;
- une valeur ;
- des bornes autorisées ;
- une date d'effet ;
- un état brouillon, validé, actif ou retiré ;
- l'auteur et l'approbateur ;
- une justification ;
- un historique immuable.

## Distinction fondamentale

Tout ne doit pas être modifiable depuis l'administration.

### Administrable

- prix ;
- quotas ;
- coefficients ;
- pourcentages dans les bornes constitutionnelles ;
- seuils opérationnels ;
- activation contrôlée d'une fonctionnalité.

### Non administrable sans changement de code ou amendement

- interdiction de vendre les données ;
- nécessité d'une preuve avant facturation ;
- absence de double rémunération ;
- droits d'accès ;
- règles de sécurité ;
- invariants constitutionnels ;
- contraintes garantissant l'équilibre d'une ventilation.

## Application temporelle

Une campagne, un abonnement ou une transaction conserve un instantané de la version de configuration ayant gouverné sa création.

Une modification :

- ne réécrit pas l'histoire ;
- ne modifie pas silencieusement une campagne active ;
- possède une date d'effet ;
- peut être simulée avant activation ;
- peut être retirée par une nouvelle version, jamais par suppression de l'ancienne.

## Contrôles proposés

- validation à deux personnes pour les paramètres financiers critiques ;
- somme des ventilations vérifiée automatiquement ;
- seuil minimal de part utilisateur si constitutionnalisé ;
- aperçu de l'impact financier ;
- journal d'audit ;
- retour à une version antérieure par réactivation contrôlée ;
- tests automatisés des invariants.

## Conséquences

Le code contient les mécanismes et les garde-fous. L'administration contrôle les paramètres autorisés sans pouvoir désactiver les principes fondateurs.
