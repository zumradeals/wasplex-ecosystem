# DS-0001 — Identité visuelle, langage d'interface et Design System

**État :** proposé à la validation du fondateur  
**Date :** 22 juillet 2026  
**Décideur design :** SIRR, sur mandat du fondateur  
**Dépendances :** Constitution v1.4, AMD-0007, AMD-0008, ADR-0001, ADR-0004, ADR-0006, ADR-0008  
**Référence fondatrice :** emblème du martin-pêcheur tenant un poisson fourni par le fondateur

## 1. Intention

Wasplex doit être perçu comme :

- digne ;
- accessible ;
- précis ;
- énergique ;
- africain sans caricature ;
- technologique sans froideur ;
- économique sans promesse de richesse facile ;
- sécurisant sans apparence bancaire trompeuse ;
- solidaire sans exploiter la pauvreté.

L'interface ne cherche pas à éblouir. Elle doit rendre compréhensibles valeur, état, risque, preuve et action.

## 2. Nom et écriture

L'écriture officielle est **Wasplex**, avec une majuscule initiale.

Les termes officiels sont :

- Wasplex ;
- WasPoint ;
- WP ;
- Fonds Social Wasplex ;
- Cartes Wasplex ;
- Wasplex Alertes ;
- Wasplex Institutions lorsque le portail doit être nommé.

Les variantes `WASPLEX` sont réservées aux petits labels visuels. `WasPlex` n'est pas l'écriture courante officielle.

Le terme « Agent » reste absent.

## 3. Symbole fondateur

Le martin-pêcheur représente :

- observation ;
- patience ;
- précision ;
- rapidité au moment juste ;
- capacité à transformer une opportunité en valeur concrète.

Le poisson représente la valeur économique captée dans l'océan numérique puis rendue utile. Il ne représente jamais l'utilisateur comme proie.

L'ensemble traduit la mission : Wasplex ne capture pas la personne ; il capte une opportunité économique pour en redistribuer la valeur.

Le symbole ne doit pas être utilisé pour glorifier prédation, domination ou richesse facile.

## 4. Système de logo

Le logo officiel futur comprend quatre variantes issues d'une redessination vectorielle contrôlée :

1. **Emblème complet** : martin-pêcheur, poisson et mot Wasplex.
2. **Signature horizontale** : symbole simplifié et mot Wasplex.
3. **Icône compacte** : tête/silhouette reconnaissable du martin-pêcheur pour favicon, application et avatar.
4. **Monochrome** : forme unique pour documents, tampons et faibles moyens d'impression.

L'image fondatrice actuelle est une référence de direction artistique, pas le fichier maître final. Elle doit être redessinée en vectoriel avant usage institutionnel, impression ou marque déposée.

La simplification conserve :

- crête ;
- bec horizontal ;
- contraste bleu/orange ;
- poisson identifiable ;
- énergie directionnelle ;
- personnalité chaleureuse.

Elle supprime les détails impossibles à lire à petite taille.

## 5. Protection du logo

L'espace de protection minimal correspond à la hauteur du « W » du mot-symbole.

Le logo ne doit pas être :

- étiré ;
- incliné ;
- recoloré arbitrairement ;
- enfermé dans une forme non approuvée ;
- posé sur un fond illisible ;
- découpé au niveau du poisson ou du bec ;
- animé de manière enfantine dans un contexte financier ou d'urgence ;
- confondu avec une institution publique.

La taille minimale exacte sera validée sur les fichiers vectoriels finaux. En dessous du seuil de lisibilité, seule l'icône compacte est utilisée.

## 6. Architecture des couleurs

Wasplex sépare deux familles :

- **couleurs de marque** : identité et reconnaissance ;
- **couleurs sémantiques** : état, sécurité et conséquence.

Une couleur de marque ne remplace jamais une couleur d'état.

### 6.1. Palette de marque

