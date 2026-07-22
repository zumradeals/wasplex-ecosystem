# ADR-0004 — Autorisations par capacités, finalités et portées

**État :** adopté par le fondateur  
**Date :** 22 juillet 2026  
**Décideur architectural :** SIRR, sur mandat du fondateur  
**Dépendances :** Constitution v1.4, AMD-0006, AMD-0009, AMD-0010, AMD-0012, ADR-0001, ADR-0002, ADR-0003

## 1. Contexte

Wasplex réunit utilisateurs, annonceurs, institutions affiliées et équipes internes. Une même personne peut appartenir à une organisation, exercer plusieurs fonctions ou recevoir temporairement une responsabilité particulière.

Un contrôle fondé uniquement sur des rôles tels que « administrateur », « finance », « police » ou « annonceur » accorderait des droits trop larges. Il ne permettrait pas d'exprimer le territoire, le dossier, la finalité, les montants, la durée, la confidentialité ou la séparation des tâches.

Wasplex doit donc autoriser une action précise, non accorder un accès général à une catégorie de personne.

## 2. Décision

Wasplex adopte un modèle hybride combinant :

- rôles comme ensembles de capacités proposées ;
- capacités atomiques comme droits d'action ;
- attributs du sujet, de l'organisation, de la ressource et du contexte ;
- finalité obligatoire pour les accès sensibles ;
- portée explicite ;
- conditions et durée ;
- approbations lorsque le risque l'exige.

Une décision d'accès est accordée seulement si toutes les dimensions nécessaires sont valides.

> Une identité n'accède jamais à une donnée simplement parce qu'elle possède un rôle. Elle doit posséder la capacité appropriée, pour une finalité autorisée, dans une portée déterminée, pendant une durée valide et dans les conditions exigées.

Le refus est la règle par défaut.

## 3. Sujets autorisables

### 3.1. Personne

Tout accès humain est nominatif. Le compte partagé est interdit. La personne possède son propre niveau d'identité, ses facteurs d'authentification, ses appartenances et ses délégations.

### 3.2. Organisation

Une organisation Wasplex, annonceur ou institution affiliée possède des capacités contractuelles. Celles-ci ne deviennent effectives qu'à travers un représentant nominatif autorisé.

Une organisation n'agit jamais anonymement.

### 3.3. Compte technique

Un compte technique représente un service ou une intégration, non une personne. Il ne permet aucune connexion interactive, utilise des secrets ou certificats renouvelables, possède des capacités minimales et une expiration ou revue obligatoire.

Une clé technique n'hérite jamais des droits d'un administrateur humain.

## 4. Acteurs et qualifications

ADR-0004 ne crée aucun nouvel acteur constitutionnel. Les partenaires agréés, prestataires, auditeurs, membres d'équipe et représentants sont des qualifications ou relations rattachées à Wasplex, un annonceur ou une institution affiliée.

Le concept générique « Agent » demeure exclu. Pour une institution, le terme officiel est **utilisateur institutionnel habilité**.

## 5. Modèle d'une autorisation

Une attribution contient au minimum :

- **sujet** : personne, organisation ou compte technique ;
- **capacité** : action atomique autorisée ;
- **ressource** : type d'objet visé ;
- **portée** : objets accessibles ;
- **finalité** : raison autorisée ;
- **conditions** : authentification, appareil, seuil, état ou approbation ;
- **début et expiration** ;
- **source** : contrat, fonction, décision, délégation ou urgence ;
- **auteur et approbateur** ;
- **version de politique** ;
- **statut** : proposée, active, suspendue, expirée ou révoquée.

Une capacité est nommée par domaine et action, par exemple :

- `campaign.create`
- `campaign.approve`
- `wallet.withdrawal.review`
- `wallet.adjustment.propose`
- `alert.case.read`
- `institution.capability.grant`
- `configuration.c1.approve`

Un nom de capacité ne vaut pas autorisation sans ses autres dimensions.

## 6. Rôles

Un rôle est un modèle versionné regroupant des capacités et conditions ordinaires. Il simplifie l'attribution, mais ne constitue pas l'autorité finale.

Les rôles sont :

- explicites et documentés ;
- propres à une organisation ou un domaine ;
- composables sans créer de privilège implicite ;
- versionnés par ADR-0002 ;
- périodiquement révisés.

La modification d'un rôle n'étend pas silencieusement toutes les attributions existantes. L'impact est simulé et les droits sensibles exigent reconfirmation.

Aucun rôle « super administrateur » permanent n'existe. Le fondateur lui-même ne contourne ni ledger, ni preuve, ni séparation des tâches.

## 7. Portées

Une portée peut limiter une capacité à :

- soi-même ;
- une organisation ;
- un ou plusieurs dossiers ;
- une ressource précise ;
- un pays, territoire ou zone opérationnelle ;
- une campagne, programme, pool ou compte ;
- un intervalle de montants ;
- certains champs ;
- une période ;
- un environnement technique.

La portée la plus étroite nécessaire est appliquée. Une capacité nationale ne devient pas mondiale par défaut.

