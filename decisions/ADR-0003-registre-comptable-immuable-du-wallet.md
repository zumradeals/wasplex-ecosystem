# ADR-0003 — Registre comptable immuable du Wallet

**État :** proposé à la validation du fondateur  
**Date :** 22 juillet 2026  
**Décideur architectural :** SIRR, sur mandat du fondateur  
**Dépendances :** Constitution v1.4, AMD-0002, AMD-0003, AMD-0011, ADR-0001, ADR-0002

## 1. Contexte

Wasplex reconnaît des droits économiques en WasPoints, préfinance des campagnes, réserve des montants, répartit des revenus, traite des retraits et devra rapprocher ses opérations avec des banques, opérateurs Mobile Money et autres prestataires.

Un simple champ `balance` modifiable sur la fiche utilisateur ne permet ni preuve, ni reconstruction, ni détection d'un double paiement. Un historique d'événements non équilibré ne garantit pas davantage que toute valeur possède une source et une destination.

## 2. Décision

Le Wallet Wasplex repose sur un **sous-registre opérationnel en partie double, append-only, multi-comptes et séparé par devise**.

Toute opération comptable est une transaction composée d'au moins deux écritures dont la somme des débits égale exactement la somme des crédits dans une même unité monétaire.

Le ledger est la source de vérité. Les soldes affichés, tableaux de bord, statistiques et caches sont des projections reconstructibles.

Aucun utilisateur, administrateur, développeur, support ou module métier ne peut modifier directement un solde ou une écriture comptabilisée.

## 3. Périmètre

Le ledger comptabilise au minimum :

- actifs de couverture effectivement contrôlés ;
- budgets annonceurs disponibles, réservés et consommés ;
- droits utilisateurs provisoires, disponibles et réservés ;
- retraits en préparation, en transit, payés, échoués ou contestés ;
- revenus acquis de Wasplex ;
- taxes et frais externes ;
- Fonds Social et ses compartiments ;
- pools des Cartes Wasplex ;
- remboursements, promotions financées et corrections ;
- comptes techniques de rapprochement et d'attente.

Il constitue le sous-registre opérationnel de Wasplex. Il alimente la comptabilité légale, mais ne la remplace pas lorsque la juridiction impose d'autres livres, classifications ou procédures.

## 4. Plan de comptes et compartiments

Chaque compte possède :

- propriétaire économique ou finalité ;
- entité juridique ;
- pays ;
- devise ;
- module ou programme ;
- nature comptable ;
- statut ;
- restrictions de mouvement.

Les fonds suivants sont distincts même s'ils se trouvent temporairement chez un même prestataire :

- campagne annonceur ;
- droits utilisateurs ;
- ressources propres Wasplex ;
- Fonds Social ;
- pools Cartes ;
- taxes et frais ;
- paiements en transit.

Une séparation logique n'est jamais présentée comme une protection juridique si le cantonnement réel n'est pas établi.

## 5. Unités, précision et conversion

Un WP est enregistré comme une unité entière et vaut constitutionnellement 1 FCFA.

Les montants monétaires sont stockés en unités entières de la plus petite unité supportée. Aucun calcul financier n'utilise de nombre flottant binaire.

Une transaction ne s'équilibre que dans une devise. Toute conversion produit deux jambes reliées par un compte de compensation, avec taux, fournisseur, heure, frais et règle d'arrondi figés.

## 6. États des droits utilisateurs

### 6.1. WP provisoire

Droit conditionnel attribué mais non utilisable. Sa source reste identifiée et couverte ou réservée selon la règle de l'événement.

### 6.2. WP disponible

Droit validé, exigible et utilisable dans les capacités autorisées.

### 6.3. WP réservé

Droit appartenant toujours à l'utilisateur mais immobilisé pour une opération précise. Une réservation n'est ni une dépense ni un revenu Wasplex.

Le passage d'un état à un autre est une transaction équilibrée entre comptes de passif. Il ne consiste jamais à remplacer une colonne de statut sans écriture.

## 7. Sources de valeur

Aucun crédit utilisateur n'est accepté sans :

- type de source autorisé ;
- référence métier unique ;
- configuration applicable ;
- enveloppe financée ;
- montant et devise ;
- bénéficiaire ;
- preuve ou événement qualifié ;
- clé d'idempotence.

