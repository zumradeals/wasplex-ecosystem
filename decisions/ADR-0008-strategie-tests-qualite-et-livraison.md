# ADR-0008 — Stratégie de tests, qualité et critères de livraison

**État :** adopté par le fondateur  
**Date :** 22 juillet 2026  
**Décideur architectural :** SIRR, sur mandat du fondateur  
**Dépendances :** Constitution v1.4, ADR-0001 à ADR-0007

## 1. Contexte

Wasplex traite attention rémunérée, argent, solidarité, identité, urgences et actions institutionnelles. Une erreur peut produire une perte financière, une atteinte à la vie privée, une fausse prise en charge ou un préjudice humain.

Une fonctionnalité visible et démontrable n'est donc pas nécessairement correcte. Les tests doivent prouver les règles, les frontières, les erreurs, la concurrence, les reprises et les états dégradés.

Le projet peut être développé avec l'aide d'IA. Un code généré rapidement peut sembler cohérent tout en inventant des règles, contourner un module ou ignorer une situation limite.

## 2. Décision

Wasplex adopte une stratégie de qualité fondée sur :

- exigences traçables ;
- tests automatisés proportionnés au risque ;
- tests avec les technologies réelles ;
- revues humaines responsables ;
- preuves de CI ;
- environnements séparés ;
- critères de livraison obligatoires ;
- exercices périodiques de restauration et d'incident.

Aucune fonctionnalité n'est déclarée terminée sur la seule base d'une démonstration manuelle, d'un taux de couverture global ou d'une réponse d'IA.

## 3. Niveaux de risque

### Q0 — Constitutionnel ou vital

Exemples : Ledger, couverture, alertes nationales, sécurité d'un SOS, identité renforcée, séparation de fonds, droits acquis.

Une défaillance peut menacer vie, fonds, légalité ou Constitution.

### Q1 — Critique

Exemples : retrait, paiement, configuration C1, consentement, KYC, institution, Fonds Social, Cartes, autorisations privilégiées.

### Q2 — Important

Exemples : campagne, abonnement, profil, reporting, notifications contractuelles.

### Q3 — Ordinaire

Exemples : présentation, contenu non contractuel, confort sans effet économique ou de sécurité.

Chaque exigence reçoit un niveau. Le niveau détermine revues, tests, environnements, approbations et preuves.

## 4. Pyramide de tests

### 4.1. Contrôles statiques

À chaque changement :

- formatage ;
- lint ;
- analyse PHP et TypeScript ;
- compilation des types ;
- détection de secrets ;
- analyse des dépendances ;
- règles d'architecture ;
- validation des schémas OpenAPI, événements et configurations.

### 4.2. Tests unitaires de domaine

Rapides et déterministes, ils vérifient formules, transitions, politiques, conditions limites et refus.

Les règles métier sont testées sans navigateur et sans dépendance réseau.

### 4.3. Tests d'intégration de module

Ils utilisent PostgreSQL réel et les services du module. SQLite ne remplace pas PostgreSQL pour vérifier contraintes, transactions, verrouillage, JSONB, dates ou concurrence.

Ils couvrent base, migrations, stockage, files et autorisations du module.

### 4.4. Tests de contrats

Producteurs et consommateurs vérifient :

- commandes ;
- événements ;
- API ;
- webhooks ;
- adaptateurs ;
- versions et compatibilité.

### 4.5. Tests de parcours

Un nombre limité de parcours critiques est exécuté de bout en bout dans une interface réelle :

- inscription et récupération ;
- consentement et profil ;
- publicité jusqu'au gain ;
- abonnement ;
- retrait ;
- vœu social ;
- alerte et restitution ;
- accès institutionnel ;
- Carte Wasplex.

Les tests E2E ne remplacent pas les tests de domaine.

### 4.6. Tests exploratoires et humains

Accessibilité, compréhension, appareils modestes, langues, risques de manipulation, opérations institutionnelles et scénarios d'urgence nécessitent une vérification humaine structurée.

## 5. Outils initiaux

L'outillage initial recommandé est :

- Pest ou PHPUnit pour PHP/Laravel ;
- tests Laravel avec PostgreSQL ;
- Vitest et React Testing Library pour React/TypeScript ;
- Playwright pour les parcours navigateur ;
- analyse PHP renforcée par PHPStan/Larastan ;
- TypeScript en mode strict ;
- ESLint et formatage automatisé ;
- GitHub Actions pour la CI ;
- scanners de secrets, dépendances et vulnérabilités ;
- outils de charge et d'accessibilité choisis dans le registre technique.

