# P008-A — Note d’implémentation Claude Code

## Alertes, protection institutionnelle et passerelle Santé d’urgence

**Date :** 2026-07-29  
**Décision du fondateur :** l’expérience utilisateur peut être unifiée sous un ensemble « Protection & Santé », mais Alertes et Santé conservent des données, permissions, responsabilités et cycles distincts.  
**Dépôt :** `zumradeals/wasplex-ecosystem`  
**Application :** `apps/platform/`  
**État observé lors de la préparation :** `main` au commit `570646a99aa0de2307146033030e9d007a4e7f7c`. Toujours synchroniser avec le `origin/main` réellement courant avant d’agir.

---

# Mission à remettre à Claude Code

Tu travailles dans le dépôt :

```text
/var/www/html/wasplex-ecosystem
```

La mission est de mettre à jour le Grand Livre puis de construire la première tranche verticale réelle du module Alertes, avec son routage institutionnel, sa diffusion dans le Feed et une frontière d’intégration propre avec un futur domaine Santé.

Cette mission ne demande pas de construire immédiatement un dossier médical complet. Elle demande de créer une vraie application Alertes utilisable et de préparer, sans stockage médical réel, le contrat par lequel une urgence pourra ultérieurement demander une capsule médicale minimale.

Le résultat ne doit être ni une démonstration statique ni une maquette remplie de fausses données. Toute information affichée doit provenir de la base réelle, d’une configuration réelle ou d’un état vide honnête.

## 1. Discipline Git et exécution

1. Lire intégralement `CLAUDE.md` et respecter son ordre de lecture normative.
2. Vérifier le checkout de production et ne pas travailler directement dessus.
3. Exécuter `git fetch origin`.
4. Vérifier que `origin/main` est propre et identifier son commit réel.
5. Créer un worktree dédié, par exemple :

```text
/var/www/worktrees/wasplex-p008a
```

6. Créer depuis `origin/main` une branche :

```text
claude/p008a-alertes-protection
```

7. Utiliser exclusivement `wasplex_test` pour les migrations et tests de développement.
8. Ne jamais modifier la base de production pendant la construction.
9. Ne jamais afficher, copier ou committer un secret.
10. Si des données utilisateur réelles ou des mouvements financiers réels existent désormais en production, arrêter avant toute migration ou tout déploiement et le signaler.

Le présent prompt vaut autorisation de développer le périmètre défini, d’exécuter les contrôles, de committer, pousser, ouvrir une PR, fusionner et déployer selon le mode allégé de `CLAUDE.md`, uniquement si tous les contrôles sont verts et si aucune donnée réelle ou opération financière réelle ne rend obligatoire une revue préalable.

Ne force-pousse jamais. Ne réécris jamais `main`.

## 2. Sources normatives obligatoires

Lire au minimum :

- `CONSTITUTION.md` ;
- `amendments/AMD-0006*` ;
- `amendments/AMD-0007*` ;
- `amendments/AMD-0009*` ;
- `amendments/AMD-0010*` ;
- `amendments/AMD-0012*` ;
- `decisions/` : ADR-0004, ADR-0006, ADR-0007, ADR-0008, ADR-0009, DS-0001, UX-0001, UX-0002 et UX-0003 ;
- `ecosystem/alertes/` ;
- `ecosystem/institutions/` ;
- les documents relatifs aux données sensibles, autorisations, contrats de modules, audit, configuration et propriété des schémas ;
- les modules existants `Identity`, `Governance`, `Configuration`, `Advertising` et `Wallet` ;
- les tests et conventions déjà utilisés par les portails annonceur et administration.

En cas de contradiction normative réelle, arrêter uniquement la partie concernée et produire une question précise. Ne pas réinterpréter silencieusement une règle.

## 3. Décision fondatrice à formaliser

La décision suivante est validée par Koné et doit être enregistrée sans réécrire les amendements antérieurs :

