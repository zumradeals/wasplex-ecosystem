# Santé — Frontière fondatrice avec Alertes

**Statut :** spécification fondatrice, domaine non construit — P008-A
**Date :** 2026-07-29
**Dépendances :** Constitution v1.7 (article 23, AMD-0016), AMD-0006, AMD-0007, AMD-0009
**Portée :** ce document fixe uniquement la frontière entre Alertes et le futur domaine Santé. Le plan complet du domaine Santé (patients, dossiers cliniques, laboratoires, prescriptions, assurance, sang, transplantation) est réservé à une note d'implémentation distincte (« P009 — Wasplex Santé ») et n'est ni activé ni construit par ce document ni par P008-A.

## 1. Pourquoi ce document existe déjà

Le module Alertes (P008-A) doit savoir, dès sa première tranche, qu'une urgence peut un jour avoir besoin d'une information médicale minimale — sans que cela justifie de collecter, stocker ou deviner la moindre donnée médicale réelle aujourd'hui. Ce document fixe la frontière que P008-A respecte, pas le contenu du futur domaine Santé.

## 2. Formulation fondatrice (article 23, AMD-0016)

> Wasplex Alertes et le futur Wasplex Santé forment un système intégré de protection de la personne. Ils peuvent partager une expérience utilisateur et des parcours d'urgence, mais conservent des données, permissions, responsabilités, rétentions et journaux distincts. Alertes ne reçoit jamais le dossier médical longitudinal. Il ne peut demander qu'une capsule médicale d'urgence minimale, temporaire et auditée.

## 3. Ce que Santé pourra un jour fournir à Alertes — et rien de plus

Lorsque le domaine Santé existera (P009), le seul point de contact avec Alertes sera une capsule médicale d'urgence limitée à :

- groupe sanguin vérifié ;
- allergies critiques vérifiées ;
- pathologies critiques pertinentes pour les secours ;
- traitements vitaux ;
- contact d'urgence ;
- provenance, niveau de vérification et date de fraîcheur de chaque fait.

Cette capsule ne contient jamais : le dossier médical complet, un historique judiciaire, un profil publicitaire, une généalogie, ou toute information sans pertinence immédiate pour une intervention de secours.

## 4. Provenance et vérité médicale (principe, pas encore d'implémentation)

Toute donnée médicale future distinguera qui l'a produite, dans quelle organisation, à quelle date, avec quelle méthode et selon quel niveau de vérification. Une déclaration de la personne elle-même ne devient jamais, par simple affichage, un fait médical vérifié. Ce principe gouvernera Santé dès sa fondation (P009) ; il n'a aucune donnée à gouverner aujourd'hui puisqu'aucune donnée médicale réelle n'existe encore dans Wasplex.

## 5. Bris de glace médical (anticipé, non activé)

Un futur accès d'urgence à la capsule médicale exigera au minimum : une capacité critique dédiée, une organisation et un professionnel habilités, une authentification forte récente, un dossier ou SOS Alertes identifié, une finalité d'urgence réelle, une justification, une durée courte, un journal append-only et une revue a posteriori. Ces conditions sont documentées pour que la future implémentation (P009-B) ne les invente pas a posteriori ; aucune capacité correspondante n'est déclarée, activée ou simulée par P008-A.

## 6. Interdiction absolue dans ce lot

P008-A :

- ne crée aucune table `health.*` ;
- ne collecte, n'affiche ni ne déduit aucune donnée médicale réelle ou fictive présentée comme réelle ;
- n'implémente qu'un fournisseur de capsule par défaut, qui répond systématiquement par une indisponibilité explicite (`EmergencyHealthSnapshotProvider` → `EmergencyHealthSnapshotUnavailable`) ;
- garantit qu'Alertes fonctionne intégralement, sans dégradation ni message trompeur, en l'absence totale de domaine Santé.

## 7. Éléments explicitement différés à P009

Carnet médical longitudinal, comptes professionnels de santé complets, laboratoires, prescriptions, médicaments, vaccins, assurance santé, paiements médicaux, demandes de sang, coordination des cellules/tissus/organes, et toute intelligence artificielle de santé. Chacun de ces éléments exige sa propre gouvernance juridique, médicale et sécuritaire avant toute donnée réelle (voir la note de réserve P009, section « Conditions obligatoires avant toute donnée médicale réelle »).
