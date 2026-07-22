# L00 — Fondations et catalogue exécutable

**État du lot :** ouvert — cadrage proposé  
**Date d'ouverture :** 22 juillet 2026  
**Dépendances :** DS-0001, UX-0001, UX-0002, UX-0003, ADR-0001, ADR-0008

## 1. Mission du lot

L00 transforme DS-0001 en langage d'interface exécutable et fournit l'atelier dans lequel toutes les maquettes Wasplex seront examinées.

Il ne construit encore ni le Feed, ni le Wallet, ni un portail métier complet. Il rend possibles leur conception fidèle, leur comparaison et leur transmission.

## 2. Résultats attendus

L00 doit produire :

- un catalogue React/TypeScript consultable localement ;
- les tokens visuels et sémantiques de DS-0001 sous forme exécutable ;
- les primitives et composants fondamentaux ;
- un sélecteur de thème, largeur, langue, densité et état ;
- des fixtures exclusivement synthétiques ;
- les contrats FND-00-01 à FND-00-12 ;
- les captures de référence ;
- les tests visuels, responsives et accessibles ;
- le premier paquet de contexte réutilisable pour Lovable ;
- une procédure d'extraction et normalisation des TSX générés.

## 3. Emplacement officiel

Le catalogue exécutable est conservé dans le présent dépôt `wasplex-ecosystem`, sous le répertoire racine :

> `ui-catalogue/`

Ce choix maintient dans un même dépôt les décisions, contrats, prototypes normalisés, fixtures et captures qui se valident mutuellement.

Le répertoire `ui-catalogue/` :

- possède son propre manifeste de dépendances et ses commandes documentées ;
- ne transforme pas le dépôt en application de production ;
- ne contient ni Laravel, ni backend, ni secret ;
- ne devient pas une seconde implémentation métier ;
- fournit les composants et références que le futur dépôt applicatif devra intégrer conformément aux contrats ;
- conserve une provenance explicite pour tout TSX issu de Lovable ou d'une autre IA.

Aucun second dépôt de maquettage ne doit être créé sans nouvelle décision explicite.

## 4. Hors périmètre

L00 ne décide pas :

- des règles financières ou métiers ;
- de l'authentification de production ;
- des routes Laravel définitives ;
- des schémas PostgreSQL ;
- des autorisations réelles ;
- d'un backend Lovable ou Supabase ;
- du logo vectoriel définitif tant que sa redessination n'est pas validée.

Le logo actuel peut être représenté par un emplacement explicitement marqué « référence provisoire ».

## 5. Tranches de travail

### L00-A — Atelier

- FND-00-01 Catalogue de prototypes ;
- structure du projet de référence ;
- sélecteur de fixtures ;
- règles de capture déterministe.

### L00-B — Fondations visuelles

- couleurs, thèmes et contrastes ;
- typographie ;
- espacements, rayons, bordures et élévations ;
- iconographie ;
- mouvements ;
- largeurs et densités.

### L00-C — Interaction commune

- FND-00-04 navigation et lien profond ;
- FND-00-05 formulaires ;
- FND-00-06 confirmation critique ;
- FND-00-07 états système ;
- actions, focus, clavier et retour arrière.

### L00-D — Valeur, preuve et urgence

- FND-00-08 notifications ;
- FND-00-09 preuve et référence ;
- FND-00-11 montants et ventilations ;
- FND-00-12 alerte nationale ;
- résultat inconnu, hors ligne et recours.

### L00-E — Coquilles et travail professionnel

- FND-00-02 coquille mobile ;
- FND-00-03 coquille professionnelle ;
- FND-00-10 tableaux et files de travail.

## 6. Ordre de réalisation

1. adopter le contrat FND-00-01 et le manifeste de tokens ;
2. créer le catalogue minimal sans backend ;
3. implémenter les primitives ;
4. implémenter les états système ;
5. implémenter valeur, preuve et urgence ;
6. implémenter les coquilles ;
7. tester ;
8. générer les captures ;
9. produire le paquet Lovable de référence ;
10. déclarer L00 terminé.

## 7. Règles de construction

- React et TypeScript strict ;
- HTML sémantique ;
- tokens CSS exposés par variables, sans valeurs répétées arbitrairement ;
- aucun appel réseau requis pour consulter le catalogue ;
- aucune donnée personnelle réelle ;
- aucune règle métier simulée dans un composant partagé ;
- aucun composant nommé d'après une apparence lorsque son rôle est sémantique ;
- thèmes clair et sombre testés ;
- mobile modeste et desktop testés ;
- dépendances limitées et justifiées ;
- captures reproductibles.

## 8. Portes de qualité

L00 ne sort pas tant que :

- les tokens réellement utilisés ne sont pas traçables à DS-0001 ;
- chaque composant possède états, accessibilité et exemple ;
- aucune couleur seule ne porte un statut ;
- tous les contrôles sont utilisables au clavier ;
- le texte agrandi et les libellés longs restent utilisables ;
- le hors ligne et le résultat inconnu sont représentés ;
- la superposition nationale reste lisible sans imiter une notification publicitaire ;
- les montants distinguent propriété, disponibilité et réservation ;
- les captures attendues passent la revue humaine ;
- le catalogue ne dépend d'aucun backend.

## 9. Livrables documentaires initiaux

- `01-contrat-FND-00-01-catalogue-prototypes.md` ;
- `02-manifeste-tokens-executables.md` ;
- `03-registre-composants-fondamentaux.md`.

Les contrats FND-00-02 à FND-00-12 seront instruits à l'intérieur du lot dans l'ordre des tranches.

