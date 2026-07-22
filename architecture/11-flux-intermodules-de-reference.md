# Flux intermodules de référence

**Statut :** spécification proposée — ADR-0005

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