# ADR-0007 — API, webhooks et intégrations externes

**État :** adopté par le fondateur  
**Date :** 22 juillet 2026  
**Décideur architectural :** SIRR, sur mandat du fondateur  
**Dépendances :** Constitution v1.4, ADR-0001 à ADR-0006

## 1. Contexte

Wasplex doit servir son Web, sa PWA, un futur Android, des annonceurs, des institutions affiliées, des partenaires agréés et des prestataires externes.

Ces acteurs ne peuvent accéder ni aux tables PostgreSQL, ni aux modèles Laravel, ni aux contrats internes des modules. Chaque exposition augmente le risque de fuite, fraude, rejeu, dépendance et incompatibilité.

Wasplex doit offrir des interfaces stables et compréhensibles sans construire prématurément une plateforme d'intégration complexe.

## 2. Décision

Wasplex expose des **API REST JSON versionnées**, décrites par OpenAPI et servies par le monolithe Laravel.

Au lancement :

- aucun accès direct à la base ;
- aucun modèle ORM exposé ;
- aucun GraphQL public ;
- aucun API Gateway métier séparé ;
- aucune intégration partenaire partageant les identifiants d'un humain ;
- aucun webhook appliquant directement un effet métier.

Les routes Laravel, le proxy frontal et les politiques ADR-0004 constituent la frontière initiale. Une passerelle séparée ne sera introduite qu'après besoin mesuré.

## 3. Catégories d'interface

### 3.1. Web propriétaire

L'application React/Inertia utilise une session serveur sécurisée et les contrats Web du même monolithe. Elle ne devient pas une API partenaire implicite.

### 3.2. API de première partie

Le futur Android utilise une API dédiée aux applications Wasplex. Il applique les mêmes règles, états et sources de vérité que le Web.

### 3.3. API d'organisation

Des contrats distincts peuvent être ouverts aux annonceurs, institutions ou partenaires agréés. Chaque contrat possède capacités, données, quotas, pays, environnement et cycle de vie propres.

### 3.4. Webhooks entrants

Notifications provenant d'un prestataire ou d'une institution. Elles sont considérées non fiables jusqu'à authentification, validation, déduplication et rapprochement.

### 3.5. Webhooks sortants

Événements Wasplex adressés à une organisation abonnée. Ils informent d'un fait sans donner accès à la base ni garantir que le destinataire l'a traité.

### 3.6. Contrats internes

Les communications entre modules restent celles d'ADR-0005 et ne sont pas transformées en HTTP par principe.

## 4. Style d'API

Les API utilisent :

- ressources et actions métier explicites ;
- JSON UTF-8 ;
- dates avec fuseau ou UTC selon le contrat ;
- montants entiers avec devise ;
- identifiants publics d'ADR-0006 ;
- codes HTTP cohérents ;
- erreurs structurées de type `application/problem+json` ;
- liens ou curseurs de pagination ;
- en-tête de corrélation.

Une réponse ne renvoie jamais par défaut toutes les colonnes d'une table.

Les actions importantes distinguent :

- requête reçue ;
- acceptée ;
- exécutée ;
- confirmée ;
- échouée ;
- inconnue.

Un code HTTP 200 ou 202 ne signifie jamais qu'un paiement externe ou une intervention institutionnelle est confirmé.

## 5. Versionnement

Les versions majeures apparaissent dans le chemin, par exemple `/api/v1/`.

Une évolution compatible peut ajouter un champ facultatif ou un nouvel endpoint. Une suppression, modification de sens, d'unité, d'état ou d'obligation exige une nouvelle version majeure.

Chaque version possède :

- contrat OpenAPI ;
- propriétaire ;
- consommateurs connus ;
- date de lancement ;
- politique de support ;
- métriques d'usage ;
- procédure de dépréciation ;
- date éventuelle de retrait.

Un retrait ordinaire exige information, période de migration et vérification des consommateurs. Une faille grave peut imposer une suspension immédiate documentée.

## 6. Authentification

### 6.1. Web et PWA

Session serveur, cookie sécurisé, HttpOnly, politique SameSite adaptée, protection CSRF, rotation de session et authentification récente pour les actions sensibles.