Les versions exactes et outils remplaçables ne sont pas constitutionnels. Un changement d'outil doit conserver les preuves attendues.

## 6. Environnements

### Local

Données synthétiques, services simulés ou conteneurisés et configuration proche de la production.

### CI

Environnement éphémère, reproductible, PostgreSQL réel, horloge et aléas contrôlables.

### Staging

Architecture représentative, intégrations sandbox, aucune donnée personnelle réelle par défaut et aucune valeur financière réelle.

### Production

Vérifications de santé, migrations contrôlées et tests synthétiques limités qui ne créent ni gain, ni alerte publique, ni mouvement réel.

Les identifiants de sandbox ne fonctionnent jamais en production.

## 7. Données de test

Les tests utilisent fabriques et jeux synthétiques versionnés.

Une copie de production n'est jamais la solution ordinaire. Un cas exceptionnel exige minimisation, anonymisation vérifiée, accès, durée, approbation et preuve de suppression.

Les données couvrent :

- faibles revenus et petits montants ;
- appareils et réseaux partagés ;
- noms, alphabets et numéros locaux ;
- fuseaux et pays ;
- comptes incomplets ;
- doublons ;
- mineurs et données protégées simulés ;
- limites, zéros, arrondis et volumes ;
- pannes et réponses tardives.

Aucun secret réel n'entre dans un fixture, une capture ou un snapshot.

## 8. Traçabilité des exigences

Chaque exigence importante possède un identifiant relié à :

- article constitutionnel ou ADR ;
- scénario d'acceptation ;
- tests automatisés ;
- preuve d'exécution ;
- propriétaire ;
- risque.

Une fonctionnalité ne peut être couverte uniquement par un test dont personne ne peut expliquer la règle.

## 9. Couverture

Wasplex n'adopte pas un pourcentage global comme preuve suffisante.

La couverture exigée est :

- toutes les transitions Q0/Q1 ;
- toutes les branches de refus Q0/Q1 ;
- toutes les formules et bornes ;
- toutes les capacités sensibles ;
- tous les points d'irréversibilité ;
- tous les états externes inconnus ;
- toutes les compensations ;
- toutes les migrations critiques.

Le taux de lignes reste un indicateur, non la mesure suprême. Des tests de mutation ou équivalents peuvent vérifier que les tests détectent réellement une altération des règles critiques.

## 10. Tests fondés sur des propriétés

Les domaines financiers et configurables utilisent des propriétés générales :

- débits = crédits ;
- aucune valeur créée sans source ;
- aucune distribution supérieure à l'enveloppe ;
- aucune double opération avec la même idempotence ;
- aucun solde disponible négatif ;
- 1 WP = 1 FCFA ;
- conservation du partage constitutionnel ;
- réservation ne changeant pas la propriété ;
- retour de configuration ne réécrivant pas le passé ;
- ordre ou répétition d'événements sans double effet.

Les générateurs explorent combinaisons, limites, concurrence et arrondis que des exemples manuels oublieraient.

## 11. Wallet et paiements

Avant livraison, ADR-0003 exige notamment :

- équilibre de chaque journal ;
- concurrence de retraits ;
- résultat externe inconnu ;
- webhook dupliqué ;
- relance après perte réseau ;
- contre-écriture ;
- rapprochement ;
- rétrofacturation ;
- couverture insuffisante ;
- reconstruction de toutes les projections ;
- restauration depuis sauvegarde.

Aucun mock seul ne suffit : les contraintes PostgreSQL et au moins une sandbox prestataire sont testées.

## 12. Fonds Social et Cartes

Les tests couvrent :

- mandat absent, expiré ou insuffisant ;
- plusieurs appels simultanés ;
- seuil et plafond ;
- solde insuffisant ;
- participants exclus ;
- apport ;
- reliquat et réserve ;
- vœu non réalisé ;
- partenaire indisponible ;
- période et clôture de pool ;
- revenu externe nul ;
- absence de recrutement ou rendement garanti ;
- conservation et compensation de chaque montant.

## 13. Alertes et Institutions

Les tests couvrent :

- absence de réseau ;
- SOS mis en attente mais non prétendu transmis ;
- accusé manquant ;
- territoire institutionnel ;
- données masquées ;
- mineur ou personne vulnérable ;
- restitution contestée ;
- récompense préfinancée ;
- aucun paiement pour une personne retrouvée ;
- alerte sponsorisée sans priorité ;
- alerte nationale avec double validation ;
- rejeu, expiration et révocation ;
- panne du portail institutionnel ;
- affichage des moyens officiels directs.

