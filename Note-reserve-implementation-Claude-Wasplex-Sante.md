# P009 — Note de réserve d’implémentation Claude Code

## Wasplex Santé — Dossier patient, urgence, soins et services réglementés

**Statut :** réserve architecturale — ne pas exécuter sans ordre explicite de Koné  
**Date :** 2026-07-29  
**Dépôt cible :** `zumradeals/wasplex-ecosystem`  
**Application cible :** `apps/platform/`  
**Dépendance principale :** achèvement et validation du chantier `P008-A — Alertes & Protection`

---

# Instruction préalable absolue

Cette note préserve la vision et le plan du futur domaine Wasplex Santé. Elle n’autorise pas son exécution immédiate.

Claude Code ne doit commencer aucun fichier, aucune branche, aucune migration et aucune collecte de donnée médicale à partir de cette note tant que Koné n’a pas donné une instruction explicite proche de :

```text
P009-0 est ouvert. Commence la Constitution et la fondation Wasplex Santé.
```

Même après cette activation, Claude ne doit pas construire toutes les phases en une seule mission. Chaque phase possède sa branche, ses contrôles et son autorisation propres.

---

# 1. Vision du domaine

Wasplex Santé vise à donner à chaque personne une continuité médicale protégée, compréhensible et portable, depuis l’enfance jusqu’à l’âge adulte, tout en permettant aux professionnels habilités d’accéder aux informations nécessaires à la qualité des soins.

Le domaine peut couvrir à terme :

- identité patient ;
- carnet de santé longitudinal ;
- allergies, pathologies et traitements ;
- consultations et actes médicaux ;
- analyses et résultats de laboratoire ;
- prescriptions et médicaments ;
- vaccinations ;
- santé maternelle et infantile ;
- capsule médicale d’urgence ;
- établissements et professionnels de santé ;
- assurances et prises en charge ;
- paiements médicaux autorisés ;
- besoins de sang et orientation des donneurs volontaires ;
- coordination réglementée des cellules, tissus et organes.

Wasplex Santé n’est ni un médecin automatique, ni une compagnie d’assurance, ni une banque, ni un laboratoire, ni un service national de greffe. Il fournit une infrastructure de confiance, des contrats, des preuves, des autorisations et des parcours, en coopération avec des organismes légalement habilités.

## Formulation fondatrice

> Wasplex Santé protège la continuité médicale de la personne et facilite une circulation strictement autorisée des informations utiles aux soins. La personne demeure au centre de son dossier ; aucun acteur n’obtient un accès général à sa vie, et aucune donnée médicale ne devient un produit publicitaire.

---

# 2. Fusion avec Alertes, séparation technique

Wasplex peut présenter une expérience cohérente appelée :

```text
Wasplex Protection & Santé
```

Cette fusion concerne :

- la navigation ;
- les parcours d’urgence ;
- la transmission d’un SOS médical ;
- l’ouverture contrôlée d’une capsule médicale ;
- l’historique visible par la personne.

Elle ne fusionne jamais :

- les schémas PostgreSQL ;
- les permissions ;
- les finalités ;
- les durées de conservation ;
- les journaux métier ;
- les dossiers source.

Le module Alertes demande une capsule médicale par contrat. Il ne lit jamais directement les tables Santé.

Le module Santé ne lit jamais directement les tables Alertes. Il reçoit une demande d’urgence structurée et renvoie uniquement une projection médicale minimale, lorsque les conditions sont satisfaites.

---

# 3. Interdictions constitutionnelles

Wasplex Santé ne doit jamais :

1. vendre, louer ou exposer les données médicales ;
2. permettre un ciblage publicitaire fondé sur une pathologie, un handicap, un traitement, une grossesse, une analyse ou une urgence ;
3. constituer une fiche universelle mélangeant santé, casier judiciaire, publicité, situation financière, difficultés personnelles et généalogie ;
4. garantir un diagnostic ou remplacer un professionnel de santé ;
5. permettre à un professionnel de consulter tous les patients ;
6. permettre à un établissement de conserver un accès après la fin de sa finalité ;
7. cacher à la personne les accès effectués à son dossier, sauf restriction légale documentée et limitée ;
8. supprimer silencieusement un événement médical ou une preuve ;
9. modifier rétroactivement un résultat de laboratoire, une prescription ou un compte rendu ;
10. créer automatiquement un compte publicitaire ou commercial pour un nouveau-né ;
11. devenir une place de marché du sang, des cellules, tissus ou organes ;
12. rémunérer la cession d’un organe ;
13. décider seul de la compatibilité d’un donneur ou d’un receveur ;
14. activer des usages réels sans validation juridique, médicale, institutionnelle et de protection des données.

