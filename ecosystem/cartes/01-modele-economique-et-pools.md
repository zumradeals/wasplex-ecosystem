# Les Cartes Wasplex — Modèle économique et pools

**Statut :** spécification adoptée — AMD-0008
**Source :** `sources/2026-07-21-entretien-fondateur-12-cartes-wasplex.md`
**Dépendances :** Constitution v1.5, AMD-0008, Wallet, abonnements, partenaires agréés

## 1. Objet

Les Cartes Wasplex donnent accès à des services et opérations auprès de partenaires agréés et peuvent ouvrir un droit conditionnel à une redistribution issue de revenus économiques réels.

Elles ne sont ni des actions, ni des valeurs mobilières, ni un placement, ni une monnaie, ni une promesse de rendement.

## 2. Produits administrables

Chaque produit de carte possède une version immuable après commercialisation :

- identifiant et nom public ;
- pays, devise et territoires ;
- prix virtuel et frais éventuels ;
- durée de validité ;
- identifiants stables des offres d'abonnement éligibles ;
- services et partenaires accessibles ;
- coefficient maximal de pool ;
- plafonds individuels et collectifs ;
- règles d'expiration, suspension et remboursement ;
- disponibilité d'un support physique ;
- conditions juridiques applicables.

Modifier une offre crée une nouvelle version. Les droits déjà achetés restent gouvernés par la version acceptée, sauf règle légale ou changement favorable explicitement accepté.

## 3. Prix de la carte

Le prix rémunère un service identifiable : émission virtuelle, accès au réseau, avantages, sécurité, support et gestion du programme.

Il est comptablement distinct :

- des budgets publicitaires ;
- des commissions partenaires ;
- des pools de participation ;
- du Wallet des utilisateurs ;
- du Fonds Social.

Le prix d'une nouvelle carte ne finance pas la redistribution des détenteurs antérieurs. Une promotion financée par Wasplex doit être identifiée, plafonnée et comptabilisée comme telle.

La carte physique est une option payante séparée couvrant fabrication, personnalisation, livraison, remplacement et marge annoncée. Elle ne crée aucun droit économique supplémentaire par défaut.

## 4. Revenu éligible

Un revenu devient éligible au partage seulement si :

1. il provient d'une opération économique externe autorisée ;
2. sa source, son contrat et son bénéficiaire sont identifiés ;
3. il est encaissé ou irrévocablement confirmé ;
4. les taxes, frais externes, remboursements et annulations sont déterminables ;
5. il n'est pas déjà affecté à un autre régime constitutionnel ;
6. la période de contestation applicable est terminée ou provisionnée.

Les sources possibles comprennent les commissions marchandes, frais de partenaires, opérations de services, avantages sponsorisés et autres revenus contractuels affectés au programme.

Une campagne publicitaire reste régie par le partage publicitaire constitutionnel de l'article 9. Le même franc ne peut être partagé une seconde fois au titre des Cartes.

## 5. Assiette et partage

Pour une opération qualifiée :

```text
revenu brut partenaire
- taxes obligatoires
- frais externes directement imputables
- remboursements, annulations et rétrofacturations
= revenu net partageable R

part Wasplex W = R × 50 %
part communautaire C = R × 50 %
```

Les frais internes de Wasplex sont financés par W et ne réduisent pas préalablement R.

La formule échoue fermée : aucune distribution n'est créée si R est négatif, inconnu ou non rapproché.

## 6. Décomposition communautaire

La part C peut être répartie entre :

- **avantage direct D** : cashback ou avantage attribué à l'utilisateur dont l'opération a créé le revenu ;
- **pool collectif P** : redistribution entre les membres éligibles du pool associé.

```text
D = C × α
P = C - D
avec 0 ≤ α ≤ 1
```

Le coefficient α est fixé par contrat et configuration avant l'opération. Il ne peut être changé rétroactivement.

Une opération peut utiliser uniquement D, uniquement P ou une combinaison des deux. L'interface l'annonce avant confirmation.

## 7. Répartition d'un pool

À la clôture d'une période :

```text
unités_i = coefficient_carte_i × jours_éligibles_i / jours_période
part_i = pool_distribuable × unités_i / somme_des_unités_éligibles
```

Règles :

- le coefficient est connu avant l'achat ;
- une même personne ne peut contourner les plafonds avec plusieurs comptes ;
- l'activité frauduleuse ne produit aucune unité ;
- les fractions et arrondis suivent une règle publique ;
- un reliquat technique est reporté, jamais approprié silencieusement ;
- aucun membre ne reçoit plus que le plafond de sa version de carte ;
- l'excédent plafonné est reporté au pool suivant ou réaffecté selon une règle préannoncée ;
- aucun pool ne peut devenir négatif.

## 8. États comptables

Pour chaque opération et période :

- `recorded` : enregistrée, non éligible ;
- `pending_settlement` : règlement externe attendu ;
- `reserved` : encaissée mais contestable ;
- `distributable` : définitivement partageable ;
- `allocated` : parts calculées ;
- `credited_pending` : WP provisoires créés ;
- `available` : WP disponibles ;
- `reversed` : correction justifiée avant acquisition définitive ;
- `disputed` : distribution bloquée ;
- `closed` : période rapprochée.

Toute correction s'effectue par écriture compensatoire. L'historique n'est jamais réécrit.

## 9. Information utilisateur

Avant l'achat, l'utilisateur voit :

- ce qu'il achète réellement ;
- la durée et les services inclus ;
- l'absence de propriété sur Wasplex ;
- l'absence de rendement garanti ;
- la source des revenus partageables ;
- la formule et les plafonds ;
- un exemple comportant aussi un scénario à zéro revenu ;
- les frais, restrictions et règles de sortie.

Le tableau de bord distingue prix payé, avantages consommés, revenus du pool, calcul individuel, WP provisoires et WP disponibles.