> Wasplex Alertes et le futur Wasplex Santé forment un système intégré de protection de la personne. Ils peuvent partager une expérience utilisateur et des parcours d’urgence, mais conservent des données, permissions, responsabilités, rétentions et journaux distincts. Alertes ne reçoit jamais le dossier médical longitudinal. Il ne peut demander qu’une capsule médicale d’urgence minimale, temporaire et auditée.

### Interprétation obligatoire

- Fusion produit et UX : autorisée.
- Fusion des parcours d’urgence : autorisée.
- Fusion des schémas, dossiers ou permissions : interdite.
- Publicité et ciblage : aucun accès aux données d’Alertes, de SOS ou de Santé.
- Police, gendarmerie, secours et santé ne disposent jamais d’une recherche générale dans « toute la vie » d’une personne.
- Les antécédents judiciaires, médicaux, publicitaires et généalogiques ne sont jamais réunis dans une fiche universelle.
- Le terme métier « Agent » reste interdit. Un témoin de restitution est un participant ou attestateur nominatif d’un dossier, pas un nouvel acteur constitutionnel.

## 4. Mise à jour documentaire demandée

Avant ou avec le code, mettre le Grand Livre à jour.

### 4.1 Amendement

Créer le prochain identifiant AMD réellement libre après inspection du dépôt — `AMD-0016` seulement s’il est encore libre — avec un titre proche de :

```text
Protection, Santé et intégration cloisonnée des urgences
```

Le document doit enregistrer :

- l’intégration produit entre Alertes et Santé ;
- la séparation obligatoire des domaines ;
- la capsule médicale minimale ;
- l’accès d’urgence temporaire et audité ;
- l’interdiction d’un profil universel de la personne ;
- l’interdiction absolue d’utiliser les données médicales, SOS ou de sécurité pour la publicité ;
- le fait que le dossier Santé complet est différé à un chantier distinct.

Ne pas déclarer construit ce qui ne l’est pas.

### 4.2 Clarification Alertes

Créer une nouvelle spécification dans `ecosystem/alertes/` décrivant :

1. le routage institutionnel temps réel ;
2. les abonnements de réception par compétence, catégorie et territoire ;
3. les accusés `transmitted`, `received`, `accepted`, `processing`, `resolved` ;
4. la propagation d’une résolution à tous les destinataires précédemment informés ;
5. l’escalade locale, nationale ou transfrontalière autorisée ;
6. la séparation entre dossier source confidentiel et projection publique ;
7. la restitution protégée avec code unique, double confirmation et témoin facultatif ;
8. l’interdiction de divulguer automatiquement adresse, téléphone, position exacte ou document original.

### 4.3 Frontière Santé

Créer `ecosystem/sante/` s’il n’existe pas et y ajouter une spécification fondatrice limitée à :

- la frontière du domaine Santé ;
- la future capsule médicale d’urgence ;
- le principe de provenance et de vérification médicale ;
- le « bris de glace » contrôlé ;
- l’interdiction de mettre des données médicales dans Alertes ;
- les éléments explicitement différés : carnet de santé longitudinal, laboratoires, prescriptions, médicaments, assurances, dons de sang et coordination réglementée des greffes.

### 4.4 Traçabilité

Mettre à jour les index, références et matrices réellement concernées. Ne pas dupliquer les mêmes règles dans de nombreux fichiers. Préférer des références croisées vers une source normative claire.

## 5. Architecture cible

Le produit peut présenter un espace cohérent nommé « Protection & Santé », mais l’implémentation initiale doit rester un monolithe modulaire.

```text
Interface Protection & Santé
├── Alertes communautaires
├── SOS et sécurité
├── Restitutions
├── Portail institutionnel
└── Passerelle médicale d’urgence
    └── contrat seulement dans P008-A

Schéma alerts
├── dossiers sources
├── événements append-only
├── projections publiques
├── transmissions institutionnelles
├── correspondances
└── restitutions

Schéma health
└── non créé pour des données médicales réelles dans ce lot
```

Le module Alertes est propriétaire de ses tables. Il ne lit pas directement les tables internes d’Identity, Governance, Advertising ou Wallet hors contrats et relations déjà autorisés. Les autorisations passent par l’`AuthorizationGate` existante.