| Token | Clair | Sombre | Rôle |
|---|---|---|---|
| `brand.navy` | #10233F | #07182D | profondeur, cadre, textes forts |
| `brand.blue` | #075CCF | #4FA3FF | action principale et confiance |
| `brand.cyan` | #007F9F | #2BC4DE | technologie, navigation active |
| `brand.orange` | #C75100 | #FF9A3D | énergie, valeur, accent |
| `brand.gold` | #936800 | #F2C14E | Wallet et valeur confirmée contextualisée |

L'orange n'est pas la couleur d'urgence. Le doré n'est pas un synonyme universel de succès.

### 6.2. Couleurs sémantiques

| Token | Clair | Sombre | Signification exclusive |
|---|---|---|---|
| `status.success` | #137A50 | #42D392 | confirmé, réussi, disponible |
| `status.warning` | #9A5B00 | #F4B942 | attention, limite, action requise |
| `status.danger` | #B42318 | #FF6B61 | danger, échec grave, SOS |
| `status.info` | #075CCF | #70B7FF | information et progression |
| `status.pending` | #6B5B00 | #E7CF61 | en attente ou en traitement |
| `status.unknown` | #53657D | #A9B7C8 | résultat non encore établi |

« Inconnu » n'est jamais affiché en vert ou rouge comme si le résultat était établi.

### 6.3. Neutres

| Token | Clair | Sombre |
|---|---|---|
| `bg.canvas` | #F5F7FA | #07182D |
| `bg.surface` | #FFFFFF | #0E2542 |
| `bg.raised` | #F8FAFC | #173251 |
| `text.primary` | #10233F | #F5F8FC |
| `text.secondary` | #53657D | #A9B7C8 |
| `border.default` | #CBD5E1 | #35506D |
| `focus.ring` | #075CCF | #70B7FF |

Les valeurs sont des références initiales. Leur validation finale exige tests de contraste sur chaque couple réellement utilisé. Un token peut être ajusté pour accessibilité sans changer son rôle.

## 7. Règles sémantiques

- Bleu : action ou information.
- Cyan : sélection, technologie ou navigation.
- Orange : énergie de marque, opportunité ou accent économique.
- Or : valeur et Wallet, jamais preuve unique de gain.
- Vert : succès réellement confirmé.
- Ambre : attente ou prudence.
- Rouge : danger, erreur grave, SOS et urgence.
- Gris/ardoise : neutre, indisponible ou inconnu.

Aucun état n'est communiqué par couleur seule : texte, icône et forme complètent toujours.

Les couleurs de modules servent à l'orientation, pas à remplacer les statuts.

## 8. Typographie

### Police d'interface

**Inter Variable**, auto-hébergée et sous-ensemblée lorsque possible, avec repli système :

> Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif

Elle est choisie pour la lisibilité, la diversité des graisses, les chiffres et les petits écrans.

Le mot-symbole possède son dessin propre et ne devient pas une police d'interface.

### Échelle

- 12 px : annotation exceptionnelle ;
- 14 px : aide, légende et label secondaire ;
- 16 px : texte et contrôle standard ;
- 18 px : texte important ;
- 20 px : titre de section ;
- 24 px : titre d'écran ;
- 32 px : titre majeur ;
- 40 px et plus : communication, non interface dense.

Le texte courant mobile reste normalement à 16 px. Les informations financières essentielles ne descendent pas sous 16 px.

Les montants utilisent chiffres tabulaires lorsque l'alignement l'exige.

## 9. Grille et espacements

L'unité fondamentale est 4 px.

Échelle :

> 4, 8, 12, 16, 20, 24, 32, 40, 48, 64

Les écrans mobiles utilisent en général 16 px de marge et peuvent descendre à 12 px sur très petits écrans sans compresser les zones tactiles.

Les écrans professionnels utilisent une grille adaptable et une largeur maximale de lecture. L'espace blanc structure la hiérarchie ; il n'est pas un vide à remplir.

## 10. Formes, bordures et ombres

Rayons :

- 8 px : champs et petits contrôles ;
- 12 px : boutons et cartes compactes ;
- 16 px : cartes principales ;
- 24 px : panneaux expressifs ;
- pilule : uniquement pour statuts, filtres ou actions adaptées.

