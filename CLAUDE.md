# Instructions opérationnelles pour Claude Code — Wasplex

Ce fichier explique uniquement comment travailler dans le dépôt. Il ne définit pas le produit. Le fonctionnement attendu de Wasplex se trouve dans `docs/`.

## 1. Avant de coder

Pour toute mission :

1. lire `docs/00-vision-generale-wasplex.md` ;
2. lire le document métier du module concerné ;
3. inspecter le code, les migrations, les routes et les tests existants ;
4. identifier le résultat observable attendu de bout en bout ;
5. signaler toute décision commerciale ou formule encore ouverte.

Ne pas reconstruire d’anciens concepts à partir de l’historique Git lorsque les fichiers actifs de `docs/` disent autre chose.

## 2. Ne jamais inventer

Ne jamais inventer silencieusement :

- un prix ;
- un pourcentage ;
- un quota ;
- un nom de plan ou de type ;
- une formule financière ;
- une règle de remboursement ;
- un droit utilisateur ;
- une preuve de réussite ;
- une donnée personnelle ;
- un statut institutionnel.

Une valeur technique de démonstration doit être nommée explicitement comme démonstrative et ne doit pas apparaître à l’utilisateur comme une offre commerciale réelle.

## 3. Construire le parcours, pas seulement l’écran

Une fonctionnalité n’est pas terminée parce qu’un écran existe.

Avant de déclarer un lot terminé, vérifier selon le domaine :

- entrée utilisateur ou annonceur ;
- validation serveur ;
- persistance ;
- moteur métier ;
- autorisation ;
- traitement financier éventuel ;
- résultat visible ;
- erreurs et états vides ;
- tests ;
- traçabilité.

Pour la publicité, le parcours complet inclut notamment : ciblage, estimation, devis, financement, approbation, distribution réelle, preuve de l’événement, débit campagne, crédit utilisateur, part Wasplex, rapport et traitement du reliquat.

## 4. Préserver la finalité lors d’une refonte

Avant toute refonte d’un écran existant, dresser une matrice simple :

- fonction conservée ;
- fonction déplacée ;
- fonction volontairement supprimée ;
- fonction différée ;
- valeur de démonstration à ne pas reprendre.

Une interface plus propre ne doit pas supprimer le sens métier du parcours.

## 5. Git et périmètre

- travailler sur une branche dédiée ;
- ne jamais réécrire l’historique de `main` ;
- ne jamais force-pousser ;
- limiter les modifications au périmètre demandé ;
- préserver les changements humains non liés ;
- présenter le diff, les tests et les risques avant une modification destructive ou une fusion ;
- ne fusionner dans `main` qu’après instruction explicite du dirigeant.

## 6. Qualité minimale

- tests honnêtes : ne jamais déclarer vert ce qui ne l’est pas ;
- aucune opération financière sans source, idempotence et écritures traçables ;
- aucune donnée personnelle exposée inutilement ;
- aucun succès externe déclaré sans preuve ;
- français clair dans l’interface ;
- mobile prioritaire pour l’utilisateur ;
- desktop prioritaire pour annonceurs et administration ;
- états chargement, vide, erreur et reprise couverts.

## 7. Compte rendu attendu

```text
Tâche :
Branche :
Document métier lu :
Résultat observable :
Fichiers modifiés :
Tests exécutés :
Captures :
Décisions ouvertes :
Risques / éléments différés :
Action suivante proposée :
```

Le but est de construire fidèlement Wasplex, pas de produire des textes normatifs autour du produit.