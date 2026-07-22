# Fabrique des maquettes et passation à l'IA

**Statut :** spécification adoptée — UX-0002

## 1. Principe

La fabrique transforme une règle adoptée en expérience testable sans laisser l'outil de génération inventer le produit.

> Règle → contrat d'écran → exploration → normalisation → validation → production → preuve

## 2. Préparation

Avant tout prompt de maquette :

1. sélectionner un parcours de l'inventaire ;
2. identifier décisions et contrats métier ;
3. remplir le contrat d'écran ;
4. lister les états et risques ;
5. sélectionner les composants DS-0001 ;
6. définir des fixtures synthétiques ;
7. préciser ce que l'outil n'a pas le droit d'inventer.

## 3. Exploration avec Lovable ou équivalent

Le prompt d'exploration indique :

- écran et objectif ;
- acteur et terminal primaire ;
- hiérarchie et contenu littéral ;
- tokens, composants et états ;
- contraintes responsive et accessibles ;
- données fictives explicites ;
- interdiction de concevoir le backend ou la règle métier ;
- interdiction de Supabase, Lovable Cloud et services externes dans l'artefact normalisé.

Le backend temporaire éventuel de l'outil ne sert qu'à la démonstration. Il n'est ni exporté comme décision ni traduit dans Laravel.

## 4. Extraction

Les fichiers TSX, styles et ressources utiles sont récupérés avec leur provenance. Une revue repère :

- appels réseau ;
- secrets ;
- dépendances ;
- données codées en dur ;
- fausses autorisations ;
- calculs métier ;
- textes trompeurs ;
- composants redondants ;
- écarts à DS-0001.

Tout élément non justifié est supprimé ou remplacé.

## 5. Normalisation Wasplex

Le prototype normalisé :

- utilise React et TypeScript strict ;
- rend du HTML sémantique ;
- consomme les tokens et composants Wasplex ;
- reçoit des propriétés typées ;
- déclenche des intentions sans simuler une autorisation locale ;
- isole les fixtures ;
- expose chaque état dans le catalogue ;
- ne dépend d'aucun backend ;
- reste déterministe pour les captures.

Une page générée peut être découpée. La fidélité visuelle n'interdit pas une structure de code saine.

## 6. Validation

Le fondateur et SIRR valident :

- intention ;
- hiérarchie ;
- textes importants ;
- états critiques ;
- comportement responsive ;
- cohérence avec l'âme et les règles Wasplex.

La validation porte sur une version identifiée du contrat et du prototype. Une capture de discussion non versionnée n'est pas une validation durable.

## 7. Commande à l'IA de production

Le paquet de passation contient :

1. contrat d'écran ;
2. décisions supérieures ;
3. prototype normalisé ;
4. captures de référence ;
5. contrats de propriétés, commandes et événements ;
6. liste des états ;
7. tests d'acceptation ;
8. liste des interdictions.

Instruction minimale :

> Implémenter fidèlement le contrat et la référence validée dans Laravel/React/Inertia. Ne pas recopier de backend exploratoire, ne pas inventer de règle, ne pas déplacer une autorisation côté client et ne pas déclarer terminé sans les preuves exigées.

## 8. Contrôle après intégration

La revue compare :

- contrat contre comportement ;
- prototype contre production ;
- captures attendues contre captures réelles ;
- propriétés attendues contre interface Laravel/Inertia ;
- états attendus contre tests ;
- composants attendus contre réutilisation effective.

Un écart peut être accepté seulement s'il améliore accessibilité, sécurité ou cohérence et qu'il est documenté. L'IA ne corrige pas silencieusement la conception.

## 9. Définition de prêt au développement

Un écran est prêt lorsque :

- son contrat est complet ;
- les règles métier sont stabilisées ;
- ses états critiques sont représentés ;
- le prototype normalisé fonctionne ;
- les captures sont générées ;
- les textes contractuels sont validés ;
- les propriétés et commandes ont un propriétaire ;
- les critères d'acceptation sont testables ;
- les dépendances et inconnues sont explicites.

## 10. Définition de terminé

Un écran n'est terminé que lorsque :

- l'implémentation respecte le contrat ;
- les autorisations restent côté serveur ;
- toutes les variantes requises fonctionnent ;
- tests fonctionnels, visuels et accessibles passent ;
- les captures de référence sont revues ;
- aucune donnée ou dépendance exploratoire n'est présente ;
- la traçabilité relie décision, écran, code et tests.