Wasplex évite que chaque bloc devienne une grande carte arrondie. Une surface sans besoin de séparation reste simple.

Les ombres sont légères en thème clair. En thème sombre, bordures et contrastes de surface sont préférés aux halos.

Le Wallet central peut utiliser une élévation distinctive, mais ne doit pas masquer les autres navigations ni ressembler à une promesse de gain.

## 11. Iconographie

L'iconographie utilise un ensemble vectoriel cohérent :

- trait arrondi ;
- épaisseur stable ;
- grille commune ;
- formes lisibles à 20–24 px ;
- variante pleine réservée aux états forts.

Les emojis ne sont pas les icônes officielles des catégories critiques. Ils peuvent apparaître dans un contenu social, jamais comme unique signal d'un SOS, paiement ou état institutionnel.

Les icônes de perte, découverte, disparition, santé, incendie, accident et vol sont testées culturellement et accompagnées de texte.

## 12. Thèmes clair et sombre

Les deux thèmes sont officiels et fonctionnellement équivalents.

Le choix initial respecte la préférence système, puis le choix utilisateur. Wasplex ne force pas le sombre comme symbole de prestige.

Le clair est indispensable pour l'extérieur et le fort ensoleillement. Le sombre réduit l'éblouissement et convient au Feed.

Le Feed vidéo utilise des overlays sombres contrôlés même lorsque l'application est en thème clair.

Aucun thème ne peut réduire contraste, masquer un état ou modifier une signification.

## 13. Responsive

Priorité mobile à partir de 320 px de largeur utile, avec amélioration progressive.

Principes :

- aucune action essentielle réservée au survol ;
- zones tactiles d'au moins 44 × 44 px ;
- navigation accessible au pouce ;
- formulaire en une colonne sur mobile ;
- tableaux transformés en listes ou vues détaillées ;
- actions critiques visibles sans scroll trompeur ;
- clavier virtuel et safe areas pris en compte.

Desktop ne constitue pas une version étirée du mobile. Les portails professionnels utilisent navigation latérale, tableaux, filtres et panneaux adaptés.

## 14. Navigation utilisateur

La navigation principale utilisateur conserve cinq destinations maximum visibles :

- Feed ;
- Fonds Social ou espace communautaire selon l'accès ;
- Wallet ;
- Alertes ;
- Mon espace.

Le libellé final de « Social » doit éviter la confusion entre Fonds Social et réseau social. Tant que le périmètre n'est pas arrêté, l'interface affiche un nom explicite plutôt qu'un terme ambigu.

Le Wallet peut occuper la position centrale sans être agrandi au point de suggérer que l'argent domine sécurité, dignité et communauté.

Un onglet indisponible explique la condition au lieu de disparaître silencieusement.

## 15. Feed

Le Feed distingue visuellement :

- publicité rémunérée ;
- alerte communautaire ;
- alerte sponsorisée ;
- information institutionnelle ;
- pause utile ;
- contenu exploratoire futur.

### Publicité rémunérée

Affiche clairement :

- label « Publicité rémunérée » ;
- annonceur ;
- durée ou condition ;
- montant potentiel ;
- progression ;
- état du son ;
- pourquoi cette publicité, lorsque pertinent.

La récompense potentielle n'est pas présentée comme déjà acquise.

### Alerte communautaire

Affiche catégorie, lieu suffisamment général, date, statut et action sûre. Elle n'imite pas un SOS si elle n'en est pas un.

### Alerte sponsorisée

Porte toujours le label « Alerte sponsorisée ». Le boost augmente la portée, jamais la gravité, la confiance ou la priorité institutionnelle.

### Information institutionnelle

Affiche institution vérifiée, territoire, date et finalité. Elle ne ressemble pas à une publicité.

### Pause utile

Interrompt la monotonie avec prévention, conseil ou information, sans créer de faux gain ni de publicité cachée.

## 16. Alertes et urgence

Les alertes ordinaires et le SOS n'utilisent pas la même intensité.

