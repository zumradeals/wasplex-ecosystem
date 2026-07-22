# Moteur de configuration métier

**Statut :** spécification proposée — ADR-0002

## Objet

Cette spécification traduit ADR-0002 en contraintes de conception. Elle ne remplace pas la Constitution.

## Principes

- Les définitions sont créées par migration ou processus technique contrôlé ; l'administration ne crée pas librement de nouvelles clés.
- Les valeurs sont immuables après approbation.
- Une publication groupe les valeurs qui doivent prendre effet ensemble.
- La résolution est déterministe, datée et explicable.
- Toute opération sensible conserve la publication et les paramètres résolus qui l'ont gouvernée.
- Aucun fallback silencieux n'est autorisé pour une valeur financière, juridique ou de sécurité.
- Aucun code arbitraire n'est exécutable depuis une configuration.

## Contrat de définition

Chaque définition indique :

- clé stable et libellé ;
- module propriétaire ;
- description métier ;
- niveau de gouvernance C0 à C3 ;
- type, unité, précision et règle d'arrondi ;
- plages et valeurs admises ;
- portées autorisées ;
- dépendances et contraintes croisées ;
- valeur de sécurité si elle existe ;
- politique d'effet sur les objets en cours ;
- tests requis ;
- données autorisées dans l'audit.

## Portées autorisées

Les portées ordinaires sont global, pays, devise, produit, programme, offre et campagne. Une portée par utilisateur est interdite sauf besoin métier explicitement approuvé ; elle ne peut servir à favoriser secrètement une personne.

Le canal d'accès ne peut modifier une règle économique. Web, PWA, desktop et Android résolvent la même vérité.

## Formules

Les formules financières sont des modèles de code testés et versionnés. L'administration ne choisit que leurs paramètres autorisés. Toute formule vérifie conservation des fonds, bornes, unité, précision, arrondi et résultat maximal.

Les pourcentages affichés ne constituent jamais la source du calcul si leur précision est insuffisante.

## Cohérence transactionnelle

Une opération résout sa configuration une fois au début de sa transaction, puis utilise ce snapshot jusqu'à la fin. Les traitements asynchrones reçoivent la référence à la publication ; ils ne relisent pas automatiquement la version devenue active entre-temps.

Les clés d'idempotence incluent la version de l'objet métier mais ne permettent jamais de payer deux fois une même prestation.

## Cache

Le cache accélère la lecture sans devenir source de vérité. Chaque entrée porte publication, empreinte et expiration. L'activation invalide les entrées concernées. Une divergence de publication entre nœuds bloque les nouvelles opérations C1 jusqu'au retour à la cohérence.

## Administration

L'interface sépare création, simulation, revue, approbation et activation. Elle affiche l'effet humain et économique, pas seulement la représentation technique.

Les exports de configuration omettent secrets et données personnelles et possèdent une empreinte vérifiable.

## Tests d'acceptation minimaux

- impossibilité de modifier C0 ;
- refus d'auto-approbation C1 ;
- refus d'une enveloppe déficitaire ;
- absence de chevauchement ambigu ;
- respect de la date d'effet ;
- contrat existant préservé ;
- opération asynchrone utilisant sa version initiale ;
- retour créant une nouvelle version ;
- interrupteur expirant automatiquement ;
- reconstruction historique exacte ;
- égalité des résultats entre tous les canaux ;
- échec fermé si une valeur critique est inconnue.