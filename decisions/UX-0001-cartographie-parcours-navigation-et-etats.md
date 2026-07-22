# UX-0001 — Cartographie des parcours, navigation et états

**État :** proposé à la validation du fondateur  
**Date :** 22 juillet 2026  
**Décideur UX :** SIRR, sur mandat du fondateur  
**Dépendances :** Constitution v1.4, décisions métier adoptées, ADR-0001 à ADR-0009, DS-0001

## 1. Contexte

Wasplex relie plusieurs univers : publicité rémunérée, Wallet, abonnements, Fonds Social, Cartes, Alertes, annonceurs, institutions et administration.

Sans cartographie commune, chaque écran risque de créer sa propre navigation, son vocabulaire et ses raccourcis. L'utilisateur ne saurait plus si une opération est terminée, en attente, refusée ou simplement hors ligne.

## 2. Décision

Wasplex adopte une architecture d'expérience fondée sur :

- un espace utilisateur mobile-first ;
- des portails professionnels adaptés au desktop ;
- une identité commune mais des entrées explicites ;
- une navigation stable ;
- des parcours progressifs ;
- un état littéral à chaque étape ;
- une reprise sûre après interruption ;
- aucune impasse sans explication ni prochaine action ;
- aucun dark pattern commercial ou financier.

Tout parcours indique :

- où la personne se trouve ;
- ce qu'elle peut faire ;
- ce qui est requis ;
- ce qui s'est réellement passé ;
- ce qui reste inconnu ;
- quelle action sûre reste possible.

## 3. Contextes d'expérience

### 3.1. Visiteur

Peut comprendre Wasplex, consulter les principes, voir les conditions générales publiques, accéder aux informations d'urgence autorisées et choisir son espace.

Il ne voit aucun faux solde, faux gain ou fausse campagne.

### 3.2. Utilisateur

Une seule application couvre niveau gratuit et niveaux payants. L'abonnement modifie les capacités, pas l'identité de l'application.

Les membres du Fonds Social et détenteurs de Cartes restent des utilisateurs avec des droits supplémentaires, non de nouveaux acteurs.

### 3.3. Annonceur

Portail professionnel centré sur organisation, campagnes, budget, conformité, résultats agrégés et facturation.

### 3.4. Institution affiliée

Portail sur invitation et habilitation, centré sur dossiers, territoires, alertes et actions probantes.

### 3.5. Wasplex interne

Portail administré par capacités : support, modération, conformité, finance, sécurité, gouvernance et audit.

Un menu différent n'accorde jamais une autorisation différente de l'ADR-0004.

## 4. Portes d'entrée publiques

La page publique propose sans ambiguïté :

- « Découvrir Wasplex » ;
- « Créer mon compte » ;
- « Me connecter » ;
- « Espace annonceur » ;
- « Espace institutions » ;
- accès aux moyens d'urgence et alertes publiques autorisées ;
- confidentialité, conditions et principes.

L'espace institutionnel n'est pas une inscription libre. L'annonceur peut demander l'ouverture ou créer un dossier d'organisation selon les pays activés.

Un lien profond conserve sa destination après authentification lorsque l'action reste autorisée.

## 5. Création de compte utilisateur

Le parcours initial demande le minimum :

1. langue et pays de service ;
2. canal de contact disponible ;
3. preuve de contrôle du canal ;
4. nom ou identité déclarée minimale ;
5. acceptation des conditions indispensables ;
6. choix séparés des consentements facultatifs ;
7. création du moyen d'accès ;
8. présentation du fonctionnement réel.

Le téléphone est le canal privilégié lorsque disponible, mais l'architecture permet les alternatives autorisées. L'absence d'e-mail n'empêche pas le socle utilisateur.

Le profil détaillé, les intérêts et le KYC ne sont pas imposés dans un seul long formulaire. Ils sont demandés au moment où leur finalité devient compréhensible.

## 6. Onboarding

L'onboarding explique en quelques étapes :

- l'attention qualifiée ;
- la condition de complétion ;
- l'absence de campagne ou revenu garanti ;
- 1 WP = 1 FCFA ;
- les trois états du Wallet ;
- les consentements ;
- l'accès aux Alertes ;
- la place du niveau gratuit.