---

# 4. Références externes à consulter lors de l’ouverture

Au moment d’ouvrir réellement P009, Claude doit vérifier les versions actuelles et les exigences applicables, avec priorité aux sources officielles.

Références initiales :

- Organisation mondiale de la Santé — Digital Health :  
  `https://www.who.int/health-topics/digital-health`
- OMS — stratégie mondiale de santé numérique, notamment sécurité, confidentialité, interopérabilité et usage éthique :  
  `https://www.who.int/docs/default-source/documents/gs4dhdaa2a9f352b0445bafbc79ca799dce4d.pdf`
- OMS — architecture de référence pour les dossiers de santé numériques vérifiables :  
  `https://smart.who.int/ra/v0.1.0/`
- ARTCI — Loi ivoirienne n° 2013-450 relative à la protection des données à caractère personnel et décisions d’application concernant les données de santé ;
- OMS — sécurité et disponibilité du sang :  
  `https://www.who.int/news-room/fact-sheets/detail/blood-safety-and-availability`
- OMS — transplantation et risques éthiques :  
  `https://www.who.int/health-topics/transplantation`

Les références OMS orientent l’architecture mais ne remplacent ni le droit ivoirien, ni l’autorisation des autorités compétentes, ni les règles médicales nationales.

---

# 5. Conditions obligatoires avant toute donnée médicale réelle

Avant d’accepter la première donnée médicale réelle en production, les conditions suivantes doivent être remplies et documentées.

## 5.1 Gouvernance et droit

- responsable du traitement identifié ;
- finalités exactes définies ;
- bases juridiques vérifiées ;
- registre des traitements ;
- analyse d’impact sur la protection des données ;
- procédure d’exercice des droits ;
- règles spécifiques aux mineurs et représentants légaux ;
- politique de rétention et destruction ;
- procédure d’incident et notification ;
- validation des transferts hors de Côte d’Ivoire ;
- contrats avec les sous-traitants ;
- validation ARTCI ou formalités requises ;
- conseil juridique et médical documenté.

## 5.2 Sécurité

- stockage objet compatible S3 opérationnel pour documents ;
- chiffrement en transit et au repos ;
- stratégie de chiffrement applicatif des champs les plus sensibles ;
- clés gérées hors de la base de données ;
- rotation et révocation des clés ;
- sauvegardes chiffrées ;
- restauration testée ;
- antivirus et contrôle de format des pièces ;
- journal d’accès append-only ;
- MFA forte pour les professionnels ;
- séparation des environnements ;
- monitoring et procédure d’incident ;
- tests de sécurité indépendants avant ouverture publique.

## 5.3 Institutions

- établissements et ordres/professions vérifiables ;
- convention d’affiliation ;
- processus de vérification et révocation des professionnels ;
- responsables institutionnels nominatifs ;
- canaux de support et d’incident ;
- absence de comptes humains partagés.

Si une seule de ces conditions manque, le système peut continuer en environnement de test avec des données entièrement fictives, mais aucune donnée médicale réelle ne doit être importée ou créée.

---

# 6. Acteurs et identité

Les acteurs constitutionnels restent :

- Wasplex ;
- utilisateurs ;
- annonceurs ;
- institutions affiliées.

Un médecin, infirmier, pharmacien, biologiste, technicien, assureur ou responsable d’établissement est :

```text
une personne
+ une liaison compte-personne
+ une appartenance institutionnelle
+ une capacité
+ une finalité
+ une portée
+ une durée
```

Il ne devient pas un nouvel acteur constitutionnel.

## 6.1 Patient

Le patient est une personne référencée depuis le module Identity par un contrat stable. Le domaine Santé possède son dossier patient mais ne duplique pas les identifiants d’authentification.

Un patient peut exister sans compte de connexion, par exemple :

- nouveau-né ;
- mineur ;
- personne inconsciente ;
- personne créée par un établissement habilité selon une base légitime.

