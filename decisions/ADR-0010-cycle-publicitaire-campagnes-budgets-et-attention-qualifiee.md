# ADR-0010 — Cycle publicitaire : campagnes, budgets et attention qualifiée

**État :** adopté par le fondateur
**Date :** 23 juillet 2026
**Décideur architectural :** SIRR, sur mandat du fondateur
**Dépendances :** Constitution v1.5, AMD-0001, AMD-0002, AMD-0009, AMD-0010, AMD-0013, ADR-0002, ADR-0003, ADR-0004, ADR-0006
**Sources :** ecosystem/publicite/01-cycle-creation-valeur.md, 01-classification-secteurs-et-contenus.md, 02-cycle-financier-campagne.md, 02-preuves-moderation-et-destinations.md, 03-signalements-sanctions-et-remuneration.md

## 1. Contexte

Les cinq documents de `ecosystem/publicite/` sont des spécifications déjà adoptées (rendues normatives par AMD-0013 pour la classification/modération, par AMD-0002 pour le partage). Ils décrivent l'économie et les garanties attendues, mais pas le modèle technique : quelles entités persistent, quels états, quelles frontières avec les modules déjà construits (Wallet/Ledger, Governance/Authorization).

ADR-0003 §8 a déjà esquissé le cycle (préfinancement, réservation, événement qualifié, validation différée) sans le spécifier : cet ADR le complète, sans le contredire.

## 2. Décision

Publicité est un nouveau domaine (`App\Modules\Advertising`, schéma `advertising`, propriétaire confirmé par architecture/12) qui :

- **n'émet jamais de valeur lui-même** : il transmet des intentions typées à `LedgerPoster` (ADR-0003 §7, §14) — jamais d'écriture directe dans `ledger.*` ;
- **ne vend et ne détient aucune donnée personnelle** (§2 de `01-cycle-creation-valeur.md`) : la constitution de segment est un service de correspondance interne, jamais une liste exposée ;
- **ne diffuse rien sans version approuvée immuable** (`02-preuves-moderation-et-destinations.md` §2) : toute modification matérielle engendre une nouvelle version, jamais une mutation de l'existante — même principe d'immuabilité sémantique que Governance/Authorization (P003-B1.1/P003-B3).

Le refus est la règle par défaut à chaque étape (facturation, rémunération, diffusion) : aucune n'a lieu sans preuve acceptée et budget disponible vérifié atomiquement.

## 3. Modèle de données minimal

### Campaign / CampaignVersion

Une `Campaign` porte une identité stable ; chaque approbation crée une `CampaignVersion` immuable liant indivisiblement créations, audience, prix, événement attendu, rémunération, destination et durée (`02-preuves-moderation-et-destinations.md` §2). Une version a un état (draft, en revue, approuvée, suspendue, retirée) suivant un cycle comparable à `CapabilityDefinition`/`PolicyVersion`.

### AdvertiserProfile

Identité légale, représentant habilité, licences, territoires — proportionnel au risque du secteur (`02-preuves-moderation-et-destinations.md` §1). Jamais confondu avec un compte Identity : un annonceur agit toujours via un représentant nominatif (ADR-0004 §3.2).

### SectorClassification

Référence versionnée à la matrice (`01-classification-secteurs-et-contenus.md` §4) : pays, secteur, statut, âge minimal, formats, ciblages, fréquence, niveau de revue. Régie par ADR-0002 (configuration administrable versionnée), jamais codée en dur.

### AudienceSegment

Critères autorisés + estimation agrégée. Ne stocke et ne restitue jamais d'identité individuelle. Un segment sous un seuil minimal de taille, excessivement précis ou réidentifiable par requêtes successives est élargi, masqué ou refusé (AMD-0009 §13) — le seuil exact est une configuration versionnée sous ADR-0002, jamais une constante applicative. Santé, handicap, détresse, pauvreté, religion, opinion politique, origine ethnique, orientation sexuelle, vulnérabilité et données de mineurs ne sont jamais des critères de ciblage commercial ordinaires (AMD-0009 §14) ; aucune caractéristique sensible n'est déduite ou exploitée commercialement même si un algorithme pourrait l'estimer (AMD-0009 §16). Les mineurs sont soumis à un régime renforcé excluant le ciblage comportemental avancé et les secteurs à risque (AMD-0009 §15). La correspondance passe par une frontière qui ne retourne que des agrégats conformes à ces seuils — jamais un accès direct à `identity`. La qualification commerciale des données reste, selon AMD-0001 §6-8, une prérogative de Wasplex qui n'emporte ni propriété de la personne ni droit de vendre son identité.