L'utilisateur peut quitter puis reprendre. Il peut accéder à l'application après le minimum obligatoire sans compléter immédiatement tout son profil.

Aucun carrousel marketing ne dissimule une condition essentielle.

## 7. Navigation mobile utilisateur

Navigation principale à cinq destinations :

1. **Feed**
2. **Fonds**
3. **Wallet**
4. **Alertes**
5. **Mon espace**

### Feed

Publicités, alertes intégrées, informations institutionnelles et pauses utiles selon DS-0001.

### Fonds

Nom court de navigation du Fonds Social. La page explique l'accès si l'utilisateur n'est pas éligible. Elle ne se nomme plus « Social », afin d'éviter l'idée d'un réseau social général.

### Wallet

Soldes, historique, retraits et services financiers activés.

### Alertes

Annonces, déclarations, restitutions et SOS. Le SOS reste atteignable en une action claire depuis cet espace et les surfaces prévues.

### Mon espace

Profil, consentements, abonnement, Cartes, sécurité, appareils, assistance et paramètres.

La Carte Wasplex se trouve dans Wallet et Mon espace selon l'action ; elle ne reçoit pas un sixième onglet principal.

## 8. Navigation professionnelle

### Annonceur

- Vue d'ensemble
- Campagnes
- Audiences
- Budget
- Créations
- Rapports
- Facturation
- Organisation et accès
- Assistance

### Institution

- Vue d'ensemble
- Dossiers autorisés
- Alertes/SOS selon capacité
- Correspondances
- Restitutions
- Recherches autorisées
- Historique des actions
- Organisation et accès

Aucun menu générique « Base de données ».

### Administration Wasplex

Navigation par files de responsabilité :

- À traiter
- Risques et incidents
- Finance et rapprochement
- Publicité et modération
- Alertes et institutions
- Fonds Social
- Cartes et partenaires
- Configurations
- Accès
- Audit

Chaque personne ne voit que les files correspondant à ses capacités.

## 9. Feed et session d'attention

Le Feed ne fonctionne pas comme un défilement infini sans borne.

Une session indique progression raisonnable, possibilité d'arrêter et pauses utiles. Les recommandations n'ont pas pour objectif d'allonger artificiellement le temps.

Avant une publicité, l'utilisateur voit :

- annonceur ;
- format ;
- durée ou condition ;
- gain potentiel ;
- consommation approximative lorsque pertinente ;
- raison générale de l'éligibilité ;
- bouton démarrer ou choix de passer.

Le gain est présenté comme potentiel jusqu'à validation.

## 10. Parcours publicité rémunérée

États :

> éligible → prête → démarrée → en cours → complétée → transmise → en validation → disponible ou refusée

Cas particuliers :

- interrompue avant seuil : non éligible, sans punition ;
- réseau coupé : progression reprise si le format le permet ;
- preuve non transmise : état local explicite, aucun faux gain ;
- doublon : résultat existant restauré ;
- fraude suspectée : provisoire ou en examen ;
- campagne suspendue après vue de bonne foi : droit protégé selon AMD-0013.

Après complétion, l'interface ne fait pas apparaître immédiatement un gain « disponible » si le Wallet ne l'a pas comptabilisé.

## 11. Profil et consentements

Mon espace distingue :

- profil de base ;
- intérêts publicitaires ;
- situation ou données facultatives ;
- consentements ;
- identité/KYC ;
- sécurité ;
- appareils.

Chaque section indique :

- finalité ;
- caractère obligatoire ou facultatif ;
- bénéfice fonctionnel ;
- destinataires ;
- possibilité de retrait ;
- dernière modification.

Le retrait d'un consentement indique les conséquences futures avant confirmation. Il n'efface pas les preuves devant légalement rester.

Le profil n'affiche pas un score global de valeur humaine.

## 12. Abonnements

Parcours :

1. découvrir les niveaux ;
2. comparer capacités et quotas ;
3. voir prix, durée, disponibilité non garantie et renouvellement ;
4. choisir ;
5. sélectionner le paiement ;
6. confirmer ;
7. attendre le résultat réel ;
8. activer ou afficher l'état inconnu/échoué ;
9. gérer renouvellement, montée, descente ou résiliation.

