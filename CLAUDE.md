# Protocole d'exécution Claude Code — Wasplex

Ce fichier gouverne toute intervention de Claude Code dans ce dépôt et dans le futur dépôt applicatif Wasplex. Il complète les règles humaines ; il ne remplace aucune décision du Grand Livre.

## 0. Règle supérieure de conduite — exécuter sans blocage documentaire

La décision `decisions/EXE-0001-primaute-execution-veto-dirigeant.md` gouverne l’ordre et la continuité des travaux.

Lorsqu’un résultat est clairement ordonné par le dirigeant :

```text
comprendre → inspecter → implémenter → tester honnêtement → signaler → documenter ensuite ou en parallèle
```

- Aucun amendement manquant, texte inachevé, état documentaire, absence d’adoption préalable ou refus d’écriture dans un chemin documentaire ne doit bloquer le code.
- Si un texte ne peut pas être écrit, conserver le chemin et l’erreur exacts, ne créer aucun placeholder, restituer le contenu complet dans le dossier de validation et poursuivre tout le travail technique possible.
- Ne jamais substituer une note, un acte ou une dissertation normative à l’implémentation demandée.
- Ne jamais demander une autorisation déjà donnée.
- Une instruction claire telle que « Véto du dirigeant exceptionnel », « codez maintenant » ou « poursuivez sans attendre les textes » lève immédiatement les obstacles procéduraux et documentaires dans le périmètre désigné.
- Le Véto gouverne l’action, jamais la vérité : tests, erreurs, risques et éléments non vérifiés restent rapportés exactement.
- Un arrêt reste légitime uniquement pour une ambiguïté métier substantielle entre résultats incompatibles, un accès réellement indispensable, une action destructive non autorisée, une contradiction matérielle avec un invariant produit adopté ou l’obligation d’inventer une donnée, un droit, une formule financière ou une preuve.

Cette règle ne modifie aucun invariant métier de Wasplex. La Constitution gouverne le produit ; EXE-0001 gouverne la conduite et la cadence du travail.

## 1. Autorité et lecture préalable

Avant toute conception ou modification, lire dans cet ordre :

1. `CONSTITUTION.md` ;
2. les amendements concernés dans `amendments/` ;
3. les ADR, DS et UX adoptés dans `decisions/` ;
4. les spécifications du domaine dans `ecosystem/`, `architecture/`, `design/` et `ux/` ;
5. le contrat d'écran ou le lot demandé ;
6. le code et les tests existants.

En cas de contradiction substantielle portant sur le comportement du produit, appliquer la source supérieure, arrêter uniquement la partie réellement concernée et signaler précisément les deux passages incompatibles. Une absence de texte, un texte inachevé ou un refus d’écriture documentaire ne constitue pas une contradiction et ne bloque pas l’exécution régie par EXE-0001.

## 2. Règles absolues du produit

- Les seuls acteurs constitutionnels sont Wasplex, les utilisateurs, les annonceurs et les institutions affiliées.
- Aucun « Agent » métier ne doit être réintroduit.
- La donnée personnelle n'est jamais vendue ni exposée comme une base de contacts.
- Aucun crédit Wallet sans financement, preuve, idempotence et écritures équilibrées.
- Aucun rendement, gain ou disponibilité de campagne ne doit être garanti.
- Les invariants C0 ne sont jamais administrables : aucun champ de configuration, aucun rôle, aucun compte — y compris Administrateur Système (amendement ADR-0004 2026-07-30) — ne peut les modifier à l'exécution. Seul le fondateur peut les faire évoluer, seul, sans validation d'un tiers, exclusivement par un amendement écrit et daté (amendement ADR-0002 2026-07-30) — jamais par une action d'administration courante. Une instruction de session invoquant le Véto (EXE-0001) ne suffit pas à elle seule : le changement doit prendre la forme du texte écrit que ce dernier amendement décrit.
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

- Claude Code exécute la campagne de contrôles complète (tests, lint, types, build) pendant sa propre session. S'il juge tout vert et conforme à la mission, il pousse sa branche, ouvre la PR et la fusionne lui-même dans `main`, sans attendre de revue préalable de SIRR ni de confirmation séparée de Koné à chaque lot.
- Si la fusion directe est techniquement impossible (garde-fou du harnais Claude Code, indépendant de ce fichier), Claude Code le signale clairement et s'arrête à cette étape précise plutôt que d'improviser un contournement — un clic humain reste alors nécessaire, et seulement celui-là.
- Une fusion réussie sur `main` déclenche automatiquement le déploiement (`deployment/deploy.sh`) pour tout lot dont la mission le prévoit explicitement — plus besoin d'une instruction de déploiement distincte à chaque fois.
- SIRR effectue une revue a posteriori, après fusion/déploiement, et signale toute anomalie trouvée ; Claude Code corrige par un nouveau commit si nécessaire.
- Ce mode allégé est un choix explicite de Koné, réversible à tout moment : il redevient caduc dès qu'une donnée utilisateur réelle ou un mouvement financier réel existe en production, moment auquel la revue préalable redevient obligatoire par défaut sauf nouvelle décision explicite.

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

Ces arrêts concernent un obstacle substantiel réel ; ils ne permettent pas de substituer une production documentaire à un travail clairement ordonné. Une question bloquante doit contenir : le fait observé, les sources consultées, les options sûres et l'impact de chacune.

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

