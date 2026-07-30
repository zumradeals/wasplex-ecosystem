# Abonnements et types économiques d’utilisateurs

## 1. Objectif

Les abonnements Wasplex donnent accès à des capacités, services et plafonds différents. Ils ne constituent pas un investissement et ne garantissent ni revenu, ni nombre de publicités, ni montant de WasPoints.

Les abonnements sont liés à trois types économiques utilisés par le moteur publicitaire.

## 2. Plans d’abonnement

Chaque plan doit être configurable depuis l’administration avec au minimum :

- nom affiché ;
- code interne stable ;
- description ;
- prix ;
- devise ;
- durée ;
- période de renouvellement ;
- type économique rattaché ;
- services inclus ;
- conditions de retrait éventuelles ;
- plafonds ;
- dates d’effet ;
- état actif ou inactif ;
- ordre d’affichage.

Les noms historiques comme Découverte, Premium, Élite ou Master peuvent servir de référence visuelle, mais aucun de ces noms ne doit être imposé dans le code. Le dirigeant peut les renommer depuis l’administration.

## 3. Trois types économiques

Il existe exactement trois types économiques pour organiser les conditions publicitaires des utilisateurs.

Le nom public de chaque type est configurable.

Chaque type possède au minimum :

- un ou plusieurs plans d’abonnement associés ;
- un quota de publicités rémunérables par mois ;
- une règle de rémunération ;
- un plafond mensuel éventuel ;
- une période de remise à zéro ;
- une date d’effet ;
- un état actif ou inactif.

Un même plan ne doit être rattaché qu’à un seul type actif pour une période donnée.

## 4. Quota mensuel de publicités

Le quota indique le nombre maximal d’événements publicitaires rémunérables pendant une période.

La règle finale doit préciser :

- si le quota porte sur les publicités commencées ou validées ;
- si un rejet antifraude consomme une unité ;
- si une même campagne peut compter plusieurs fois ;
- si le mois est civil ou glissant ;
- la timezone de remise à zéro ;
- le comportement après épuisement du quota ;
- le traitement d’un changement de plan en cours de période.

Recommandation fonctionnelle : le quota devrait être consommé uniquement lorsqu’un événement valide produit effectivement un gain. Cette recommandation doit être confirmée avant codage définitif.

## 5. Règle de rémunération

L’ancien dépôt utilisait des paramètres comme `gain_bonus_percent`, `price_multiplier`, `quota_pubs_monthly` et un prix de base global administrable. Ces champs constituent une référence fonctionnelle utile, mais leur formule exacte n’est pas automatiquement reprise.

La nouvelle administration doit permettre de configurer la règle décidée sans modifier le code.

La règle peut prendre la forme d’un :

- pourcentage ;
- coefficient ;
- gain fixe ;
- prix différent de l’événement selon le type.

Le choix final doit être documenté dans `01-modele-economique-publicitaire.md` et ne jamais dépasser la part utilisateur réellement financée par la campagne.

## 6. Changement de plan

Lors d’un changement de plan :

- les gains déjà acquis restent inchangés ;
- les événements passés ne sont pas recalculés ;
- la nouvelle règle s’applique uniquement selon une date d’effet identifiable ;
- l’utilisateur doit connaître l’effet du changement sur son quota restant.

Les règles suivantes restent à décider :

- proratisation du prix ;
- application immédiate ou au prochain cycle ;
- calcul du quota lors d’un surclassement ;
- calcul du quota lors d’un déclassement ;
- remboursement d’une période non utilisée.

## 7. Expiration et résiliation

L’expiration d’un abonnement :

- ne supprime pas les WasPoints déjà gagnés ;
- ne supprime pas l’historique ;
- rattache l’utilisateur au plan ou type prévu pour l’état expiré ;
- désactive seulement les capacités qui dépendaient réellement du plan.

La plateforme doit éviter tout blocage artificiel du Wallet ou des retraits légitimes à cause d’une simple expiration commerciale.

## 8. Administration

L’administration doit offrir :

- création et modification versionnée des plans ;
- création et renommage des trois types ;
- rattachement des plans aux types ;
- configuration des quotas ;
- configuration de la rémunération ;
- aperçu de l’effet avant publication ;
- date de mise en application ;
- historique des anciennes versions ;
- suspension sans suppression de l’historique.

## 9. Affichage utilisateur

Avant l’achat, l’utilisateur doit voir :

- prix et durée ;
- services inclus ;
- type économique associé ;
- quota publicitaire ;
- règle de gain expliquée simplement ;
- absence de garantie de disponibilité des campagnes ;
- conditions de renouvellement et résiliation.

L’interface ne doit jamais promettre une rentabilité calculée à partir du prix de l’abonnement.

## 10. Décisions ouvertes

- noms commerciaux initiaux des trois types ;
- plans rattachés à chaque type ;
- quotas de départ ;
- sens exact du pourcentage ou coefficient ;
- règles d’upgrade et downgrade ;
- période de remise à zéro ;
- publicité visible ou non après quota ;
- plafonds mensuels en WasPoints.