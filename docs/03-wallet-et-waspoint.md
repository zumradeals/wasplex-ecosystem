# Wallet et WasPoint

## 1. Référence de valeur

Un WasPoint représente une unité interne de droit économique dans Wasplex.

**1 WP = 1 FCFA.**

Le WasPoint n’est pas présenté comme une cryptomonnaie, un placement ou une promesse de rendement.

## 2. Origine des WasPoints

Un crédit de WasPoints doit toujours avoir une origine identifiable, par exemple :

- part utilisateur d’un événement publicitaire validé ;
- avantage ou partage provenant d’une opération partenaire réelle ;
- correction justifiée ;
- autre programme explicitement financé.

Un abonnement, une cotisation au Fonds social ou un dépôt utilisateur ne crée pas automatiquement un gain en WasPoints.

## 3. États utiles

Le Wallet doit distinguer au minimum :

- **provisoire** : droit enregistré mais encore soumis à une vérification ou un délai ;
- **disponible** : montant validé utilisable ou retirable selon les conditions ;
- **réservé** : montant attribué à l’utilisateur mais temporairement engagé dans une opération ;
- **payé ou retiré** : montant effectivement réglé ;
- **annulé ou corrigé** : valeur retirée par une écriture de correction motivée.

Le solde affiché doit être reconstructible depuis l’historique des mouvements.

## 4. Traçabilité

Chaque mouvement doit enregistrer :

- un identifiant unique ;
- la personne concernée ;
- le montant et la devise ;
- le type de mouvement ;
- la source métier ;
- la date ;
- l’état ;
- la référence de campagne, partenaire, dossier ou paiement ;
- une clé empêchant le double traitement ;
- une description compréhensible.

Une correction ne supprime pas l’écriture d’origine. Elle ajoute une contre-écriture afin de conserver l’historique.

## 5. Couverture financière

Aucun WP disponible ne doit être créé sans valeur financière réellement couverte.

Pour la publicité, la couverture vient du budget préfinancé de la campagne.

Pour les partenaires, elle vient d’une commission ou d’un revenu externe validé.

Pour toute autre source, le financement doit être défini avant l’émission.

## 6. Retrait

Le parcours de retrait doit préciser :

- montant minimum ;
- frais éventuels ;
- canal de paiement ;
- identité ou niveau de vérification requis ;
- montant réservé pendant l’opération ;
- statut envoyé, confirmé, échoué ou inconnu ;
- procédure de rapprochement ;
- délai estimatif ;
- recours disponible.

Un résultat technique incertain ne doit jamais être présenté comme un paiement réussi.

## 7. Protection du solde

- Une expiration d’abonnement ne supprime pas les gains acquis.
- Une suspicion de fraude ne transforme pas automatiquement tout le solde en valeur frauduleuse.
- Un Wallet négatif ne doit pas être créé silencieusement.
- Une fraude confirmée ou une erreur réelle est traitée par une correction traçable.
- Un retrait déjà payé à tort devient un dossier de recouvrement ou de contestation, pas une mutation invisible de l’historique.

## 8. Affichage utilisateur

Le Wallet doit présenter clairement :

- solde disponible ;
- solde provisoire ;
- montant réservé ;
- historique ;
- origine de chaque gain ;
- état de chaque retrait ;
- équivalence WP/FCFA ;
- raisons d’un blocage ou d’un rejet.

## 9. Administration et rapprochement

L’administration doit pouvoir :

- rechercher une opération ;
- lire ses références ;
- consulter les preuves ;
- rapprocher un paiement externe ;
- effectuer une correction autorisée ;
- traiter une contestation ;
- exporter des états comptables sans modifier les écritures historiques.

## 10. Décisions ouvertes

- seuils de retrait par plan ;
- frais de retrait ;
- délais de disponibilité ;
- plafonds KYC ;
- canaux de paiement disponibles par pays ;
- politique de provision antifraude.