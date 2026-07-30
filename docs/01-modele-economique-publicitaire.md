# Modèle économique publicitaire Wasplex

## 1. Objectif

Wasplex permet à un annonceur de financer une campagne destinée à une audience précise et permet aux utilisateurs qui fournissent volontairement une attention publicitaire valide de recevoir des WasPoints.

Wasplex ne vend pas un fichier de personnes. Il vend un service de diffusion ciblée, de vérification de l’attention et de mesure des résultats.

## 2. Ce que l’annonceur achète

L’annonceur achète des événements publicitaires qualifiés auprès d’une audience correspondant aux critères autorisés de sa campagne.

Selon le format, un événement qualifié peut être par exemple :

- une vidéo regardée jusqu’au seuil annoncé ;
- une image affichée pendant une durée minimale définie ;
- un clic valide ;
- une demande d’appel ;
- un SMS initié ;
- une demande d’itinéraire ;
- une autre action mesurable définie dans le catalogue publicitaire.

Le simple fait qu’une publicité apparaisse à l’écran ne crée pas automatiquement un débit annonceur ni un gain utilisateur. La condition facturable doit être annoncée et techniquement vérifiée.

## 3. Ciblage et segmentation

L’annonceur construit son audience à partir de critères proposés par Wasplex. Les critères possibles doivent provenir de profils publicitaires consentis et de taxonomies administrées.

Familles de critères à prévoir :

- pays ;
- ville ;
- commune, quartier ou zone ;
- tranche d’âge ;
- genre lorsque son usage est autorisé ;
- centres d’intérêt ;
- secteur professionnel déclaré ;
- biens possédés, par exemple posséder un véhicule ;
- projets déclarés, par exemple projet d’achat automobile ;
- intentions ou besoins déclarés ;
- habitudes de consommation autorisées ;
- type économique ou niveau d’abonnement lorsque la campagne le permet.

Ces familles ne doivent pas être confondues. Être intéressé par l’automobile ne signifie pas posséder un véhicule.

### Exemple BMW

BMW doit pouvoir demander une campagne :

- en Côte d’Ivoire ;
- à Abidjan ;
- dans la commune d’Abobo ;
- pour des personnes ayant déclaré posséder un véhicule ;
- intéressées par l’automobile ;
- appartenant éventuellement à une tranche d’âge et à certains types économiques.

Wasplex calcule une audience agrégée et ne remet jamais à BMW les noms, numéros, emails ou profils individuels correspondants.

## 4. Estimation d’audience

Avant le paiement, Wasplex doit présenter une estimation fondée sur les profils consentis réellement disponibles.

L’estimation doit préciser :

- le nombre approximatif de profils correspondant aux critères ;
- la date du calcul ;
- les critères appliqués ;
- les éventuels critères trop précis ou non disponibles ;
- la portée unique estimée ;
- la fréquence envisagée ;
- le nombre estimé d’événements achetables avec le budget.

Les audiences trop petites ne doivent pas être révélées de manière permettant d’identifier une personne.

## 5. Catalogue tarifaire

Wasplex doit configurer depuis l’administration un catalogue publicitaire versionné.

Le catalogue peut comprendre :

- type d’événement facturable ;
- format : vidéo, image, bannière ou autre ;
- tranche de durée ;
- prix de base ;
- devise ;
- pays ou marché ;
- période d’application ;
- coefficient de rareté ou de précision du segment ;
- coefficient de volume ;
- coefficient lié au type économique ciblé ;
- coefficient lié au niveau de preuve ;
- minimum de campagne ;
- taxes et frais externes applicables ;
- règle d’arrondi ;
- date d’entrée en vigueur.

Aucun prix commercial ne doit être codé en dur dans l’interface.

### Deux modèles de calcul possibles

Le moteur peut être construit selon l’une des approches suivantes, à valider avant l’implémentation finale :

1. **Catalogue par produit** : un prix administré pour chaque combinaison, par exemple vidéo 30 secondes complétée.
2. **Prix de base et coefficients** : un prix de base auquel sont appliqués des coefficients de durée, ciblage, volume et type d’utilisateur.

Le système doit pouvoir expliquer le calcul à l’annonceur et conserver la version utilisée par la campagne.

## 6. Devis annonceur

Avant tout financement, l’annonceur doit voir un devis compréhensible comprenant au minimum :

- audience estimée ;
- événement acheté ;
- format et durée ;
- prix unitaire estimatif ;
- nombre d’événements visés ;
- budget total ou budget choisi ;
- taxes et frais externes ;
- montant net distribuable estimé ;
- estimation de la part Wasplex ;
- estimation de la part utilisateurs ;
- gain applicable par type d’utilisateur ;
- dates de début et de fin ;
- fréquence maximale ;
- traitement du solde non consommé.

L’ancienne maquette de campagne contenait plusieurs fonctions utiles à conserver dans le produit final : choix de durée, coût par vue, récompense utilisateur, estimation des vues, WasPoints distribués, budget, dates, CTA, niveaux ciblés et centres d’intérêt. Ses anciennes valeurs numériques ne sont pas des tarifs validés.

## 7. Financement et consommation du budget

Le cycle attendu est :