Cela crée un **sujet de soins protégé**, pas un compte commercial Wasplex.

## 6.2 Mineurs et représentants

Le système doit modéliser explicitement :

- représentant légal ;
- nature de l’autorité ;
- preuve ;
- date de début ;
- date d’expiration ;
- restrictions éventuelles ;
- suspension ou contestation ;
- transfert progressif des droits à l’enfant ;
- passage à la majorité.

Une simple relation familiale déclarée ne donne jamais automatiquement accès au dossier.

## 6.3 Professionnels

Chaque professionnel est vérifié par :

- identité ;
- organisation ;
- profession ;
- numéro ou preuve d’habilitation si applicable ;
- territoire ;
- période de validité ;
- statut actif, suspendu ou révoqué.

La plateforme ne doit pas présenter une personne comme médecin, laboratoire ou assureur sans preuve institutionnelle.

---

# 7. Architecture technique

Wasplex Santé reste dans le monolithe modulaire Laravel/PostgreSQL/React.

Créer à terme :

```text
App\Modules\Health
```

Schéma PostgreSQL :

```text
health
```

Le module Santé possède ses tables et expose :

- commandes ;
- requêtes autorisées ;
- projections ;
- événements ;
- contrats intermodules.

Il n’accorde aucun accès direct à ses tables aux modules Alertes, Advertising, Wallet ou Institutions.

## 7.1 Répartition des responsabilités

| Domaine | Responsabilité |
|---|---|
| Identity | personne, compte, liaisons et appartenances |
| Governance | capacités, finalités, autorisations et décisions |
| Configuration | paramètres versionnés |
| Health | dossier patient et événements de santé |
| Alerts | urgence et routage des secours |
| Wallet/Ledger | écritures financières |
| Advertising | aucune donnée médicale |
| Institutions | affiliations et utilisateurs habilités |

## 7.2 Données structurées et documents

- PostgreSQL conserve les données structurées, états, références, index et métadonnées.
- Les fichiers binaires médicaux sont stockés dans un stockage objet compatible S3.
- PostgreSQL ne stocke pas les fichiers médicaux lourds.
- Chaque fichier possède empreinte, type, taille, provenance, propriétaire, politique de rétention, statut antivirus et version.
- Une URL de stockage n’est jamais publique ; l’accès passe par une autorisation et un lien court signé.

## 7.3 Chiffrement

Les secrets, notes sensibles et certains identifiants médicaux utilisent un chiffrement applicatif par enveloppe lorsque le modèle de menace le justifie.

Les clés :

- ne résident pas dans les tables avec les données ;
- sont distinctes par environnement ;
- sont rotatives ;
- permettent une révocation ;
- produisent un audit d’usage.

Ne pas inventer une cryptographie maison.

## 7.4 Interopérabilité

Évaluer les standards d’interopérabilité de santé, notamment FHIR, mais ne pas importer toute leur complexité dans la première migration.

Le premier modèle interne doit :

- conserver des identifiants stables ;
- exprimer la provenance ;
- versionner les corrections ;
- utiliser des codes médicaux lorsqu’ils sont officiellement disponibles ;
- permettre un futur mapping vers un standard.

Le choix d’une version FHIR et d’un profil national nécessite une ADR distincte.

---

# 8. Modèle de données cible

Les noms exacts sont adaptés aux conventions du dépôt. Les concepts suivants doivent être couverts progressivement, pas nécessairement dans une seule migration.

## 8.1 Fondation patient

### `health.patients`

- UUID v7 ;
- référence de personne Identity ;
- statut ;
- pays et territoire de référence ;
- date de création ;
- origine du dossier ;
- fusion/duplication contestée ;
- état de vérification ;
- aucune information publicitaire.

### `health.patient_representations`

- patient ;
- représentant par liaison personne-compte ;
- base et nature de représentation ;
- preuve ;
- champs/actions autorisés ;
- début, fin, révocation ;
- état ;
- décisionnaire.

### `health.care_organizations`

Projection Santé d’une institution affiliée :

- organisation Identity ;
- catégorie ;
- licence ou preuve ;
- territoires ;
- état ;
- dates d’habilitation ;
- aucune duplication des données légales inutiles.

### `health.practitioner_credentials`