Les recherches transversales institutionnelles exigent catégorie, territoire, période, finalité et référence de dossier ou motif probant. Elles ne constituent jamais un accès à « toute la base ».

## 8. Finalités

Pour toute consultation ou export sensible, l'appelant choisit une finalité autorisée et, lorsque requis, fournit une référence de dossier.

Exemples :

- exécution d'un contrat ;
- assistance demandée ;
- traitement d'une alerte ;
- obligation réglementaire ;
- enquête antifraude ;
- rapprochement financier ;
- audit ;
- urgence vitale.

La finalité est contrôlée, enregistrée et contestable. Un champ textuel libre ne suffit pas lorsque le risque exige une preuve.

Une donnée obtenue pour une finalité ne peut être réutilisée pour publicité, prospection ou curiosité interne.

## 9. Conditions contextuelles

L'autorisation peut exiger :

- authentification forte récente ;
- appareil ou réseau conforme ;
- compte et organisation actifs ;
- KYC ou habilitation suffisante ;
- formation ou certification valide ;
- approbation secondaire ;
- absence de conflit d'intérêts ;
- seuil financier ;
- horaire ou lieu autorisé ;
- consentement ou base de traitement applicable ;
- non-participation préalable à l'acte contrôlé.

Un contexte de risque peut imposer une authentification renforcée, un masquage, une approbation ou un refus. Il ne crée pas seul une sanction.

## 10. Décisions possibles

Le moteur d'autorisation retourne une décision explicite :

- **autorisé** ;
- **refusé** ;
- **authentification renforcée requise** ;
- **approbation requise** ;
- **autorisé avec champs masqués** ;
- **autorisé en lecture seule**.

Il retourne également la politique, le motif technique sûr, les obligations et la durée de validité. L'interface ne devine jamais une autorisation à partir de l'affichage d'un bouton.

## 11. Application des contrôles

Chaque requête HTTP, commande métier, tâche asynchrone, export, recherche et action administrative est contrôlé côté serveur au moment de l'exécution.

Le masquage d'un écran n'est pas une mesure de sécurité.

Dans le monolithe Laravel :

- les modules déclarent leurs capacités et politiques ;
- un service central évalue le contexte commun ;
- le module propriétaire de la ressource conserve la décision métier finale ;
- les contrôleurs, commandes et workers utilisent les mêmes politiques ;
- PostgreSQL ajoute des contraintes et permissions de défense en profondeur pour les actifs critiques.

Une tâche différée transporte l'identité initiatrice, la finalité, la portée et la politique applicable. Elle ne s'exécute pas avec un pouvoir système illimité.

## 12. Séparation des tâches

Une matrice interdit notamment qu'une même personne soit seule à :

- proposer et approuver une configuration C1 ;
- initier et approuver un ajustement Wallet ;
- créer et valider une institution ;
- émettre et confirmer une alerte nationale ;
- créer et approuver une campagne sensible ;
- initier et rapprocher son propre paiement ;
- accorder et auditer son propre accès ;
- exporter et approuver seule un export massif sensible.

La séparation est vérifiée au moment de l'action, pas uniquement lors de l'attribution du rôle.

## 13. Accès par catégorie

### 13.1. Utilisateur

Accède à ses données, consentements, opérations, alertes et droits selon leur état. L'accès à ses propres données ne permet pas de contourner les restrictions protégeant autrui, une enquête ou un secret légalement protégé.

### 13.2. Annonceur

Les représentants accèdent aux campagnes, budgets et résultats de leur organisation selon leur fonction. Ils ne voient aucune identité d'audience. Les résultats restent agrégés et protégés contre la réidentification.

### 13.3. Institution affiliée

Chaque représentant est nominatif et rattaché à une institution vérifiée. Ses capacités sont limitées par mission, territoire, catégorie, dossier et durée.

Une institution ne peut accéder au Wallet, au profil publicitaire ou aux données sociales sans fondement distinct et capacité explicite.

### 13.4. Wasplex

Les équipes internes reçoivent les capacités nécessaires à leur fonction. Support, finance, conformité, modération, sécurité, administration et audit restent séparés.

L'appartenance à Wasplex n'accorde aucun droit général de consultation.

## 14. Délégation

Une délégation indique déléguant, bénéficiaire, capacités, portée, durée, motif et possibilité ou non de sous-déléguer.

Elle ne peut dépasser les droits du déléguant, prolonger leur expiration ou contourner une approbation. La sous-délégation est interdite par défaut.

Le départ, la suspension ou la perte de fonction d'une personne révoque immédiatement ses délégations dérivées.

## 15. Cycle de vie

Le cycle est :

> demandée → examinée → approuvée → active → revue → expirée ou révoquée

Les droits élevés sont temporaires ou soumis à revue périodique. Les accès inutilisés, orphelins, conflictuels ou liés à une relation terminée sont automatiquement signalés puis suspendus selon le risque.

La révocation prend effet rapidement dans les sessions, caches, files et intégrations. Une session ouverte ne conserve pas un droit retiré.

## 16. Bris de glace

Le bris de glace est réservé au confinement d'un dommage imminent lorsqu'aucun parcours normal n'est suffisamment rapide.

Il exige :