1. campagne créée en brouillon ;
2. audience et devis calculés ;
3. annonceur confirme le budget ;
4. paiement reçu et rapproché ;
5. campagne examinée et approuvée ;
6. campagne activée ;
7. une somme est réservée lorsqu’un événement commence si nécessaire ;
8. l’événement est validé ou rejeté ;
9. le budget est consommé uniquement si l’événement est valide ;
10. la valeur est répartie ;
11. le reliquat est réutilisé ou remboursé selon la règle commerciale affichée.

Les états du budget doivent distinguer :

- reçu ;
- disponible ;
- réservé ;
- consommé ;
- remboursable ;
- remboursé ;
- contesté.

## 8. Partage de la valeur

Pour chaque événement publicitaire validé, après retrait des taxes obligatoires et des frais externes directement liés à l’encaissement :

- 50 % du montant net distribuable reviennent à Wasplex ;
- 50 % sont destinés à la rémunération des utilisateurs.

Les frais internes de Wasplex sont financés par la part Wasplex.

La somme des parts doit toujours correspondre exactement au montant net distribuable.

## 9. Trois types économiques d’utilisateurs

La part utilisateur est gouvernée par trois types économiques liés aux niveaux d’abonnement.

Pour chacun des trois types, l’administration doit pouvoir configurer :

- le nom affiché ;
- un identifiant interne stable ;
- les abonnements rattachés ;
- le nombre de publicités rémunérables par mois ;
- le pourcentage, coefficient ou montant de rémunération applicable ;
- le plafond mensuel éventuel ;
- la période et la date de remise à zéro ;
- le pays ou marché ;
- la devise ;
- les dates d’effet ;
- l’état actif ou inactif.

Le nom du type ne doit jamais être codé comme une règle technique.

### Décision encore ouverte : sens du pourcentage

La signification exacte du pourcentage attribué à chaque type doit être décidée avant de finaliser le moteur financier. Les options à trancher sont notamment :

- pourcentage de la moitié utilisateurs ;
- multiplicateur d’un gain de base ;
- gain fixe par événement selon le type ;
- prix annonceur différent selon le type atteint ;
- poids dans une enveloppe collective.

Le choix doit indiquer clairement la destination de tout reliquat et préserver le partage global 50/50. Aucun développeur ne doit choisir silencieusement une de ces options.

## 10. WasPoint et gain utilisateur

La référence est :

**1 WasPoint = 1 FCFA.**

Avant de commencer une publicité rémunérée, l’utilisateur doit connaître :

- la durée ou l’action requise ;
- le gain exact applicable à son type ;
- la raison pour laquelle le gain peut être refusé ;
- son quota restant si cette information est pertinente.

Le montant affiché avant la participation doit être le montant effectivement crédité lorsque l’événement est validé.

## 11. Distribution dans le Feed

Une campagne ne doit pas être affichée indistinctement à tous les utilisateurs.

Le moteur de distribution doit vérifier au minimum :

- campagne approuvée et active ;
- dates de diffusion ;
- budget suffisant ;
- correspondance avec le profil publicitaire ;
- consentement actif ;
- type ou abonnement ciblé ;
- quota mensuel restant ;
- fréquence maximale par utilisateur ;
- absence de double rémunération ;
- règles de sécurité et de modération.

Une publicité peut éventuellement rester visible après épuisement du quota sans être rémunérée, mais ce comportement doit être décidé et affiché sans ambiguïté.

## 12. Preuve et lutte contre la fraude

Une vue ou action rémunérée doit posséder :

- un identifiant unique ;
- la campagne et sa version ;
- l’utilisateur bénéficiaire ;
- l’événement attendu ;
- le seuil atteint ;
- les horodatages utiles ;
- la règle de prix ;
- la règle de rémunération ;
- la décision de validité ;
- une clé empêchant la double facturation.

Une même preuve ne peut pas débiter deux fois la campagne ni créditer deux fois l’utilisateur.

## 13. Reporting annonceur

L’annonceur reçoit des résultats agrégés :

- budget initial, consommé et restant ;
- événements commencés, validés et rejetés ;
- portée unique ;
- fréquence ;
- taux de complétion ;
- clics ou CTA ;
- répartition géographique agrégée ;
- performance par segment lorsque la taille protège les utilisateurs ;
- coût moyen réel ;
- dates et statut de la campagne.

Aucun rapport ne doit exposer les identités ou coordonnées des personnes ciblées.

## 14. État actuel du dépôt

Le dépôt actuel possède déjà des briques de campagne, budget, ciblage, estimation, média, secteurs, événement qualifié, Wallet et partage 50/50.

Le modèle commercial reste partiel : le prix actuel est encore démonstratif, le devis complet n’existe pas, les trois types et leurs quotas ne sont pas encore intégrés, et le Feed ne réalise pas encore toute la distribution ciblée attendue.

Ces écrans et services doivent être considérés comme une base technique, pas comme le modèle économique final.

## 15. Décisions à compléter par le dirigeant

- sens exact du pourcentage des trois types ;
- prix ou formule par format et durée ;
- prix minimum d’une campagne ;
- coefficients de ciblage ;
- CTA facturables ;
- traitement des publicités après quota ;
- règle d’upgrade ou downgrade en cours de mois ;
- fréquence maximale ;
- règles du reliquat et du remboursement ;
- taxes et frais retenus avant partage.