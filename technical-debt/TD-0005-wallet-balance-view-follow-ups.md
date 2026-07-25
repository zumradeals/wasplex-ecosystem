# TD-0005 — Suivis différés de la consultation de solde Wallet

**Statut :** ouvert
**Date :** 2026-07-25
**Origine :** P006-A — comptes Ledger individuels et consultation du solde WP disponible
**Composant :** `App\Modules\Wallet\Balance`
**Référence normative :** ADR-0003, ADR-0004, AMD-0011, `ecosystem/wallet/01-nature-waspoint-et-etats-wallet.md`, `ecosystem/wallet/02-retraits-et-finalite.md`

## Décision de pilotage

Ce lot ferme TD-0004-F (compte `user_rights` désormais provisionné par personne, plus mutualisé par devise) et ouvre la première route et capacité réelles du Wallet (`wallet.view`), strictement limitée à la consultation du solde disponible. Les éléments ci-dessous sont connus, documentés et volontairement différés.

## Éléments catalogués

### TD-0005-A — Seul l'état « disponible » est provisionné par personne

`PersonLedgerAccounts` ne provisionne qu'un compte `user_rights` par personne et devise (l'état disponible). Aucun compte provisoire ni réservé par personne n'existe : aucune transition réelle n'en produit encore (aucune validation différée publicitaire, aucun cycle de retrait).

**Risque :** aucun à ce stade — `PersonBalanceProjection::forPerson()` retourne honnêtement 0 pour `provisional`/`reserved`, une valeur exacte plutôt que devinée.
**Mesure temporaire :** documenté dans `PersonLedgerAccounts` et `PersonBalanceProjection`.
**Porte de reprise :** avant qu'un flux réel produise un WP provisoire (validation différée publicitaire) ou réservé (retrait, ecosystem/wallet/02 §3), provisionner les comptes correspondants par personne et les transitions équilibrées entre eux (architecture/05 « Transitions WP »).

### TD-0005-B — Aucun historique d'opérations exposé

`ecosystem/wallet/01-nature-waspoint-et-etats-wallet.md` §7 exige un historique (montant, état, origine, date, référence) en plus des trois totaux. Ce lot n'expose que les totaux par devise (`GET /wallet/balance`), aucune liste de postings.

**Risque :** aucun à ce stade — la donnée existe déjà dans `ledger.postings` (label, montant, date, dimensions), rien n'est perdu.
**Mesure temporaire :** aucune ; simple absence de route.
**Porte de reprise :** avant l'écran complet Wallet du catalogue UX (`U-006-01-apercu-wallet`), construire une route d'historique paginée sur les postings du compte individuel.

### TD-0005-C — Aucun cycle de retrait

`ecosystem/wallet/02-retraits-et-finalite.md` (états `draft` → … → `paid`/`failed_confirmed`/`unknown_reconciliation`, canaux, KYC, PSP) reste entièrement hors périmètre. `wallet.view` ne couvre que la consultation ; aucune capacité `wallet.withdraw*` n'existe.

**Risque :** aucun à ce stade — aucune route ne permet de mouvementer les WP hors du cycle publicitaire déjà existant.
**Mesure temporaire :** aucune.
**Porte de reprise :** lot dédié, nécessitant un choix de canal/PSP réel avant toute écriture (AMD-0011 art. 15 : dépôt, transfert, paiement partenaire restent désactivés ; le retrait lui-même est une fonction du socle, mais exige une intégration prestataire non encore choisie).

### TD-0005-D — Aucun octroi automatique du grant `wallet.view`

Même situation que `campaign.create` (P005-B) : la capacité est déclarée et vérifiée réellement par la route, mais aucun mécanisme n'accorde encore ce grant `self` à la création d'un compte. Un utilisateur réel n'a donc aujourd'hui aucun moyen d'obtenir ce grant hors d'une émission manuelle.

**Risque :** aucun à ce stade — aucun espace utilisateur réel n'est encore ouvert en production (porte de reprise générale, TD-0001).
**Mesure temporaire :** aucune ; identique à toutes les capacités `self` déjà déclarées.
**Porte de reprise :** avant l'ouverture d'un espace utilisateur réel, concevoir l'émission automatique des grants `self` de base (`wallet.view`, `campaign.create`, …) à la création ou vérification d'un compte.

## Porte de reprise générale

Compatible avec la porte de reprise déjà posée par `TD-0001` à `TD-0004` :

1. branchement réel du moteur d'autorisation sur les routes sensibles ;
2. activation d'un espace administrateur ou institutionnel en production ;
3. traitement d'opérations financières ou de données personnelles restreintes ;
4. audit de sécurité précédant le lancement public complet.

## Règle du registre

Toute nouvelle dette technique acceptée reçoit un identifiant `TD-NNNN`, un risque explicite, une mesure temporaire et une porte de reprise. Une dette documentée peut différer une correction ; elle ne peut jamais supprimer une garantie constitutionnelle ni autoriser silencieusement un risque en production.