- personne et appartenance institutionnelle ;
- profession ;
- spécialité ;
- référence d’habilitation ;
- organisme émetteur ;
- dates ;
- état ;
- preuve.

## 8.2 Consentement et accès

### `health.consent_directives`

- patient ou représentant ;
- finalité ;
- catégorie de données ;
- organisation ou professionnel ;
- portée ;
- début et expiration ;
- version du texte accepté ;
- retrait ;
- exceptions légales ou vitales documentées.

### `health.access_events`

Append-only :

- demandeur ;
- organisation ;
- patient ;
- capacité ;
- finalité ;
- données effectivement consultées ;
- contexte de session ;
- résultat ;
- justification ;
- corrélation ;
- date ;
- accès normal ou urgence.

Le retrait du consentement empêche les nouveaux accès qui en dépendaient. Il ne détruit pas automatiquement les actes que la loi ou la continuité des soins impose de conserver.

## 8.3 Dossier clinique

### `health.encounters`

- patient ;
- établissement ;
- professionnel responsable ;
- type de rencontre ;
- début et fin ;
- statut ;
- motif ;
- provenance ;
- signature ou validation ;
- amendement éventuel.

### `health.conditions`

- condition ou diagnostic ;
- code et système de codage éventuel ;
- statut clinique et vérification ;
- auteur ;
- encounter source ;
- dates ;
- confidentialité renforcée éventuelle ;
- historique des corrections.

### `health.allergies`

- substance ;
- réaction ;
- gravité ;
- statut ;
- provenance ;
- vérification ;
- dates.

### `health.observations`

- type ;
- valeur et unité ;
- méthode ;
- intervalle de référence éventuel ;
- auteur ou appareil ;
- date ;
- statut ;
- provenance.

### `health.medication_records`

- médicament ;
- statut ;
- posologie structurée ;
- début et fin ;
- prescripteur ;
- prescription source ;
- raison de l’arrêt ;
- version.

### `health.immunizations`

- vaccin ;
- dose ;
- lot si autorisé ;
- établissement ;
- professionnel ;
- date ;
- preuve ;
- statut.

## 8.4 Laboratoire

### `health.lab_orders`

- prescripteur ;
- établissement ;
- patient ;
- examens demandés ;
- statut ;
- dates ;
- finalité.

### `health.lab_results`

- commande ;
- laboratoire ;
- résultat structuré ;
- unité ;
- plage de référence ;
- indicateur critique ;
- auteur/validateur ;
- date ;
- statut ;
- version et correction ;
- document signé éventuel.

Un résultat corrigé conserve l’ancienne version et la raison. Aucun résultat n’est écrasé.

## 8.5 Prescriptions et pharmacie

### `health.prescriptions`

- patient ;
- prescripteur habilité ;
- établissement ;
- lignes prescrites ;
- durée ;
- statut ;
- signature ;
- date ;
- renouvellement ;
- annulation et motif.

### `health.dispensing_events`

- prescription ;
- pharmacie affiliée ;
- professionnel ;
- quantité délivrée ;
- substitution autorisée ;
- date ;
- preuve ;
- aucun paiement directement écrit dans Santé.

## 8.6 Capsule médicale d’urgence

### `health.emergency_capsules`

- patient ;
- statut ;
- version ;
- responsable de validation ;
- consentement ou base d’urgence ;
- date de dernière vérification ;
- expiration de certaines données.

### `health.emergency_facts`

Uniquement :

- groupe sanguin vérifié ;
- allergies critiques ;
- pathologies critiques utiles aux secours ;
- traitements vitaux ;
- instructions urgentes vérifiées ;
- contact d’urgence ;
- provenance ;
- niveau de vérification ;
- date de fraîcheur.

Une donnée déclarée uniquement par l’utilisateur reste marquée comme telle. Elle ne reçoit pas le même niveau qu’une donnée validée par un professionnel ou laboratoire habilité.

---

# 9. Provenance et vérité médicale

Toute donnée clinique doit indiquer :

- qui l’a produite ;
- dans quelle organisation ;
- dans quel contexte ;
- à quelle date ;
- avec quelle méthode ;
- selon quel niveau de vérification ;
- si elle a été corrigée ;
- quelle version est courante.

Le système distingue :

```text
self_declared
practitioner_recorded
laboratory_verified
institution_verified
device_reported
imported_unverified
```