Publicité décide qu'une attention est qualifiée ; le Wallet vérifie l'instruction, l'enveloppe et l'équilibre avant de comptabiliser. Fonds Social, Cartes et Abonnements suivent le même principe dans leurs propres compétences.

Un module ne poste jamais directement dans les tables du ledger. Il transmet une commande typée à un service comptable autorisé.

## 8. Cycle publicitaire de référence

### Préfinancement

L'encaissement confirmé d'un annonceur augmente l'actif de couverture et crée une dette de campagne envers l'annonceur ou la finalité financée. Il ne constitue pas immédiatement un revenu Wasplex.

### Réservation

L'allocation à une campagne déplace le passif vers son compartiment réservé sans créer de revenu.

### Événement qualifié

La part nette distribuable financée est sortie du budget de campagne et répartie selon la Constitution entre droit utilisateur et revenu Wasplex. Taxes, frais externes et montants non distribuables utilisent leurs comptes propres.

### Validation différée

Si une vérification subsiste, la part utilisateur est comptabilisée en provisoire puis transférée en disponible après décision. Un rejet justifié utilise la transaction contraire prévue, sans effacer la première écriture.

## 9. Retraits

Le retrait suit les états suivants :

> demandé → contrôlé → réservé → transmis → résultat confirmé

Le résultat confirmé est payé ou échoué. Un résultat peut rester **inconnu** tant que les preuves externes se contredisent ou manquent.

### Demande

Le Wallet vérifie disponibilité, limites, KYC, canal et frais annoncés.

### Réservation atomique

Le montant et les frais autorisés passent de disponible à réservé dans la même transaction qui crée l'intention de retrait. Deux demandes concurrentes ne peuvent réserver les mêmes WP.

### Transmission

La commande au prestataire possède une référence Wasplex unique. Les répétitions techniques utilisent la même clé d'idempotence et ne créent jamais une nouvelle intention.

### Paiement confirmé

Le passif utilisateur réservé est soldé contre un compte de paiement à décaisser ou de compensation. La sortie de l'actif n'est reconnue qu'à partir d'une preuve externe suffisante et rapprochée.

### Échec confirmé

La réservation est libérée vers le disponible par transaction équilibrée.

### Résultat inconnu

Les WP restent réservés. Wasplex interroge et rapproche avant toute nouvelle tentative. L'absence de réponse n'est ni un échec ni un succès.

## 10. Idempotence et concurrence

Chaque intention métier possède une clé d'idempotence unique dans son périmètre. La même demande avec la même empreinte retourne le résultat existant ; la même clé avec un contenu différent est rejetée.

PostgreSQL garantit l'atomicité par transaction, contraintes uniques et verrouillage ciblé. Wasplex ne prétend pas à une livraison réseau exactement une fois ; il obtient un effet comptable unique malgré des messages livrés au moins une fois.

Les workers asynchrones utilisent l'outbox transactionnelle adoptée par ADR-0001.

## 11. Immutabilité et corrections

Une transaction comptabilisée ne peut être modifiée ni supprimée.

Toute correction utilise :

1. une contre-écriture liée à l'original ;
2. un motif et une preuve ;
3. l'autorité et les approbations requises ;
4. éventuellement une nouvelle écriture correcte.

Une contre-écriture ne masque pas l'erreur. L'original, la correction et le résultat restent visibles et auditables.

Les périodes peuvent être clôturées. Une écriture tardive est comptabilisée dans une période ouverte avec référence à la période concernée, sans rouvrir silencieusement l'histoire.

## 12. Rapprochement externe

Wasplex réalise un rapprochement au moins entre :

- ledger interne ;
- statut transactionnel du prestataire ;
- relevé de règlement bancaire ou Mobile Money ;
- fichiers de frais, remboursements et rétrofacturations.

Les webhooks sont authentifiés, horodatés, dédupliqués et conservés avec leur empreinte. Ils constituent une preuve, pas une autorité suffisante à eux seuls.

Tout écart devient un dossier de rapprochement : montant manquant, doublon, référence inconnue, statut divergent, frais inattendus, paiement orphelin ou rétrofacturation.

Aucun écart n'est corrigé par modification directe du ledger.

## 13. Couverture

Le système calcule au minimum :

> Ratio de couverture = actifs nets admissibles / droits utilisateurs exigibles