## 6. Première tranche verticale à construire

Construire une tranche utilisable de bout en bout comprenant :

1. création d’une alerte communautaire ;
2. création minimale d’un SOS ;
3. revue et publication d’une projection publique sûre ;
4. routage vers une institution habilitée ;
5. suivi honnête des états de transmission ;
6. affichage public et intégration au Feed ;
7. signalement d’une correspondance ;
8. restitution sécurisée d’un bien ;
9. clôture et propagation de la résolution.

Le périmètre doit rester simple et robuste. Toute amélioration non indispensable est inscrite dans le catalogue de dette technique au lieu de provoquer une cascade de corrections hors mission.

## 7. Module et schéma Alertes

Créer un module proche de :

```text
App\Modules\Alerts
```

Utiliser le schéma PostgreSQL `alerts`, sauf si une décision existante impose déjà un autre nom. Dans ce cas, appliquer le nom normatif existant et le signaler.

Créer un `AlertsServiceProvider` et l’enregistrer selon la convention des autres modules.

### 7.1 Identifiants

- UUID v7 pour les identifiants exposables.
- Aucun identifiant séquentiel interne dans une URL publique.
- Clés étrangères et contraintes PostgreSQL explicites.
- Suppression physique restrictive pour les dossiers possédant événements, transmissions ou preuves.
- La disparition publique d’une alerte est une transition métier ou un retrait de projection, jamais l’effacement silencieux de l’historique.

### 7.2 Tables minimales

Adapter les noms aux conventions réelles du dépôt, mais couvrir les concepts suivants :

#### `alerts.cases`

- identifiant UUID v7 ;
- auteur éventuel par liaison personne-compte ;
- catégorie ;
- nature `community` ou `sos` ;
- niveau de vérification ;
- état courant projeté ;
- pays et territoire ;
- position exacte séparée et jamais publique ;
- description source ;
- dates de création, expiration et clôture ;
- raison de clôture ;
- référence de politique/configuration appliquée.

#### `alerts.case_events`

Journal append-only de la machine d’états :

- dossier ;
- type d’événement ;
- ancien et nouvel état si transition ;
- personne/organisation/canal responsable ;
- horodatage ;
- corrélation et idempotence ;
- métadonnées minimales non sensibles.

Une correction ajoute un événement compensatoire. Elle ne modifie pas l’événement historique.

#### `alerts.publications`

Projection publique distincte :

- dossier source ;
- version publiée ;
- titre et résumé minimisés ;
- zone approximative ;
- champs autorisés par la politique de catégorie ;
- statut de validation ;
- date de publication et d’expiration ;
- auteur de la validation ;
- retrait et motif éventuels.

Ne jamais recopier automatiquement :

- position exacte ;
- téléphone ou adresse ;
- document d’identité original ;
- informations médicales ;
- témoins ;
- preuves de propriété ;
- données confidentielles d’une disparition.

Dans ce lot, ne pas accepter de fichier d’identité ou de preuve réelle tant que le stockage objet compatible S3, l’antivirus et les règles de rétention ne sont pas opérationnels. Utiliser un état d’interface honnête indiquant que l’ajout de justificatif sécurisé sera disponible ultérieurement.

#### `alerts.institution_dispatches`

- dossier ;
- organisation destinataire ;
- territoire et catégorie justifiant le routage ;
- état `created`, `transmitted`, `received`, `accepted`, `processing`, `resolved` ou état latéral autorisé ;
- horodatages probants ;
- utilisateur institutionnel responsable lorsque nécessaire ;
- canal ;
- corrélation ;
- erreur ou impossibilité structurée.

Ne jamais transformer automatiquement `transmitted` en `received` ou `accepted`.

#### `alerts.correspondence_reports`

- dossier ;
- déclarant ;
- description non publique ;
- caractéristiques secrètes ou réponse de vérification ;
- état de revue ;
- rapprochement éventuel ;
- aucune révélation des réponses attendues au déclarant.