Les noms sont adaptés au catalogue normatif, mais la distinction ne doit pas disparaître.

Wasplex ne transforme jamais une déclaration personnelle en fait médical vérifié.

---

# 10. Capacités et autorisations

Les clés exactes doivent respecter le catalogue Governance. Familles prévues :

```text
health.patient.create
health.record.view_self
health.record.view_represented
health.record.view_scoped
health.encounter.create
health.encounter.amend
health.condition.record
health.allergy.record
health.observation.record
health.prescription.issue
health.prescription.dispense
health.lab_order.create
health.lab_result.record
health.lab_result.validate
health.emergency_capsule.manage_self
health.emergency_capsule.read
health.consent.manage
health.access_audit.view_self
health.insurance.submit
health.insurance.decide
health.blood_request.publish
health.blood_candidate.contact
```

Chaque décision combine :

- identité ;
- organisation ;
- appartenance ;
- capacité ;
- opération ;
- finalité ;
- patient ;
- relation de soins ;
- territoire ;
- durée ;
- catégorie de données ;
- assurance de session.

Un rôle ou métier n’accorde jamais à lui seul l’accès au dossier.

---

# 11. Bris de glace médical

Le bris de glace est une exception contrôlée pour un danger sérieux et immédiat.

## Conditions minimales

- professionnel ou secouriste institutionnel habilité ;
- organisation active ;
- authentification forte récente ;
- dossier ou SOS identifié ;
- finalité d’urgence ;
- justification obligatoire ;
- durée courte ;
- accès limité à la capsule ;
- journal append-only ;
- détection d’abus ;
- revue a posteriori ;
- information du patient lorsque cela est légalement et médicalement approprié.

## Interdictions

Le bris de glace ne permet pas :

- l’accès au dossier longitudinal complet par défaut ;
- l’export massif ;
- l’accès publicitaire ;
- la consultation sans événement d’urgence ;
- la modification des résultats ;
- l’accès financier au Wallet ;
- l’effacement des traces.

## Parcours Alertes

```text
SOS médical créé
→ institution reçoit
→ professionnel accepte
→ demande capsule avec finalité d’urgence
→ Governance décide
→ Santé produit une projection minimale
→ accès journalisé
→ urgence clôturée
→ autorisation temporaire expire
→ revue et information éventuelle du patient
```

---

# 12. Interfaces

## 12.1 Patient — mobile-first

Navigation future possible :

- Ma santé ;
- Urgence ;
- Mes consultations ;
- Résultats ;
- Ordonnances ;
- Médicaments ;
- Vaccins ;
- Assurances ;
- Accès à mon dossier ;
- Représentants et consentements.

L’écran principal doit montrer :

- dernières informations réellement disponibles ;
- alertes médicales vérifiées ;
- prochaine action utile ;
- provenance et niveau de vérification ;
- qui a consulté le dossier ;
- aucun diagnostic inventé.

## 12.2 Professionnel — desktop-first

- recherche d’un patient selon une relation ou un dossier autorisé ;
- contexte de soin ;
- informations pertinentes ;
- création d’un encounter ;
- observations ;
- prescriptions ;
- commandes de laboratoire ;
- historique des versions ;
- justification d’accès ;
- alertes de sécurité.

Le professionnel ne dispose jamais d’une « base de données des patients » librement explorée.

## 12.3 Établissement

- équipes et habilitations ;
- files de soins ;
- dossiers partagés selon mission ;
- incidents ;
- audit ;
- intégrations techniques ;
- statistiques agrégées autorisées.

## 12.4 Administration Wasplex

- affiliations ;
- vérifications ;
- incidents ;
- accès d’urgence ;
- demandes de droits ;
- qualité des données ;
- configuration ;
- audit.

Aucun employé Wasplex n’obtient un accès médical général par son appartenance interne.

---

# 13. Parcours fonctionnels

## 13.1 Création d’un dossier patient

1. Vérifier si une personne/dossier existe déjà.
2. Éviter les doublons.
3. Créer le patient avec origine et niveau de vérification.
4. Établir le représentant si nécessaire.
5. Enregistrer la finalité et la base de création.
6. Ne créer aucun profil publicitaire.

## 13.2 Consultation

