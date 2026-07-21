# Abonnements publicitaires

**Statut :** spécification métier fondatrice  
**Source :** `sources/2026-07-21-entretien-fondateur-07-abonnements-publicitaires.md`  
**Dépendances :** Constitution v0.4, AMD-0004 proposé, ADR-0002

## 1. Objet du contrat

Un abonnement est un contrat de service donnant accès à des capacités supplémentaires. Il n'est ni un investissement, ni une épargne, ni l'achat d'un revenu futur.

Les niveaux sont des entités administrables. Aucun nom commercial, prix, quota, durée, coefficient ou avantage n'est codé en dur.

## 2. Socle universel gratuit

Tout utilisateur conserve gratuitement :

- compte, profil et gestion des consentements ;
- accès raisonnable aux campagnes éligibles disponibles ;
- réception, consultation et retrait des gains selon les règles générales ;
- Wallet, historique et explication des calculs ;
- droits relatifs à ses données ;
- sécurité essentielle, récupération de compte, assistance et recours.

Un niveau payant ne peut acheter une meilleure protection des données, la propriété des gains déjà acquis ou un traitement de fraude moins rigoureux.

## 3. Catalogue versionné

Chaque version d'offre définit au minimum :

- identifiant stable et nom affiché ;
- prix, devise, taxes et durée ;
- date d'effet et période de commercialisation ;
- droits et avantages ;
- quotas et cycles de remise à zéro ;
- règles d'éligibilité et de priorité ;
- plafonds de rémunération ;
- conditions de retrait éventuellement spécifiques ;
- renouvellement, grâce, résiliation et remboursement ;
- modalités de surclassement et déclassement.

Une souscription conserve la version contractée. Une modification administrative ne change pas rétroactivement un contrat actif, sauf obligation légale ou changement explicitement accepté.

## 4. Financement d'un événement

Pour un événement donné :

`net_distribuable = brut - taxes - frais_externes_imputables - pertes_externes_justifiées`

`part_wasplex = net_distribuable × 50 %`

`part_utilisateur_financée = net_distribuable × 50 %`

Le montant annoncé à l'utilisateur ne peut dépasser la part utilisateur financée pour cet événement. Si un segment d'adhésion coûte davantage, le prix total de l'événement augmente avant diffusion ; le partage 50/50 s'applique ensuite à ce net plus élevé.

Une promotion financée par Wasplex est une écriture séparée provenant de la part ou des ressources propres de Wasplex. Elle ne doit pas être fusionnée avec le gain publicitaire ordinaire.

## 5. Contrôle de solvabilité avant activation

Une campagne ne peut être activée que si le système démontre que son budget couvre :

- le nombre maximal d'événements finançables ;
- la part utilisateur promise pour chaque variante éligible ;
- la part Wasplex ;
- les taxes et frais prévisibles ;
- toute promotion explicitement attachée et sa source.

La réservation budgétaire se fait avant l'affichage de la récompense. Une configuration impossible est rejetée, non corrigée après consommation.

## 6. États de souscription

États minimaux :

- `pending_payment` : paiement non confirmé ;
- `active` : droits applicables ;
- `grace_period` : maintien temporaire explicitement configuré ;
- `cancel_at_period_end` : renouvellement arrêté, droits maintenus jusqu'à l'échéance ;
- `expired` : retour au niveau gratuit ;
- `suspended` : droits restreints pendant une procédure régulière ;
- `refunded` : remboursement traité selon ses effets annoncés.

Chaque transition conserve date, motif, acteur, offre/version, paiement et clé d'idempotence.

## 7. Changements de niveau

Le surclassement prend effet après confirmation du paiement complémentaire. Prorata, crédit résiduel ou prix fixe sont possibles si la méthode est affichée avant confirmation.

Le déclassement prend normalement effet à l'échéance. Les gains et événements passés ne sont jamais recalculés selon le nouveau niveau.

## 8. Quotas

Un quota est un plafond d'utilisation, jamais une promesse de stock publicitaire.

Les compteurs sont séparés par type et fenêtre temporelle. L'atteinte d'un quota bloque seulement les événements concernés. Elle ne désactive ni le contrat ni ses autres services.

## 9. Expiration, résiliation et remboursement

L'expiration ou la résiliation ne supprime jamais les WP acquis, l'historique, les droits de retrait, les données ou les consentements. Elle retire uniquement les capacités du niveau expiré.

Les remboursements sont traités selon une politique publiée. Les gains valides ne sont repris qu'en cas de fraude établie ou d'erreur manifeste, au moyen d'une contrepassation traçable.

## 10. Séparation comptable

Les recettes d'abonnement, budgets annonceurs, promotions Wasplex, fonds utilisateurs et Fonds social utilisent des comptes distincts dans le grand livre. Aucun transfert implicite n'est autorisé.

## 11. Exigences d'interface

Avant paiement, afficher prix total, durée, renouvellement, capacités, plafonds et phrase explicite :

> Cet abonnement augmente vos capacités. Il ne garantit ni publicité disponible ni revenu.

L'utilisateur peut consulter sa version d'offre, sa période, ses quotas consommés, sa prochaine échéance et l'état du renouvellement.