Les scénarios vitaux sont aussi exercés manuellement avec procédures et responsables.

## 14. Autorisations et données

Les tests ADR-0004/ADR-0006 vérifient :

- refus par défaut ;
- isolement organisationnel et territorial ;
- champs masqués ;
- délégation limitée ;
- révocation immédiate ;
- séparation des tâches ;
- bris de glace ;
- export ;
- recherche sans résultat journalisée ;
- suppression et conservation ;
- projection sans donnée interdite ;
- restauration réappliquant les suppressions.

Des tests négatifs tentent volontairement l'accès horizontal, vertical et intermodule.

## 15. Contrats et intégrations

Les tests ADR-0005/ADR-0007 vérifient :

- compatibilité OpenAPI ;
- versions d'événement ;
- outbox/inbox ;
- duplication et ordre inversé ;
- saga reprise ;
- quarantaine ;
- signature et rejeu de webhook ;
- rotation de clé ;
- SSRF ;
- délai et disjoncteur ;
- sandbox isolée ;
- réponse tardive ;
- résultat inconnu ;
- données sensibles absentes des logs et payloads.

Les faux prestataires reproduisent succès, échec, timeout, doublon, statut contradictoire et réponse malformée.

## 16. Migrations

Chaque migration importante est testée :

- sur schéma de la version précédente ;
- avec volume représentatif ;
- pendant coexistence des versions ;
- avec vérifications avant/après ;
- avec reprise après interruption ;
- avec contraintes et index ;
- sans perte ;
- avec rapprochement financier lorsqu'applicable.

Une migration économique n'est jamais validée uniquement parce que la commande s'est terminée sans erreur.

## 17. Réseau, appareils et performance

Le Web est testé avec :

- petit écran ;
- mémoire et processeur modestes ;
- débit faible ;
- latence élevée ;
- coupure ;
- reprise ;
- batterie ou fermeture simulée ;
- appareil partagé ;
- navigateur supporté ;
- médias légers.

Chaque parcours possède un budget mesurable de poids, requêtes, délai et consommation. Ces seuils sont définis avant livraison selon le canal et surveillés dans le temps.

Les tests de charge vérifient concurrence, files, base, cache, stockage et prestataires sans utiliser la production comme premier laboratoire.

## 18. Accessibilité et compréhension

Le produit vise au minimum WCAG 2.2 niveau AA pour les parcours concernés, avec contrôles automatisés et humains.

Sont vérifiés :

- navigation clavier ;
- lecteur d'écran ;
- contraste ;
- taille et zoom ;
- libellés ;
- erreurs ;
- focus ;
- absence de dépendance exclusive à la couleur ;
- langage simple ;
- conséquences économiques explicites ;
- langues et formats locaux.

Une conformité automatique ne prouve pas à elle seule l'utilisabilité.

## 19. Sécurité

Chaque livraison exécute selon le risque :

- analyse statique ;
- dépendances ;
- secrets ;
- configuration ;
- tests d'autorisation ;
- tests d'entrée et de fichiers ;
- analyse dynamique ;
- revue de menace ;
- tests de session ;
- tests de récupération ;
- tests d'abus et limitation.

Les fonctions Q0/Q1 reçoivent une revue sécurité avant production. Les vulnérabilités connues sont classées, corrigées ou explicitement bloquantes selon la politique.

## 20. Résilience

À fréquence définie, Wasplex exerce :

- restauration PostgreSQL ;
- reconstruction Ledger ;
- reprise outbox/inbox ;
- perte d'un worker ;
- indisponibilité d'un prestataire ;
- mode lecture seule ;
- révocation d'un administrateur compromis ;
- rotation de secrets ;
- récupération d'objet ;
- reprise des Alertes.

Une procédure jamais testée n'est pas considérée comme opérationnelle.

## 21. Code produit avec une IA

Tout code, migration, test, configuration ou texte produit avec une IA est traité comme une proposition non vérifiée.

Il exige :

- responsable humain identifiable ;
- comparaison avec Constitution et ADR ;
- revue du diff ;
- exécution des tests ;
- vérification des dépendances et licences ;
- absence de secrets ou données copiées ;
- contrôle des règles inventées ;
- preuve identique à un code écrit manuellement.

Un test généré par la même IA que le code ne constitue pas une validation indépendante suffisante pour Q0/Q1.