1. Résoudre le professionnel et l’établissement.
2. Vérifier la relation de soins et la finalité.
3. Autoriser seulement les catégories utiles.
4. Créer l’encounter.
5. Ajouter les faits avec provenance.
6. Signer/valider.
7. Notifier ou rendre visible au patient selon la règle.

## 13.3 Laboratoire

1. Prescription ou demande autorisée.
2. Réception par laboratoire affilié.
3. Prélèvement et identifiants.
4. Résultat structuré.
5. Validation nominative.
6. Alerte critique selon protocole.
7. Publication au patient et prescripteur.
8. Correction par nouvelle version.

## 13.4 Prescription et délivrance

1. Prescripteur habilité.
2. Prescription signée.
3. Pharmacie autorisée.
4. Vérification de validité.
5. Délivrance enregistrée.
6. Paiement éventuel demandé au Wallet par contrat séparé.
7. Santé reçoit seulement le résultat de paiement nécessaire, pas l’accès au ledger.

## 13.5 Assurance

1. Couverture vérifiée.
2. Consentement/finalité.
3. Dossier de prise en charge minimisé.
4. Envoi à l’assureur habilité.
5. État `submitted`, `received`, `under_review`, `approved`, `partially_approved`, `rejected`, `paid` ou `unknown`.
6. Rapprochement financier dans Wallet/Ledger.
7. Aucun statut inventé.

---

# 14. Paiements médicaux

Santé ne modifie jamais un solde.

Toute opération financière :

- utilise une commande Wallet/Ledger ;
- possède une idempotency key ;
- référence patient, facture, établissement et finalité ;
- distingue montant brut, frais, assurance, reste à charge et remboursement ;
- reste atomique et rapprochable ;
- ne marque jamais payé sans preuve externe ou ledger.

Le dossier médical peut référencer une transaction, mais ne contient pas les écritures comptables internes.

Les paramètres de tarification, frais, plafonds et couvertures sont configurables et versionnés. Aucun pourcentage inventé dans le code.

---

# 15. Sang

Wasplex peut faciliter une demande de sang uniquement avec un service de santé ou de transfusion habilité.

## Règles

- la demande provient d’un établissement vérifié ;
- le besoin, groupe, composant, territoire et durée sont définis ;
- les donneurs sont volontaires et consentants ;
- Wasplex produit des candidats de contact, jamais une décision de compatibilité ;
- l’éligibilité finale, le prélèvement, les analyses et la transfusion appartiennent aux professionnels ;
- aucune publicité ne cible une maladie ou un patient ;
- aucune publication n’expose l’identité du receveur ;
- les dons sont soumis aux dépistages et systèmes qualité applicables ;
- aucun WP n’est accordé automatiquement pour le sang ;
- toute indemnité éventuelle exige une décision juridique et institutionnelle distincte.

Les recommandations de l’OMS privilégient les dons volontaires non rémunérés et le dépistage des infections avant utilisation. Wasplex doit s’aligner sur les autorités transfusionnelles compétentes, pas inventer ses propres critères.

---

# 16. Cellules, tissus et organes

Ce sous-domaine reste fermé par défaut.

Il ne peut être ouvert qu’après :

- décision constitutionnelle dédiée ;
- avis juridique ;
- autorité nationale compétente ;
- établissements de transplantation habilités ;
- protocole éthique et médical ;
- protection du donneur ;
- traçabilité ;
- lutte contre le trafic ;
- interdiction de commercialisation ;
- supervision indépendante.

Wasplex ne doit jamais :

- publier une annonce « organe recherché » au grand public comme un bien ;
- mettre directement donneur et receveur en relation ;
- afficher un prix ;
- rémunérer un organe ;
- sélectionner médicalement le bénéficiaire ;
- contourner une liste ou autorité officielle.

Le rôle éventuel de Wasplex serait limité à une coordination institutionnelle autorisée et auditée.

---

# 17. Intelligence artificielle

Aucun diagnostic généré par IA ne doit être ajouté implicitement.

Une future IA de santé exigerait une décision séparée couvrant :

- usage exact ;
- données d’entraînement ;
- performances ;
- biais ;
- explicabilité ;
- supervision humaine ;
- responsabilité ;
- validation clinique ;
- statut réglementaire ;
- surveillance post-déploiement.