Les jetons d'accès ne sont pas stockés dans un stockage JavaScript accessible lorsque la session serveur suffit.

### 6.2. Android futur

Flux d'autorisation avec PKCE, navigateur système, jeton d'accès court, renouvellement rotatif et stockage sécurisé du terminal.

Le mot de passe n'est pas remis directement à l'application mobile par un flux propriétaire non contrôlé.

### 6.3. Organisation et intégration technique

Chaque intégration possède une identité technique distincte, rattachée à une organisation et limitée par ADR-0004.

Pour les risques élevés, Wasplex exige une authentification par clé asymétrique, certificat mutuel ou signature équivalente. Une simple clé API permanente ne suffit pas pour déplacer de la valeur, consulter des données sensibles ou émettre une alerte critique.

### 6.4. Révocation

Identifiants, certificats et secrets sont renouvelables, révocables et séparés par environnement. Une révocation coupe sessions, jetons et files futures sans effacer l'audit passé.

## 7. Autorisation

Toute requête externe passe par ADR-0004 :

- sujet ;
- organisation ;
- capacité ;
- finalité ;
- portée ;
- pays ;
- ressource ;
- durée ;
- conditions.

L'API ne fait jamais confiance à un `organization_id`, `user_id` ou territoire fourni par le client sans vérifier son appartenance et sa portée.

Une application Android Wasplex ne reçoit pas davantage de droits que son utilisateur.

## 8. Idempotence

Les commandes créant ou déplaçant une valeur exigent un en-tête `Idempotency-Key`.

La clé est liée à :

- client ;
- organisation ;
- endpoint et version ;
- identité initiatrice ;
- empreinte du corps ;
- période de conservation appropriée.

La répétition identique retourne le résultat déjà connu. La même clé avec un contenu différent retourne un conflit et ne produit aucun effet.

Une réponse réseau perdue ne justifie jamais la création d'une nouvelle intention financière.

## 9. Pagination, filtres et recherche

Les grandes collections utilisent une pagination par curseur stable. Le curseur est opaque, limité à la requête et ne constitue pas une autorisation.

Les filtres, tris, champs et inclusions utilisent des listes autorisées. Aucun paramètre ne devient fragment SQL.

Les limites empêchent export involontaire, scraping ou reconstruction d'une personne. Un export possède son endpoint, sa capacité et son audit propres.

## 10. Limitation et abus

Les limites sont appliquées selon plusieurs dimensions :

- IP ou réseau ;
- compte ;
- appareil ;
- organisation ;
- capacité ;
- endpoint ;
- risque ;
- pays.

Une adresse IP partagée n'est pas une preuve de fraude. Les limites combinent donc plusieurs signaux et offrent une récupération proportionnée.

Les réponses de limitation indiquent un délai de reprise lorsque cela est sûr. Les seuils sont versionnés par ADR-0002.

Les protections couvrent énumération d'identifiants, credential stuffing, création automatisée de comptes, scraping, surcharge de recherche, abus de médias et rafales financières.

## 11. Webhooks entrants

Un webhook entrant exige :

- fournisseur connu ;
- endpoint propre au fournisseur et à l'environnement ;
- signature du corps brut ;
- horodatage ;
- tolérance temporelle bornée ;
- identifiant d'événement ou nonce ;
- protection contre le rejeu ;
- taille et type limités ;
- schéma versionné.

Wasplex vérifie avant tout effet. Il enregistre durablement l'entrée dans une inbox, puis répond rapidement. Le traitement métier est asynchrone et idempotent.

Une signature valide prouve l'émetteur technique, pas la vérité économique. Paiements et retraits restent rapprochés selon ADR-0003.

## 12. Webhooks sortants

Les webhooks Wasplex proviennent de l'outbox ADR-0005.

Chaque livraison possède :

- événement et version ;
- identifiant stable ;
- horodatage ;
- tentative ;
- signature ;
- destination enregistrée ;
- corrélation ;
- données minimales.

Les secrets de destination sont chiffrés et rotatifs. Wasplex bloque les destinations privées ou dangereuses afin de limiter les attaques SSRF.

