# Wasplex Ecosystem

Dépôt normatif et architectural de l'écosystème Wasplex.

La Constitution, les amendements, décisions, spécifications métier, ADR, parcours UX et le catalogue UI exécutable constituent le Grand Livre transmis aux équipes humaines et aux IA de développement.

## État de référence

- Constitution v1.5 adoptée ;
- AMD-0001 à AMD-0015 adoptés ; AMD-0015 attend sa consolidation dans la Constitution v1.6 ;
- ADR-0001 à ADR-0009 adoptés ;
- DS-0001 adopté ;
- UX-0001 à UX-0003 adoptés ;
- catalogue React/TypeScript initial dans `ui-catalogue/`.

## Ordre de lecture

1. `CONSTITUTION.md`
2. `GLOSSARY.md`
3. `decisions/README.md`
4. amendements et spécifications du domaine concerné
5. ADR adoptés dans `decisions/`, puis leurs spécifications dans `architecture/`
6. DS et documents `design/`
7. décisions et documents `ux/`
8. `CLAUDE.md` avant toute exécution avec Claude Code

Une maquette, un prompt ou une implémentation ne peut contredire une règle supérieure adoptée.

Les brouillons d'architecture historiques non normatifs sont isolés dans `history/architecture-drafts/`. Ils ne doivent jamais être utilisés à la place des ADR adoptés.

## Architecture officielle

Monolithe modulaire Laravel, PostgreSQL, React, TypeScript, Inertia et Vite. Supabase ne fait pas partie de l'architecture officielle.

## Branche principale

`main` est la branche stable de référence. Les branches historiques restent consultables tant qu'elles ne sont pas explicitement archivées ou supprimées.