Dans les premières phases, l’IA peut éventuellement aider à classer un document ou préparer une saisie, mais toute donnée clinique reste validée par une personne habilitée et la provenance de l’assistance est conservée.

---

# 18. Phasage d’exécution

## P009-0 — Constitution, droit et architecture

Objectif :

- répondre aux questions fondatrices ;
- adopter AMD Santé ;
- rédiger ADR de sécurité, données, interopérabilité et consentement ;
- définir les contrats de modules ;
- obtenir les validations juridiques/médicales initiales ;
- aucun code médical réel.

Livrables :

- Constitution Santé ;
- matrice des finalités ;
- matrice des données ;
- matrice des accès ;
- modèle de menace ;
- politique de rétention ;
- plan d’analyse d’impact ;
- plan d’incident ;
- architecture d’interopérabilité ;
- UX fondatrice.

## P009-A — Fondation patient et professionnels

Objectif :

- patients ;
- représentants ;
- établissements ;
- professionnels ;
- consentements ;
- accès et audit ;
- aucun dossier clinique complexe.

Écrans :

- création/liaison du patient ;
- représentants ;
- profil établissement/professionnel ;
- consentements ;
- historique des accès.

## P009-B — Capsule médicale d’urgence

Objectif :

- données vitales minimales ;
- provenance et vérification ;
- contrat avec Alertes ;
- bris de glace ;
- audit et expiration.

C’est seulement à cette phase que le provider indisponible créé dans P008-A reçoit une implémentation réelle.

## P009-C — Carnet médical longitudinal

Objectif :

- encounters ;
- conditions ;
- allergies ;
- observations ;
- traitements ;
- vaccins ;
- documents ;
- historique et corrections.

## P009-D — Laboratoires, prescriptions, pharmacies et assurance

Objectif :

- commandes et résultats ;
- prescription signée ;
- délivrance ;
- prise en charge ;
- intégration Wallet/Ledger ;
- rapprochement.

## P009-E — Sang et coordination hautement réglementée

Objectif :

- demandes institutionnelles de sang ;
- volontariat ;
- orientation ;
- contrôles ;
- aucune compatibilité décidée par Wasplex.

Cellules, tissus et organes restent un chantier ultérieur indépendant, même après P009-E.

---

# 19. Tests transversaux obligatoires

Chaque phase conserve tous les tests existants et ajoute les tests correspondant à son périmètre.

## 19.1 Autorisation

- patient voit son dossier ;
- représentant actif voit seulement le périmètre autorisé ;
- représentant expiré ou contesté refusé ;
- professionnel sans relation de soins refusé ;
- professionnel hors organisation refusé ;
- organisation suspendue refusée ;
- accès hors finalité refusé ;
- session faible déclenche step-up ;
- accès d’urgence strictement limité ;
- aucun accès croisé entre établissements.

## 19.2 Provenance

- déclaration utilisateur distincte d’une validation médicale ;
- laboratoire seul peut valider son résultat ;
- correction crée une nouvelle version ;
- historique non réécrit ;
- provenance affichée.

## 19.3 Confidentialité

- aucune donnée Santé dans Advertising ;
- aucune donnée médicale dans le Feed ;
- aucune exportation implicite ;
- documents servis par liens courts autorisés ;
- journal d’accès sans contenu médical excessif ;
- suppression publique distincte de la rétention clinique légale.

## 19.4 Urgence

- provider indisponible produit un état honnête ;
- accès sans urgence refusé ;
- MFA absente : step-up ;
- justification absente : refus ;
- projection limitée aux champs vitaux ;
- expiration automatique ;
- audit et revue ;
- fermeture SOS n’efface pas l’accès historique.

## 19.5 Finance

- Santé ne modifie jamais le ledger ;
- paiement idempotent ;
- statut externe inconnu reste `unknown` ;
- remboursement par opération compensatoire ;
- aucune couverture d’assurance inventée.

## 19.6 Mineurs

- nouveau-né sans compte commercial ;
- représentant vérifié ;
- accès limité ;
- changement à la majorité ;
- conflit de garde suspendant l’accès ;
- journal complet.

## 19.7 Sécurité

- chiffrement/déchiffrement autorisé ;
- clé absente : échec fermé ;
- fichier infecté ou type interdit rejeté ;
- URL signée expirée ;
- rate limiting ;
- logs sans secret ;
- tests de restauration ;
- tests d’isolement production/test.