Les échecs temporaires sont repris avec délai progressif et variation aléatoire. Les échecs persistants suspendent l'abonnement et alertent son propriétaire. Aucun message n'est abandonné silencieusement.

Le destinataire doit dédupliquer. Wasplex ne garantit pas une livraison exactement une fois.

## 13. Rejeu et horloge

Une requête signée sensible contient horodatage, nonce, empreinte et durée de validité. Un nonce déjà vu est refusé.

Une dérive d'horloge raisonnable est tolérée et surveillée. Une tolérance plus large ne devient pas un moyen de rejouer indéfiniment une requête.

## 14. Prestataires de paiement

Chaque prestataire est isolé par l'adaptateur ADR-0003/ADR-0005.

L'API externe ne connaît que le modèle normalisé Wasplex :

- intention ;
- bénéficiaire ;
- montant ;
- frais ;
- statut ;
- référence ;
- preuve ;
- résultat inconnu.

Délais, erreurs et statuts propres au prestataire sont traduits sans changer leur niveau de certitude.

Les reprises automatiques ne concernent que les opérations prouvées idempotentes. Un paiement au résultat inconnu n'est jamais relancé aveuglément.

## 15. Institutions

Les intégrations institutionnelles disposent de contrats séparés par mission.

Une institution ne reçoit pas une API générale de recherche. Chaque accès respecte dossier, capacité, finalité, territoire, période et champs autorisés.

Les alertes nationales critiques utilisent une interface à assurance renforcée :

- institution souveraine habilitée ;
- identité technique forte ;
- double décision nominative en amont ;
- territoire et catégorie ;
- date d'effet et expiration ;
- séquence anti-rejeu ;
- accusé explicite ;
- audit complet.

Une simple clé partenaire ne peut émettre une alerte nationale.

## 16. Annonceurs

L'API annonceur peut permettre :

- campagnes ;
- créations ;
- ciblages autorisés ;
- budgets ;
- états ;
- rapports agrégés ;
- factures.

Elle ne permet jamais :

- export d'identités ;
- récupération de profils individuels ;
- segments réidentifiables ;
- modification de la rémunération après activation ;
- contournement de la modération ;
- activation d'un secteur interdit.

Toute modification matérielle crée une nouvelle version de campagne selon AMD-0013.

## 17. Partenaires des Cartes

Un partenaire agréé reçoit uniquement les capacités nécessaires à une opération :

- vérifier un droit ou statut minimal ;
- proposer une opération ;
- recevoir un résultat ;
- consulter ses propres rapprochements.

Il ne reçoit ni solde complet, ni historique général, ni pouvoir de créditer un Wallet.

Une opération partenaire possède référence, montant, bénéficiaire, consentement ou autorisation, preuve, idempotence et rapprochement.

## 18. SMS, e-mail et notifications

Les prestataires de communication reçoivent le minimum nécessaire. Les modèles de message sont versionnés et les secrets ou données financières détaillées sont exclus lorsque le canal n'est pas suffisamment sûr.

Un statut « remis » du prestataire ne prouve pas que la personne a lu ou compris le message.

Les codes sensibles sont courts dans le temps, limités en tentatives et jamais journalisés en clair.

## 19. Fichiers

Les API de fichier utilisent ADR-0006 :

1. demande d'autorisation de dépôt ;
2. URL signée courte ;
3. dépôt en quarantaine ;
4. contrôle taille, type réel, empreinte et menace ;
5. validation métier ;
6. publication autorisée.

Un fichier n'est jamais servi directement à partir d'un chemin fourni par le client.

## 20. Délais, reprise et disjoncteurs

Chaque dépendance externe possède :

- délai de connexion et de réponse ;
- politique de reprise ;
- opérations rejouables ;
- disjoncteur ;
- limite de concurrence ;
- solution de repli ;
- état de santé ;
- responsable.

Une panne externe ne bloque pas nécessairement tout Wasplex. Le mode dégradé indique précisément la capacité indisponible.

Une réponse tardive après expiration est encore rapprochée ; elle n'est pas ignorée si elle a pu produire un effet.

