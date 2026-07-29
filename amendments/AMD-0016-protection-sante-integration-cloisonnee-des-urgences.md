# AMD-0016 — Protection, Santé et intégration cloisonnée des urgences

**État :** adopté par le fondateur — intégration proposée à la Constitution v1.7
**Date :** 29 juillet 2026
**Source :** Note d'implémentation Claude Code « P008-A — Alertes, protection institutionnelle et passerelle Santé d'urgence » (2026-07-29), confirmée en conversation le même jour.
**Amendements liés :** AMD-0006 (institutions), AMD-0007 (alertes), AMD-0009 (données)

## Motif

Le module Alertes (AMD-0007) et le futur domaine Wasplex Santé partagent une même finalité de protection de la personne en situation d'urgence, mais AMD-0007 ne précise pas comment ces deux domaines doivent coexister sans que l'un absorbe les données ou les permissions de l'autre. Sans ce cadre, la construction d'Alertes risquerait soit d'ignorer le futur besoin médical d'urgence, soit d'anticiper une fusion de dossiers que rien ne justifie et que la Constitution interdirait par ailleurs (article 8/AMD-0009 : cloisonnement de la santé du profil publicitaire ; article 13/AMD-0006 : aucune capacité financière ou sectorielle activée sans vérification propre).

## Décision fondatrice

> Wasplex Alertes et le futur Wasplex Santé forment un système intégré de protection de la personne. Ils peuvent partager une expérience utilisateur et des parcours d'urgence, mais conservent des données, permissions, responsabilités, rétentions et journaux distincts. Alertes ne reçoit jamais le dossier médical longitudinal. Il ne peut demander qu'une capsule médicale d'urgence minimale, temporaire et auditée.

## Article proposé

1. **Intégration produit.** Wasplex peut présenter une expérience cohérente réunissant Alertes et Santé (par exemple « Protection & Santé »), incluant une navigation commune et des parcours d'urgence partagés.
2. **Séparation obligatoire des domaines.** Cette intégration ne fusionne jamais les schémas de données, les permissions, les finalités, les durées de conservation ni les journaux métier d'Alertes et de Santé. Chaque domaine reste propriétaire de ses propres tables au sens de l'architecture en monolithe modulaire déjà adoptée.
3. **Capsule médicale d'urgence minimale.** Alertes ne peut demander à Santé qu'une capsule médicale d'urgence — groupe sanguin vérifié, allergies critiques, pathologies critiques pertinentes, traitements vitaux, contact d'urgence, provenance et niveau de vérification — jamais le dossier médical longitudinal complet, un historique judiciaire, un profil publicitaire, une généalogie ou toute information sans pertinence immédiate pour les secours.
4. **Accès d'urgence temporaire et audité.** Toute lecture de la capsule médicale exige une capacité critique dédiée, une finalité d'urgence réelle, une durée courte, une justification, un journal append-only et une revue a posteriori. Cette capacité peut être documentée par anticipation mais ne doit être ni auto-attribuée ni activée artificiellement avant qu'un besoin réel et vérifié existe.
5. **Interdiction du profil universel.** Aucune fiche ne réunit santé, casier judiciaire, publicité, situation financière ou généalogie d'une personne. Police, gendarmerie, secours et santé ne disposent jamais d'une recherche générale dans l'ensemble de la vie d'une personne.
6. **Interdiction publicitaire absolue.** Les données médicales, de sécurité, de SOS ou d'alerte ne sont jamais utilisées à des fins publicitaires, de ciblage ou de profilage commercial, conformément à l'article 8 et à AMD-0009.
7. **Vocabulaire.** Le terme métier « Agent » demeure interdit (article 7). Un témoin de restitution reste un participant ou attestateur nominatif d'un dossier, jamais un nouvel acteur constitutionnel.
8. **Différé du dossier Santé complet.** Le dossier médical longitudinal, les comptes professionnels de santé complets, les laboratoires, prescriptions, médicaments, assurances, dons de sang et la coordination réglementée des greffes constituent un chantier distinct (Wasplex Santé), non construit par le présent amendement et soumis à ses propres conditions préalables (gouvernance juridique, sécurité, validation institutionnelle) avant toute donnée médicale réelle.

## Décision d'adoption

Le fondateur a validé cette décision le 2026-07-29, en autorisant explicitement la construction de la première tranche du module Alertes (P008-A) sur cette base, tout en réservant strictement l'ouverture du domaine Santé à une instruction ultérieure et distincte (P009).

## Effet de l'adoption

Cet amendement introduit l'article 23 de la Constitution (Protection, Santé et intégration cloisonnée des urgences) et ne modifie ni ne réduit les articles 13 et 14 déjà adoptés (AMD-0006, AMD-0007) : il précise leur articulation avec un domaine Santé qui n'existe pas encore en production.
