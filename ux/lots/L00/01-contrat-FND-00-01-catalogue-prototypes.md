# FND-00-01 — Catalogue de prototypes et sélecteur de fixtures

**État :** adopté — L00-A  
**Parcours parent :** fondation transversale  
**Risque :** Q2, avec contenu de démonstration Q0/Q1  
**Terminal primaire :** desktop ; consultation mobile obligatoire  
**Dépendances :** DS-0001, UX-0002, UX-0003, ADR-0008

## 1. Objectif humain

Permettre au fondateur, à SIRR, aux responsables métier, designers, développeurs et IA de voir exactement la même version d'un écran, dans un état, une largeur, un thème et une langue identifiés.

Le catalogue doit rendre la conception vérifiable sans avoir besoin d'un backend, d'un compte réel ou d'une explication orale.

Il ne doit jamais laisser croire qu'une fixture représente un solde, une alerte, une identité ou une décision réelle.

## 2. Utilisateurs du catalogue

- fondateur : revue et validation de l'intention ;
- architecte UX : contrats, états et cohérence ;
- équipe métier : exactitude des textes et conséquences ;
- développement : référence implémentable ;
- qualité : captures et tests ;
- IA : contexte borné, lisible et non ambigu.

Le catalogue n'est pas un portail de production et ne reçoit aucune autorisation métier.

## 3. Entrée

- lancement local documenté ;
- aucune authentification ;
- aucune connexion réseau obligatoire ;
- ouverture directe possible sur une histoire par lien stable ;
- état de démonstration visible en permanence.

## 4. Architecture d'information

Navigation primaire :

1. **Fondations** ;
2. **Composants** ;
3. **Coquilles** ;
4. **Écrans par lot** ;
5. **États critiques** ;
6. **Captures et conformité**.

Chaque entrée affiche :

- identifiant officiel ;
- nom ;
- statut de préparation UX-0003 ;
- risque ;
- décisions applicables ;
- version du contrat ;
- composant ou écran rendu ;
- fixture active ;
- largeur, thème, langue et densité ;
- date/commit de référence ;
- critères non encore satisfaits.

## 5. Contrôles globaux

| Contrôle | Valeurs initiales | Règle |
|---|---|---|
| Thème | clair, sombre, système | le rendu reste déterministe en capture |
| Largeur | 320, 360, 390, 768, 1024, 1440 px | aucune largeur « magique » cachée |
| Langue | français, variante texte long | l'internationalisation réelle viendra ensuite |
| Densité | mobile confortable, professionnel standard | jamais au détriment des cibles tactiles |
| Mouvement | normal, réduit | respecte la préférence utilisateur |
| Réseau | en ligne simulé, faible, hors ligne | aucune requête réelle nécessaire |
| Données | fixture nommée | source synthétique toujours visible |

## 6. Sélection d'une histoire

Le lien stable encode au minimum :

- identifiant écran/composant ;
- fixture ;
- thème ;
- largeur de référence ;
- langue.

Une actualisation restaure la sélection. Une fixture inconnue revient à un état sûr avec explication ; elle ne charge pas silencieusement l'état principal.

## 7. Fixtures

Chaque fixture :

- possède un identifiant et une description ;
- contient uniquement des données synthétiques ;
- stabilise dates, heures et montants ;
- indique l'état métier représenté ;
- ne déclenche aucun appel externe ;
- peut être chargée directement ;
- précise la valeur protégée et la prochaine action pour Q0/Q1.

Noms recommandés :

- `default` ;
- `loading` ;
- `empty` ;
- `offline` ;
- `denied` ;
- `unknown-result` ;
- `confirmed` ;
- `failed-recoverable` ;
- états métier explicites supplémentaires.

## 8. États du catalogue

### Catalogue vide

Explique comment enregistrer le premier composant. N'affiche aucun faux exemple comme s'il était adopté.

### Histoire invalide

Affiche identifiant demandé, cause, chemins disponibles et lien vers le registre. Ne produit pas une page blanche.

### Fixture invalide

Conserve l'histoire, signale la fixture inconnue et propose les fixtures valides.

### Composant en erreur

Isole l'erreur à la zone de rendu, conserve navigation et métadonnées, et ne masque pas l'échec.

### Capture

Masque uniquement les contrôles non pertinents, conserve identifiant/version et stabilise animation, date et réseau.

## 9. Responsive

Le catalogue lui-même est desktop primaire. Sur mobile :

- la navigation devient un panneau explicite ;
- le rendu peut occuper toute la largeur ;
- les métadonnées restent consultables ;
- aucun iframe ou canevas n'impose un défilement horizontal non signalé.

Le contrôle de largeur simule un viewport sans prétendre remplacer les tests sur appareil réel.

## 10. Accessibilité

- navigation clavier complète ;
- lien d'évitement vers le rendu ;
- structure de titres ordonnée ;
- nom accessible de chaque contrôle ;
- focus visible ;
- changement de fixture annoncé sans voler le focus ;
- erreurs reliées à leur cause ;
- aucun statut porté par couleur seule ;
- zone de rendu distinguée des outils.

## 11. Données, sécurité et confidentialité

- aucune donnée de production ;
- aucun secret ou jeton ;
- aucune télémétrie externe par défaut ;
- ressources essentielles locales ;
- images de personnes fictives ou ressources autorisées ;
- fixtures contrôlées contre téléphone, e-mail, identifiant et géolocalisation réels ;
- bannière persistante « Démonstration — données fictives ».

## 12. Emplacement et frontière du code

L'implémentation de ce contrat réside dans :

> `wasplex-ecosystem/ui-catalogue/`

Elle reste autonome, front-end uniquement et consultable sans backend. Les contrats demeurent dans `ux/lots/` ; les composants, fixtures, histoires, tests et captures exécutables résident dans `ui-catalogue/`.

Le futur dépôt applicatif consommera ou réimplémentera les composants normalisés selon la stratégie d'intégration alors adoptée. Il ne doit pas importer les outils internes du catalogue dans le bundle de production.

## 13. Structure technique indicative

La structure exacte sera adaptée au futur dépôt applicatif, mais sépare :

- registre des histoires ;
- composants Wasplex ;
- fixtures ;
- tokens ;
- coquilles ;
- écrans prototypes ;
- outils du catalogue ;
- tests ;
- captures.

Le catalogue ne doit pas importer les contrôleurs, modèles ou adaptateurs de production pour fonctionner.

## 14. Captures attendues

- page Fondations en clair et sombre ;
- composant avec sélecteur de fixture ;
- largeur 320 px ;
- largeur 1440 px ;
- texte long ;
- hors ligne ;
- résultat inconnu ;
- composant en erreur ;
- mode capture.

## 15. Critères d'acceptation

- lancement local en une commande documentée ;
- fonctionnement sans réseau ;
- lien stable reproductible ;
- sélection thème/largeur/langue/fixture ;
- fixtures synthétiques vérifiables ;
- capture déterministe ;
- erreur isolée ;
- navigation clavier ;
- absence de backend et de secrets ;
- métadonnées de traçabilité visibles ;
- tests automatiques et revue humaine réalisés.

## 16. Interdictions

- connecter Supabase ou Lovable Cloud ;
- présenter le catalogue comme environnement de staging ;
- utiliser un compte ou un Wallet réel ;
- permettre une action institutionnelle ou financière réelle ;
- cacher l'origine synthétique des données ;
- approuver automatiquement une histoire parce qu'elle compile ;
- laisser un prompt génératif modifier le registre officiel.