---

# 20. UX et vérité affichée

Chaque écran doit gérer :

- chargement ;
- vide ;
- erreur ;
- hors ligne ;
- information non vérifiée ;
- information périmée ;
- accès refusé ;
- consentement requis ;
- step-up requis ;
- indisponibilité institutionnelle ;
- opération externe en attente ;
- donnée corrigée.

Les formulations distinguent :

- « déclaré par vous » ;
- « enregistré par un professionnel » ;
- « vérifié par un laboratoire » ;
- « en attente » ;
- « inconnu » ;
- « expiré ».

Ne jamais afficher :

- « vous êtes compatible » sans établissement compétent ;
- « assuré » sans confirmation ;
- « payé » sans preuve ;
- « diagnostic confirmé » sans provenance habilitée ;
- « les secours ont accès » sans décision d’autorisation réussie.

---

# 21. Dette technique

Lorsqu’un défaut non bloquant apparaît :

1. vérifier qu’il ne compromet pas la sécurité du patient, la confidentialité, l’intégrité clinique, l’autorisation ou l’argent ;
2. l’inscrire dans le catalogue de dette technique existant ;
3. indiquer risque, mesure temporaire et condition de reprise ;
4. éviter d’élargir automatiquement le lot.

Sont toujours bloquants :

- fuite médicale ;
- mélange de patients ;
- autorisation contournable ;
- résultat ou prescription modifiable sans historique ;
- identité professionnelle non vérifiable ;
- faux paiement ;
- corruption ou perte de dossier ;
- sauvegarde non restaurable ;
- secret committé ;
- usage publicitaire.

---

# 22. Déploiement progressif

Le module Santé doit utiliser des feature flags côté serveur.

Ordre recommandé :

1. environnement de test avec données fictives ;
2. démonstration fermée ;
3. établissement pilote contractuel ;
4. nombre limité de professionnels ;
5. capsule d’urgence pilote ;
6. audit de sécurité et de conformité ;
7. montée progressive.

Aucune donnée fictive n’est présentée comme réelle en production.

Un environnement de démonstration utilise des patients synthétiques clairement identifiés et entièrement séparés.

---

# 23. Critères de réussite

Wasplex Santé est réussi si :

- la personne comprend qui détient et consulte ses informations ;
- les professionnels disposent de données utiles, actuelles et vérifiables ;
- les établissements ne voient que leurs dossiers autorisés ;
- une urgence peut obtenir rapidement une capsule vitale sans ouvrir toute la vie médicale ;
- les corrections restent traçables ;
- les mineurs et personnes vulnérables sont protégés ;
- Wallet et Santé restent comptablement séparés ;
- aucune donnée médicale ne rejoint la publicité ;
- aucun mécanisme de sang ou greffe ne contourne les institutions compétentes ;
- le système reste utilisable sur mobile et réseau faible ;
- la confiance prime sur la quantité de données collectées.

---

# 24. Format de mission lors de l’activation

Lorsque Koné ouvrira une phase, Claude devra répondre avec :

```text
P009-[PHASE] — DOSSIER WASPLEX SANTÉ

Branche :
Commit de base :
Sources normatives :
Validation juridique/médicale disponible :
Base réellement utilisée :

Objectif :
Résultat observable :
Architecture :
Schéma :
Capacités :
Finalités :
Consentements :
Audit :
Sécurité :
Interfaces :

Tests :
Contrôles qualité :
Parcours navigateur :
Captures :

Données réelles :
Secrets :
État Git :
Déploiement :

Dettes techniques :
Risques :
Éléments différés :
Questions bloquantes :
```

Claude ne doit jamais présenter une phase ultérieure comme réalisée.

---

# 25. Rappel final à Claude

Wasplex Santé est un domaine vital. La vitesse de construction ne doit jamais être obtenue en mélangeant les dossiers, en abaissant les autorisations, en inventant une vérité médicale ou en exposant une personne.

Construire progressivement :

```text
Constitution
→ identité patient
→ consentement et autorisation
→ capsule d’urgence
→ carnet médical
→ laboratoires et prescriptions
→ assurance et paiements
→ sang réglementé
→ éventuels usages de transplantation sous autorité compétente
```

Ne pas exécuter cette note avant l’ordre explicite de Koné.