L'offre gratuite est visible au même titre que les autres et reste utilisable.

Aucun niveau n'utilise une phrase de rendement. Les quotas sont présentés comme maximums, non comme nombre de publicités garanti.

Un paiement inconnu ne déclenche pas une nouvelle demande automatique.

## 13. Wallet

Page principale :

- disponibles ;
- provisoires ;
- réservés ;
- équivalent FCFA ;
- dernière synchronisation ;
- actions autorisées ;
- historique filtrable ;
- états nécessitant attention.

L'historique est la première voie de compréhension. Chaque ligne ouvre une preuve et une explication.

Une donnée locale ancienne affiche sa date et n'autorise aucun mouvement.

## 14. Retrait

Parcours :

1. saisir montant ;
2. choisir destination autorisée ;
3. vérifier identité et limites ;
4. afficher brut, frais, net et délai indicatif ;
5. confirmer avec authentification récente ;
6. réserver les WP ;
7. transmettre ;
8. suivre : confirmé, échoué ou inconnu ;
9. libérer ou clôturer selon preuve.

Après transmission, le bouton de nouvelle tentative disparaît tant que le résultat est inconnu.

L'utilisateur peut consulter le dossier, recevoir une notification et contacter l'assistance sans perdre la référence.

## 15. Fonds Social — accès

L'onglet Fonds est visible pour expliquer le programme, mais l'accès aux fonctions de participation exige :

- abonnement publicitaire éligible actif ;
- adhésion distincte à un programme social ;
- mandat de contribution ;
- conditions et vérifications applicables.

Un utilisateur non éligible voit l'explication et les conditions, pas les dossiers privés ni les vœux des membres.

L'adhésion publicitaire et l'adhésion sociale sont présentées sur deux écrans et contrats distincts.

## 16. Fonds Social — adhésion et mandat

Parcours :

1. vérifier l'éligibilité publicitaire ;
2. découvrir les programmes sociaux ;
3. comparer plafond, apport, vœux, contribution et risques ;
4. choisir ;
5. lire le mandat ;
6. définir ou accepter plafond de prélèvement, fréquence et seuils ;
7. confirmer ;
8. activer après paiement/vérification ;
9. suivre contributions et indice de réciprocité limité.

Une augmentation de charge exige un nouveau consentement.

Le mandat indique ce qui se passe en cas de solde insuffisant, d'absence répétée ou de suspension.

## 17. Fonds Social — vœu

Parcours :

1. vérifier ancienneté, réciprocité, adhésion, nombre de vœux et absence de conflit ;
2. choisir catégorie et urgence ;
3. décrire le besoin ;
4. choisir partenaire lorsque disponible ou parcours hors partenaire ;
5. fournir estimation et justificatifs ;
6. calculer apport et besoin restant ;
7. enregistrer le brouillon ;
8. soumettre ;
9. vérification ;
10. accepté en file, complément demandé ou refus motivé ;
11. constituer l'apport ;
12. déclencher l'appel selon règles ;
13. réaliser avec preuve ;
14. clôturer et traiter reliquat.

Un vœu en argent libre n'est pas proposé comme catégorie ordinaire. Les besoins sans partenaire utilisent paiement justifié au fournisseur, remboursement contrôlé ou dispositif exceptionnel validé ; jamais un transfert opaque par défaut.

Le parcours d'urgence possède une évaluation distincte et ne promet pas la réalisation.

## 18. Cartes Wasplex

Parcours :

1. vérifier abonnement éligible ;
2. découvrir les produits Carte ;
3. voir services, prix, durée et absence de rendement garanti ;
4. choisir et payer ;
5. état paiement ;
6. émettre la carte virtuelle ;
7. activer ;
8. utiliser chez un partenaire ;
9. suivre opérations et distributions réelles ;
10. demander éventuellement le support physique ;
11. renouveler, suspendre ou fermer.

La carte n'est pas présentée comme carte bancaire avant activation réglementaire.

