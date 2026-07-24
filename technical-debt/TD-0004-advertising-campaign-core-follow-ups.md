# TD-0004 — Suivis différés du noyau technique Publicité

**Statut :** ouvert
**Date :** 2026-07-24
**Origine :** P005-A — noyau technique Publicité (campagnes et budgets)
**Composant :** `App\Modules\Advertising`
**Référence normative :** ADR-0010, `ecosystem/publicite/*`, ADR-0002, ADR-0003

## Décision de pilotage

Ce noyau pose le modèle de données, l'immuabilité de version et le câblage exact du cycle financier sur `LedgerPoster`, sans brancher formats précis, prix réels, méthode anti-fraude concrète, file de modération humaine ni capacité `advertising.*` (ADR-0010 §8). Les éléments ci-dessous sont connus, documentés et volontairement différés.

## Éléments catalogués

### TD-0004-A — Registre central ADR-0002 non construit ; configuration locale minimale à la place

`SectorClassification` et `AudienceSegmentSizeThreshold` reprennent localement un cycle de vie minimal (`draft`/`active`/`retired`, une version active à la fois) plutôt que de référencer le registre central ADR-0002 §8 (`Definition`/`Release`/`ValueVersion`/`Approval`/`Simulation`/`Activation`/`Binding`/`SafetySwitch`), qui n'existe pas encore dans le dépôt.

**Risque :** aucun à ce stade — ces deux tables sont déjà versionnées, non codées en dur, avec échec fermé en l'absence de valeur active (voir `AudienceSegmentGuard::activeThreshold()`). Le risque n'apparaît que si une interface d'administration devait un jour éditer ces valeurs sans passer par le futur registre.
**Mesure temporaire :** documentée dans `App\Modules\Advertising\Enums\ConfigurationState`.
**Porte de reprise :** avant toute interface d'administration de ces valeurs, migrer vers de véritables `Binding` du registre ADR-0002 plutôt que des tables locales.

### TD-0004-B — Règle d'arrondi du partage 50/50 non versionnée

Sur un montant impair, `CampaignBudgetService::acceptQualifiedEvent()` fait absorber l'unité résiduelle par la part Wasplex (`intdiv($amount, 2)` pour la part utilisateur, le reste pour Wasplex). Le ratio 50/50 lui-même est constitutionnel et non paramétrable (AMD-0002), mais la règle d'arrondi sur un montant impair est actuellement un choix de code, non une valeur versionnée conforme à ADR-0002 §5 (« chaque formule fixe son mode d'arrondi »).

**Risque :** aucun risque financier (la somme des deux parts égale toujours exactement le montant, testé), mais la règle n'est pas elle-même auditable indépendamment du code.
**Mesure temporaire :** documentée en commentaire à l'endroit exact du calcul.
**Porte de reprise :** avant un volume réel de production, formaliser cette règle d'arrondi comme une valeur versionnée du futur registre ADR-0002, référencée par `reward_configuration_key`/`version` plutôt qu'implicite.

### TD-0004-C — Aucune taxe ni frais externe modélisés dans le net distribuable

`net distribuable = prix appliqué` dans ce noyau (`02-cycle-financier-campagne.md` §7 n'est pas implémenté au-delà du cas sans déduction) : aucun compte de taxe, aucun frais externe. ADR-0010 §8 exclut explicitement « prix unitaires, coefficients » de ce lot.

**Risque :** aucun à ce stade — ce noyau ne traite aucune campagne réelle.
**Mesure temporaire :** `applied_price_amount` est fourni tel quel par l'appelant, jamais recalculé.
**Porte de reprise :** avant tout traitement d'une campagne réelle, modéliser les comptes de taxes/frais et ajuster `acceptQualifiedEvent()` pour déduire ces montants avant la répartition 50/50, conformément à `02-cycle-financier-campagne.md` §7.

### TD-0004-D — Dossier annonceur minimal

`AdvertiserProfile` ne porte que les champs minimaux d'ADR-0010 §3 (identité légale, représentant, licences, territoires, statut). Le dossier complet de `02-preuves-moderation-et-destinations.md` §1 (bénéficiaires effectifs, preuve de propriété de l'offre, droits sur contenus, intermédiaires de conversion) n'est pas modélisé : ce noyau ne construit ni preuve concrète ni file de modération (ADR-0010 §8).

**Risque :** aucun à ce stade.
**Mesure temporaire :** `ModerationCase` reste le point d'attache prévu pour ces preuves lorsqu'elles existeront.
**Porte de reprise :** avant l'activation d'un flux de revue humaine réel, étendre `AdvertiserProfile`/`ModerationCase` avec les champs complets du dossier.

### TD-0004-E — Aucune capacité `advertising.*` ne gouverne encore ce module

Même situation que TD-0003-A (Wallet) : aucune route, aucun contrôleur, aucune capacité Governance/Authorization n'expose ce module (ADR-0010 §5, §8).

**Risque :** identique à TD-0003-A.
**Mesure temporaire :** identique — aucune route, aucune façade alternative.
**Porte de reprise :** créer et faire vérifier `campaign.create`, `campaign.approve`, `campaign.moderate` (ADR-0004 §5) via `AuthorizationGate` avant toute route réelle.

### TD-0004-F — Aucune attribution par utilisateur individuel

`QualifiedEvent` ne référence aucun utilisateur bénéficiaire (fidèle au modèle minimal d'ADR-0010 §3, qui n'en liste pas). La part utilisateur de la répartition 50/50 est créditée à un compte `user_rights` mutualisé (`SharedLedgerAccounts::userRights()`), pas à un compte par utilisateur : Wallet/Ledger (P004-A) n'a lui-même pas encore construit de compte par utilisateur.

**Risque :** aucun à ce stade — ce noyau ne verse aucun gain à un utilisateur réel.
**Mesure temporaire :** le compte `user_rights.advertising.{devise}` représente l'agrégat des droits générés par la publicité, en attente d'attribution individuelle.
**Porte de reprise :** avant de créditer un utilisateur réel, Wallet/Ledger et Identity doivent d'abord établir le provisionnement de comptes par utilisateur (dette partagée avec P004-A) ; `QualifiedEvent` devra alors référencer le bénéficiaire.

## Porte de reprise générale

Compatible avec la porte de reprise déjà posée par `TD-0001`, `TD-0002` et `TD-0003` :

1. branchement réel du moteur d'autorisation sur les routes sensibles ;
2. activation d'un espace administrateur ou institutionnel en production ;
3. traitement d'opérations financières ou de données personnelles restreintes ;
4. audit de sécurité précédant le lancement public complet.

## Règle du registre

Toute nouvelle dette technique acceptée reçoit un identifiant `TD-NNNN`, un risque explicite, une mesure temporaire et une porte de reprise. Une dette documentée peut différer une correction ; elle ne peut jamais supprimer une garantie constitutionnelle ni autoriser silencieusement un risque en production.
