# EXE-0001 — Primauté de l’exécution et Véto du dirigeant exceptionnel

- **État :** adopté par instruction expresse du dirigeant ; entrée en vigueur par fusion dans `main`
- **Date :** 2026-07-29
- **Décideur :** dirigeant actuel et propriétaire de la vision de Wasplex
- **Nature :** règle supérieure de conduite des travaux humains et artificiels
- **Périmètre :** conception, implémentation, tests, documentation, intégration et compte rendu

## 1. Objet

La présente décision empêche qu’un texte, un acte, un registre, un état documentaire, une permission d’écriture documentaire ou l’absence d’adoption préalable paralyse un chantier clairement ordonné par le dirigeant.

Elle ne modifie aucun invariant métier de la Constitution, des amendements ou des décisions adoptées. Elle gouverne l’ordre et la continuité du travail.

## 2. Doctrine permanente « exécuter d’abord »

Lorsque le résultat demandé est suffisamment clair, l’agent applique l’ordre suivant :

1. comprendre le résultat attendu ;
2. inspecter l’existant et les règles utiles ;
3. implémenter ;
4. tester honnêtement ;
5. corriger autant que possible ;
6. signaler les résultats réels ;
7. documenter ensuite ou en parallèle.

Le code et la gouvernance peuvent avancer ensemble. En cas de conflit de calendrier, l’implémentation clairement ordonnée avance.

Une étude ou une conception préalable ne devient obligatoire que si le dirigeant la demande expressément ou si une ambiguïté substantielle empêche réellement de choisir entre des comportements incompatibles.

## 3. Textes et chemins documentaires non bloquants

L’absence, l’inachèvement ou l’inaccessibilité technique d’un amendement, d’une spécification, d’un dossier de validation ou d’un autre texte ne constitue pas, à elle seule, un motif d’arrêt du codage.

Lorsqu’une écriture documentaire est refusée par le harnais ou les permissions, l’agent :

1. conserve le chemin et le message d’erreur exacts ;
2. ne crée aucun placeholder, faux document complet, `TODO` vide ou contenu tronqué ;
3. conserve le texte intégral préparé et le restitue dans le dossier de validation ou le rapport final ;
4. poursuit immédiatement toutes les tâches techniques non bloquées ;
5. inscrit la régularisation documentaire dans les éléments différés ;
6. ne demande pas à l’utilisateur d’effectuer un collage manuel pendant que le reste du chantier peut avancer.

Une permission refusée dans `amendments/` ou dans un autre répertoire documentaire est un fait technique. Elle ne doit recevoir aucune justification constitutionnelle inventée.

## 4. Véto du dirigeant exceptionnel

Le Véto du dirigeant exceptionnel est l’instruction par laquelle le dirigeant lève, pour un chantier déterminé, un obstacle procédural, documentaire, normatif ou institutionnel à l’exécution.

Aucune formule sacramentelle n’est requise. Constituent notamment un Véto lorsqu’elles sont clairement attribuables au dirigeant :

- « Véto du dirigeant exceptionnel » ;
- « codez maintenant » ;
- « poursuivez sans attendre les textes » ;
- « cette procédure ne bloque pas ce chantier » ;
- toute instruction équivalente dont le sens est non ambigu.

Le Véto commande l’action dans le périmètre ordonné. Aucun agent ne peut lui opposer un contre-véto documentaire.

## 5. Le Véto ne falsifie jamais la réalité

Il demeure interdit de :

- déclarer réussi un test qui a échoué ;
- déclarer codée une capacité qui ne l’est pas ;
- déclarer effectué un déploiement qui ne l’est pas ;
- cacher une erreur, un risque ou une partie non vérifiée ;
- inventer, altérer ou supprimer une preuve ;
- inscrire un secret dans le dépôt ;
- réécrire l’historique de `main` ;
- contourner une protection technique ou de sécurité.

Les tests, gardes et audits disent la vérité sur le résultat. Leur échec commande une correction ou un compte rendu fidèle, pas l’abandon silencieux du travail.

## 6. Blocages réels

Un arrêt et une question précise restent justifiés lorsque :

- une ambiguïté métier substantielle empêche de choisir entre des résultats incompatibles ;
- l’ordre exige réellement un accès, un secret ou un environnement absent ;
- une action destructive ou difficilement réversible n’est pas clairement autorisée ;
- le comportement demandé contredit matériellement un invariant produit adopté ;
- la poursuite imposerait d’inventer une donnée, un droit, une formule financière ou une preuve.

Dans ce cas, l’agent réalise d’abord tout ce qui demeure possible, isole le seul point bloquant, apporte sa preuve et propose la solution la plus directe.

## 7. Articulation avec le Grand Livre

La Constitution et les amendements continuent de gouverner le comportement du produit, les droits, la sécurité, les données, la finance et les relations entre acteurs.

La présente décision gouverne la conduite des travaux : aucun texte ne doit être utilisé comme prétexte pour remplacer le code demandé par un placeholder, une dissertation normative ou une suspension générale.

En cas de conflit :

- sur le comportement du produit, la source normative supérieure adoptée prévaut ;
- sur l’ordre et la cadence du travail, la dernière instruction claire du dirigeant et la présente décision prévalent ;
- sur la réalité du résultat, les tests, preuves et observations fidèles prévalent.

## 8. Entrée en vigueur

La fusion de la branche portant EXE-0001 dans `main` vaut adoption et rend cette directive applicable à toutes les futures sessions de travail sur Wasplex, sans nouvel acte par chantier.

> **Le dirigeant ordonne. L’ingénierie construit. Les tests disent la vérité. La gouvernance accompagne et constate. Aucun texte ne paralyse l’évolution de Wasplex.**
