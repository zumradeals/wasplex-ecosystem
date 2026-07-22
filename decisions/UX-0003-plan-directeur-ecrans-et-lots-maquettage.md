# UX-0003 — Plan directeur des écrans et lots de maquettage

**État :** adopté par le fondateur  
**Date :** 22 juillet 2026  
**Décideur UX :** SIRR, sur mandat du fondateur  
**Dépendances :** Constitution v1.4, ADR-0001 à ADR-0009, DS-0001, UX-0001, UX-0002

## 1. Contexte

UX-0001 définit les parcours et les états honnêtes de Wasplex. UX-0002 impose, pour chaque écran important, un contrat versionné et une référence visuelle exécutable.

Il manque encore le plan qui transforme ces deux décisions en chantier maîtrisable. Concevoir les écrans au fil des idées créerait doublons, composants incompatibles, dépendances tardives et surfaces critiques oubliées. Concevoir tout un module dans Lovable avant ses fondations ferait également valider des détails sans disposer d'un langage commun stable.

## 2. Décision

Wasplex adopte un catalogue directeur versionné des écrans et un ordre de maquettage par lots.

Chaque écran reçoit :

- un identifiant stable relié à un parcours UX-0001 ;
- un acteur et une finalité ;
- un terminal primaire ;
- un niveau de risque Q0 à Q3 ;
- une famille de composants ;
- des états obligatoires ;
- des dépendances ;
- un statut de préparation ;
- un lot de conception et de livraison.

Le catalogue est un inventaire vivant. Ajouter un écran est permis ; renommer, fusionner ou supprimer un identifiant publié exige une trace afin de préserver les liens vers contrats, prototypes, tests et décisions.

## 3. Unité de conception

Un écran est une surface possédant au moins un objectif, un état ou un contrat d'interaction distinct. Un dialogue critique, un panneau plein écran mobile ou une superposition nationale peut donc constituer un écran contractuel même sans route propre.

Ne constituent pas automatiquement un nouvel écran :

- une variation purement cosmétique ;
- une largeur responsive du même contrat ;
- un filtre simple ;
- un état couvert dans le contrat parent ;
- une modale sans conséquence propre.

Une variante devient contractuelle lorsqu'elle change vérité affichée, valeur protégée, autorisation, prochaine action ou niveau de risque.

## 4. Identifiants

Format :

> `[PARCOURS]-[NN]-[nom-court]`

Exemples :

- `U-006-01-apercu-wallet` ;
- `U-007-04-retrait-resultat-inconnu` ;
- `I-002-02-dossier-alerte` ;
- `W-001-03-publication-configuration`.

Les préfixes de parcours restent ceux de `ux/05-inventaire-des-parcours.md` :

- `U` : utilisateur ou visiteur ;
- `A` : annonceur ;
- `I` : institution ;
- `W` : Wasplex interne.

Les fondations transversales utilisent `FND` et ne représentent pas un acteur métier.

## 5. Terminaux primaires

### Mobile primaire

Utilisateur, Feed, Wallet, Fonds, Cartes, Alertes et SOS.

Références minimales : petit Android représentatif, mobile courant et fonctionnement avec clavier ouvert lorsque saisie.

### Desktop primaire

Annonceur, institution et administration Wasplex.

Références minimales : ordinateur portable courant et écran desktop. Une table critique ne devient pas une pile illisible sur mobile ; les actions mobiles nécessaires possèdent un contrat adapté.

### Universel

Accès public, connexion, récupération, contenus critiques, SOS et alerte nationale lorsque le canal le permet.

## 6. Statuts de préparation

Chaque entrée du catalogue porte un statut :

- **identifié** : besoin reconnu, contrat non commencé ;
- **bloqué** : décision métier ou dépendance manquante ;
- **à spécifier** : règles suffisantes pour rédiger le contrat ;
- **contractualisé** : contrat complet, revue en attente ;
- **prêt à maquettage** : contrat et fixtures validés ;
- **exploratoire** : variantes en cours dans Lovable ou équivalent ;
- **normalisé** : prototype TSX conforme à DS-0001 ;
- **validé visuellement** : version de référence approuvée ;
- **implémentable** : paquet de passation complet ;
- **implémenté** : production conforme et preuves acceptées ;
- **retiré** : conservé dans l'historique, non utilisé.