Les WP disponibles et réservés exigibles sont couverts à au moins 100 %. Les actifs indisponibles, contestés, non rapprochés ou appartenant à Wasplex ne sont pas comptés comme couverture admissible.

Le contrôle est effectué après tout mouvement critique et périodiquement de manière indépendante.

Un risque de couverture déclenche :

- blocage de toute émission ou sortie aggravante ;
- interdiction de transférer les fonds vers Wasplex ;
- conservation de la consultation ;
- rapprochement immédiat ;
- gouvernance de crise et communication exacte.

Un excédent apparent n'est jamais balayé vers Wasplex avant clôture, rapprochement et validation des obligations.

## 14. Accès et séparation des tâches

- les modules métier émettent des intentions ;
- le service Ledger valide et poste ;
- le service Paiements communique avec les prestataires ;
- le rapprochement compare les preuves ;
- l'administration approuve les ajustements autorisés ;
- l'audit consulte sans poster.

Aucune personne ne peut seule initier, approuver, comptabiliser et rapprocher un ajustement critique.

Le support ne dispose d'aucun bouton « modifier le solde ». Il peut ouvrir un dossier ou proposer un journal correctif contrôlé.

## 15. Données techniques minimales

### LedgerTransaction

Identité, type, état, date métier, date comptable, source, configuration, idempotence, corrélation, auteur technique et preuve.

### Posting

Transaction, compte, sens débit/crédit, montant entier, devise, dimensions et libellé.

### Account

Code, nature, propriétaire, devise, entité, compartiment, statut et restrictions.

### PaymentIntent

Montants brut, frais et net, bénéficiaire, canal, état, référence Wasplex, référence prestataire et point d'irréversibilité.

### ExternalEvidence

Prestataire, type, référence, horodatage, empreinte du contenu, authenticité et lien vers l'objet protégé.

### ReconciliationCase

Écart, sources comparées, gravité, responsable, résolution et écritures correctives liées.

Les projections de solde sont régénérables depuis les postings.

## 16. Sécurité et preuve d'altération

Les droits SQL interdisent les mises à jour et suppressions ordinaires sur les écritures comptabilisées. Les opérations passent par un rôle applicatif restreint et des procédures contrôlées.

Des empreintes par lots et ancrages périodiques rendent toute altération détectable. Cette protection ne transforme pas le WP en blockchain ou cryptomonnaie.

Les sauvegardes, journaux et preuves critiques suivent ADR-0001. Une restauration du ledger exige reconstruction des projections et rapprochement externe avant reprise des sorties.

## 17. Tests obligatoires

Avant production, Wasplex démontre notamment :

- équilibre de chaque transaction ;
- impossibilité de poster un montant nul, négatif ou dans deux devises ;
- impossibilité de double crédit avec la même source ;
- impossibilité de double réservation concurrente ;
- conservation de l'argent à chaque formule ;
- retrait inconnu restant réservé ;
- webhook dupliqué sans second effet ;
- correction uniquement par contre-écriture ;
- reconstruction exacte de tous les soldes ;
- détection d'un écart de prestataire ;
- arrêt automatique en cas de couverture insuffisante ;
- restauration et rapprochement après incident.

Des tests fondés sur des propriétés vérifient que, quelle que soit la séquence valide d'opérations, le ledger reste équilibré et aucun compte ne viole ses restrictions.

## 18. Conséquences

### Bénéfices

- soldes prouvables et reconstructibles ;
- doubles dépenses empêchées ;
- séparation réelle des fonds ;
- incidents de paiement traités sans supposition ;
- audit et rapprochement possibles ;
- évolution future sans changement de source de vérité.

### Coûts

- davantage d'écritures et de comptes ;
- conception rigoureuse de chaque mouvement ;
- besoin de rapprochement opérationnel ;
- impossibilité d'appliquer des corrections rapides et opaques.

Ces coûts sont acceptés : dans Wasplex, une correction facile ne doit jamais être plus importante qu'une valeur juste et traçable.

## 19. Règle obligatoire

> Aucun solde n'est une donnée modifiable. Il est le résultat reconstructible d'écritures équilibrées, immuables, financées, autorisées et rapprochables.

Tout futur prompt concernant WP, budget, Wallet, retrait, Fonds Social, Cartes, frais, remboursement ou revenu doit citer cet ADR et fournir les écritures attendues.