### SOS

- rouge sémantique ;
- action immédiate ;
- instructions courtes ;
- localisation et numéro de rappel ;
- état exact de transmission ;
- numéros officiels visibles ;
- aucune publicité ou récompense.

### Alerte nationale critique

Se superpose au contenu Wasplex et interrompt la publicité. Elle affiche :

- autorité vérifiée ;
- gravité ;
- territoire ;
- heure ;
- instruction principale ;
- expiration ou mise à jour ;
- accès aux détails.

Elle ne comporte ni Wallet, ni réaction sociale, ni sponsor, ni animation décorative.

Si la réception n'est pas vérifiée ou si l'information est ancienne, l'interface l'indique explicitement.

## 17. Wallet

Le Wallet doit inspirer contrôle et traçabilité, pas casino ou enrichissement rapide.

Il affiche séparément :

- WP disponibles ;
- WP provisoires ;
- WP réservés ;
- équivalent FCFA ;
- date de mise à jour ;
- opérations ;
- frais ;
- états inconnus ou en rapprochement.

Le vert est réservé à un succès confirmé. Le doré identifie la valeur sans prétendre qu'une opération est réussie.

Les nombres sont regroupés lisiblement, sans décimales inutiles. Toute approximation porte `≈`.

Un retrait utilise récapitulatif, montant brut, frais, net, destination, point d'irréversibilité et confirmation explicite.

## 18. Abonnements, Fonds Social et Cartes

### Abonnements

Les niveaux ne sont pas présentés comme rang humain, richesse ou supériorité morale. Prix, durée, quotas, disponibilité non garantie et avantages sont visibles.

### Fonds Social

Le vocabulaire parle de solidarité, mandat, apport, contribution, réserve et vœu. Il évite « épargne garantie », « cagnotte gagnée » ou « tour assuré ».

L'interface distingue adhésion publicitaire et adhésion sociale.

### Cartes Wasplex

La carte peut être valorisante sans employer « actionnaire », « investissement » ou « dividende garanti ». Elle montre services, validité, opérations partenaires et distributions réellement établies.

Le support physique futur reprend les mêmes identifiants visuels sans créer une apparence de carte bancaire avant autorisation réglementaire.

## 19. Portails professionnels

Annonceurs, institutions et administration partagent les fondations visuelles mais pas la même navigation.

### Annonceur

Priorité à campagne, budget, audience autorisée, modération, résultats et facturation.

### Institution

Priorité à dossiers, territoires, urgence, capacité, preuve et actions autorisées. Aucun écran intitulé simplement « Base de données ».

### Administration

Priorité à état du système, files d'approbation, risques, audit et configuration. Les actions critiques ne sont jamais noyées dans des boutons identiques.

Les portails professionnels utilisent le vouvoiement.

## 20. Composants obligatoires

Le Design System fournit au minimum :

- Button ;
- IconButton ;
- Link ;
- Input ;
- Textarea ;
- Select ;
- Checkbox ;
- Radio ;
- Switch ;
- Field ;
- FormError ;
- Card ;
- StatusBadge ;
- AlertBanner ;
- Modal ;
- Drawer ;
- BottomSheet ;
- Tabs ;
- BottomNavigation ;
- SideNavigation ;
- DataTable ;
- EmptyState ;
- Skeleton ;
- Progress ;
- Amount ;
- TransactionStatus ;
- EvidenceStatus ;
- MediaPlayer ;
- CampaignCard ;
- AlertCard ;
- WalletSummary ;
- ConfirmationStep.

Chaque composant possède états normal, focus, survol si applicable, pressé, désactivé, chargement, erreur et lecture seule.

Les états métier pending, unknown et failed ne sont pas réduits aux états techniques loading/error.

## 21. Boutons et actions

Hiérarchie :

1. primaire : action principale de l'écran ;
2. secondaire : alternative sûre ;
3. tertiaire : action faible ;
4. danger : action destructive ou risquée ;
5. lien : navigation contextuelle.