Un écran ne saute pas directement de « identifié » à « implémenté ».

## 7. Lots officiels

### L00 — Fondations et catalogue

Tokens exécutables, thèmes, typographie, icônes, mises en page, navigation, formulaires, statuts, listes, tableaux, dialogues, notifications, montants, preuves et sélecteur de fixtures.

Ce lot produit le catalogue React interne. Il précède les maquettes métier haute fidélité.

### L01 — Accès, session et identité minimale

Accueil public, choix d'espace, création de compte, connexion, vérification de canal, récupération, session, appareil partagé, consentements initiaux et onboarding.

### L02 — Coquilles et navigations par acteur

Coquille utilisateur mobile, annonceur desktop, institution desktop, administration Wasplex, centre de notifications et assistance.

### L03 — Feed et publicité rémunérée

Éligibilité, démarrage volontaire, attention, interruption, preuve, validation, gain provisoire, refus, pauses utiles et alertes intégrées.

### L04 — Wallet et retraits

Soldes, ledger lisible, détail d'opération, retrait, réservation, échec, résultat inconnu, rapprochement visible et recours.

### L05 — Alertes, restitutions et urgences

Annonces ordinaires, déclarations, correspondances, restitution, sponsorisation, SOS, réception institutionnelle et alerte nationale critique.

### L06 — Annonceurs et campagnes

Organisation, ciblage agrégé, création, création publicitaire, préfinancement, diffusion, modération, budget, rapports et remboursement.

### L07 — Institutions

Activation nominative, tableau de bord, dossiers autorisés, recherche finalisée, alertes/SOS, correspondances, restitution, historique et accès.

### L08 — Fonds Social

Découverte, porte d'éligibilité, adhésion, mandat, contributions, réciprocité, déclaration de vœu, urgence, apport, collecte, réalisation et clôture.

### L09 — Cartes Wasplex

Éligibilité, catalogue, acquisition, émission virtuelle, activation, opérations partenaires, distributions réelles, support physique, suspension et clôture.

### L10 — Administration, configuration et audit

Files de travail, modération, finance, configurations C1, double contrôle, publication, accès, audit, incidents et bris de glace.

### L11 — Compléments, assistance et qualité finale

Préférences, langues, appareils, téléchargements de preuve, centre d'aide, contestations, états rares, accessibilité renforcée et cohérence intermodules.

## 8. Règles d'ordonnancement

L'ordre des lots représente les dépendances, non une obligation de livrer tout L03 avant le moindre travail L04.

Un écran peut avancer lorsque :

- son contrat métier est stable ;
- ses composants fondamentaux existent ;
- ses entrées et sorties sont connues ;
- ses états Q0/Q1 sont définis ;
- sa dépendance à un autre écran est simulable proprement.

Les écrans Q0 sont contractualisés tôt, mais leur haute fidélité n'est pas validée avant les composants nécessaires. Les lots professionnels peuvent progresser en parallèle après L00 à L02.

## 9. Priorité de conception

La priorité combine :

1. gravité du risque ;
2. centralité dans la mission ;
3. nombre de parcours dépendants ;
4. fréquence d'usage ;
5. incertitude à réduire ;
6. coût d'une correction tardive.

La première tranche de référence comprend :

- coquille mobile utilisateur ;
- page d'entrée publique ;
- Feed avant, pendant et après publicité ;
- aperçu Wallet et détail d'opération ;
- retrait avec résultat inconnu ;
- création d'une alerte ordinaire ;
- SOS hors ligne et transmis ;
- alerte nationale superposée ;
- coquilles annonceur, institution et administration ;
- composants communs utilisés par ces surfaces.

Cette tranche vérifie le langage Wasplex sur valeur, attention, preuve et urgence avant multiplication des écrans.

## 10. États et variantes

Le catalogue ne duplique pas chaque état en route séparée. Il relie chaque écran à une matrice de fixtures.

Tout écran couvre les états UX-0002 applicables. Pour Q0/Q1, l'inventaire indique explicitement :

- valeur protégée ;
- vérité connue ou inconnue ;
- action sûre disponible ;
- reprise ;
- recours ;
- preuve attendue.

