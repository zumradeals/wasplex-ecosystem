# Ventilation du budget publicitaire par niveau d'adhésion

- **Statut :** exploration économique v0.1
- **Source :** `sources/2026-07-21-clarification-fondateur-03-propriete-ventilation.md`
- **Dépend de :** cycle fondamental de création de valeur publicitaire

## 1. Intention fondatrice

Pour un budget publicitaire de référence égal à 100 % :

- 50 % reviennent à Wasplex ;
- 50 % sont destinés à la rémunération des utilisateurs ;
- la rémunération utilisateur dépend du niveau d'adhésion et d'un quota maximal de publicités par mois.

Cette règle est une intention de modèle. Son assiette exacte et sa formule interne doivent être définies avant adoption.

## 2. Valeurs communiquées

| Élément | Valeur de travail |
|---|---:|
| Part Wasplex | 50 % |
| Pool utilisateurs | 50 % |
| Gratuit | 10 |
| Niveaux payants | 20, 30, 35, 40 |
| Quota mensuel | variable selon le niveau |

Les valeurs associées aux niveaux ne sont pas encore qualifiées comme pourcentages absolus, poids relatifs ou multiplicateurs.

## 3. Problème mathématique à résoudre

Si 10, 20, 30, 35 et 40 sont des parts du même pool, leur somme vaut 135 et non 100. Elles ne peuvent donc pas être appliquées simultanément comme pourcentages absolus d'un même montant.

Trois interprétations sont possibles :

### Option A — Poids relatifs

Les valeurs sont des poids. Pour une période donnée :

`part_niveau = pool_utilisateurs × poids_niveau / somme_des_poids_éligibles`

Avantage : la totalité du pool est toujours distribuée.  
Risque : le gain individuel dépend du nombre d'utilisateurs et de vues dans chaque niveau.

### Option B — Multiplicateurs de récompense

Une vue possède une récompense de base et chaque niveau applique un multiplicateur.

Avantage : compréhension facile par l'utilisateur.  
Risque : le nombre et le mélange des vues doivent rester compatibles avec le budget réellement disponible.

### Option C — Sous-budgets réservés

Chaque niveau reçoit un pourcentage du pool utilisateurs.

Avantage : prévisibilité budgétaire.  
Condition : les pourcentages doivent totaliser exactement 100 % et la gestion des sous-budgets non consommés doit être définie.

Aucune option n'est adoptée à ce stade.

## 4. Suggestion d'architecture économique

Séparer trois notions :

1. **part constitutionnelle** : 50 % Wasplex / 50 % utilisateurs, si cette règle est confirmée sur une assiette définie ;
2. **coefficient de niveau** : avantage relatif du niveau d'adhésion ;
3. **quota mensuel** : nombre maximal d'événements rémunérables.

Cette séparation évite de confondre la part globale destinée aux utilisateurs avec la récompense individuelle.

## 5. Garde-fous proposés

- aucune récompense ne peut dépasser le budget utilisateur disponible ;
- la somme des affectations doit être vérifiée avant activation ;
- un quota ne doit jamais créer une promesse de revenu garanti ;
- les valeurs affichées à l'utilisateur doivent indiquer qu'il s'agit de maxima ou de conditions, selon le cas ;
- une campagne conserve la version de la formule applicable lors de son lancement ;
- un changement administratif ne modifie pas rétroactivement une campagne active ;
- les arrondis et reliquats doivent être explicitement affectés ;
- une simulation doit précéder toute activation.

## 6. Questions bloquantes

1. Combien de niveaux existent exactement et quels sont leurs noms ?
2. À quels niveaux correspondent 20, 30, 35 et 40 ?
3. Ces valeurs sont-elles des poids, des multiplicateurs ou des parts réservées ?
4. Les 50/50 s'appliquent-ils au budget brut ou au montant net après taxes et frais ?
5. Que devient le pool d'un niveau qui ne consomme pas son quota ?
6. Le supplément payé par un annonceur pour cibler un niveau alimente-t-il d'abord ce niveau ?
