# TD-0008 — Suivis différés du dépôt Wallet GeniusPay (pilote CI, AMD-0017)

**Statut :** ouvert
**Date :** 2026-07-30
**Origine :** AMD-0017 — pilote de dépôt Wallet en Côte d'Ivoire via GeniusPay
**Composant :** `App\Modules\Wallet\Deposit`
**Référence normative :** AMD-0011 (article 17), ecosystem/wallet/05, ADR-0007

## Décision de pilotage

Ce lot livre un premier parcours de dépôt réel de bout en bout (initiation, checkout GeniusPay, webhook signé, crédit Ledger équilibré, page de retour honnête), vérifié par un parcours navigateur réel avec un prestataire GeniusPay simulé localement (aucun compte GeniusPay réel disponible dans cet environnement de développement). Les éléments ci-dessous sont connus, documentés et volontairement différés.

## Éléments catalogués

### TD-0008-A — Aucune configuration de production réelle

`GENIUSPAY_API_KEY`, `GENIUSPAY_API_SECRET` et `GENIUSPAY_WEBHOOK_SECRET` (`.env.example`, `config/services.php`) ne portent aucune valeur réelle — Claude Code n'a reçu aucun identifiant GeniusPay réel et n'en invente aucun (EXE-0001 §5).

**Risque :** en production sans ces valeurs, `HttpGeniusPayClient` échoue proprement (403/401 GeniusPay) et `DepositInitiationController` répond `503 payment_provider_unavailable` — aucun mensonge d'état, mais le dépôt reste indisponible.
**Mesure temporaire :** aucune.
**Porte de reprise :** avant toute mise en production réelle de ce pilote, le fondateur ou l'équipe finance renseigne les identifiants réels du compte marchand GeniusPay (sandbox puis live) dans l'environnement de production, jamais dans le dépôt Git.

### TD-0008-B — Même absence de planification que TD-0007-A

`wallet:process-deposit-webhooks` (commande de reprise, ADR-0007 §11) existe et fonctionne, mais — comme `alerts:transmit-dispatches` (TD-0007-A) — aucun mécanisme de planification n'existe nulle part dans le dépôt à ce jour.

**Risque :** un webhook dont le traitement immédiat échoue (exception PHP, panne DB transitoire) reste non traité jusqu'à exécution manuelle de la commande. Le dépôt correspondant reste honnêtement `pending`/`unknown_reconciliation`, jamais faussement `completed`.
**Mesure temporaire :** exécution manuelle.
**Porte de reprise :** même porte que TD-0007-A — câblage d'une planification unique pour l'ensemble des commandes de reprise du dépôt, avant l'activation réelle en production.

### TD-0008-C — Aucun délai ni rapprochement actif pour un dépôt `pending` sans webhook

`ecosystem/wallet/05` §5 documente le principe (jamais présenté comme réussi ni échoué), mais aucune tâche ne fait activement transiter un dépôt `pending` trop ancien vers `unknown_reconciliation`, ni ne relance `GET /payments/{reference}` auprès de GeniusPay pour un rapprochement actif (l'intégration reste uniquement poussée par le webhook, jamais tirée).

**Risque :** un dépôt dont le webhook s'est perdu (panne réseau prolongée côté GeniusPay) reste indéfiniment `pending`, visible comme tel (jamais un faux succès), mais sans résolution automatique.
**Mesure temporaire :** aucune ; la personne voit honnêtement « paiement en cours de confirmation ».
**Porte de reprise :** avant l'activation réelle en production, ajouter une tâche planifiée de rapprochement actif (délai + appel `GET /payments/{reference}`) sur les dépôts `pending` au-delà d'un seuil.

### TD-0008-D — Aucun écran de supervision des dépôts en litige

Aucun écran admin n'expose les dépôts `unknown_reconciliation` ou les webhooks à signature invalide/répétée pour une revue humaine — même limite déjà connue pour d'autres files d'attente (TD antérieurs).

**Risque :** aucun à ce stade, aucun volume réel n'existe.
**Mesure temporaire :** aucune ; consultable via `ledger.wallet_deposits`/`ledger.wallet_deposit_webhook_events` directement.
**Porte de reprise :** avant un volume réel de dépôts, construire un écran admin de supervision (même famille que « Alertes et institutions » ou « Finance et rapprochement »).

### TD-0008-E — Financement de campagne par le wallet annonceur reste hors périmètre

Ce lot construit uniquement le dépôt (cash → WP). Il ne modifie pas `campaign.fund` (toujours réservée au personnel finance Wasplex, migration `2026_07_25_100008`) : le financement d'une campagne par débit du wallet propre de l'annonceur (décision de principe déjà actée en conversation le 2026-07-29, distincte du présent lot) reste un chantier séparé, non commencé.

**Risque :** aucun à ce stade — aucune régression, `campaign.fund` continue de fonctionner exactement comme avant ce lot.
**Mesure temporaire :** aucune.
**Porte de reprise :** lot dédié, sur décision explicite distincte.

## Porte de reprise générale

Compatible avec la porte de reprise déjà posée par `TD-0001` à `TD-0007` :

1. branchement réel du moteur d'autorisation sur les routes sensibles ;
2. activation d'un espace administrateur ou institutionnel en production ;
3. traitement d'opérations financières ou de données personnelles restreintes — **ce lot déclenche explicitement ce point** : un pilote de mouvement financier réel existe désormais (sous réserve de TD-0008-A, identifiants de production) ;
4. audit de sécurité précédant le lancement public complet.

## Règle du registre

Toute nouvelle dette technique acceptée reçoit un identifiant `TD-NNNN`, un risque explicite, une mesure temporaire et une porte de reprise. Une dette documentée peut différer une correction ; elle ne peut jamais supprimer une garantie constitutionnelle ni autoriser silencieusement un risque en production.