Une période de distribution nulle affiche clairement zéro et sa cause générale.

## 19. Alertes — architecture

L'espace Alertes contient :

- alertes actives autorisées ;
- mes déclarations ;
- déclarer ;
- objets/documents/véhicules ;
- personnes disparues/retrouvées ;
- restitutions ;
- SOS.

Les filtres n'exposent pas de localisation précise ou identité sensible.

Les actions « perdu » et « trouvé » restent symétriques afin de faciliter les correspondances.

## 20. Déclaration ordinaire

Parcours :

1. catégorie ;
2. perdu/trouvé/disparu/retrouvé ;
3. informations minimales ;
4. lieu et période proportionnés ;
5. média contrôlé ;
6. coordonnées protégées ;
7. récompense facultative pour bien autorisé ;
8. prévisualisation des données publiques ;
9. soumission ;
10. modération ou publication ;
11. correspondances ;
12. validation ;
13. restitution/réunification ;
14. clôture.

Les informations sensibles sont montrées au déclarant avant publication avec une explication de visibilité.

## 21. Alerte sponsorisée

Le boost n'est proposé qu'après validation de l'alerte.

Parcours :

1. choisir portée autorisée, durée et surfaces ;
2. voir coût et estimation non garantie ;
3. préfinancer ;
4. activer ;
5. suivre exposition ;
6. arrêter ou laisser expirer ;
7. restituer le budget non consommé selon règles.

Le mot « sponsorisée » reste visible. Aucun choix ne modifie gravité, statut ou priorité institutionnelle.

## 22. SOS

Accessible rapidement, même si l'authentification complète n'est pas possible.

Parcours :

1. choisir nature ;
2. afficher immédiatement le numéro officiel pertinent ;
3. demander localisation juste à temps ;
4. recueillir informations minimales ;
5. confirmer l'envoi lorsque possible ;
6. transmettre ;
7. afficher littéralement : envoi en cours, transmis, reçu ou échec ;
8. permettre appel direct ;
9. conserver référence et suivi.

Hors ligne, Wasplex prépare le signalement, affiche les numéros et indique « non transmis ». Il ne montre jamais une animation de succès sans accusé.

## 23. Alerte nationale critique

Lorsqu'une alerte authentifiée est reçue :

1. interrompre le contenu et la publicité ;
2. afficher autorité, territoire, heure, gravité et instruction ;
3. permettre détails et accessibilité ;
4. conserver une trace dans le centre d'alertes ;
5. mettre à jour ou expirer selon la source.

La fermeture visuelle ne supprime pas la possibilité de retrouver l'alerte active. Une alerte vitale peut exiger un accusé de lecture sans prétendre que l'utilisateur est en sécurité.

## 24. Récupération et appareil partagé

L'application montre toujours le compte actif et propose une déconnexion visible.

Parcours de récupération :

1. identifier le compte par canal autorisé ;
2. vérifier contrôle actuel ;
3. appliquer niveau de preuve proportionné ;
4. révoquer sessions/appareils suspects ;
5. restaurer accès ;
6. imposer protections temporaires sur mouvements sensibles ;
7. informer sur les changements.

La récupération ne supprime ni solde ni historique.

Le mode appareil partagé évite aperçu sensible dans les notifications, permet verrouillage rapide et ne mémorise pas silencieusement le compte.

## 25. Assistance et contestation

Tout refus économique ou restriction importante propose :

- motif compréhensible ;
- règle ou catégorie ;
- effet sur les fonds ;
- pièces attendues ;
- délai lorsque connu ;
- voie de contestation ;
- référence.

Le centre d'assistance organise les demandes par dossier et non uniquement par conversation.

Une contestation ne crée pas automatiquement gain, accès ou suspension de mesure de sécurité.

## 26. Notifications

Le centre de notifications interne est la source consultable.

Canaux externes :

- push ;
- SMS ;
- e-mail ;
- appel ou canal institutionnel lorsqu'autorisé.

Ils servent de rappel et transportent le minimum.

Catégories :

- sécurité ;
- finance ;
- campagnes ;
- Fonds Social ;
- Alertes ;
- institution ;
- produit ;
- communication facultative.

