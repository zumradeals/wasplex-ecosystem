# UX-0002 — Catalogue des écrans, contrats et maquettes exécutables

**État :** proposé à validation du fondateur  
**Date :** 22 juillet 2026  
**Décideur UX :** SIRR, sur mandat du fondateur  
**Dépendances :** Constitution v1.4, ADR-0001, ADR-0004, ADR-0006, ADR-0008, DS-0001, UX-0001

## 1. Contexte

UX-0001 cartographie les acteurs, parcours, navigations et états de Wasplex. Il exige une spécification écran pour chaque parcours critique, sans encore définir la forme de cette spécification ni la manière de transmettre fidèlement l'intention à une équipe ou une IA de développement.

Une capture isolée montre une apparence, mais ne prouve ni le comportement, ni les états dégradés, ni les autorisations, ni l'origine des données. À l'inverse, un prompt détaillé sans référence visuelle laisse trop de place à l'interprétation. Pour Wasplex, cette ambiguïté est dangereuse sur les surfaces financières, sociales, institutionnelles et d'urgence.

Des outils de génération visuelle tels que Lovable peuvent accélérer l'exploration. Ils peuvent aussi introduire leur propre backend, une authentification, un schéma de données ou une logique métier incompatibles avec l'architecture Wasplex.

## 2. Décision

Wasplex adopte, pour chaque écran important, un dossier de conception versionné composé de :

1. un contrat d'écran lisible et testable ;
2. une référence visuelle exécutable en React et TypeScript ;
3. des jeux de données exclusivement synthétiques ;
4. les états et variantes requis ;
5. des captures de référence produites depuis le prototype ;
6. des critères d'acceptation fonctionnels, visuels, responsives et accessibles.

La source de vérité suit cet ordre :

1. Constitution, amendements et décisions adoptées ;
2. contrats métier et architecture ;
3. UX-0001 et contrat d'écran ;
4. DS-0001 et composants normalisés ;
5. prototype exécutable validé ;
6. captures de référence ;
7. prompt ou production brute d'un outil génératif.

Une maquette ne peut donc jamais contredire une règle supérieure, même si elle a été visuellement approuvée.

## 3. Trois niveaux de conception

### 3.1. Maquette exploratoire

Production rapide destinée à comparer des compositions, interactions ou variantes. Elle peut être créée avec Lovable ou un outil équivalent.

Elle n'est pas normative, ne reçoit aucune donnée réelle et ne décide d'aucune règle métier.

### 3.2. Prototype Wasplex normalisé

Référence exécutable conservée dans le dépôt, écrite en React et TypeScript, utilisant les tokens, composants, mots et états de DS-0001.

Elle fonctionne avec des fixtures synthétiques, des adaptateurs simulés et sans dépendance à un backend de production.

### 3.3. Écran de production

Implémentation Laravel, React, TypeScript et Inertia reliée aux contrats, politiques, commandes et requêtes officiels.

Elle reproduit l'expérience validée sans reprendre les fausses données, règles temporaires ou services techniques du prototype.

## 4. Place de Lovable

Lovable est autorisé comme atelier d'exploration et d'accélération visuelle.

Peuvent être récupérés après revue :

- pages et composants TSX ;
- structure et hiérarchie visuelles ;
- styles et animations utiles ;
- comportements d'interface ;
- variantes responsives ;
- fixtures synthétiques.

Sont rejetés ou remplacés avant normalisation :

- Lovable Cloud, Supabase ou tout backend généré ;
- schémas, migrations et accès directs à une base ;
- authentification et autorisations inventées ;
- fonctions serveur et secrets ;
- appels réseau non approuvés ;
- logique financière, sociale, publicitaire ou institutionnelle inventée ;
- dépendances inutiles ;
- valeurs métier codées en dur.

Le code produit par Lovable ou toute IA reste du code non fiable au sens d'ADR-0008. Son origine doit être traçable et sa normalisation revue.

## 5. Contrat d'écran obligatoire

