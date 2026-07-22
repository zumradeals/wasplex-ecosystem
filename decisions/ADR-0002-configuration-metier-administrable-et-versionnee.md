# ADR-0002 — Configuration métier administrable et versionnée

**État :** adopté par le fondateur  
**Date :** 22 juillet 2026  
**Décideur architectural :** SIRR, sur mandat du fondateur  
**Dépendances :** Constitution v1.4, AMD-0012, AMD-0013, ADR-0001

## 1. Contexte

Wasplex possède de nombreuses valeurs variables : offres, prix, quotas, plafonds, commissions, critères, délais, catégories, territoires et règles de programme. Les coder en dur rendrait chaque évolution coûteuse et permettrait à des versions différentes de l'application d'appliquer des règles incompatibles.

À l'inverse, rendre toute règle librement modifiable depuis l'administration créerait un pouvoir dangereux : modification du partage constitutionnel, insolvabilité d'une campagne, changement rétroactif d'un abonnement, prélèvement social non consenti ou effacement d'une interdiction.

Wasplex a donc besoin d'un moteur de configuration administrable, mais limité par la Constitution, typé, versionné, daté, approuvé et reconstructible.

## 2. Décision

Wasplex adopte un **registre central de configuration métier versionnée** dans le monolithe modulaire.

Le registre détermine quelles valeurs sont configurables, qui peut les proposer, quelles validations sont exigées, quand elles prennent effet et quelles opérations les utilisent. Il ne contient aucun pouvoir permettant de modifier les invariants constitutionnels.

Chaque opération importante conserve :

- la version de l'objet métier qui l'a créée ;
- l'identifiant de la publication de configuration applicable ;
- les paramètres économiques résolus ou leur empreinte vérifiable ;
- la date métier d'effet ;
- la date technique d'enregistrement ;
- la règle d'arrondi et l'unité employées.

Une règle nouvelle ne réécrit jamais une opération passée.

## 3. Réponse 1 — Quelles règles sont modifiables et avec quelle autorité ?

### 3.1. Niveau C0 — Constitutionnel

Exemples :

- parité 1 WP = 1 FCFA ;
- partage publicitaire constitutionnel 50/50 ;
- donnée personnelle jamais vendue comme produit ;
- droits fondamentaux du niveau gratuit ;
- interdictions publicitaires ;
- couverture minimale du Wallet ;
- absence de rendement garanti ;
- séparation des fonds et protection des gains acquis.

Ces règles ne figurent pas comme champs modifiables dans l'administration. Le logiciel les applique sous forme d'invariants et de validations automatisées. Toute évolution exige un amendement constitutionnel adopté, une migration explicitement approuvée et une nouvelle version applicative.

### 3.2. Niveau C1 — Critique financier ou de droits

Exemples :

- prix et structure d'une offre ;
- taux, frais, plafonds, seuils et formules ;
- répartition interne d'une enveloppe autorisée ;
- règles d'éligibilité ayant un effet économique ;
- apport et contribution du Fonds Social ;
- rémunération et pool des Cartes ;
- délais de disponibilité ou de contestation ;
- conditions de retrait ;
- activation d'une capacité financière par pays.

Ils exigent : auteur nominatif, analyse d'impact, simulation, approbation par deux personnes habilitées dont une indépendante de l'auteur, date d'effet future et journal d'audit. L'auteur ne peut activer sa propre proposition.

Une configuration C1 ne peut être publiée si elle crée une valeur non financée, dépasse une enveloppe, réduit un droit acquis, viole un mandat ou contredit une porte réglementaire.

### 3.3. Niveau C2 — Commercial ou opérationnel sensible

Exemples :

- noms et avantages non fondamentaux des offres ;
- quotas et fréquence des campagnes ;
- catégories, territoires, durées et formats ;
- critères de sponsorisation d'une alerte ;
- niveaux et limites d'un programme ;
- règles de modération par pays ;
- habilitations-types institutionnelles.

Ils exigent un auteur et un approbateur distincts, une prévisualisation de l'impact, une date d'effet et un retour contrôlé.

### 3.4. Niveau C3 — Présentation ou exploitation ordinaire

Exemples :

- texte d'aide ;
- ordre d'affichage ;
- visuels non contractuels ;
- coordonnées d'assistance ;
- activation progressive d'une fonction sans effet économique ou juridique.