#### `alerts.restitutions`

- dossier et correspondance validée ;
- organisation ou lieu neutre de remise si disponible ;
- état ;
- défi/code à usage unique stocké sous forme de condensat, jamais en clair ;
- expiration ;
- confirmation distincte de remise ;
- confirmation distincte de réception ;
- participant/témoin facultatif ;
- contestation éventuelle ;
- journal d’événements associé.

Le témoin ne reçoit aucune capacité générale et ne devient pas un acteur Wasplex supplémentaire.

## 8. Catégories initiales

Le système doit pouvoir représenter au minimum :

### Communautaire

- objet perdu ;
- objet trouvé ;
- document perdu ;
- document trouvé ;
- véhicule volé ou perdu ;
- véhicule retrouvé ;
- personne disparue ;
- personne retrouvée.

### SOS

- incendie ;
- accident ;
- urgence médicale ;
- vol ou braquage en cours.

Les catégories sensibles — mineur, personne vulnérable, disparition, véhicule volé et document d’identité — ne sont jamais publiées automatiquement. Elles exigent une revue renforcée.

Les catégories, durées, preuves, rétentions, territoires, fréquences et règles de diffusion variables utilisent le registre Configuration existant. Les invariants de sécurité restent dans le code et les décisions C0.

## 9. Machine d’états

Implémenter côté serveur une machine d’états explicite et testée.

### Dossier communautaire

Exemple minimal :

```text
draft
→ submitted
→ under_review
→ published | restricted | rejected
→ matched
→ restitution_scheduled
→ resolved | disputed | expired | withdrawn
```

### SOS

Appliquer les états déjà adoptés :

```text
created
→ transmitted
→ received
→ accepted
→ processing
→ resolved
```

Avec les états latéraux documentés par AMD-0007.

Le frontend affiche exactement l’état prouvé. Si aucune institution n’est configurée ou n’a accusé réception, afficher :

```text
Demande enregistrée — transmission institutionnelle non confirmée.
```

Ne jamais afficher « les secours arrivent » sans événement institutionnel probant.

## 10. Routage institutionnel

Réutiliser `Identity`, les organisations, appartenances et le moteur d’autorisation existants.

Chaque destinataire est déterminé par :

- catégorie ;
- compétence ;
- territoire ;
- état d’affiliation ;
- capacité active ;
- période de validité.

Une même base PostgreSQL n’autorise jamais une institution à explorer toutes les tables. Le portail utilise des projections et requêtes du module Alertes, toujours filtrées par l’`AuthorizationGate`.

Prévoir des capacités atomiques proches de :

```text
alert.case.submit
alert.case.view_self
alert.case.review
alert.case.publish
alert.case.receive
alert.case.acknowledge
alert.case.accept
alert.case.process
alert.case.transfer
alert.case.resolve
alert.match.propose
alert.match.validate
alert.return.verify
```

Avant de les créer, vérifier les conventions du catalogue Governance. Les noms commerciaux, « police », « gendarmerie », « premium » ou « admin » ne deviennent jamais des clés d’autorisation.

Les capacités nationales critiques peuvent être documentées mais aucune route réelle d’émission nationale ne doit être activée dans P008-A. Cette opération exige une mission dédiée, MFA forte et double approbation nominative.

## 11. Transmission et quasi-temps réel

Ne pas ajouter Redis, WebSocket, microservice ou nouvelle infrastructure sans nécessité démontrée.

Pour le pilote :

- transaction locale ;
- outbox PostgreSQL transactionnelle ;
- worker Laravel ou commande idempotente ;
- rafraîchissement/polling raisonnable dans le portail institutionnel ;
- accusés enregistrés ;
- rejeu sans duplication ;
- statut `unknown` ou `impossible` en cas d’incertitude.

Le polling est une solution transitoire acceptable. Le cataloguer comme dette technique si une diffusion push institutionnelle devient nécessaire.

## 12. SOS sans authentification

Un SOS minimal peut être créé sans compte, conformément aux textes existants.

