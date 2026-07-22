# Manifeste des tokens exécutables

**État :** proposition L00  
**Source normative :** DS-0001

## 1. Principe

Les tokens traduisent DS-0001 en valeurs réutilisables. Ils ne créent pas une seconde charte et ne permettent pas à chaque écran d'inventer ses couleurs, espacements ou statuts.

Ordre de résolution :

1. token sémantique ;
2. token de composant exceptionnel et documenté ;
3. valeur brute uniquement pour une donnée réellement unique et revue.

## 2. Familles obligatoires

| Famille | Exemples | Usage |
|---|---|---|
| `brand` | navy, blue, cyan, orange, gold | identité |
| `status` | success, warning, danger, info, pending, unknown | vérité d'état |
| `bg` | canvas, surface, raised, overlay | surfaces |
| `text` | primary, secondary, inverse, disabled | contenu |
| `border` | default, strong, danger | séparation |
| `focus` | ring, offset | clavier et accessibilité |
| `space` | 1 à 16 sur base 4 px | rythme |
| `radius` | sm, md, lg, xl, pill | formes |
| `type` | familles, tailles, graisses, hauteurs | typographie |
| `shadow` | none, low, raised, overlay | élévation limitée |
| `motion` | duration, easing, distance | mouvement |
| `layout` | gutters, content, breakpoints, z-index | composition |
| `control` | heights, touch-target, icon sizes | interaction |

## 3. Couleurs de marque

| Token | Clair | Sombre |
|---|---|---|
| `brand.navy` | `#10233F` | `#07182D` |
| `brand.blue` | `#075CCF` | `#4FA3FF` |
| `brand.cyan` | `#007F9F` | `#2BC4DE` |
| `brand.orange` | `#C75100` | `#FF9A3D` |
| `brand.gold` | `#936800` | `#F2C14E` |

## 4. Couleurs d'état

| Token | Clair | Sombre | Sens exclusif |
|---|---|---|---|
| `status.success` | `#137A50` | `#42D392` | confirmé |
| `status.warning` | `#9A5B00` | `#F4B942` | attention/action requise |
| `status.danger` | `#B42318` | `#FF6B61` | danger/échec grave/SOS |
| `status.info` | `#075CCF` | `#70B7FF` | information/progression |
| `status.pending` | `#6B5B00` | `#E7CF61` | en attente |
| `status.unknown` | `#53657D` | `#A9B7C8` | résultat non établi |

Un composant consomme ces rôles. Il ne déduit jamais `success` d'un montant positif ou `danger` d'une simple promotion.

## 5. Neutres initiaux

| Token | Clair | Sombre |
|---|---|---|
| `bg.canvas` | `#F5F7FA` | `#07182D` |
| `bg.surface` | `#FFFFFF` | `#0E2542` |
| `bg.raised` | `#F8FAFC` | `#173251` |
| `text.primary` | `#10233F` | `#F5F8FC` |
| `text.secondary` | `#53657D` | `#A9B7C8` |
| `border.default` | `#CBD5E1` | `#35506D` |
| `focus.ring` | `#075CCF` | `#70B7FF` |

Les tokens manquants, notamment disabled, inverse, overlay et bordures fortes, seront dérivés puis soumis aux tests de contraste avant stabilisation.

## 6. Espacement et formes

Échelle d'espacement :

> 4, 8, 12, 16, 20, 24, 32, 40, 48, 64 px

Rayons :

- `radius.sm` : 8 px ;
- `radius.md` : 12 px ;
- `radius.lg` : 16 px ;
- `radius.xl` : 24 px ;
- `radius.pill` : 9999 px, usage limité.

Les valeurs sont exprimées par tokens ; l'interface ne transforme pas chaque bloc en carte arrondie.

## 7. Typographie

- famille : Inter Variable auto-hébergée, puis replis DS-0001 ;
- texte courant mobile : 16 px par défaut ;
- échelle : 12, 14, 16, 18, 20, 24, 32, 40 px et plus pour communication ;
- montants alignés : chiffres tabulaires ;
- tailles 12 px réservées aux annotations exceptionnelles ;
- graisse et hauteur de ligne définies séparément de la taille.

## 8. Mouvement

Les tokens de mouvement doivent distinguer :

- micro-retour immédiat ;
- transition standard ;
- ouverture de panneau ;
- urgence sans animation décorative.

Le mode mouvement réduit supprime déplacement et pulsation non essentiels. Aucune animation ne sert de preuve de paiement, transmission ou succès.

## 9. Cibles et contrôles

- cible tactile minimale : 44 × 44 px, avec préférence 48 px sur mobile ;
- champ courant mobile : hauteur compatible avec texte 16 px ;
- focus visible non masqué par une barre fixe ;
- icône seule toujours nommée ;
- zone dangereuse séparée des actions ordinaires.

## 10. Breakpoints de validation

Les captures de référence utilisent :

- 320 px : petit mobile ;
- 360 px : mobile courant compact ;
- 390 px : mobile courant large ;
- 768 px : tablette/transition ;
- 1024 px : portable compact ;
- 1440 px : desktop.

Ces valeurs servent aux preuves, pas à déclencher mécaniquement chaque adaptation. Les composants réagissent d'abord à l'espace disponible et au contexte.

## 11. Format de distribution

Les tokens doivent pouvoir alimenter :

- variables CSS des thèmes ;
- objets TypeScript typés lorsque nécessaire ;
- catalogue de documentation ;
- tests de contraste et de cohérence ;
- export contrôlé vers un outil de maquettage.

Une valeur possède une seule définition génératrice. Les exports sont produits, non maintenus manuellement en parallèle.

## 12. Validation

Avant stabilisation :

- contraste de chaque couple réellement utilisé ;
- comparaison clair/sombre ;
- daltonisme et couleur non exclusive ;
- texte agrandi ;
- appareil mobile en luminosité difficile ;
- impression ou monochrome pour les preuves pertinentes ;
- inventaire des valeurs brutes restantes.

