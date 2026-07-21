# Gouvernance documentaire

## Niveaux d'autorité

1. **Constitutionnel** — principe supérieur, modifiable uniquement par amendement adopté.
2. **Normatif** — règle obligatoire de produit, économie, sécurité, données ou marque.
3. **Spécification** — comportement précis et testable.
4. **Décision architecturale (ADR)** — choix technique, contexte et conséquences.
5. **Indicatif** — exemple ou piste sans caractère obligatoire.

## États d'un document

- `exploration` : question ouverte ;
- `draft` : proposition rédigée ;
- `review` : soumise au fondateur ;
- `adopted` : validée explicitement ;
- `superseded` : remplacée avec conservation de l'historique ;
- `rejected` : examinée puis écartée.

## Séparation obligatoire

Chaque affirmation doit pouvoir être classée comme :

- parole ou décision du fondateur ;
- fait observé dans un ancien produit ;
- hypothèse de conception ;
- recommandation de l'architecte ;
- obligation juridique à vérifier ;
- règle adoptée.

## Processus de décision

1. Exposer la question.
2. Documenter la finalité recherchée.
3. Identifier acteurs, bénéfices, risques et abus possibles.
4. Comparer les options.
5. Choisir explicitement.
6. Écrire les invariants et critères d'acceptation.
7. Faire valider par le fondateur.
8. Enregistrer la décision et ses conséquences.

## Justification obligatoire de l'ordre des travaux

Avant d'ouvrir une nouvelle matière du Grand Livre, sa note d'ouverture doit expliquer :

1. son lien direct avec les articles déjà adoptés ;
2. pourquoi elle doit être instruite maintenant ;
3. quelles décisions ultérieures dépendent d'elle ;
4. quels risques naîtraient d'un traitement prématuré ou tardif ;
5. ce qui demeure volontairement hors périmètre ;
6. le résultat documentaire attendu ;
7. le critère permettant de déclarer la matière suffisamment comprise.

L'ordre des travaux suit les dépendances conceptuelles et économiques de Wasplex, non la facilité de rédaction ni l'ordre des écrans de l'ancienne application.

## Principe de traçabilité

Aucune fonctionnalité ne doit apparaître dans le futur code sans référence à une règle métier, une spécification ou une décision architecturale versionnée dans ce dépôt.