Une approbation simple peut suffire. Une modification C3 ne peut changer le sens d'un contrat, masquer un coût, diminuer la sécurité ou altérer une preuve.

### 3.5. Lois et pays

Une matrice nationale ajoute les restrictions juridiques et opérationnelles d'un territoire. Elle ne peut être moins protectrice que la Constitution. Une interdiction nationale prime immédiatement pour l'avenir ; elle ne réécrit pas l'historique.

### 3.6. Ce qui n'est pas une configuration

Les secrets, mots de passe, clés, jetons, documents KYC et données personnelles ne sont jamais stockés dans le registre métier. Les secrets utilisent un gestionnaire séparé.

Un administrateur ne peut saisir du PHP, JavaScript, SQL ou une formule exécutable libre. Les calculs utilisent des modèles de formule pré-approuvés, des paramètres typés et des contraintes d'unité.

## 4. Réponse 2 — Quand une configuration prend-elle effet ?

Toute version suit les états :

> Brouillon → simulé → en revue → approuvé → programmé → actif → remplacé

Elle peut aussi devenir rejetée, annulée avant effet ou suspendue par une mesure d'urgence.

Une publication possède :

- un début d'effet explicite ;
- éventuellement une fin d'effet ;
- un fuseau de référence ;
- un périmètre déterminé ;
- une justification ;
- ses approbateurs ;
- un manifeste immuable et une empreinte.

L'activation est atomique : une opération ne peut jamais lire la moitié d'une ancienne publication et la moitié d'une nouvelle.

La prise d'effet immédiate est réservée aux mesures de protection urgentes et ne permet ni crédit, ni débit, ni confiscation. Elle est limitée, expire automatiquement et exige une revue après incident.

## 5. Résolution des règles

La résolution est déterministe. L'ordre est :

1. invariants constitutionnels ;
2. interdictions ou obligations légales du pays ;
3. publication globale active ;
4. publication nationale active ;
5. version du produit, programme, offre ou campagne ;
6. contrat ou mandat accepté ;
7. paramètres propres à l'opération.

Deux règles de même niveau ne peuvent se chevaucher sur le même périmètre et la même période. Une ambiguïté critique bloque l'opération au lieu d'utiliser une valeur par défaut silencieuse.

Les montants sont stockés en unités entières adaptées à la devise. Les pourcentages utilisent une précision définie, et chaque formule fixe son mode d'arrondi. Les dates sont enregistrées en UTC et présentées dans le fuseau pertinent.

## 6. Réponse 3 — Que deviennent les opérations déjà engagées ?

### Publicité

Une campagne est liée à sa version de prix, ciblage, rémunération, budget et preuve lors de son activation. Une modification matérielle crée une nouvelle version. Chaque événement utilise la version diffusée au moment de son exécution.

### Abonnements

L'offre acceptée gouverne le cycle déjà payé. Un changement de prix ou d'avantage s'applique au prochain renouvellement après information. Une amélioration peut être accordée immédiatement ; une réduction n'est jamais rétroactive.

### Fonds Social

L'adhésion, le mandat, le vœu et l'appel collectif conservent leurs versions respectives. Toute augmentation de charge ou extension de mandat exige un nouveau consentement. Une règle de sécurité peut suspendre un nouvel appel sans annuler les droits déjà acquis.

### Cartes Wasplex

La carte conserve la version contractuelle achetée pendant sa période. Les règles d'un pool sont figées pour la période de calcul annoncée. Une période clôturée n'est jamais recalculée avec une formule nouvelle.

### Wallet

Frais, taux, destination et montant net sont figés lors de la confirmation de l'opération. Une écriture comptabilisée n'est jamais recalculée ; toute correction passe par contre-écriture référencée.

### Alertes et Institutions

Les règles nouvelles s'appliquent aux actions futures. Une mesure urgente peut suspendre une diffusion, révoquer une capacité ou protéger une donnée immédiatement, mais elle conserve la preuve et ne falsifie aucun état antérieur.

### Antifraude et conformité

Une nouvelle règle peut déclencher un examen ou empêcher une nouvelle opération. Elle ne transforme pas automatiquement une opération antérieurement valide en fraude et ne confisque pas seule un gain.

