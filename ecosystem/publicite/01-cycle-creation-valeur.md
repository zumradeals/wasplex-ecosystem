# Cycle fondamental de création de valeur publicitaire

- **Statut :** spécification métier v0.1 à valider
- **Autorité :** normative après validation
- **Dépend de :** Constitution, articles 1 à 10
- **Source :** `sources/2026-07-21-entretien-fondateur-02-publicite.md`

## 1. Pourquoi ce chapitre vient maintenant

La publicité constitue la source primaire de valeur économique de Wasplex. Le wallet, les WasPoints, les niveaux d'adhésion et les fonds transversaux ne peuvent être définis correctement avant de savoir :

- ce que l'annonceur achète ;
- quel événement justifie une facturation ;
- quel événement justifie une rémunération ;
- comment une audience est constituée ;
- quelle somme devient distribuable.

Ce chapitre définit le contrat de valeur, pas encore l'interface annonceur ni les pourcentages de redistribution.

## 2. Objet économique vendu

Wasplex vend un service de mise en relation publicitaire produisant une attention :

- volontaire ;
- qualifiée par des critères consentis ;
- mesurable ;
- vérifiable ;
- conforme aux règles de campagne.

Wasplex ne vend pas :

- une identité ;
- une base de contacts ;
- un numéro de téléphone ;
- une adresse électronique ;
- une liste nominative ;
- un droit d'accès aux profils individuels ;
- la donnée personnelle elle-même.

L'actif économique est la pertinence prouvée de la rencontre entre un message et une audience consentante.

## 3. Unité facturable

Un affichage seul n'est jamais facturable.

Une campagne devient facturable événement par événement lorsqu'un **événement publicitaire qualifié** est produit et accepté.

Un événement qualifié doit comporter au minimum :

- un identifiant unique ;
- la campagne et le format concernés ;
- une preuve de la condition attendue ;
- un horodatage fiable ;
- une décision anti-fraude ;
- une règle de prix applicable ;
- un statut empêchant toute double facturation ;
- une trace d'audit.

Selon le format, la condition peut être une complétion, une durée minimale, une interaction demandée ou une autre action publiée avant la campagne.

## 4. Symétrie facturation-rémunération

Pour une même condition publicitaire :

1. aucune facturation annonceur sans preuve acceptée ;
2. aucune rémunération utilisateur sans preuve acceptée ;
3. une même preuve ne peut produire qu'une seule facturation et une seule rémunération, sauf règle explicite contraire ;
4. une preuve rejetée doit porter une raison traçable ;
5. les litiges et corrections ne doivent jamais effacer l'historique.

Le moment technique exact de réservation, facturation, règlement et crédit sera défini avec le modèle financier.

## 5. Constitution des segments

L'annonceur décrit une audience au moyen de critères autorisés. Wasplex effectue la correspondance en interne.

Exemples de familles de critères à instruire :

- pays, région ou zone ;
- tranche d'âge ;
- langue ;
- centres d'intérêt ;
- activité professionnelle ;
- habitudes de consommation déclarées ;
- niveau d'adhésion ou classe d'engagement autorisée.

L'annonceur reçoit :

- une estimation agrégée de la taille du segment ;
- un prix ;
- des résultats agrégés ;
- les indicateurs de campagne autorisés.

Il ne reçoit jamais l'identité ni les coordonnées des personnes du segment.

## 6. Niveau d'adhésion comme qualification

Le niveau d'adhésion peut devenir un critère de qualification d'audience si :

1. l'utilisateur en est informé ;
2. la sélection ne révèle pas son identité ;
3. le segment respecte un seuil minimal d'anonymisation ;
4. le niveau n'est pas utilisé comme approximation abusive d'une caractéristique sensible ;
5. le socle gratuit conserve un accès réel à l'écosystème ;
6. le prix supplémentaire payé par l'annonceur possède une justification économique publique ;
7. la valeur supplémentaire attribuable à cette qualification est répartie selon une règle traçable.

Le niveau exact d'adhésion visible dans les rapports reste à décider. Une classe d'engagement agrégée peut être préférable à la divulgation du niveau commercial exact.

## 7. Cascade de répartition

Chaque montant facturable doit être ventilé selon une cascade versionnée.

Destinataires constitutionnellement envisagés :

1. utilisateur à l'origine de l'attention qualifiée ;
2. Wasplex ;
3. fonds permanents autorisés de l'écosystème.

Les pourcentages ne sont pas fixés dans ce document.

Avant activation d'une formule, le système devra vérifier que :

- la somme des parts égale le montant distribuable ;
- aucune part n'est négative ;
- la version de la formule est enregistrée ;
- le calcul peut être reproduit ;
- les frais, taxes, remboursements et provisions sont traités explicitement ;
- la formule ne dépense pas un revenu non encaissé ou non garanti.

## 8. Invariants

1. La donnée personnelle n'est jamais un produit.
2. Aucun affichage seul n'est facturable.
3. Aucune facturation sans preuve vérifiable.
4. Aucune rémunération sans accomplissement de la condition annoncée.
5. Aucune double facturation ou double rémunération pour une même preuve.
6. Aucun accès annonceur aux identités ou coordonnées.
7. Chaque franc facturé possède une trace et une destination explicites.
8. Toute formule économique est versionnée et auditable.
9. La révocation d'un consentement doit empêcher les ciblages futurs concernés.
10. Une statistique agrégée ne doit pas permettre la réidentification.

## 9. Hors périmètre volontaire

Ce chapitre ne fixe pas encore :

- les formats publicitaires ;
- les prix unitaires ;
- les pourcentages ;
- la monnaie et la fiscalité ;
- le prépaiement ou le crédit annonceur ;
- la nature juridique du WasPoint ;
- les règles détaillées du wallet ;
- les secteurs publicitaires autorisés ;
- les seuils d'anonymisation ;
- les méthodes techniques anti-fraude.

## 10. Critère de compréhension suffisante

La matière sera suffisamment comprise lorsque Wasplex pourra décrire sans ambiguïté, pour un événement publicitaire :

1. ce que l'annonceur a demandé ;
2. ce que l'utilisateur a volontairement accompli ;
3. la preuve produite ;
4. le prix applicable ;
5. la somme distribuable ;
6. les destinataires ;
7. les données visibles par chaque acteur ;
8. la gestion d'un rejet, doublon ou litige.