Aucun prompt ne remplace une spécification exécutable.

## 22. Revue de code

Toute modification est revue par une autre personne que son auteur.

Pour Q0/Q1, la revue comprend au minimum :

- propriétaire métier ou architecture ;
- expertise technique concernée ;
- finance, sécurité, données ou opérations selon l'impact.

L'auteur ne fusionne pas seul sa modification critique. Les changements automatiques de dépendances passent les mêmes contrôles adaptés.

## 23. Défauts et sévérité

- **S0** : danger vital, perte ou création de fonds, violation constitutionnelle ou fuite critique.
- **S1** : atteinte grave à un droit, sécurité ou fonctionnement critique.
- **S2** : fonction importante incorrecte avec solution de contournement contrôlée.
- **S3** : défaut ordinaire ou cosmétique.

Aucun S0/S1 connu n'est accepté pour une livraison générale. Une exception ne peut jamais autoriser déséquilibre Ledger, perte de preuve, contournement d'autorisation ou mensonge d'état.

## 24. Tests instables

Un test instable n'est pas relancé jusqu'à devenir vert sans diagnostic.

Il possède propriétaire, incident, cause, échéance et décision. Un test Q0/Q1 instable bloque le chemin concerné jusqu'à stabilisation ou remplacement probant.

La quarantaine de tests ne devient pas un cimetière invisible.

## 25. Gates de CI

Une fusion peut être bloquée par :

- format ou analyse statique ;
- test unitaire/intégration ;
- règle d'architecture ;
- contrat incompatible ;
- migration dangereuse ;
- secret ;
- vulnérabilité bloquante ;
- test d'autorisation ;
- invariant financier ;
- preuve manquante ;
- revue obligatoire absente.

Les gates diffèrent selon les chemins modifiés. Un changement documentaire ne lance pas nécessairement une charge complète ; un changement Ledger lance la matrice renforcée.

## 26. Definition of Done Wasplex

Une fonctionnalité est terminée seulement si :

1. finalité et règles sont documentées ;
2. article, ADR et propriétaire sont identifiés ;
3. états, refus et erreurs sont définis ;
4. autorisations et données sont classées ;
5. configuration et historique sont déterminés ;
6. écritures et source économique sont décrites si nécessaire ;
7. contrats et idempotence sont définis ;
8. migrations sont sûres ;
9. tests proportionnés sont exécutés ;
10. accessibilité et réseau faible sont vérifiés ;
11. métriques, journaux et alertes existent ;
12. sécurité et confidentialité sont revues ;
13. documentation utilisateur et opérationnelle est à jour ;
14. plan de déploiement et retour est prêt ;
15. preuves de CI sont conservées ;
16. aucun défaut bloquant connu ne subsiste ;
17. le responsable accepte explicitement la livraison.

« Le code fonctionne » ne satisfait pas cette définition.

## 27. Exceptions

Une exception qualité est :

- précise ;
- motivée ;
- limitée ;
- datée ;
- assortie d'un propriétaire et d'une correction ;
- approuvée selon le risque ;
- visible dans le registre.

Elle ne peut suspendre un invariant constitutionnel, financier, vital ou de preuve.

## 28. Preuves de livraison

Chaque livraison conserve :

- commit et artefact ;
- dépendances ;
- tests et résultats ;
- migrations ;
- contrats ;
- analyses ;
- approbations ;
- configuration ;
- date et environnement ;
- incidents éventuels ;
- décision de déploiement.

Un artefact construit une fois est promu entre environnements ; il n'est pas recompilé différemment pour la production.

## 29. Conséquences

### Bénéfices

- qualité mesurée par les risques réels ;
- détection des doubles effets et violations de frontières ;
- confiance dans les migrations et reprises ;
- code IA soumis aux mêmes responsabilités ;
- critères communs pour humains et machines.

### Coûts

- temps de conception et de tests ;
- maintenance des fixtures et environnements ;
- exercices périodiques ;
- discipline de preuve et revue.

Ces coûts sont acceptés : Wasplex ne peut redistribuer de la valeur ou relayer une urgence sur la base d'une simple impression de fonctionnement.

## 30. Règle obligatoire

> Une fonctionnalité Wasplex n'est terminée que lorsque ses règles, autorisations, données, écritures, erreurs, états dégradés, migrations, tests, observations et documents sont démontrés.

Tout futur prompt de développement doit inclure les critères de l'ADR-0008 et produire les tests avec le code.