- identité renforcée ;
- motif structuré ;
- capacité et portée minimales ;
- durée courte et expiration automatique ;
- notification immédiate aux responsables ;
- journalisation renforcée ;
- revue indépendante après usage.

Il ne permet jamais de modifier une écriture Ledger, changer un invariant constitutionnel, effacer une preuve ou émettre seul une alerte nationale.

## 17. Assistance et représentation

Le support ne se connecte jamais comme l'utilisateur en utilisant ou réinitialisant secrètement son mot de passe.

Une session d'assistance éventuelle est :

- demandée ou justifiée ;
- visible ;
- limitée ;
- temporaire ;
- enregistrée ;
- interdite pour les secrets, facteurs d'authentification et actions financières sauf procédure séparée.

Toute action réalisée au nom d'une personne distingue l'initiateur, le représentant et le bénéficiaire.

## 18. Accès aux champs, exports et recherches

Une capacité de lecture d'un dossier n'autorise pas automatiquement chaque champ.

Les documents KYC, données médicales, coordonnées, localisation précise, informations de mineurs, données d'urgence et secrets financiers utilisent des capacités spécifiques et un masquage par défaut.

Les exports sont plus risqués que la consultation. Ils exigent capacité distincte, finalité, volume, format, durée de disponibilité, chiffrement, filigrane ou empreinte, approbation et preuve de suppression lorsque nécessaire.

Les recherches sensibles sont journalisées même lorsqu'elles ne retournent aucun résultat.

## 19. Journal d'autorisation

Wasplex conserve au minimum :

- sujet réel et organisation ;
- capacité demandée ;
- ressource et portée ;
- finalité ;
- décision et politique ;
- facteurs ou approbations ;
- champs rendus ou masqués ;
- date, contexte et corrélation ;
- identité du délégant ou du compte technique ;
- bris de glace éventuel.

Le journal prouve l'accès sans recopier inutilement le contenu consulté. Il suit les protections immuables d'ADR-0001 et AMD-0012.

## 20. Consentement, propriété et autorisation

Le consentement, la base de traitement, la propriété fonctionnelle de la donnée et l'autorisation technique sont distincts.

Une permission technique ne crée ni consentement ni base légale. Un consentement ne donne pas à n'importe quel employé le droit de consulter. Le module propriétaire vérifie les deux lorsque nécessaire.

## 21. Cache et disponibilité

Une décision peut être mise en cache brièvement avec sujet, politique, ressource, contexte et expiration.

Les révocations et incidents critiques invalident les caches. Si l'autorité d'accès est indisponible :

- les nouvelles actions sensibles échouent fermées ;
- les consultations essentielles peuvent utiliser une autorisation locale courte expressément prévue ;
- aucun droit élevé n'est créé par défaut.

## 22. Modèle technique minimal

- **CapabilityDefinition** : capacité, domaine, action, risque et obligations.
- **RoleTemplate** : ensemble versionné de capacités proposées.
- **Subject** : identité humaine, organisation ou compte technique.
- **Membership** : relation nominative entre personne et organisation.
- **Grant** : capacité, portée, finalité, conditions, validité et source.
- **Delegation** : transmission limitée d'un grant.
- **AccessRequest** : demande et justification.
- **Approval** : décision nominative.
- **PolicyVersion** : logique active versionnée par ADR-0002.
- **AuthorizationDecision** : résultat explicable.
- **BreakGlassSession** : pouvoir exceptionnel limité.
- **AccessAuditEvent** : preuve de décision et d'utilisation.

## 23. Tests obligatoires

Wasplex démontre notamment :

- refus par défaut ;
- isolement entre organisations ;
- impossibilité d'élargir une délégation ;
- expiration et révocation effectives sur session et worker ;
- séparation des tâches ;
- masquage des champs sensibles ;
- absence d'identités dans les rapports annonceurs ;
- restriction territoriale institutionnelle ;
- journalisation d'une recherche sans résultat ;
- impossibilité d'auto-approuver un accès critique ;
- bris de glace expirant ;
- impossibilité de modifier Ledger ou Constitution par bris de glace ;
- comportement fermé lors d'une panne d'autorisation ;
- égalité des contrôles entre Web, PWA, desktop, API et Android futur.

## 24. Conséquences

### Bénéfices

- pouvoirs compréhensibles et limités ;
- moindre impact d'un compte compromis ;
- institutions et annonceurs isolés ;
- accès sensibles finalisés et auditables ;
- révocation précise sans bloquer toute une organisation ;
- aucune dépendance à un rôle tout-puissant.

### Coûts

- politiques plus riches qu'un simple RBAC ;
- interface de demande et revue ;
- tests systématiques par domaine ;
- gestion des expirations, délégations et caches.

Ces coûts sont acceptés : Wasplex ne peut protéger les populations avec des accès internes incontrôlés.

## 25. Règle obligatoire

> Aucun rôle ne suffit à autoriser une action sensible. Toute décision doit être liée à une capacité, une finalité, une portée, une durée, des conditions et une identité responsable.

Tout futur prompt concernant administration, institution, annonceur, support, finance, audit, export, recherche ou compte technique doit citer cet ADR.