Exiger seulement les informations utiles :

- catégorie ;
- position si autorisée ;
- description courte adaptée à la catégorie ;
- numéro de rappel facultatif ;
- langue ;
- idempotency key ;
- consentement minimal et avertissement de sécurité.

Appliquer :

- rate limiting ;
- validation serveur ;
- marquage `unverified` ;
- aucune publication publique automatique ;
- aucune promesse de transmission ;
- journalisation proportionnée ;
- aucun profil publicitaire créé depuis ce SOS.

Si les numéros officiels d’urgence sont configurés, les afficher comme solution directe. Ne pas coder en dur des numéros nationaux dans le composant React.

## 13. Projection publique et confidentialité

Construire un service de politique de publication par catégorie.

La politique définit les champs publiables ; l’utilisateur peut réduire la visibilité mais ne peut jamais l’élargir.

Pour un document perdu, publier une fiche Wasplex dérivée, jamais l’image brute de la pièce. Si une future image est autorisée, elle devra passer par stockage sécurisé, contrôle, version publiée séparée et validation humaine.

Pour une disparition, protéger contre la traque, les conflits de garde et les violences. P008-A peut accepter le dossier et le maintenir `under_review`, mais ne doit pas prétendre avoir automatisé cette décision sensible.

## 14. Interface utilisateur mobile-first

Activer une destination réelle `/alerts`.

Construire :

- onglet « Alertes » du Feed fonctionnel ;
- destination « Alertes » de la navigation mobile ;
- liste des alertes publiées, récentes et territorialement pertinentes ;
- onglet « Mes déclarations » ;
- création d’alerte par catégorie ;
- formulaire SOS très court ;
- détail avec statut, historique public sûr et bouton de correspondance ;
- suivi personnel avec états probants ;
- parcours de restitution lorsque le dossier est éligible.

États obligatoires :

- chargement ;
- vide ;
- erreur ;
- hors ligne ;
- non vérifié ;
- en revue ;
- transmission non confirmée ;
- résolu ;
- expiré.

Utiliser les jetons DS-0001, le français clair, l’accessibilité et les appareils modestes. Ne pas reproduire aveuglément les anciennes captures : elles donnent une intention, pas une architecture ni une preuve de fonctionnement.

## 15. Intégration au Feed

Le Feed doit pouvoir recevoir des éléments typés :

```text
advertisement
community_alert
official_notice
useful_advice
```

Dans P008-A, brancher au minimum `community_alert` publié et validé.

Garanties :

- une alerte n’est jamais comptée comme vue publicitaire ;
- elle ne consomme aucun quota publicitaire ;
- elle ne déclenche aucun événement d’attention qualifiée ;
- elle ne crédite aucun WP ;
- quitter une publicité pour une alerte ne marque jamais frauduleusement la publicité comme terminée ;
- l’alerte résolue cesse d’être injectée ;
- une projection retirée n’est plus affichée.

La cadence d’insertion entre publicités est configurable et versionnée. Les valeurs « toutes les 5 ou 10 publicités » sont des exemples, pas des constantes constitutionnelles.

Ajouter une surface compacte de type rail/carrousel :

- verticale sur les grands écrans si cela reste cohérent avec le Feed ;
- horizontale ou empilée sur mobile ;
- sans cacher les contrôles publicitaires essentiels.

La sponsorisation d’alertes reste visible comme fonctionnalité future si nécessaire, mais aucun paiement, tarif, boost ou Wallet ne doit être simulé dans P008-A.

## 16. Portail institutionnel desktop-first

Créer un espace institutionnel distinct du portail personnel Wasplex.

Il doit afficher :

- organisation et utilisateur connecté ;
- capacités actives ;
- territoire ;
- échéance d’habilitation ;
- nouvelles transmissions ;
- dossiers reçus, acceptés, en traitement, transférés et résolus ;
- recherche strictement autorisée par dossier, catégorie, territoire et période ;
- actions disponibles selon la capacité exacte.

Ne jamais utiliser « Espace agents ». Utiliser « Portail des institutions Wasplex » ou « Espace institutionnel ».

