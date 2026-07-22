# Ventilation du budget publicitaire par niveau d'adhésion

- **Statut :** spécification adoptée — AMD-0002 et AMD-0004
- **Sources :**
  - `sources/2026-07-21-clarification-fondateur-03-propriete-ventilation.md`
  - `sources/2026-07-21-clarification-fondateur-04-fonds-social.md`
  - `sources/2026-07-21-entretien-fondateur-05-cycle-financier-campagne.md`
- **Dépend de :** cycle fondamental et cycle financier de la publicité

## 1. Ventilation globale définie

Pour chaque événement publicitaire validé :

1. calculer le montant brut attribuable ;
2. isoler taxes obligatoires, frais externes directement imputables, remboursements et invalidations ;
3. obtenir le montant net distribuable ;
4. répartir ce net à parts égales :
   - 50 % Wasplex ;
   - 50 % rémunération publicitaire des utilisateurs.

Les frais internes de Wasplex sont financés par sa part.

Le Fonds social est autonome et n'intervient pas automatiquement dans cette ventilation.

Le ratio est constitutionnel, adopté par l'article 9 et AMD-0002 ; il n'est pas administrable.

## 2. Distribution interne de la part utilisateur

La part utilisateur dépend :

- du type d'événement ;
- du niveau d'adhésion publicitaire ;
- du quota mensuel ;
- des coefficients et règles versionnés ;
- de la validité de la preuve.

Les valeurs `10, 20, 30, 35, 40` étaient uniquement des exemples d'écriture. Elles ne définissent aucune valeur future.

## 3. Paramètres à administrer

Pour chaque niveau d'adhésion :

- identifiant et nom ;
- prix et périodicité ;
- quota mensuel d'événements rémunérables ;
- coefficient ou règle de rémunération ;
- accès aux formats ;
- éligibilité au ciblage qualifié ;
- date d'effet ;
- statut actif ou retiré.

Ces paramètres respectent ADR-0002.

## 4. Séparation recommandée

1. **ventilation globale** : net Wasplex / utilisateurs ;
2. **coefficient de niveau** : avantage relatif ;
3. **quota mensuel** : plafond d'événements rémunérables.

## 5. Garde-fous

- aucune récompense ne dépasse le budget disponible ;
- toute ventilation est validée mathématiquement ;
- aucun quota ne promet un revenu garanti ;
- maxima, conditions et périodes sont affichés clairement ;
- la campagne conserve sa version de formule ;
- les changements ne sont pas rétroactifs ;
- arrondis et reliquats ont une destination explicite ;
- une simulation précède l'activation ;
- l'administration ne modifie pas les invariants constitutionnels.

## 6. Questions restantes

1. Quels seront les niveaux définitifs et leurs noms ?
2. Quelle formule reliera niveau, quota et rémunération par événement ?
3. Que devient une part utilisateur non distribuée à cause des quotas ?
4. Le supplément de ciblage d'un niveau bénéficie-t-il particulièrement aux utilisateurs de ce niveau ?