## 7. Réponse 4 — Tester, approuver, publier, surveiller et corriger

### 7.1. Validation avant publication

Le registre impose :

- schéma de type, unité, plage et valeurs autorisées ;
- contraintes entre paramètres ;
- conformité constitutionnelle ;
- solvabilité et conservation des enveloppes ;
- contrôle des dates et chevauchements ;
- simulation sur scénarios normaux, limites et extrêmes ;
- estimation du nombre d'utilisateurs, campagnes, contrats et fonds affectés ;
- comparaison avant/après ;
- tests automatisés du domaine propriétaire.

Une publication C1 ne peut être approuvée sans simulation financière équilibrée.

### 7.2. Prévisualisation

Avant approbation, l'interface montre en langage clair :

- ancienne et nouvelle valeur ;
- périmètre et date ;
- opérations futures affectées ;
- contrats protégés ;
- coût ou engagement maximal ;
- risques et alertes ;
- validations manquantes.

### 7.3. Publication

La publication est effectuée par un service dédié, jamais par une modification directe en base. Elle produit un événement audité, invalide les caches concernés et vérifie que tous les nœuds utilisent la même publication.

En cas d'indisponibilité du registre, les lectures peuvent utiliser la dernière publication valide en cache. Une opération financière dont la règle ne peut être résolue de façon certaine échoue fermée et n'est pas exécutée.

### 7.4. Surveillance

Après activation, Wasplex surveille au minimum :

- dépenses et distributions ;
- couverture et écarts comptables ;
- taux d'erreur et de refus ;
- volumes anormaux ;
- populations exclues ou sur-exposées ;
- échecs de résolution ;
- différence entre simulation et réalité ;
- incidents et signalements.

Toute publication critique possède une période de surveillance renforcée et un responsable nominatif.

### 7.5. Correction et retour

Une version active n'est ni éditée ni supprimée. Une correction ou un retour crée une nouvelle publication. Le retour arrête les nouveaux effets ; il ne rembobine ni contrats, ni campagnes, ni ledger.

Un **interrupteur de sécurité** peut suspendre une capacité précise. Il exige motif, périmètre, durée, identité, journalisation et revue. Il ne peut déplacer de valeur, modifier une formule, effacer une preuve ou contourner une approbation.

## 8. Modèle technique minimal

Le module Configuration contient au minimum :

- **Definition** : clé stable, propriétaire, type, unité, niveau C0-C3, schéma, contraintes et documentation ;
- **Release** : manifeste cohérent de versions destinées à être activées ensemble ;
- **ValueVersion** : valeur immuable, périmètre, validité métier et date d'enregistrement ;
- **Approval** : décision nominative, rôle, motif et date ;
- **Simulation** : entrées, résultats, alertes et empreinte de la version testée ;
- **Activation** : preuve de mise en effet et état de propagation ;
- **Binding** : référence conservée par une offre, campagne, mandat, pool ou opération ;
- **SafetySwitch** : suspension temporaire, ciblée et expirante.

Le registre est administré dans Laravel et stocké dans PostgreSQL. Il appartient au module Administration et Gouvernance, mais chaque définition possède un domaine métier responsable.

## 9. Conséquences

### Bénéfices

- aucune valeur métier importante n'est codée en dur ;
- le passé reste reconstructible ;
- les fondateurs peuvent administrer sans intervention technique ordinaire ;
- une erreur est détectable et contenue ;
- les calculs financiers restent déterministes ;
- les règles nationales peuvent évoluer sans dupliquer l'application.

### Coûts

- davantage de métadonnées et de validations ;
- création d'un simulateur et d'une interface d'approbation ;
- obligation de concevoir chaque règle avant de la rendre configurable ;
- tests de compatibilité entre publications et opérations longues.

Ces coûts sont acceptés parce qu'ils empêchent une configuration simple de devenir une fraude, une dette ou une violation constitutionnelle.

## 10. Règle de conception obligatoire

> Rien n'est configurable simplement parce qu'une valeur peut changer. Une valeur n'entre dans le registre que si son propriétaire, son type, sa portée, ses contraintes, son autorité, sa date d'effet et son traitement historique sont définis.

Tout futur prompt de développement concernant un paramètre Wasplex doit citer cet ADR et identifier son niveau C0, C1, C2 ou C3.