## 21. Sandbox et environnements

Annonceurs, institutions et partenaires disposent d'un environnement de test séparé :

- données fictives ;
- identifiants distincts ;
- endpoints distincts ;
- aucun mouvement financier réel ;
- aucune alerte publique ;
- limites et scénarios simulés ;
- clés impossibles à utiliser en production.

La promotion en production exige contrat, vérification, tests, capacités et responsables.

## 22. Documentation et portail

Chaque API publiée possède :

- spécification OpenAPI validée ;
- exemples sans données réelles ;
- authentification ;
- capacités ;
- idempotence ;
- erreurs ;
- limites ;
- webhooks ;
- changelog ;
- environnement de test ;
- contact d'incident.

La documentation est générée et vérifiée depuis le contrat. Elle n'est pas un texte divergent du comportement réel.

## 23. Journalisation et observabilité

Wasplex enregistre sans secrets :

- client ;
- sujet et organisation ;
- endpoint et version ;
- capacité ;
- résultat ;
- statut métier ;
- latence ;
- volume ;
- corrélation ;
- idempotence ;
- prestataire ;
- tentative.

Les corps D3/D4 ne sont pas copiés dans les logs. Les diagnostics utilisent références et empreintes.

Les métriques distinguent erreurs Wasplex, refus métier, limitation, panne externe, résultat inconnu et contrat invalide.

## 24. CORS, navigateur et transport

Les origines navigateur sont explicitement autorisées. CORS n'est jamais utilisé comme mécanisme d'autorisation.

Toutes les communications utilisent un transport chiffré. Les en-têtes de sécurité, tailles maximales, méthodes autorisées et politiques de cache sont définis par type de ressource.

Les réponses contenant des données personnelles ou financières ne sont pas mises en cache publiquement.

## 25. Application Android

L'API Android :

- ne duplique aucune règle métier ;
- utilise les contrats versionnés ;
- stocke les secrets dans les mécanismes sécurisés du terminal ;
- permet révocation de l'appareil ;
- met en file uniquement les commandes explicitement rejouables ;
- affiche la fraîcheur des données hors ligne ;
- ne valide aucun gain ou paiement localement.

L'attestation du terminal peut être un signal de risque, jamais une preuve suffisante d'identité ou d'attention.

## 26. Tests obligatoires

Wasplex démontre notamment :

- aucun endpoint sans authentification ou décision publique explicite ;
- isolation entre organisations ;
- idempotence financière ;
- rejet d'une clé réutilisée avec un corps différent ;
- rejet d'un webhook falsifié, expiré ou rejoué ;
- déduplication d'un webhook valide ;
- protection SSRF des destinations ;
- limitation ne condamnant pas automatiquement un réseau partagé ;
- compatibilité de contrat ;
- pagination stable ;
- absence de données interdites dans rapports et logs ;
- rotation et révocation des secrets ;
- disjoncteur et mode dégradé ;
- sandbox incapable d'atteindre la production ;
- résultat inconnu conservé ;
- application Android incapable de créer une vérité locale ;
- tests de charge, contrats, autorisations et menaces sur les endpoints sensibles.

## 27. Conséquences

### Bénéfices

- intégrations limitées et révocables ;
- Android préparé sans dupliquer le métier ;
- partenaires isolés ;
- paiements et webhooks résistants aux répétitions ;
- évolution versionnée ;
- observabilité des dépendances.

### Coûts

- contrats OpenAPI et compatibilité à maintenir ;
- gestion des identités techniques et rotations ;
- sandbox et tests partenaires ;
- supervision des webhooks et dépréciations.

Ces coûts sont acceptés : une intégration rapide ne doit jamais devenir un accès permanent au cœur de Wasplex.

## 28. Règle obligatoire

> Une intégration externe ne reçoit jamais un accès au cœur de Wasplex. Elle reçoit un contrat minimal, versionné, authentifié, autorisé, observable, idempotent lorsque nécessaire et révocable.

Tout futur prompt créant un endpoint, webhook, application mobile ou connecteur doit citer cet ADR.