# ADR-0004 — Habilitations institutionnelles par capacités et portées — brouillon historique non normatif

**État :** proposé  
**Date :** 2026-07-21

## Décision proposée

Combiner contrôle par rôles et contrôle par attributs.

Le rôle fournit un ensemble initial de capacités. Les attributs restreignent leur exercice par organisation, territoire, catégorie, finalité, sensibilité, dossier et période.

Une simple vérification de rôle telle que `is_police` ou `is_hospital` est insuffisante.

## Modèle minimal

- organizations ;
- organization_verifications ;
- organization_memberships ;
- institutional_capabilities ;
- capability_grants ;
- territorial_scopes ;
- case_assignments ;
- access_justifications ;
- institutional_audit_events ;
- technical_credentials ;
- incidents et suspensions.

## Règles

- refus par défaut ;
- aucune permission implicite par catégorie d'organisation ;
- autorisation recalculée côté serveur à chaque opération sensible ;
- champs de réponse construits selon la capacité ;
- expiration automatique des accès ;
- réauthentification pour les actes critiques ;
- idempotence des transmissions et confirmations ;
- journal append-only ;
- tests automatisés de non-accès entre domaines ;
- révocation immédiate propagée aux sessions et intégrations.

## Conséquence

Cette approche demande davantage de configuration qu'un portail unique par type d'institution, mais empêche qu'une organisation légitime obtienne des données sans rapport avec sa mission.