Les notifications critiques nécessaires ne sont pas désactivées comme une newsletter, mais leur canal et leur contenu restent proportionnés.

Chaque notification importante ouvre l'objet exact et non la page d'accueil.

## 27. États transversaux

Chaque parcours définit :

- brouillon ;
- prêt ;
- en cours ;
- en attente ;
- action requise ;
- confirmé ;
- échoué ;
- inconnu ;
- suspendu ;
- expiré ;
- annulé ;
- compensé ;
- clôturé.

Les libellés techniques peuvent varier par domaine, mais leur signification reste cohérente avec DS-0001.

Une attente possède motif, valeur protégée, prochaine étape et moyen de suivi.

## 28. Hors ligne et reprise

Les actions sont classées :

### Consultables hors ligne

Reçus synchronisés, numéros d'urgence, certains contenus et états avec date.

### Préparables hors ligne

Brouillon d'alerte, formulaire, préférence ou action explicitement rejouable.

### Interdites hors ligne

Retrait, paiement, consentement critique, validation d'attention, activation institutionnelle, mouvement Wallet et confirmation d'urgence.

Après retour réseau, Wasplex montre ce qui a été envoyé, refusé, fusionné ou reste à confirmer. Il ne rejoue pas une commande critique sans idempotence.

## 29. Permissions du terminal

Caméra, localisation, notifications et microphone sont demandés :

- au moment utile ;
- avec finalité ;
- avec alternative ;
- sans coercition ;
- une permission à la fois lorsque possible.

Refuser la localisation n'empêche pas de saisir un lieu manuellement si le parcours le permet.

Le paramètre système et le consentement métier restent distincts.

## 30. Liens profonds et retour

Un lien profond contient une destination, pas une autorisation.

Après connexion, KYC, paiement ou approbation, l'utilisateur revient à l'étape initiale si elle reste valide. Sinon Wasplex explique pourquoi et propose la destination sûre la plus proche.

Un lien expiré, révoqué ou déjà utilisé affiche son état réel.

## 31. Annonceur — onboarding

Parcours :

1. créer ou rejoindre une organisation ;
2. vérifier représentant ;
3. fournir informations légales ;
4. choisir pays d'activité ;
5. soumettre justificatifs ;
6. recevoir approuvé, complément ou refus ;
7. inviter des collaborateurs ;
8. alimenter le budget ;
9. créer la première campagne.

Le portail indique clairement les capacités en attente. Une organisation non validée peut préparer un brouillon mais ne diffuse pas.

## 32. Annonceur — campagne

Parcours :

1. objectif et événement qualifié ;
2. création et destination ;
3. pays, calendrier et format ;
4. audience par critères autorisés ;
5. estimation agrégée et seuils ;
6. niveaux d'adhésion ciblés si autorisés ;
7. fréquence ;
8. prix et budget ;
9. preuve attendue ;
10. récapitulatif ;
11. préfinancement ;
12. soumission ;
13. modération ;
14. programmation ;
15. diffusion ;
16. suivi ;
17. suspension, correction ou clôture ;
18. solde restant.

Une modification matérielle crée une nouvelle version et repasse en revue.

Les rapports n'affichent aucune identité et expliquent les données insuffisantes ou masquées.

## 33. Institution — activation

Le compte est créé ou invité par une procédure Wasplex autorisée.

Parcours :

1. invitation nominative ;
2. contrôle d'identité ;
3. rattachement à l'organisation ;
4. acceptation des responsabilités ;
5. MFA ;
6. capacités, territoires et durée ;
7. formation ou validation si requise ;
8. activation ;
9. revue périodique.

Un mot de passe temporaire ne suffit pas à conserver un accès durable.

## 34. Institution — dossier

Parcours :

1. recevoir ou rechercher dans la portée autorisée ;
2. justifier finalité si nécessaire ;
3. consulter les champs minimaux ;
4. accepter ou refuser la prise en charge ;
5. agir ;
6. ajouter une preuve ;
7. transmettre ou clôturer ;
8. journaliser.

Un dossier affiche toujours institution, capacité, territoire et dernière action.

