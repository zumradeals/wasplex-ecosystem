# Flux intermodules de référence

**Statut :** spécification d'application — ADR-0005 adopté

## Rémunération publicitaire

`Publicité → Saga rémunération → Wallet → Publicité/Notifications`

Source de vérité de la qualification : Publicité.  
Source de vérité du droit WP : Wallet.

## Retrait

`Utilisateur → Wallet → Saga retrait → Adaptateur paiement → Rapprochement → Wallet`

Source de vérité de l'intention et de la réservation : Wallet.  
La preuve externe complète mais ne remplace pas le ledger.

## Vœu social

`Fonds Social → Wallet → Fonds Social → Prestataire/Partenaire → Wallet`

Source de vérité du vœu : Fonds Social.  
Source de vérité de chaque réservation et mouvement : Wallet.

## Récompense d'alerte

`Alertes → Wallet → Alertes/Institution → Validation restitution → Wallet`

La déclaration, la restitution et la rémunération sont des faits distincts. Une personne retrouvée ne déclenche jamais une récompense.

## Distribution Carte

`Cartes → Calcul de pool → Wallet → Cartes/Notifications`

Cartes justifie le revenu externe et la formule. Wallet vérifie et comptabilise. Une période de pool clôturée n'est pas recalculée.

## Récompense Live

`Live → Qualification d'intervalle/interaction → Saga rémunération → Wallet → Live/Notifications`

Live possède la session, la présence et la preuve. Publicité possède le budget lorsqu'il s'agit d'un Live publicitaire. Wallet possède le droit WP. Une instruction porte une clé idempotente, la publication de configuration et l'enveloppe réservée ; aucune présence seule ne crée de valeur.

Une interruption arrête prospectivement les nouveaux événements. Les événements déjà qualifiés restent contrôlables et ne sont annulés qu'avec une cause probante.

## Transmission institutionnelle

`Alertes → Autorisations → Institutions → Adaptateur → Accusé → Alertes`

Envoyé, reçu, accepté et pris en charge restent des états différents.

## Règle commune

Pour chaque flux, la spécification détaillée doit indiquer :

- initiateur ;
- commande ;
- propriétaire de chaque état ;
- événements ;
- enveloppe ou source économique ;
- autorisations ;
- idempotence ;
- délais ;
- résultat inconnu ;
- compensations ;
- preuve de clôture.
