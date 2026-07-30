# Wallet — Dépôt GeniusPay (pilote Côte d'Ivoire)

**Statut :** spécification adoptée — AMD-0017
**Dépendances :** `CONSTITUTION.md` article 17 (AMD-0011), `ecosystem/wallet/01-nature-waspoint-et-etats-wallet.md`, `ecosystem/wallet/01-ledger-et-couverture.md`, `ecosystem/wallet/03-mouvements-et-portes-activation.md`, ADR-0003, ADR-0007 §11, §14

## 1. Portée

Ce document précise, pour le seul pilote autorisé par AMD-0017 (dépôt utilisateur, Côte d'Ivoire, prestataire GeniusPay, devise XOF), la machine d'états, la comptabilisation et la sécurité du webhook entrant. Il ne couvre ni le retrait (`ecosystem/wallet/02`), ni le transfert entre utilisateurs, ni le paiement partenaire, ni la fonction financière d'une Carte — ces mouvements restent désactivés (article 17, alinéa 15).

## 2. Machine d'états

États minimaux, sur le modèle du cycle de retrait (`ecosystem/wallet/02` §2) :

- `draft` — intention créée côté Wasplex, aucun appel GeniusPay encore effectué ;
- `awaiting_provider` — paiement créé chez GeniusPay (`POST /payments`), `checkout_url` obtenue, personne pas encore redirigée ou en cours de paiement ;
- `pending` — personne redirigée vers le checkout GeniusPay, en attente de dénouement ;
- `completed` — paiement confirmé par un webhook `payment.success` signé et vérifié, WP crédité ;
- `failed` — paiement confirmé en échec (`payment.failed`) ou annulé (`payment.cancelled`) par un webhook signé et vérifié, aucun WP crédité ;
- `unknown_reconciliation` — état ambigu (délai dépassé sans webhook, signature invalide, ou incohérence entre le montant attendu et le montant confirmé) — jamais présenté comme un succès.

Transitions autorisées : `draft → awaiting_provider`, `awaiting_provider → {pending, completed, failed, unknown_reconciliation}` (le passage direct à `completed`/`failed`/`unknown_reconciliation` couvre un webhook arrivant avant la persistance de `pending`, cas défensif), `pending → {completed, failed, unknown_reconciliation}`, `unknown_reconciliation → {completed, failed}` (résolution ultérieure par rapprochement). `completed` et `failed` sont terminaux : aucune transition n'en repart (une correction passe par une contre-écriture Ledger, jamais par une réécriture d'état — article 17, alinéa 9).

## 3. Comptabilisation

Une seule transaction Ledger équilibrée, comptabilisée uniquement au passage à `completed` (jamais à l'initiation) :

| Compte | Sens | Montant |
|---|---|---|
| `coverage.wallet.{devise}` (actif) | Débit | `net_amount` (montant réellement reçu par Wasplex après frais GeniusPay) |
| `tax_and_fees.wallet.{devise}` (charge) | Débit | `fees` (frais GeniusPay, charge propre de Wasplex) |
| `user_rights.person.{id}.{devise}.available` (passif) | Crédit | `amount` (montant demandé par la personne — parité 1 WP = 1 FCFA, AMD-0003) |

Total débit = `net_amount + fees` = `amount` = total crédit. Le WP crédité ne dépend jamais du montant rapporté par le webhook seul : il est repris de l'intention de dépôt déjà enregistrée côté Wasplex (`draft`), le webhook ne fait que confirmer son dénouement. Un écart entre le montant attendu et le montant confirmé par GeniusPay place le dépôt en `unknown_reconciliation`, jamais en `completed` par approximation.

L'idempotence de la transaction Ledger utilise l'identifiant du dépôt comme clé (`LedgerPoster`, ADR-0003 §10) : un webhook rejoué ne crédite jamais deux fois.

## 4. Sécurité du webhook entrant (ADR-0007 §11)

1. Le corps brut et la signature (`X-GeniusPay-Signature`, HMAC-SHA256 avec le secret webhook configuré) sont vérifiés avant tout effet.
2. Toute réception, signature valide ou non, est enregistrée durablement dans une inbox avant traitement métier.
3. Une signature invalide est rejetée (401) et journalisée, sans effet sur aucun dépôt.
4. Le traitement métier (rapprochement avec le dépôt `pending`, comptabilisation) est idempotent et peut être rejoué sans double effet — y compris par la commande de reprise si le traitement immédiat échoue.
5. Une signature valide prouve l'émetteur technique, pas la vérité économique définitive (ADR-0007 §11) : le rapprochement du montant (§3 ci-dessus) reste une vérification distincte.

## 5. Délai et absence de confirmation

Un dépôt `pending` sans webhook après un délai raisonnable n'est jamais présenté comme réussi ni comme définitivement échoué : il passe en `unknown_reconciliation` et reste visible comme tel jusqu'à résolution (rapprochement manuel ou requête différée à GeniusPay).

## 6. Hors périmètre de ce pilote

Retrait, transfert entre utilisateurs, paiement partenaire, fonction financière d'une Carte, tout pays hors Côte d'Ivoire, tout prestataire hors GeniusPay — voir AMD-0017 article 7 (extension future exige une nouvelle décision explicite).
