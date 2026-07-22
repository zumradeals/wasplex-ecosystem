# Cycle financier d'une campagne publicitaire

- **Statut :** spécification adoptée — Constitution et AMD-0002
- **Source :** `sources/2026-07-21-entretien-fondateur-05-cycle-financier-campagne.md`
- **Dépend de :** `01-cycle-creation-valeur.md`

## 1. Justification de cette étape

Le cycle de valeur a défini ce que l'annonceur achète et la preuve requise. Ce chapitre définit maintenant quand l'argent est garanti, réservé, consommé, partagé ou restitué.

Il est nécessaire avant le WasPoint et le wallet, car aucune valeur ne doit être créditée sans financement disponible.

## 2. Principe de couverture préalable

1. Une campagne ne peut être activée sans financement intégral de son budget engagé.
2. Une promesse de paiement, une facture future ou un crédit non garanti ne constitue pas un financement.
3. Le financement reçu est affecté à la campagne mais ne devient pas immédiatement un revenu acquis.
4. La campagne ne peut engager davantage que son montant disponible.
5. Toute activation conserve la version des règles financières et tarifaires applicables.

## 3. États financiers du budget

Le tableau de bord et le ledger annonceur distinguent :

- **initial** : montant affecté lors du financement ;
- **disponible** : montant libre pouvant couvrir de nouveaux événements ;
- **réservé** : montant temporairement bloqué pour des événements en contrôle ;
- **consommé** : montant définitivement affecté à des événements validés ;
- **frais** : frais externes applicables, identifiés séparément ;
- **remboursable** : solde pouvant faire l'objet d'un remboursement ;
- **remboursé** : montant effectivement restitué.

Invariant comptable à préciser selon les frais :

`initial + financements complémentaires = disponible + réservé + consommé + remboursé + autres sorties explicitement justifiées`

Aucun montant ne disparaît sans écriture traçable.

## 4. Cycle d'un événement

### 4.1 Avant exécution

Le système vérifie atomiquement que le budget disponible couvre le coût maximal applicable.

### 4.2 Pendant contrôle

Le coût est déplacé de `disponible` vers `réservé`.

### 4.3 Validation

Si la preuve est acceptée :

- le montant réservé devient consommé ;
- le montant net distribuable est calculé ;
- les parts Wasplex et utilisateur sont enregistrées ;
- l'événement devient non reproductible pour la facturation.

### 4.4 Rejet ou expiration

Si la preuve est rejetée ou expire :

- la réservation est libérée ;
- le montant retourne au disponible ;
- aucun gain utilisateur et aucun revenu Wasplex ne sont acquis ;
- le motif reste auditable.

Toutes les opérations doivent être atomiques et idempotentes.

## 5. Clôture et solde non consommé

À la fin, à l'arrêt ou à l'expiration d'une campagne, le disponible peut, selon la politique applicable :

- rester dans le portefeuille publicitaire ;
- être affecté à une autre campagne ;
- prolonger la campagne ;
- être remboursé.

Les montants réservés attendent validation, rejet ou expiration avant liquidation finale.

Wasplex ne s'approprie pas un solde inutilisé. Les frais externes non récupérables doivent avoir été annoncés avant financement.

## 6. Tarification

Le prix d'un événement est calculé à partir :

1. d'un prix de base versionné par catégorie ;
2. de coefficients ou suppléments administrables ;
3. des règles applicables à la campagne lors de son activation.

Facteurs possibles :

- type d'événement ;
- format et durée ;
- localisation ;
- précision et rareté du segment ;
- niveau de preuve ;
- fréquence ;
- période ;
- volume ;
- niveau d'adhésion ciblé.

La formule exacte n'est pas codée en dur. Le code applique et contrôle une configuration versionnée.

## 7. Net distribuable

Pour chaque événement validé :

`net distribuable = brut attribuable - taxes obligatoires - frais externes directement imputables - remboursements ou invalidations applicables`

Les catégories de déduction doivent être documentées et justifiables.

Ne sont pas déductibles avant partage :

- salaires et équipes Wasplex ;
- hébergement et serveurs ;
- développement ;
- sécurité interne ;
- communication ;
- autres frais généraux internes.

Ces charges sont financées par la part Wasplex.

## 8. Partage de principe

Le net distribuable est partagé :

- 50 % Wasplex ;
- 50 % rémunération publicitaire utilisateur.

La part utilisateur est ensuite déterminée selon le type d'événement, le niveau d'adhésion, le quota et la configuration versionnée.

Le Fonds social n'est pas destinataire automatique de ce partage.

La constitutionnalisation du 50/50 fait l'objet de l'amendement AMD-0002.

## 9. Provisions de risque

Une provision peut retarder la disponibilité d'un montant pour couvrir fraude, remboursement ou rétrofacturation.

Elle doit posséder :

- une raison ;
- un montant ;
- une date de création ;
- une échéance ;
- une règle de libération ;
- un résultat final ;
- une trace d'audit.

Elle ne devient jamais une retenue définitive sans événement justificatif.

## 10. Paramètres administrables

- budgets minimum et maximum ;
- durée et expiration ;
- catégories d'événements ;
- prix de base ;
- coefficients ;
- frais externes reconnus ;
- durée de réservation ;
- seuils antifraude ;
- règles de remboursement ;
- plafonds et fréquences.

Ces paramètres sont soumis à ADR-0002. Ils ne peuvent contourner les invariants constitutionnels.

## 11. Critères d'acceptation

Pour toute campagne, Wasplex doit pouvoir démontrer :

1. que le financement précédait la diffusion ;
2. qu'aucune dépense n'a dépassé le budget ;
3. l'état de chaque unité monétaire ;
4. la preuve liée à chaque consommation ;
5. la libération de chaque réservation rejetée ;
6. le calcul du net distribuable ;
7. le partage appliqué ;
8. la destination du solde non consommé ;
9. la version de configuration utilisée.
