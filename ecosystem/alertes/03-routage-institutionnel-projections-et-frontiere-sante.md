# Alertes — Routage institutionnel, projections publiques et frontière Santé

**Statut :** spécification métier — P008-A
**Date :** 2026-07-29
**Dépendances :** Constitution v1.7 (article 23, AMD-0016), AMD-0006, AMD-0007, `ecosystem/alertes/02-cycle-alertes-restitutions.md`, `ecosystem/institutions/01-affiliation-capacites-responsabilites.md`

Ce document ne répète pas les règles déjà fixées ailleurs ; il les articule pour la première tranche technique du module Alertes (P008-A). En cas de doute sur une machine d'états ou une définition déjà posée, la source citée fait foi.

## 1. Routage institutionnel

### 1.1 Principe

Un dossier n'est jamais visible par une institution du seul fait de son existence. Il est **transmis** à une organisation affiliée précise, selon une décision de routage fondée sur :

- la catégorie du dossier (`ecosystem/alertes/02` §1, §8) ;
- la compétence sectorielle de l'organisation (`ecosystem/institutions/01` §1) ;
- le territoire du dossier et le territoire de compétence de l'organisation ;
- l'état d'affiliation de l'organisation (`ecosystem/institutions/01` §2 : seules `active` et, selon restriction, `restricted` peuvent recevoir) ;
- l'existence d'une capacité active et non expirée détenue par au moins un utilisateur institutionnel de cette organisation pour cette catégorie.

Un dossier sans organisation éligible reste dans l'état `created` (ou `transmitted` sans destinataire réel) : Wasplex n'invente jamais un routage de complaisance. L'interface affiche alors littéralement « aucune institution disponible pour cette catégorie et ce territoire », jamais une transmission simulée.

### 1.2 Une transmission, un dossier de routage

Chaque transmission crée un enregistrement de routage distinct (`alerts.institution_dispatches`), séparé du dossier source (`alerts.cases`). Un même dossier peut faire l'objet de plusieurs transmissions successives (transfert vers une autre organisation), mais jamais de deux transmissions actives simultanées vers la **même** organisation — un doublon est refusé ou absorbé (idempotence), jamais dupliqué silencieusement.

### 1.3 Machine d'états de la transmission

La machine d'états et le principe « la transmission n'est pas une réception ; la réception n'est pas une acceptation ; l'acceptation n'est pas une intervention réussie » sont définis intégralement par `ecosystem/institutions/01` §6 et `ecosystem/alertes/02` §3. Ce document n'en donne pas une seconde version : le code applique exactement ce tableau, jamais une variante locale.

### 1.4 Capacités atomiques

Les capacités listées par la mission P008-A (`alert.case.submit`, `.view_self`, `.review`, `.publish`, `.receive`, `.acknowledge`, `.accept`, `.process`, `.transfer`, `.resolve`, `alert.match.propose`, `.validate`, `alert.return.verify`) suivent le modèle d'habilitation de `ecosystem/institutions/01` §4 : `organisation + utilisateur + capacité + finalité + territoire + catégorie + durée + base d'accès`. Aucune capacité n'accorde un accès global : `alert.case.receive` sur la catégorie `medical_emergency` ne donne aucun droit sur `stolen_vehicle`, et réciproquement.

Les capacités nationales critiques (émission d'une alerte nationale) restent hors périmètre de P008-A (AMD-0007 §17 ; Constitution article 14.17-21) : aucune route réelle d'émission n'est activée par ce lot, quelle que soit la capacité documentée.

## 2. Projections publiques

### 2.1 Séparation dossier source / projection

`alerts.publications` est la seule table lue par le Feed et par l'espace Alertes public. Elle ne référence le dossier source (`alerts.cases`) que par identifiant ; aucune colonne sensible du dossier source (position exacte, téléphone, document complet, réponse de vérification de correspondance, témoin) n'existe dans la projection, pas seulement masquée à l'affichage — absente de la table elle-même.

### 2.2 Politique par catégorie

Chaque catégorie définit, via le registre de configuration existant (Governance/Configuration, ADR-0002), l'ensemble maximal de champs publiables (`allowed_fields`). Le déclarant peut réduire cet ensemble avant publication ; il ne peut jamais l'élargir au-delà du maximum sûr défini par la politique (`ecosystem/alertes/02` §5).

### 2.3 Catégories sensibles

`missing_person`, `found_person`, les mineurs, personnes vulnérables et documents d'identité exigent une revue renforcée avant toute publication (AMD-0007 §8 ; `ecosystem/alertes/02` §6). Aucune de ces catégories n'est publiée automatiquement, même après vérification technique minimale.

### 2.4 Retrait

Le retrait d'une publication (résolution, expiration, fraude, danger découvert) masque la diffusion sans détruire le dossier source, ses événements, preuves ou transmissions (AMD-0007 §15 ; Constitution article 14.15).

## 3. Frontière fonctionnelle entre Alertes et Santé

### 3.1 Principe (article 23, AMD-0016)

Alertes et le futur domaine Santé forment une expérience intégrée mais des systèmes de données séparés. Alertes ne lit jamais directement une table Santé. Santé ne lit jamais directement une table Alertes.

### 3.2 Contrat, pas couplage

L'unique point de contact est un contrat applicatif (`EmergencyHealthSnapshotProvider`), pas une jointure ni un accès direct :

- Alertes formule une demande structurée (dossier ou SOS identifié, finalité d'urgence) ;
- le fournisseur Santé, lorsqu'il existera, répond par une capsule minimale ou par une indisponibilité explicite ;
- P008-A n'implémente que le fournisseur par défaut, qui retourne toujours l'indisponibilité — Alertes fonctionne intégralement sans Santé.

### 3.3 Bris de glace (anticipé, non activé)

Les conditions du bris de glace médical (capacité critique, finalité d'urgence, authentification forte, justification, durée courte, audit append-only, revue a posteriori) sont documentées par la réserve d'implémentation Wasplex Santé (P009) et ne sont ni activées ni simulées par P008-A.

## 4. Confidentialité et anonymat

Un SOS peut être créé sans authentification complète (AMD-0007 §2 ; Constitution article 14.2). Les données minimales requises, les limites anti-abus et le marquage `unverified` sont fixés par `ecosystem/alertes/02` §2. Aucune biométrie ni reconnaissance faciale n'est utilisée implicitement (AMD-0007 §14 ; Constitution article 14.14).

## 5. Preuve et vérité des états

Aucun statut n'est affiché sans événement probant correspondant (AMD-0007 §5-6 ; `ecosystem/institutions/01` §6). Une absence de destinataire, de canal fonctionnel ou d'accusé se traduit par un état honnête (`unanswered`, `impossible`, ou un message explicite d'indisponibilité), jamais par une animation de succès.

## 6. Escalade et souveraineté institutionnelle

Le transfert d'un dossier d'une organisation à une autre (état `transferred`) reste un routage ordinaire, gouverné par les mêmes règles de compétence et territoire que le routage initial.

L'alerte nationale critique reste un domaine distinct, régi intégralement par AMD-0007 §17-21 et Constitution article 14.17-21 : seule une institution souveraine explicitement habilitée peut l'émettre, avec authentification renforcée et double validation nominative. P008-A ne construit aucune route d'émission réelle à ce niveau ; le module Alertes se contente de savoir *afficher* une telle alerte si elle est reçue via un canal déjà autorisé par ailleurs, sans jamais en fabriquer l'autorité.