Chaque consultation sensible est auditée. Une institution ne peut pas accéder au dossier source complet si sa projection institutionnelle n’autorise pas les champs correspondants.

## 17. Administration Wasplex

Remplacer l’état « bientôt disponible » de la destination « Alertes et institutions » uniquement lorsque le backend réel existe.

Créer un premier écran personnel Wasplex permettant, selon les capacités :

- revue des alertes communautaires ;
- validation ou restriction de publication ;
- supervision des SOS non routés ou en échec ;
- contrôle des transmissions ;
- validation de correspondance ;
- suivi des contestations de restitution.

Aucun rôle global `admin`. Chaque section et chaque action utilisent la capacité correspondante.

## 18. Passerelle Santé

Dans P008-A, ne créer aucun dossier médical longitudinal et ne collecter aucune donnée médicale réelle.

Créer seulement un contrat applicatif dans une frontière clairement séparée, par exemple :

```text
EmergencyHealthSnapshotProvider
EmergencyHealthSnapshot
EmergencyHealthSnapshotUnavailable
```

Le provider par défaut retourne un résultat indisponible explicite. Alertes doit fonctionner normalement sans Santé.

Le futur contrat pourra fournir uniquement :

- groupe sanguin vérifié ;
- allergies critiques vérifiées ;
- pathologies critiques pertinentes ;
- traitements vitaux ;
- contact d’urgence ;
- provenance, niveau de vérification et date de fraîcheur.

Il ne fournira jamais :

- dossier médical complet ;
- historique judiciaire ;
- profil publicitaire ;
- généalogie ;
- informations sans pertinence immédiate pour les secours.

Une lecture future exigera une capacité critique, une finalité d’urgence, une durée courte, une justification, un audit et une notification/revue ultérieure. Cette capacité peut être documentée, mais ne doit pas être auto-attribuée ni activée artificiellement dans P008-A.

## 19. Éléments explicitement différés

Ne pas construire dans ce lot :

- carnet médical longitudinal ;
- comptes médicaux professionnels complets ;
- laboratoire, ordonnance ou médicament ;
- assurance santé ;
- marché ou rémunération du sang ou des organes ;
- émission réelle d’alerte nationale critique ;
- Cell Broadcast ;
- SMS opérateur réel ;
- sponsorisation payante ;
- récompense financière ;
- upload de document d’identité ou preuve réelle ;
- moteur biométrique ou reconnaissance faciale ;
- recherche transversale dans la vie d’une personne ;
- application Android native.

Créer ou compléter une seule entrée de dette technique structurée avec ces éléments, leur risque, la mesure temporaire et la condition de reprise. Ne pas ouvrir une dette distincte pour chaque détail.

## 20. Tests obligatoires

Conserver tous les tests existants verts et ajouter au minimum les scénarios suivants.

### Domaine

- création transactionnelle d’un dossier et de son premier événement ;
- refus des transitions invalides ;
- idempotence de la soumission ;
- journal append-only ;
- retrait public sans destruction du dossier source ;
- expiration.

### Confidentialité

- projection publique sans téléphone, adresse, coordonnées exactes, médical, témoin ou preuve ;
- document complet impossible à publier ;
- dossier de disparition non auto-publié ;
- institution sans capacité refusée ;
- institution hors territoire refusée ;
- appartenance suspendue ou organisation non active refusée ;
- aucune lecture croisée entre deux organisations.

### SOS

- création anonyme minimale ;
- état initial `created` ;
- absence de destinataire : aucune fausse transmission ;
- `transmitted` ne vaut ni `received` ni `accepted` ;
- résolution seulement par transition autorisée ;
- rate limiting et validation.

### Routage

- sélection par compétence, catégorie et territoire ;
- doublon de dispatch refusé ou idempotent ;
- résolution propagée aux destinataires ;
- erreur de transport conservée sans faux succès.

### Restitution

