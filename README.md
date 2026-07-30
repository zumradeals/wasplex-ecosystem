# Wasplex

Ce dépôt contient l’application Wasplex et sa documentation métier opérationnelle.

Wasplex n’est plus piloté par une Constitution, des amendements, des lois internes ou un Grand Livre. La référence de construction se trouve dans `docs/` : un fichier simple par domaine, rédigé pour expliquer directement le produit attendu.

## Ordre de lecture

1. `docs/00-vision-generale-wasplex.md`
2. le document métier du module concerné dans `docs/`
3. `CLAUDE.md` pour la méthode de travail
4. le code et les tests existants

## Règle de construction

Un écran, une table ou une route ne suffisent pas à déclarer une fonctionnalité terminée. Le parcours réel doit fonctionner de bout en bout et produire le résultat métier décrit dans `docs/`.

Lorsqu’une information commerciale ou une formule n’est pas décidée, elle doit rester indiquée comme décision ouverte. Elle ne doit pas être remplacée par une valeur fictive présentée comme réelle.

## Socle technique actuel

- Laravel / PHP
- PostgreSQL
- React / TypeScript
- Inertia / Vite
- monolithe modulaire

Le code, les tests, les migrations et le déploiement restent dans leurs répertoires techniques existants.