### QualifiedEvent

Un événement publicitaire qualifié (`01-cycle-creation-valeur.md` §3) : identifiant unique, campagne/version, format, preuve de la condition, horodatage, décision anti-fraude, règle de prix appliquée, statut empêchant toute double facturation, corrélation. **Une clé d'idempotence obligatoire** (même discipline que `TransactionIntent` du Ledger) empêche qu'une même preuve produise deux facturations ou deux rémunérations (§4 de `01-cycle-creation-valeur.md`, invariant 5).

### CampaignBudget (projection, pas source de vérité)

Les états `initial / disponible / réservé / consommé / remboursable / remboursé` (`02-cycle-financier-campagne.md` §3) ne sont **jamais des colonnes mutables** : ce sont des projections reconstruites depuis des comptes `ledger.accounts` dédiés par campagne (compartiment « campagne annonceur », ADR-0003 §4), exactement comme un solde utilisateur. Publicité ne maintient aucun solde d'autorité — seul le Ledger en a un.

### ModerationCase / ReportCase

Dossier de revue humaine (`02-preuves-moderation-et-destinations.md` §1, §4 ; `03-signalements-sanctions-et-remuneration.md` §1-2) : campagne concernée, motif, gravité, décision, mesure conservatoire, recours. Un signalement n'est jamais à lui seul une preuve de violation (§1).

## 4. Cycle financier — mapping exact vers le Ledger (ADR-0003)

Chaque transition d'état du budget (`02-cycle-financier-campagne.md` §4) est une transaction équilibrée `LedgerPoster`, jamais un changement de statut :

| Transition métier | Écriture Ledger |
|---|---|
| Financement reçu | Débit actif de couverture / Crédit passif « budget campagne — initial→disponible » |
| Avant exécution (§4.1) | Vérification de solde disponible, aucune écriture |
| Pendant contrôle (§4.2) | Débit disponible / Crédit réservé (même compte de campagne) |
| Validation (§4.3) | Débit réservé / Crédit consommé, puis répartition du net distribuable : crédit passif droits utilisateur (provisoire ou disponible selon §6.1 ADR-0003) + crédit revenu Wasplex, au ratio fixe 50/50 (AMD-0002, non paramétrable) |
| Rejet/expiration (§4.4) | Contre-écriture : débit réservé / crédit disponible |
| Signalement → mesure conservatoire (`03-...md` §3) | Aucune écriture directe ; blocage applicatif de toute nouvelle réservation sur la campagne concernée, budget déjà réservé/consommé inchangé jusqu'à résolution |

Chaque ligne cite sa source (référence `QualifiedEvent` ou dossier de modération) et sa clé d'idempotence, conformément à ADR-0003 §7, §10.

**Aucune formule de prix ou de cascade n'est codée en dur** (`01-cycle-creation-valeur.md` §7, `02-cycle-financier-campagne.md` §6, §10) : seul le ratio 50/50 est fixe (AMD-0002, constitutionnel). Tout le reste — prix de base, coefficients, seuils antifraude, durée de réservation — est une configuration versionnée sous ADR-0002, jamais une constante applicative.

## 5. Frontière avec Governance/Authorization

Aucune capacité `advertising.*` n'existe avant cet ADR. Cet ADR **prépare seulement la place**, sur le modèle exact de TD-0003-A (Wallet) : `campaign.create`, `campaign.approve`, `campaign.moderate` (déjà citées en exemple dans ADR-0004 §5) devront être créées et vérifiées via `AuthorizationGate` avant toute route réelle — pas construites dans le premier lot de ce domaine.

