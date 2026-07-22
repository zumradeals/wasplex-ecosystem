# Canaux, accès et mode dégradé

**Statut :** spécification d'application — ADR-0001 adopté

## Ordre de livraison

1. Web mobile responsive, canal universel et complet pour l'utilisateur.
2. PWA utilisant exactement la même application et la même logique métier.
3. Interfaces desktop adaptatives pour les opérations professionnelles complexes.
4. Android natif ultérieur, seulement lorsqu'une capacité du terminal le justifie.

Aucun canal ne possède sa propre règle économique ni sa propre source de vérité.

## Budget de sobriété

Les parcours essentiels doivent être conçus pour petits écrans, mémoire limitée, latence élevée et réseau intermittent. Les médias utilisent plusieurs qualités et formats. Avant un contenu lourd, l'utilisateur connaît sa durée, son format et, lorsque possible, sa taille approximative.

Les budgets chiffrés de poids, temps de chargement et compatibilité sont définis et testés avant chaque version de production.

## Mode dégradé

Une donnée locale affiche sa date de synchronisation. Elle ne devient jamais une preuve temps réel.

Peuvent rester disponibles selon le contexte : reçus synchronisés, numéros d'urgence, brouillon de SOS, état connu d'une opération et interfaces de récupération. Un solde en cache ne permet ni retrait, ni transfert, ni réservation.

Un SOS n'est « transmis » qu'après accusé serveur. Sans réseau, Wasplex affiche les moyens officiels directs et l'état exact de la tentative.

## Attention publicitaire

Une session publicitaire possède un identifiant signé, une version de campagne, des bornes de progression et une clé d'idempotence. Une coupure peut autoriser la reprise, jamais le double crédit. Le téléchargement, l'affichage, le temps observé, l'interaction, la transmission et la validation sont des états distincts.

## Appareils partagés

L'appareil n'est pas la personne. Le produit fournit déconnexion visible, compte actif explicite, sessions isolées, révocation d'appareil et authentification récente pour toute opération sensible.

## Permissions

Caméra, géolocalisation, microphone et notifications sont demandés juste à temps, avec finalité et solution de repli. Leur refus ne bloque que la capacité qui en dépend réellement.