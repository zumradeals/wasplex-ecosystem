# Ledger Wallet en partie double

**Statut :** spécification proposée — ADR-0003

## Règles structurelles

- Une transaction comptabilisée contient au moins deux postings.
- Pour chaque devise, total débits = total crédits.
- Un posting utilise un montant entier strictement positif ; son sens est explicite.
- Un compte n'accepte qu'une devise et les types de mouvement autorisés.
- Le ledger ne contient aucun UPDATE ou DELETE métier d'une écriture comptabilisée.
- Les soldes sont des vues ou projections vérifiables, jamais des champs d'autorité.
- Toute transaction référence source, configuration et clé d'idempotence.

## Familles de comptes

### Actifs

Couverture bancaire, couverture Mobile Money, couverture chez prestataire, encaissements à recevoir, compensation et actifs en rapprochement.

### Passifs

Budgets annonceurs, droits WP provisoires, disponibles et réservés, retraits à payer, Fonds Social, pools Cartes, taxes et remboursements dus.

### Revenus et charges Wasplex

Revenus acquis, promotions financées, frais supportés, pertes externes reconnues et comptes de clôture selon les exigences comptables.

Les comptes opérationnels sont rapprochés avec la comptabilité légale ; aucune famille n'autorise un mélange de propriété économique.

## Modèles de journaux

Chaque mouvement autorisé possède un modèle de journal versionné indiquant comptes débitables/créditables, preuve, module source, approbations, bornes et libellé.

L'administration choisit un modèle et des paramètres autorisés ; elle ne compose pas librement un journal financier.

## Transitions WP

- Provisoire vers disponible : débit du passif provisoire, crédit du passif disponible.
- Disponible vers réservé : débit du passif disponible, crédit du passif réservé.
- Réservé vers disponible : débit du passif réservé, crédit du passif disponible.
- Réservé vers payé : débit du passif réservé, crédit du payable/compte de compensation, puis règlement contre l'actif externe.
- Annulation ou correction : contre-écriture du journal identifié.

Le sens comptable exact dépend du plan de comptes adopté, mais la conservation et la séparation sont invariantes.

## Clôture

Les périodes comptables possèdent états ouvert, en rapprochement et clôturé. Une clôture conserve les transactions tardives dans une période suivante avec date métier originale.

## Projections

Au minimum :

- solde utilisateur par état ;
- solde de campagne ;
- engagements par programme ;
- retraits en transit ;
- couverture nette admissible ;
- position par prestataire ;
- écritures non rapprochées.

Chaque projection possède un point de contrôle et peut être reconstruite intégralement.