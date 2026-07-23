# TD-0003 — Suivis différés du noyau du ledger Wallet

**Statut :** ouvert
**Date :** 2026-07-23
**Origine :** P004-A — noyau du registre comptable Wallet en partie double
**Composant :** `App\Modules\Wallet\Ledger`
**Référence normative :** ADR-0003, architecture/05-ledger-wallet-partie-double.md, architecture/12-propriete-des-donnees-et-schemas.md

## Décision de pilotage

Ce noyau pose les fondations vérifiables du ledger (modèle de données, invariants en base, service de comptabilisation, projection de solde) sans brancher aucun cycle métier réel (retraits, publicité, rapprochement, couverture). Les éléments ci-dessous sont connus, documentés et volontairement différés : ils ne bloquent pas la conception des prochains lots, mais devront être réévalués avant que ce noyau protège effectivement des parcours financiers réels.

## Éléments catalogués

### TD-0003-A — Aucune capacité `wallet.*` ne gouverne encore l'appel à LedgerPoster

`LedgerPoster` n'est protégé par aucune vérification Governance/Authorization : tout code PHP disposant d'une instance du service peut comptabiliser une transaction. P004-A §3.E demandait explicitement de préparer la place sans construire l'intégration.

**Risque :** un futur module pourrait invoquer `LedgerPoster` sans passer par une autorisation adaptée s'il n'est pas averti de cette frontière.
**Mesure temporaire :** aucune route, aucun contrôleur, aucune façade publique alternative n'expose ce service ; seul du code serveur explicitement écrit pour cela peut l'atteindre. La visibilité des classes du module reste le seul rempart actuel.
**Porte de reprise :** avant qu'un module métier réel (Publicité, retraits, Fonds Social, Cartes) n'appelle `LedgerPoster` depuis une route ou un job accessible à un acteur non technique, créer les capacités `wallet.*` nécessaires dans Governance/Authorization et les faire vérifier en amont de chaque appel.

### TD-0003-B — `LedgerTransactionState` ne porte qu'une seule valeur (`posted`)

Ce noyau ne comptabilise que des transactions déjà décidées par le module appelant, dans un seul appel atomique. Les états transitoires d'une intention non encore comptabilisée (retrait « demandé » avant réservation, ADR-0003 §9 ; validation différée d'un événement publicitaire, ADR-0003 §8) n'existent pas encore comme lignes du ledger.

**Risque :** aucun à ce stade ; le risque apparaîtrait si un futur module tentait de représenter un état « en attente » directement dans `ledger_transactions` plutôt que dans son propre objet métier (PaymentIntent, etc.).
**Mesure temporaire :** documenté dans `LedgerTransactionState` lui-même ; toute transaction insérée dans le ledger est déjà définitive.
**Porte de reprise :** avant de construire un cycle métier avec états provisoires (retraits, validation différée), confirmer que ces états vivent dans l'objet métier du module concerné, jamais comme état mutable d'une ligne déjà comptabilisée.

### TD-0003-C — Aucun déclencheur n'empêche d'ajouter des postings à une transaction déjà comptabilisée dans un appel SQL antérieur

Une première version de ce noyau tentait d'interdire, en base, tout INSERT de posting référençant un `ledger_transaction_id` déjà comptabilisé par une transaction SQL PostgreSQL antérieure (comparaison de `xmin` avec `pg_current_xact_id()`). Cette vérification s'est révélée incorrecte : sous `RefreshDatabase` (savepoints imbriqués) comme pour tout appelant légitime qui engloberait `LedgerPoster::post()` dans sa propre transaction PostgreSQL, `pg_current_xact_id()` renvoie l'identifiant de la transaction de plus haut niveau alors que `xmin` reflète celui de la sous-transaction — les deux appels de `LedgerPoster::postInternal()` échouaient alors systématiquement, y compris pour leur propre écriture initiale. Elle a été retirée.

**Risque :** rien n'empêche, en base seule, un accès direct (hors `LedgerPoster`) d'ajouter un posting supplémentaire à une transaction déjà comptabilisée, tant que l'ensemble résultant reste équilibré par devise (le déclencheur `postings_enforce_balance` ne peut pas distinguer ce cas d'un ajout légitime dans le même appel).
**Mesure temporaire :** frontière de module (ADR-0003 §7, §14) : seul `LedgerPoster` écrit dans `ledger.ledger_transactions` et `ledger.postings`, et il crée toujours la transaction et ses postings dans le même appel atomique. Aucune façade alternative n'expose ces tables en écriture.
**Porte de reprise :** si un besoin réel de défense en profondeur apparaît (par exemple avant d'exposer une capacité d'écriture à un contexte moins maîtrisé), concevoir un mécanisme robuste aux sous-transactions (par exemple une colonne technique posée une seule fois par une contrainte d'exclusion, plutôt qu'une comparaison d'identifiants de transaction SQL).

### TD-0003-D — Aucun catalogue de modèles de journaux

`LedgerTransaction.configuration_key` / `configuration_version` sont des références libres, sans table de catalogue correspondante : architecture/05 décrit des « modèles de journaux versionnés indiquant comptes débitables/créditables, preuve, module source, approbations, bornes et libellé », que ce noyau ne construit pas encore (hors périmètre P004-A §5).

**Risque :** aucun à ce stade, ce noyau ne compose aucun journal métier.
**Mesure temporaire :** les colonnes existent et sont prêtes à référencer un futur catalogue, sans le présupposer.
**Porte de reprise :** avant qu'un module métier compose des journaux à partir de modèles administrables, concevoir ce catalogue et le lier à ces colonnes.

### TD-0003-E — Aucune clôture de période

`business_date` et `accounting_date` existent sur chaque transaction, mais aucune entité « période comptable » (ouverte / en rapprochement / clôturée, ADR-0003 §11, architecture/05 "Clôture") n'existe encore.

**Risque :** aucun à ce stade.
**Mesure temporaire :** toute transaction est actuellement acceptée dans n'importe quelle période ; ce noyau ne construit aucun cycle réel qui en dépendrait.
**Porte de reprise :** avant d'activer un cycle métier réel nécessitant une clôture (rapprochement, reporting réglementaire), concevoir l'entité période et son état.

## Porte de reprise générale

Tous les points ci-dessus restent compatibles avec la porte de reprise déjà posée par `TD-0001` et `TD-0002` :

1. branchement réel du moteur d'autorisation sur les routes sensibles ;
2. activation d'un espace administrateur ou institutionnel en production ;
3. traitement d'opérations financières ou de données personnelles restreintes ;
4. audit de sécurité précédant le lancement public complet.

## Règle du registre

Toute nouvelle dette technique acceptée reçoit un identifiant `TD-NNNN`, un risque explicite, une mesure temporaire et une porte de reprise. Une dette documentée peut différer une correction ; elle ne peut jamais supprimer une garantie constitutionnelle ni autoriser silencieusement un risque en production.