Les états nécessitant une composition sensiblement différente reçoivent une capture de référence distincte.

## 11. Composants avant pages

L00 ne cherche pas à créer une bibliothèque abstraite exhaustive. Un composant entre dans le Design System exécutable lorsqu'il :

- apparaît dans au moins deux surfaces ;
- porte une règle sémantique stable ;
- protège une interaction critique ;
- ou garantit accessibilité et cohérence.

Les composants initiaux comprennent notamment :

- coquille et navigation ;
- bouton, lien et action dangereuse ;
- champ, sélection, OTP et téléversement ;
- statut littéral ;
- montant WP/FCFA ;
- répartition et ventilation ;
- carte d'information ;
- ligne d'historique et preuve ;
- bannière hors ligne ;
- résultat inconnu ;
- confirmation critique ;
- alerte et superposition nationale ;
- tableau et file de travail ;
- pagination et filtres ;
- skeleton, vide, erreur et refus ;
- recours et référence de dossier.

## 12. Utilisation de Lovable

Les sessions Lovable sont découpées par lot ou famille cohérente, non par application entière.

Chaque session reçoit :

- extraits normatifs nécessaires ;
- contrat d'écran ;
- composants et tokens disponibles ;
- fixtures ;
- largeurs et thèmes ;
- interdictions UX-0002.

Une session peut explorer plusieurs variantes d'un écran. Elle ne crée pas seule un nouvel identifiant officiel et ne synchronise aucun backend dans le dépôt Wasplex.

## 13. Critères d'entrée dans un lot Lovable

Un écran est envoyé à l'exploration seulement si :

- son identifiant existe ;
- son objectif humain est formulé ;
- son contrat est suffisamment rempli ;
- ses textes critiques sont connus ;
- ses fixtures sont synthétiques ;
- ses états attendus sont listés ;
- ses composants disponibles ou manquants sont connus ;
- ses décisions supérieures sont citées.

## 14. Critères de sortie d'un lot

Un lot est terminé côté conception lorsque :

- tous les écrans prioritaires ont un contrat ;
- les prototypes normalisés couvrent les états exigés ;
- les composants nouveaux sont documentés ;
- les captures de référence sont générées ;
- la revue responsive et accessible est faite ;
- les inconnues restantes sont inscrites ;
- les paquets de passation sont prêts ;
- le catalogue et la traçabilité sont à jour.

## 15. Gouvernance du catalogue

SIRR maintient cohérence, identifiants, dépendances et critères. Le fondateur valide les orientations, surfaces fondatrices et références qui engagent l'identité ou la vision.

Les responsables métier valident règles et textes contractuels de leur domaine. Une IA peut proposer, classer et détecter des manques ; elle ne marque jamais seule un écran « validé visuellement » ou « implémenté ».

## 16. Conséquences

### Bénéfices

- périmètre visible et planifiable ;
- réduction des oublis et doublons ;
- fondations conçues avant prolifération ;
- séparation entre dépendances et priorités ;
- lots Lovable plus précis et moins coûteux ;
- traçabilité écran par écran ;
- possibilité de paralléliser sans fragmenter l'expérience.

### Coûts

- entretien du catalogue ;
- arbitrage explicite des fusions et variantes ;
- travail initial sur les fondations ;
- refus de maquetter prématurément certains écrans séduisants.

Ces coûts sont acceptés afin que la vitesse de production ne détruise pas la cohérence du produit.

## 17. Règle obligatoire

> Aucun écran Wasplex n'est maquetté ou commandé isolément sans identifiant, parcours parent, niveau de risque, contrat et lot. La conception avance par dépendances maîtrisées, non par accumulation de pages.

## Addendum v1.5 — L12 Live

AMD-0014 ajoute le lot officiel **L12 — Live** : découverte, fiche préalable, salle spectateur, interactions, progression et résultat de récompense, planification diffuseur, financement, régie, modération, interruption et rapport. L12 réutilise L00, l'identité L01/L02, le Feed L03, le Wallet L04, la priorité d'urgence L05, les capacités L06/L07 et la gouvernance L10.

L12 ne peut être déclaré implémentable sans les états réseau faible, reconnexion, doublon, modération, résultat financier inconnu et interruption par alerte nationale.
