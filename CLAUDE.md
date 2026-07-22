# Protocole d'exécution Claude Code — Wasplex

Ce fichier gouverne toute intervention de Claude Code dans ce dépôt et dans le futur dépôt applicatif Wasplex. Il complète les règles humaines ; il ne remplace aucune décision du Grand Livre.

## 1. Autorité et lecture préalable

Avant toute conception ou modification, lire dans cet ordre :

1. `CONSTITUTION.md` ;
2. les amendements concernés dans `amendments/` ;
3. les ADR, DS et UX adoptés dans `decisions/` ;
4. les spécifications du domaine dans `ecosystem/`, `architecture/`, `design/` et `ux/` ;
5. le contrat d'écran ou le lot demandé ;
6. le code et les tests existants.

En cas de contradiction, appliquer la source supérieure, arrêter la partie concernée et signaler précisément les deux passages incompatibles. Ne jamais résoudre silencieusement une contradiction normative.

## 2. Règles absolues du produit

- Les seuls acteurs constitutionnels sont Wasplex, les utilisateurs, les annonceurs et les institutions affiliées.
- Aucun « Agent » métier ne doit être réintroduit.
- La donnée personnelle n'est jamais vendue ni exposée comme une base de contacts.
- Aucun crédit Wallet sans financement, preuve, idempotence et écritures équilibrées.
- Aucun rendement, gain ou disponibilité de campagne ne doit être garanti.
- Les invariants C0 ne sont jamais administrables.
- Les paramètres commerciaux, quotas, prix, seuils et offres ne sont jamais codés en dur : ils sont versionnés et auditables.
- Les noms commerciaux ne servent jamais de clés d'autorisation.
- Le socle initial reste PHP/Laravel, PostgreSQL et React/TypeScript, sous forme de monolithe modulaire.
- Supabase et toute architecture distribuée supplémentaire sont interdits sans nouvelle décision adoptée.
- Mobile est primaire pour l'utilisateur ; desktop est primaire pour annonceurs, institutions et administration.

## 3. Une tâche, une branche

Pour chaque tâche validée :

1. synchroniser `main` sans réécrire son historique ;
2. créer une branche `claude/<lot-ou-ticket>-<objet-court>` ;
3. vérifier que la branche courante n'est pas `main` avant toute écriture ;
4. limiter les modifications au périmètre annoncé ;
5. préserver les changements humains non liés.

Claude Code ne pousse jamais directement sur `main`, ne force-pousse jamais et ne fusionne jamais une branche.

## 4. Cycle obligatoire avant commit

Claude Code peut modifier localement la branche et exécuter les contrôles nécessaires. Il **ne doit pas créer de commit ni pousser** avant validation explicite de Koné ou de SIRR.

Il présente d'abord un dossier de validation comprenant :

- objectif et résultat observable ;
- fichiers créés, modifiés ou supprimés ;
- décisions et exigences tracées ;
- choix techniques et raisons ;
- migrations, données, permissions et effets financiers éventuels ;
- tests exécutés avec résultats ;
- captures pour tout changement visuel ;
- risques, hypothèses et éléments non réalisés ;
- diff disponible pour revue.

Après validation explicite seulement, Claude Code peut créer un commit intentionnel et pousser **la branche dédiée**. Une nouvelle validation est nécessaire si le périmètre ou la solution change matériellement après cette autorisation.

## 5. Fusion et mise en production

- Une pull request reste en brouillon jusqu'à revue de SIRR et décision de Koné.
- Seul Koné autorise la fusion dans `main`.
- Une validation de code n'autorise pas implicitement un déploiement.
- Aucun déploiement, migration de production, activation pays, traitement réel ou communication externe sans instruction distincte et explicite.

## 6. Discipline d'implémentation

- Respecter les frontières de modules et leur propriété des données.
- Passer par commandes, contrats et événements documentés ; aucun accès opportuniste aux tables d'un autre domaine.
- Toute opération économique est atomique, idempotente, rapprochable et auditable.
- Les corrections comptables utilisent des contre-écritures ; aucune mutation ou suppression du ledger.
- Tout état externe incertain reste `unknown` jusqu'au rapprochement ; ne jamais inventer un succès.
- Toute autorisation exprime identité, organisation, capacité, finalité, portée, territoire et durée utiles.
- Toute collecte de donnée exprime finalité, base, minimisation, rétention et droits.
- Tout écran expose les états chargement, vide, erreur, hors ligne et inconnu pertinents.
- Les appareils modestes, réseaux faibles, accessibilité et langue française claire font partie de la qualité minimale.

## 7. Changements nécessitant un arrêt

Arrêter et demander une décision si la tâche exige :

- de modifier un invariant constitutionnel ou une décision adoptée ;
- d'inventer une formule économique, un pourcentage ou un droit ;
- d'activer une capacité juridiquement conditionnée ;
- d'ajouter un acteur, un module transversal ou une dépendance structurante non décidés ;
- d'exposer des données personnelles à un annonceur ou à une institution au-delà de sa finalité ;
- de supprimer des données, réécrire l'historique Git ou lancer une action irréversible ;
- de contourner un test, un gate ou une règle de sécurité pour terminer plus vite.

Une question bloquante doit contenir : le fait observé, les sources consultées, les options sûres et l'impact de chacune.

## 8. Format de compte rendu

```text
Tâche :
Branche :
Décisions appliquées :
Résultat :
Fichiers :
Tests :
Captures :
Risques / hypothèses :
Éléments différés :
Autorisation demandée : revue du diff / commit / push / fusion / déploiement
```

Une autorisation ne vaut que pour l'étape explicitement demandée.

