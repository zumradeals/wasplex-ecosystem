# Ventilation du budget publicitaire par niveau d'adhésion

- **Statut :** exploration économique v0.2
- **Sources :**
  - `sources/2026-07-21-clarification-fondateur-03-propriete-ventilation.md`
  - `sources/2026-07-21-clarification-fondateur-04-fonds-social.md`
- **Dépend de :** cycle fondamental de création de valeur publicitaire

## 1. Intention fondatrice

Pour un budget publicitaire de référence égal à 100 % :

- 50 % reviennent à Wasplex ;
- 50 % sont destinés à la rémunération des utilisateurs ;
- la rémunération utilisateur dépend du niveau d'adhésion ;
- chaque niveau possède un quota maximal de publicités rémunérables par mois ;
- les paramètres exacts sont définis par Wasplex dans la configuration administrative.

L'assiette exacte du partage 50/50 — montant brut ou net distribuable — reste à définir.

## 2. Valeurs illustratives retirées du modèle

Les valeurs `10, 20, 30, 35, 40` communiquées lors de l'entretien étaient uniquement des exemples d'écriture. Elles ne définissent :

- ni les niveaux définitifs ;
- ni leurs pourcentages ;
- ni leurs poids ;
- ni leurs multiplicateurs.

Aucune incohérence mathématique ne doit donc être déduite de ces exemples.

## 3. Paramètres à administrer

Pour chaque niveau d'adhésion, la configuration devra pouvoir définir au minimum :

- identifiant et nom du niveau ;
- prix et périodicité ;
- quota mensuel d'événements rémunérables ;
- coefficient ou règle de rémunération ;
- accès aux formats publicitaires ;
- éligibilité au ciblage qualifié ;
- date d'effet ;
- statut actif ou retiré.

Les paramètres devront respecter ADR-0002 sur la configuration métier versionnée.

## 4. Suggestion d'architecture économique

Séparer trois notions :

1. **ventilation globale** : part Wasplex / part utilisateurs ;
2. **coefficient de niveau** : avantage relatif défini par la configuration ;
3. **quota mensuel** : nombre maximal d'événements rémunérables.

Cette séparation évite de confondre la part globale destinée aux utilisateurs avec la récompense individuelle.

## 5. Garde-fous proposés

- aucune récompense ne peut dépasser le budget utilisateur disponible ;
- toute ventilation doit être mathématiquement valide avant activation ;
- un quota ne crée jamais une promesse de revenu garanti ;
- les valeurs affichées indiquent clairement maxima, conditions et périodes ;
- une campagne conserve la version de formule applicable lors de son lancement ;
- un changement administratif n'est pas rétroactif ;
- arrondis et reliquats possèdent une destination explicite ;
- une simulation précède toute activation ;
- les paramètres constitutionnels ne sont pas modifiables par un simple administrateur.

## 6. Questions restantes

1. Quels seront les niveaux définitifs et leurs noms ?
2. Quelle formule reliera niveau, quota et rémunération par événement ?
3. Les 50/50 s'appliquent-ils au budget brut ou au montant net après taxes et frais ?
4. Que devient un budget utilisateur non consommé ?
5. Le supplément payé pour cibler un niveau alimente-t-il particulièrement les utilisateurs de ce niveau ?