Chaque contrat porte un identifiant stable et précise au minimum :

- nom, route et version ;
- acteur, contexte et terminal principal ;
- objectif humain ;
- préconditions et critères d'éligibilité ;
- capacités et finalités requises ;
- informations affichées et leur source autoritative ;
- actions, commandes et conséquences ;
- états initiaux, transitoires et terminaux ;
- chargement, vide, refus, erreur, hors ligne, obsolescence et résultat inconnu ;
- règles de reprise, idempotence et retour arrière ;
- données sensibles, masquage et conservation locale ;
- notifications et voies de recours ;
- comportement responsive ;
- accessibilité ;
- événements de mesure autorisés ;
- critères d'acceptation et preuves attendues ;
- décisions dont il dépend.

Le contrat décrit l'intention et la vérité de l'écran. Le prototype démontre sa forme et ses interactions.

## 6. États visuels à produire

Un écran ordinaire possède au minimum :

- état principal ;
- chargement ;
- absence de données ;
- erreur récupérable ;
- indisponibilité ou refus ;
- petit écran mobile ou largeur desktop selon son contexte principal.

Les écrans Q0 et Q1 possèdent en plus, lorsque applicables :

- confirmation avant irréversibilité ;
- traitement en cours ;
- succès confirmé ;
- résultat inconnu ;
- échec avec valeur protégée ;
- réseau interrompu et reprise ;
- double action ou demande déjà reçue ;
- session expirée ;
- permission refusée ;
- données locales obsolètes ;
- suspension, contestation et recours ;
- mode appareil partagé ;
- variante de langue longue et accessibilité renforcée.

Aucun état financier ou d'urgence ne peut être déduit uniquement d'une couleur ou d'une animation.

## 7. Référence visuelle officielle

La référence officielle n'est pas un fichier HTML statique indépendant. Elle est rendue par des composants React/TypeScript utilisant du HTML sémantique.

Elle doit :

- reprendre les tokens de DS-0001 ;
- réutiliser les composants normalisés ;
- être consultable dans un catalogue interne de prototypes ;
- permettre le choix explicite des fixtures et états ;
- fonctionner sans compte, secret, réseau ou base réelle ;
- être déterministe afin de produire des captures comparables ;
- rendre visibles la largeur, le thème, la langue et l'état testés ;
- éviter d'embarquer une copie du domaine métier dans l'interface.

Storybook ou un outil similaire peut être introduit si le volume le justifie. Il n'est pas obligatoire au lancement : un catalogue React simple, maintenu dans le dépôt, suffit.

## 8. Frontière entre prototype et production

Le prototype peut simuler un solde, un paiement, une alerte ou une réponse institutionnelle. Il doit les marquer comme données de démonstration et ne jamais les présenter comme vérité réelle.

Dans la production :

- Laravel porte règles, autorisations et décisions ;
- PostgreSQL et les registres officiels portent les faits persistants ;
- Inertia transmet des propriétés typées ;
- React présente l'état et émet des intentions ;
- aucune valeur financière ou permission sensible n'est décidée dans un composant ;
- aucune fixture n'entre dans un chemin de production.

La fidélité demandée concerne l'expérience validée, non la reproduction aveugle de la structure interne du code exploratoire.

## 9. Responsive par acteur

### Utilisateur

La référence primaire est mobile. Les maquettes couvrent au minimum un petit appareil Android représentatif et une largeur mobile courante. Le desktop utilisateur reste utilisable sans devenir la référence dominante.

### Annonceur, institution et administration

La référence primaire est desktop, avec tables, files de travail et densité maîtrisée. Les actions urgentes ou de consultation nécessaires sur mobile possèdent une variante dédiée ; le desktop n'est pas simplement réduit jusqu'à devenir illisible.

### Surfaces universelles

Connexion, récupération, SOS, alerte nationale et contenus publics critiques sont conçus pour les appareils modestes, orientations et largeurs pertinentes.

## 10. Passation à une IA de développement