Un écran ne possède normalement qu'une action primaire dominante.

Un bouton désactivé explique la raison lorsque celle-ci n'est pas évidente.

Les actions irréversibles utilisent un verbe précis : « Confirmer le retrait », « Révoquer l'accès », « Envoyer le SOS ». Elles évitent « Continuer » lorsque la conséquence est économique ou critique.

## 22. Formulaires

Chaque champ possède un label permanent. Le placeholder donne un exemple mais ne remplace pas le label.

Les erreurs :

- apparaissent près du champ ;
- expliquent comment corriger ;
- ne reposent pas sur la couleur ;
- conservent les données sûres déjà saisies ;
- placent le focus utile ;
- distinguent format, refus métier et panne.

Les formulaires longs sont découpés par intention, avec progression compréhensible et sauvegarde de brouillon lorsque sûre.

## 23. États d'interface

Toute surface dynamique prévoit :

- initial ;
- chargement ;
- vide ;
- partiel ;
- succès ;
- avertissement ;
- échec ;
- inconnu ;
- hors ligne ;
- accès refusé ;
- maintenance ;
- lecture seule.

Un écran vide explique ce qui manque et l'action possible. Un skeleton ne reste pas indéfiniment sans état d'erreur.

## 24. Mouvement

Durées ordinaires : 150 à 250 ms. Les mouvements complexes restent rares.

Sont interdits :

- défilement conçu pour rendre dépendant ;
- confettis financiers ;
- clignotement agressif hors urgence justifiée ;
- animation empêchant une action ;
- compteurs simulant un gain avant validation ;
- autoplay sonore.

Le mode réduction des animations est respecté.

Une animation d'urgence sert à attirer l'attention sans dépasser les limites de sécurité visuelle.

## 25. Médias et sobriété

Le lecteur propose qualité adaptée, sous-titres, son désactivé par défaut lorsque le contexte l'exige, durée et progression.

Le téléchargement n'est pas confondu avec le visionnage.

Images modernes, tailles réservées et chargement différé réduisent données et déplacements visuels.

Une version texte ou image doit être possible pour certains contenus lorsque la campagne et sa preuve l'autorisent.

## 26. Accessibilité

DS-0001 applique ADR-0008 et vise WCAG 2.2 AA.

Exigences :

- contraste vérifié ;
- navigation clavier ;
- focus visible ;
- lecteur d'écran ;
- titres hiérarchiques ;
- labels et descriptions ;
- zones tactiles ;
- zoom ;
- orientation ;
- sous-titres ;
- erreurs annoncées ;
- aucune couleur seule ;
- temps supplémentaire lorsque possible ;
- langage simple.

Les alertes, Wallet, KYC et confirmations reçoivent une revue humaine renforcée.

## 27. Ton utilisateur

L'application destinée aux populations utilise **tu** de manière respectueuse, simple et constante.

Le ton est :

- direct ;
- digne ;
- honnête ;
- encourageant ;
- non infantilisant ;
- non culpabilisant ;
- sans promesse de gain.

Exemples :

- « Regarde jusqu'à la fin pour rendre cette publicité éligible à 30 WP. »
- « Ton retrait a été transmis. Le paiement n'est pas encore confirmé. »
- « Cette opération est en vérification. Tes WP restent réservés. »
- « Aucun vœu n'est garanti. Tu peux suivre ici l'état de ta demande. »

## 28. Ton professionnel

Les portails annonceurs, institutions et administration utilisent **vous**.

Le ton est factuel, probant et orienté action :

- « La campagne est suspendue dans l'attente des justificatifs. »
- « La transmission a été reçue par le système de l'institution ; la prise en charge n'est pas encore confirmée. »
- « Cette configuration nécessite une seconde approbation. »

## 29. Mots interdits ou encadrés

Sans qualification juridique et fonctionnelle appropriée, l'interface évite :

- argent facile ;
- revenu garanti ;
- investissement sûr ;
- dividende garanti ;
- actionnaire ;
- épargne garantie ;
- base de données à louer ;
- agent institutionnel ;
- retrait instantané si non prouvé ;
- intervention confirmée si seulement transmise.

