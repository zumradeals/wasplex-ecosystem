# AMD-0017 — Pilote de dépôt Wallet en Côte d'Ivoire via GeniusPay

**État :** adopté par le fondateur — exception pilote à l'article 17 (AMD-0011)
**Date :** 2026-07-30
**Source :** Question de contrôle posée par Claude Code lors de l'intégration demandée de la documentation `API_Documentation geniuspay.md` (ajoutée au dépôt le 2026-07-29), confirmée en conversation le 2026-07-30.
**Amendements liés :** AMD-0003 (nature du WasPoint), AMD-0011 (Wallet couvert, séparé et reconstructible — article 17), ADR-0007 (API, webhooks et intégrations externes)

## Motif

Le dirigeant a demandé l'intégration de l'API marchand GeniusPay (paiements Wave, Orange Money, MTN Money, carte) pour permettre à une personne de recharger son propre Wallet WP. Or l'article 17, alinéa 15 (AMD-0011) dispose : « Dépôt, transfert entre utilisateurs, paiement partenaire, chargement ou fonction financière d'une Carte restent désactivés jusqu'à validation réglementaire et opérationnelle par pays. » `ecosystem/wallet/03-mouvements-et-portes-activation.md` §3 précise que cette activation exige une validation portant sur le service de paiement, la monnaie électronique, la garde, le remboursement, la lutte contre le blanchiment et le financement du terrorisme (LBC/FT), et le rapprochement.

Construire un dépôt fonctionnel sans traiter cette contradiction aurait silencieusement contredit un invariant produit déjà adopté (CLAUDE.md §7 ; EXE-0001 §6). Claude Code a donc arrêté l'implémentation, exposé le fait exact et les sources, puis proposé les options sûres. Le dirigeant a choisi d'exercer son Véto exceptionnel (EXE-0001 §4) pour un pilote strictement borné plutôt que de renoncer à la fonctionnalité ou de la construire en silence.

## Décision fondatrice

> Le dépôt utilisateur est activé en pilote pour la Côte d'Ivoire, exclusivement via le prestataire GeniusPay, sous la responsabilité directe du dirigeant. Ce pilote ne vaut pas validation juridique définitive du service de paiement, de la monnaie électronique, de la garde, du remboursement ou du dispositif LBC/FT pour une exploitation pérenne — il est réversible et strictement borné au périmètre décrit ci-dessous. Tous les autres mouvements désactivés par l'article 17 (transfert entre utilisateurs, paiement partenaire, chargement ou fonction financière d'une Carte) demeurent désactivés sans exception.

## Article proposé

1. **Périmètre du pilote.** Le dépôt utilisateur (recharge du Wallet WP propre à une personne, portée `self`, jamais celui d'autrui) est activé exclusivement pour le pays Côte d'Ivoire (`CI`) et exclusivement via le prestataire GeniusPay (`http://pay.genius.ci/api/v1/merchant`), en devise XOF.
2. **Restrictions inchangées.** Aucune autre porte de l'article 17 n'est levée par le présent amendement : transfert entre utilisateurs, paiement partenaire, chargement ou fonction financière d'une Carte restent désactivés (article 17, alinéa 15 ; `ecosystem/wallet/03` §4, §5, §7).
3. **Invariants du ledger inchangés.** Un dépôt reste soumis sans exception à l'intégralité de l'article 17 : partie double équilibrée, idempotence, absence de double affectation, rapprochement, résultat inconnu jamais déguisé en succès, aucun crédit sans preuve externe suffisante (article 17, alinéas 1, 7, 9, 12, 23 ; `ecosystem/wallet/01` §8).
4. **Parité et frais.** Le WP crédité correspond exactement au montant que la personne a demandé de déposer (parité 1 WP = 1 FCFA, AMD-0003). Les frais prélevés par GeniusPay sont comptabilisés comme une charge propre de Wasplex (compte `tax_and_fees`), jamais répercutés silencieusement sur le montant crédité à la personne — un dépôt ne devient jamais recette Wasplex (`ecosystem/wallet/03` §3).
5. **Nature du pilote.** Cette activation est expérimentale et réversible, décidée par le dirigeant en connaissance du texte qu'il lève pour ce périmètre précis. Elle ne constitue pas, à elle seule, la preuve que les exigences énumérées par `ecosystem/wallet/03` §3 (service de paiement, monnaie électronique, garde, remboursement, LBC/FT, rapprochement) sont satisfaites de façon pérenne ni pour un autre pays.
6. **Intégration isolée.** GeniusPay est intégré comme adaptateur isolé (ADR-0007 §14) : Wasplex ne manipule que le modèle normalisé (intention, montant, frais, statut, référence, preuve). Aucun identifiant ni secret GeniusPay n'est codé en dur ni versé au dépôt Git (EXE-0001 §5).
7. **Extension future.** Toute extension à un autre pays, un autre prestataire, ou un autre type de mouvement (transfert, paiement partenaire, carte) exige une nouvelle décision explicite du dirigeant, jamais une extrapolation silencieuse du présent pilote.
8. **Administrabilité.** La capacité, les comptes Ledger et la machine d'états techniques du dépôt restent administrables et versionnés (article 17, alinéa 24), sans jamais pouvoir contredire les invariants ci-dessus.

## Décision d'adoption

Le fondateur a exercé son Véto exceptionnel (EXE-0001 §4) le 2026-07-30, en réponse à la question de contrôle explicite posée par Claude Code sur la contradiction entre l'intégration demandée de GeniusPay et l'article 17, alinéa 15. Le Véto porte précisément sur le pilote décrit ci-dessus ; il ne lève aucune autre restriction de l'article 17 et ne dispense d'aucun autre invariant constitutionnel.

## Effet de l'adoption

Cet amendement introduit une exception pilote, réversible et strictement bornée à l'article 17 existant (AMD-0011) ; il ne le remplace pas, n'en modifie aucun autre alinéa, et n'affecte aucun autre article de la Constitution.