Une commande de réalisation remise à une IA doit contenir :

- identifiant du contrat d'écran ;
- décisions et contrats métier applicables ;
- prototype et captures validées ;
- composants autorisés ;
- propriétés et événements typés attendus ;
- états à implémenter ;
- comportements responsive et accessibles ;
- tests et preuves exigés ;
- interdictions explicites d'inventer règle, donnée, dépendance ou backend.

L'IA doit produire une matrice de traçabilité entre contrat, code et tests. Une ressemblance visuelle seule ne constitue pas une livraison.

## 11. Revue et validation

Une maquette passe successivement par :

1. contrôle de cohérence constitutionnelle et métier ;
2. revue du contrat d'écran ;
3. revue visuelle et linguistique ;
4. revue des états et risques ;
5. revue responsive et accessibilité ;
6. validation explicite de la référence ;
7. intégration et tests de production ;
8. comparaison visuelle et fonctionnelle.

Une modification normative après validation change la version du contrat et régénère les captures concernées. Une retouche purement technique sans effet observable ne crée pas artificiellement une nouvelle décision UX.

## 12. Captures et tests visuels

Les captures sont générées depuis le prototype ou la production à des tailles nommées et versionnées. Elles servent à la revue et à la détection de régression ; elles ne remplacent pas le prototype.

Les différences automatiques sont examinées, non acceptées mécaniquement. Les contenus variables, dates et animations sont stabilisés dans les fixtures.

Pour Q0/Q1, les tests lient au moins :

- état métier attendu ;
- texte littéral ;
- action disponible ou bloquée ;
- valeur protégée ;
- prochaine étape ;
- preuve visuelle.

## 13. Nommage et rangement

Les contrats utilisent un identifiant lié au parcours de `ux/05-inventaire-des-parcours.md`, puis un identifiant d'écran stable.

Convention initiale :

> `[PARCOURS]-[NN]-[nom-court]`

Exemples :

- `U-006-01-apercu-wallet` ;
- `U-007-03-retrait-resultat-inconnu` ;
- `I-002-02-dossier-alerte`.

Le rangement exact du futur dépôt applicatif sera décidé lors de son initialisation. Les noms stables, contrats et liens de traçabilité demeurent indépendants d'un outil particulier.

## 14. Ordre de conception

Le catalogue ne sera pas dessiné au hasard ni écran par écran selon l'inspiration.

Ordre initial :

1. fondations et composants partagés ;
2. accès, session et récupération ;
3. navigation et coquilles par acteur ;
4. Feed et publicité ;
5. Wallet et retrait ;
6. Alertes, SOS et alerte nationale ;
7. annonceur et campagne ;
8. institutions ;
9. Fonds Social ;
10. Cartes Wasplex ;
11. administration, configuration et audit ;
12. écrans secondaires et assistance.

Les surfaces Q0 peuvent être avancées si leur contrat métier est suffisamment stabilisé.

## 15. Conséquences

### Bénéfices

- intention visuelle transmissible sans ambiguïté ;
- réduction des inventions par les IA ;
- réutilisation réelle du Design System ;
- couverture des états invisibles dans une capture unique ;
- comparaison objective entre attendu et réalisé ;
- accélération possible par Lovable sans dépendance à son backend ;
- passation durable entre fondateur, architecte, designer et développeur.

### Coûts

- production de fixtures, contrats et variantes ;
- revue et normalisation du code exploratoire ;
- maintenance des captures et prototypes ;
- conception explicite des erreurs et modes dégradés.

Ces coûts sont acceptés : ils remplacent les réécritures, incohérences et compromis tardifs par une preuve précoce et contrôlable.

## 16. Règle obligatoire

> Aucun écran Wasplex important n'est confié au développement avec une capture ou un prompt seuls. Il possède un contrat versionné et une référence exécutable validée. Un outil génératif peut proposer la forme ; il ne décide jamais de la vérité, des droits ni de l'architecture Wasplex.