L'institution ne peut pas parcourir librement les profils ou Wallets.

## 35. Administration — files de travail

Le dashboard interne montre d'abord :

- éléments nécessitant action ;
- gravité ;
- délai ;
- fonds engagés ;
- utilisateurs affectés ;
- séparation de tâches ;
- preuve manquante.

Chaque dossier critique possède :

- résumé ;
- historique ;
- configuration applicable ;
- décisions possibles ;
- conséquence ;
- approbateurs ;
- audit.

Un administrateur n'édite jamais directement une valeur finale dans un tableau.

## 36. Authentification et étape renforcée

La connexion donne accès au compte selon son niveau courant. Une action sensible peut demander une authentification récente sans forcer une nouvelle connexion générale.

Après authentification renforcée, l'utilisateur revient à la confirmation exacte, avec les données revalidées.

Une session expirée pendant un formulaire conserve uniquement un brouillon sûr et protège les données sensibles.

## 37. Accessibilité et compréhension

Chaque parcours est réalisable :

- au clavier lorsque le canal s'y prête ;
- avec lecteur d'écran ;
- à 200 % de zoom ;
- sans couleur seule ;
- sur petit écran ;
- avec texte plus long ;
- avec réduction des animations.

Les étapes critiques utilisent une phrase principale, un état et une action. Les informations secondaires sont accessibles sans cacher la conséquence.

## 38. Mesure UX

Wasplex mesure :

- abandon par étape ;
- erreurs ;
- temps de compréhension ;
- reprises après coupure ;
- recours ;
- faux doubles clics évités ;
- autorisations refusées ;
- succès réel ;
- accessibilité.

Ces mesures ne servent pas à pousser l'utilisateur à regarder davantage de publicités ou à souscrire sous pression.

Les analyses respectent AMD-0009 et ne créent pas de surveillance cachée.

## 39. Anti-patterns interdits

- défilement infini sans borne ni pause ;
- bouton de fermeture caché ;
- abonnement précoché ;
- consentement regroupé ;
- fausse rareté ;
- compteur de gain avant validation ;
- retrait affiché payé sur simple transmission ;
- urgence colorée pour une promotion ;
- module verrouillé sans explication ;
- navigation différente selon un gain attendu ;
- demande de toutes les permissions au premier lancement ;
- perte silencieuse du formulaire ;
- recours introuvable ;
- support modifiant directement le résultat.

## 40. Inventaire avant écran

Avant toute maquette, chaque parcours doit produire :

- acteur et contexte ;
- préconditions ;
- point d'entrée ;
- étapes ;
- données ;
- autorisations ;
- décisions ;
- états ;
- erreurs ;
- hors ligne ;
- notifications ;
- reprise ;
- sortie ;
- preuve ;
- métriques.

Les spécifications écran par écran dériveront de cet inventaire.

## 41. Tests obligatoires

Avant adoption des écrans :

- parcours principal et alternatives ;
- refus et inéligibilité ;
- coupure à chaque étape critique ;
- double clic ;
- retour arrière ;
- session expirée ;
- permission refusée ;
- résultat inconnu ;
- appareil partagé ;
- accessibilité ;
- langue longue ;
- montant limite ;
- utilisateur gratuit ;
- abonnement expiré ;
- institution hors territoire ;
- partenaire indisponible ;
- recours.

## 42. Conséquences

### Bénéfices

- navigation stable ;
- modules complexes compréhensibles ;
- états financiers honnêtes ;
- reprise après interruption ;
- distinction claire des espaces ;
- base fiable pour les écrans et prompts.

### Coûts

- conception des alternatives et erreurs ;
- centre de notifications et dossiers ;
- davantage d'états explicites ;
- tests de parcours approfondis.

Ces coûts sont acceptés : une expérience simple n'est pas une expérience qui cache la complexité, mais une expérience qui la rend maîtrisable.

## 43. Règle obligatoire

> Aucun parcours Wasplex ne se termine par une ambiguïté silencieuse. La personne doit connaître l'état, la valeur protégée, la prochaine action et la voie de recours.

Tout futur prompt d'écran ou parcours doit citer UX-0001.