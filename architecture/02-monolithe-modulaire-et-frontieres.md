# Monolithe modulaire et frontières fonctionnelles

**Statut :** spécification d'application — ADR-0001 adopté

## Forme initiale

Wasplex est livré comme un monolithe modulaire Laravel. Il s'agit d'une seule application déployable, mais non d'un bloc sans frontières.

Cette forme réduit coûts, exploitation distribuée et pannes réseau internes. Elle n'autorise aucun accès libre entre modules.

## Modules métier initiaux

- **Identité et Accès** : comptes, authentification, appareils, KYC, organisations et autorisations individuelles.
- **Consentements et Profil** : préférences, finalités, consentements, profil déclaratif, segmentation protégée.
- **Publicité** : annonceurs, campagnes, ciblage, budget réservé, diffusion, preuve d'attention et qualification.
- **Abonnements** : offres, cycles, droits, quotas et changements de niveau.
- **Wallet et Ledger** : écritures, soldes, réservations, couverture, retraits et rapprochement.
- **Fonds Social** : adhésions, mandats, apports, appels, vœux, réserves et réalisations.
- **Alertes et Restitutions** : déclarations, SOS, diffusion, correspondances, récompenses et clôtures.
- **Institutions** : affiliations, représentants, capacités, territoires et actions probantes.
- **Cartes Wasplex** : cartes, éligibilité, partenaires agréés, opérations et pools.
- **Live** : sessions bornées, diffuseurs autorisés, présence, interactions, preuves, modération et instructions de récompense.
- **Administration et Gouvernance** : configurations versionnées, approbations, bris de glace et audit des décisions.

Médias, notifications, recherche, antifraude, conformité et connecteurs externes sont des services transversaux gouvernés ; ils ne possèdent aucun droit implicite sur tous les domaines.

## Propriété et accès aux données

Chaque table a un module propriétaire. Les autres modules peuvent obtenir un résultat par contrat interne, projection de lecture autorisée ou événement, mais ne modifient jamais directement cette table.

Une base PostgreSQL commune est admise au lancement. Des schémas, conventions, permissions techniques et tests d'architecture matérialisent les frontières.

## Contrats internes

Toute action intermodule critique comprend : acteur, finalité, source, règle active, clé d'idempotence, corrélation et résultat. Les événements sont écrits transactionnellement dans une outbox avant traitement asynchrone.

Un échec est rejouable sans doubler débit, crédit, récompense, publicité ou notification.

## Invariants

- Publicité ne crédite pas le Wallet ; elle émet une instruction financée après qualification.
- Fonds Social ne prélève pas ; il sollicite une réservation ou un débit conforme au mandat.
- Cartes ne créent pas de valeur et ne contournent jamais le Wallet.
- Live ne crédite pas le Wallet ; il qualifie des événements préfinancés et émet des instructions idempotentes.
- Live ne devient jamais une voie de contournement de la modération publicitaire, des alertes nationales ou des consentements.
- Alertes ne nourrissent jamais le profil publicitaire.
- Institutions n'obtiennent aucun accès transversal.
- Administration ne réécrit ni ledger ni preuves.
- Antifraude peut recommander ou restreindre selon procédure, mais ne prononce pas seule une décision importante.

L'extraction future d'un module en service séparé exige une raison mesurée ; elle n'est jamais un objectif esthétique.
