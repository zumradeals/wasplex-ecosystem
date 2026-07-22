# Tokens visuels et thèmes

**Statut :** spécification proposée — DS-0001

## Niveaux de tokens

1. **Primitifs** : couleurs, tailles et valeurs brutes.
2. **Sémantiques** : surface, texte, action, danger, attente.
3. **Composants** : bouton, champ, carte, navigation.
4. **Modules** : accents limités n'altérant pas les statuts.

Un écran consomme normalement les tokens sémantiques ou composants, pas les primitifs.

## Exemples

```css
:root {
  --color-bg-canvas: #F5F7FA;
  --color-bg-surface: #FFFFFF;
  --color-text-primary: #10233F;
  --color-text-secondary: #53657D;
  --color-action-primary: #075CCF;
  --color-focus-ring: #075CCF;
  --color-status-success: #137A50;
  --color-status-warning: #9A5B00;
  --color-status-danger: #B42318;
}

[data-theme="dark"] {
  --color-bg-canvas: #07182D;
  --color-bg-surface: #0E2542;
  --color-text-primary: #F5F8FC;
  --color-text-secondary: #A9B7C8;
  --color-action-primary: #4FA3FF;
  --color-focus-ring: #70B7FF;
  --color-status-success: #42D392;
  --color-status-warning: #F4B942;
  --color-status-danger: #FF6B61;
}
```

Ces extraits ne suffisent pas : les couples fond/texte sont testés dans les composants réels.

## Couches

Échelle recommandée :

- contenu ;
- navigation fixe ;
- menu ;
- modal ;
- toast ;
- alerte critique.

Aucun nombre arbitraire de z-index n'est ajouté dans un écran.

## Mouvement

Les tokens de durée, courbe et distance respectent réduction des animations. Une animation ne déclenche pas de layout coûteux sur chaque image lorsque transformation ou opacité suffit.