- correspondance candidate non décisive ;
- code unique expirant et stocké sous forme de condensat ;
- rejeu du code refusé ;
- remise et réception distinctes ;
- témoin facultatif sans capacité implicite ;
- aucune divulgation automatique d’adresse.

### Feed

- alerte publiée injectée ;
- alerte non publiée ou résolue absente ;
- aucun QualifiedEvent, quota ou WP produit par une alerte ;
- abandon propre de la publicité interrompue ;
- compteur Wallet inchangé.

### Santé

- provider indisponible par défaut ;
- Alertes fonctionne sans Santé ;
- aucune table ou donnée médicale créée accidentellement ;
- aucune donnée Santé transmise à Advertising.

### HTTP et UI

- 401/403 structurés selon les conventions existantes ;
- URLs publiques en UUID ;
- écran mobile Alertes ;
- portail institutionnel desktop ;
- états vide, erreur et transmission non confirmée ;
- aucune donnée simulée.

## 21. Contrôles de qualité

Exécuter au minimum :

```text
php artisan migrate:fresh --env=testing
php artisan test --env=testing
composer validate --strict
composer lint:check
composer types:check
npm run lint:check
npm run format:check
npm run types:check
php artisan wayfinder:generate --with-form
npm run build
git diff --check
```

Vérifier explicitement que `current_database()` vaut `wasplex_test` avant les migrations destructives de test.

Effectuer un parcours navigateur réel :

1. création d’une alerte ;
2. revue et publication ;
3. apparition dans l’onglet Alertes ;
4. apparition dans le Feed sans WP ;
5. réception par une institution habilitée ;
6. accusé et changement d’état ;
7. résolution ;
8. disparition du Feed ;
9. affichage de l’historique sûr.

Produire des captures mobile et desktop.

## 22. Critères d’acceptation

Le lot est acceptable uniquement si :

- le Grand Livre exprime clairement la fusion produit et la séparation des domaines ;
- le module Alertes existe réellement ;
- un utilisateur peut déclarer et suivre une alerte ;
- un SOS ne prétend jamais être transmis sans preuve ;
- une institution habilitée voit uniquement ses dossiers autorisés ;
- une projection publique ne révèle aucun champ interdit ;
- une alerte réelle peut apparaître dans le Feed sans effet publicitaire ou financier ;
- une restitution peut être confirmée sans dévoiler automatiquement les coordonnées privées ;
- Santé est représenté par un contrat indisponible, pas par de fausses données médicales ;
- tous les tests et contrôles sont verts ;
- aucun secret, donnée réelle, faux chiffre, faux partenaire ou faux accusé institutionnel n’est introduit.

## 23. Politique de simplicité

Nous recherchons une première version propre et cohérente, pas la perfection absolue.

Si un défaut non bloquant ou une amélioration future est découvert :

1. ne pas élargir automatiquement la mission ;
2. vérifier qu’il ne compromet ni sécurité, argent, confidentialité, intégrité ni vérité affichée ;
3. l’inscrire dans le catalogue de dette technique avec risque et porte de reprise ;
4. continuer le lot principal.

Corriger immédiatement uniquement :

- fuite de donnée ;
- autorisation contournable ;
- écriture financière non fondée ;
- transition mensongère ;
- perte ou corruption de données ;
- migration dangereuse ;
- régression empêchant le parcours principal.

## 24. Compte rendu final obligatoire

Répondre avec :

```text
P008-A — DOSSIER FINAL ALERTES & PROTECTION

Branche :
Commit de base :
Commit(s) créé(s) :
PR :
Fusion :
Déploiement :

Documents adoptés ou créés :
Architecture réalisée :
Schéma et migrations :
Capacités :
Parcours utilisateur :
Portail institutionnel :
Administration :
Intégration Feed :
Passerelle Santé :

Tests :
Contrôles qualité :
Parcours navigateur :
Captures :

Données réelles détectées :
Secrets :
État Git :
État production :

Dettes techniques cataloguées :
Risques résiduels :
Éléments différés :
Anomalies :
```

Ne pas présenter comme réalisé un élément seulement documenté ou différé.

