# ADR-0003 — Registre comptable immuable du Wallet — brouillon historique non normatif

**État :** proposé  
**Date :** 2026-07-21

## Contexte

Le Wallet doit gérer des gains provisoires, disponibles et réservés, des retraits asynchrones, des rejets anti-fraude et de futurs modules sans permettre la double dépense ni l'effacement de l'historique.

## Décision proposée

Construire le Wallet sur un grand livre en partie double, append-only.

Les trois états sont des comptes comptables, non trois colonnes de solde modifiées directement. Les soldes affichés sont dérivés d'écritures équilibrées. Une erreur est corrigée par contrepassation ; une écriture validée n'est ni modifiée ni supprimée.

Comptes minimaux :

- utilisateur / provisoire ;
- utilisateur / disponible ;
- utilisateur / réservé ;
- campagne annonceur / fonds réservés ;
- plateforme / part Wasplex ;
- règlement / transit prestataire ;
- comptes de contrepartie et d'ajustement strictement contrôlés.

## Exigences

- montants entiers en WP, sans flottants ;
- transaction de base de données atomique ;
- clé d'idempotence par événement externe ;
- référence vers la campagne, l'utilisateur, la règle tarifaire et sa version ;
- journal d'audit séparé des écritures comptables ;
- rapprochement quotidien avec campagnes préfinancées et prestataires de paiement ;
- séparation des rôles entre configuration, validation, correction et audit ;
- aucune suppression physique d'une écriture financière ;
- reconstruction vérifiable de tout solde à une date donnée.

## Conséquences

Cette approche demande plus de rigueur initiale qu'un simple champ `balance`, mais elle évite les doubles crédits, rend les litiges explicables et permet d'intégrer retraits, Fonds social et paiements sans reconstruire le cœur financier.

## Limite de lancement recommandée

Pour réduire le risque réglementaire et opérationnel, le premier périmètre devrait limiter le Wallet aux récompenses financées par les campagnes et aux retraits via un prestataire autorisé. Les dépôts en espèces, transferts P2P et paiements chez des tiers sont différés jusqu'à validation juridique et contractuelle.