La séparation des tâches s'applique explicitement : l'auteur d'une campagne ne peut jamais être son propre approbateur pour une campagne à risque élevé (`02-preuves-moderation-et-destinations.md` §1, « validation humaine indépendante de son créateur ») — même matrice que `GrantManager::activate()` (P003-B3, TD-0001-A).

## 6. Protection de l'utilisateur de bonne foi (AMD-0013 art. 6, `03-...md` §4)

Une rémunération déjà versée sur un événement valide n'est reprise que par contre-écriture explicite, motivée, avec preuve et notification — jamais par modification de l'écriture d'origine (cohérent avec ADR-0003 §11). Ceci est un invariant, pas une option de configuration.

Une fraude confirmée annule la valeur frauduleuse par écritures traçables (contre-écriture, jamais modification). Un montant déjà retiré par l'utilisateur devient, le cas échéant, une créance documentée — jamais un solde Wallet négatif créé silencieusement (AMD-0010 §14). La décision anti-fraude attachée à un `QualifiedEvent` porte un niveau gradué (anomalie, suspicion faible, suspicion sérieuse, fraude confirmée — AMD-0010 §10), jamais un score binaire tranchant seul une sanction.

## 7. Tests obligatoires (dérivés des invariants §8 de `01-cycle-creation-valeur.md` et §11 de `02-cycle-financier-campagne.md`)

- aucune facturation sans preuve acceptée, aucune rémunération sans preuve acceptée ;
- une même preuve ne produit jamais deux facturations ni deux rémunérations (idempotence) ;
- un événement ne peut jamais réserver plus que le disponible (vérification atomique, cohérente avec la réservation de retrait ADR-0003 §9) ;
- une réservation rejetée libère exactement le montant réservé, jamais plus ni moins ;
- le net distribuable recalculé correspond exactement à la somme des parts enregistrées (conservation de la valeur, méthode déjà utilisée en P004-A) ;
- une campagne suspendue ne peut plus réserver de nouveau budget, mais les réservations déjà engagées suivent leur cycle jusqu'à résolution ;
- une modification matérielle d'une campagne approuvée crée toujours une nouvelle version, jamais une mutation de l'ancienne (test d'immuabilité, sur le modèle de `SemanticImmutabilityTest`) ;
- aucun accès direct aux tables `ledger.*` depuis `App\Modules\Advertising` (seul `LedgerPoster` écrit) ;
- aucune donnée d'identité individuelle n'est retournée par une requête de correspondance d'audience ;
- un segment sous le seuil minimal de taille configuré est refusé ou élargi, jamais retourné tel quel (AMD-0009 §13).

## 8. Hors périmètre de cet ADR

Formats publicitaires précis, prix unitaires, coefficients, méthodes anti-fraude concrètes, intégration effective de la modération humaine (file de travail, UI) — laissés à la configuration (ADR-0002) et aux lots d'implémentation ultérieurs. Le premier lot de code (P005-A) ne devra construire que le noyau : modèle de données, transitions de budget câblées sur `LedgerPoster`, immuabilité de version — sans écran, sans route, sans capacité `advertising.*`, exactement sur le modèle de P003-B1 et P004-A.

## 9. Conséquences

**Bénéfices :** le budget d'une campagne devient aussi prouvable et reconstructible qu'un solde utilisateur ; aucune double facturation possible par construction ; la frontière avec Governance/Authorization est posée avant que quiconque ne soit tenté de coder un contrôle d'accès ad hoc.

**Coûts :** toute transition de budget passe par une écriture comptable complète plutôt qu'une mise à jour de statut — plus lourd à écrire, mais seule méthode qui satisfait ADR-0003 §19 (« aucun solde n'est une donnée modifiable »).

## 10. Règle obligatoire

> Aucune valeur publicitaire n'existe sans preuve acceptée, budget vérifié atomiquement et écriture Ledger équilibrée. Aucune campagne ne diffuse sans version approuvée immuable. Tout futur prompt concernant campagne, budget publicitaire, événement qualifié, segment ou modération doit citer cet ADR et les cinq documents de `ecosystem/publicite/`.