Les phrases publicitaires restent soumises à AMD-0013.

## 30. Localisation

Tout texte d'interface est externalisé. Aucune phrase essentielle n'est construite par concaténation fragile.

Les formats respectent :

- langue ;
- pluriels ;
- noms ;
- date et heure ;
- devise ;
- numéro de téléphone ;
- territoire ;
- niveau de lecture.

Le français est la langue initiale de référence. Les langues locales et usuelles sont ajoutées avec traduction humaine ou revue responsable. Une traduction IA non relue n'est pas publiée pour un parcours critique.

## 31. Imagerie

Les images représentent la diversité des populations avec dignité.

Sont évités :

- misérabilisme ;
- pauvreté mise en spectacle ;
- stéréotypes ethniques ou professionnels ;
- fausse scène institutionnelle ;
- personne vulnérable utilisée pour provoquer un clic ;
- argent volant, pluie de pièces ou luxe irréaliste ;
- témoignage synthétique non signalé.

Une personne identifiable possède les droits et consentements nécessaires.

## 32. Tokens et implémentation

Les décisions visuelles sont stockées sous forme de tokens versionnés :

- couleur ;
- typographie ;
- espace ;
- rayon ;
- bordure ;
- ombre ;
- mouvement ;
- couche ;
- breakpoint.

Les tokens génèrent variables CSS et types TypeScript. Les composants React les consomment ; les valeurs arbitraires dans les écrans sont refusées par revue et tests lorsque possible.

Le Design System reste dans le monolithe au lancement. Aucun package séparé ou site complexe n'est requis avant besoin réel.

## 33. Documentation des composants

Chaque composant documente :

- finalité ;
- anatomie ;
- propriétés ;
- états ;
- comportements responsive ;
- clavier et lecteur d'écran ;
- contenu recommandé ;
- erreurs ;
- exemples autorisés et interdits ;
- tests ;
- version.

Les captures ne sont pas la spécification. Le composant exécutable et ses règles font autorité.

## 34. Gouvernance du design

Un changement de token sémantique ou de composant critique évalue :

- contraste ;
- sens ;
- modules affectés ;
- captures ;
- régression ;
- accessibilité ;
- coût réseau ;
- compatibilité.

Une couleur de statut ne change pas localement dans un écran.

Les variantes temporaires sont mesurées et supprimées. Un test visuel ne peut manipuler une conséquence financière ou une urgence.

## 35. Tests obligatoires

Avant adoption complète du Design System :

- contrastes clair/sombre ;
- tailles 320 px à desktop ;
- zoom 200 % ;
- clavier ;
- lecteur d'écran ;
- zones tactiles ;
- réseau faible ;
- réduction des animations ;
- thèmes ;
- langues plus longues ;
- montants importants ;
- tous les états ;
- Feed sur vidéo claire et sombre ;
- SOS et alerte nationale ;
- Wallet et résultat inconnu ;
- portail institutionnel ;
- impression monochrome du logo futur.

Les régressions visuelles complètent, sans remplacer, les assertions sémantiques.

## 36. Conséquences

### Bénéfices

- identité reconnaissable sans confusion fonctionnelle ;
- états économiques honnêtes ;
- cohérence mobile/desktop ;
- accessibilité et sobriété ;
- composants réutilisables ;
- prompts futurs moins ambigus.

### Coûts

- redessination vectorielle du logo ;
- création et test des tokens ;
- bibliothèque de composants ;
- revue rédactionnelle et traduction ;
- discipline contre les variantes locales.

Ces coûts sont acceptés : l'âme de Wasplex doit être perceptible sans rendre ses règles moins claires.

## 37. Règle obligatoire

> Dans Wasplex, la beauté renforce la compréhension. Elle ne masque jamais un coût, une attente, un risque, une absence de preuve ou une condition de rémunération.

Tout futur prompt d'interface doit citer DS-0001 et utiliser les tokens et composants officiels.