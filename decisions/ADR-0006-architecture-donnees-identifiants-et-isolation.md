# ADR-0006 — Architecture des données, identifiants et isolation

**État :** adopté par le fondateur  
**Date :** 22 juillet 2026  
**Décideur architectural :** SIRR, sur mandat du fondateur  
**Dépendances :** Constitution v1.4, AMD-0009, AMD-0010, AMD-0011, AMD-0012, ADR-0001 à ADR-0005

## 1. Contexte

Wasplex doit représenter personnes, organisations, campagnes, consentements, écritures financières, vœux sociaux, alertes, institutions et Cartes dans plusieurs pays.

Une base unique sans frontières faciliterait les croisements abusifs, les suppressions en cascade et les dépendances entre modules. Une base physique par module augmenterait prématurément les coûts d'exploitation, les sauvegardes et les transactions distribuées.

Wasplex doit conserver la simplicité d'un PostgreSQL central tout en matérialisant la propriété, la confidentialité, le territoire et le cycle de vie de chaque donnée.

## 2. Décision

Au lancement, Wasplex utilise **une instance PostgreSQL principale par environnement**, organisée en schémas fonctionnels appartenant aux modules du monolithe.

Chaque donnée possède :

- un propriétaire fonctionnel unique ;
- une classification ;
- une finalité ;
- un périmètre d'organisation, de pays ou d'entité lorsqu'il existe ;
- une politique de conservation ;
- une source ;
- des dates métier et techniques ;
- des contraintes d'intégrité ;
- une règle d'accès ;
- une règle de suppression, anonymisation ou conservation.

Une instance commune ne crée aucun droit de lecture ou d'écriture transversal.

## 3. Schémas fonctionnels

Les schémas initiaux sont :

- `identity` : personnes, comptes, coordonnées, appareils, authentification et KYC ;
- `privacy` : consentements, finalités, préférences, profil déclaratif et données déduites ;
- `advertising` : annonceurs, campagnes, audiences, budgets, diffusions et preuves d'attention ;
- `subscriptions` : offres, souscriptions, cycles, droits et quotas ;
- `ledger` : comptes, transactions, postings, paiements, couverture et rapprochement ;
- `social_fund` : programmes, adhésions, mandats, vœux, appels, apports et réalisations ;
- `alerts` : dossiers, SOS, correspondances, diffusions, restitutions et récompenses ;
- `institutions` : organisations affiliées, représentants, capacités et actions ;
- `cards` : cartes, produits, pools, partenaires agréés et opérations ;
- `governance` : configurations, autorisations, approbations et preuves administratives ;
- `integration` : outbox, inbox, sagas, adaptateurs, webhooks et quarantaines ;
- `reporting` : projections autorisées et reconstruisibles.

Les noms définitifs peuvent évoluer par migration contrôlée. Leur responsabilité ne peut être fusionnée par commodité.

## 4. Propriété des tables

Une table appartient à un seul module. Seul ce module :

- définit ses migrations ;
- modifie ses lignes ;
- interprète ses états ;
- décide de leur cycle de vie ;
- publie les contrats de consultation ou événements.

Les autres modules utilisent ADR-0005. Les jointures ad hoc entre schémas dans le code métier sont interdites.

Les clés étrangères sont obligatoires à l'intérieur d'un module lorsque la relation l'exige. Les références intermodules utilisent des identifiants stables, un contrat et une preuve d'origine ; elles n'utilisent jamais de suppression en cascade entre domaines.

Des vues de lecture approuvées peuvent exposer un sous-ensemble minimal, sans donner accès aux tables sources.

## 5. Identifiants

### 5.1. Identifiant interne

Les nouveaux objets métier utilisent un UUID version 7 généré par l'application ou PostgreSQL selon une implémentation testée. Il fournit unicité distribuée et ordre temporel raisonnable sans compteur global.

### 5.2. Identifiant public

Un objet sensible ou exposé dans une URL peut recevoir un identifiant public aléatoire distinct. L'autorisation ne repose jamais sur son caractère difficile à deviner.

### 5.3. Référence humaine

Une référence lisible peut être produite pour retrait, campagne, alerte ou dossier. Elle est unique dans son périmètre, mais ne constitue ni secret ni preuve d'autorisation.

Téléphone, e-mail, numéro de document et identifiant d'un prestataire ne deviennent jamais clé primaire.

Aucun identifiant supprimé n'est réattribué.

## 6. Personne, compte et organisation

Wasplex distingue :

- **personne** : individu concerné ;
- **compte** : moyen d'accès à Wasplex ;
- **organisation** : structure annonceur, institutionnelle ou interne ;
- **appartenance** : relation nominative et temporelle entre personne et organisation ;
- **représentation** : capacité d'agir dans une portée.

Un compte n'est pas automatiquement une personne vérifiée. Un appareil n'est ni compte ni personne.

Les quatre acteurs constitutionnels ne sont pas remplacés par ces objets techniques.

## 7. Isolation organisationnelle et territoriale

Toute ligne appartenant à une organisation porte son `organization_id`. Toute donnée gouvernée par un territoire porte explicitement pays de service, entité juridique et, si nécessaire, zone opérationnelle.

Le pays n'est jamais déduit uniquement du numéro de téléphone, de l'adresse IP ou de la devise.

Les requêtes sont filtrées côté serveur selon ADR-0004. Des contraintes, vues, rôles PostgreSQL ou politiques de ligne protègent en profondeur les données critiques.

Wasplex n'utilise pas un schéma PostgreSQL par annonceur ou institution. Cette multiplication serait coûteuse et n'améliorerait pas automatiquement la sécurité.

Une obligation future de résidence nationale peut conduire à un déploiement physique séparé sans changer le modèle de propriété.

## 8. Classification des données

### D0 — Publique

Information destinée à une diffusion publique autorisée.

### D1 — Interne

Donnée d'exploitation non publique dont l'exposition aurait un impact limité.

### D2 — Personnelle

Donnée associée à une personne identifiable : coordonnées, profil, historique ou préférence.

### D3 — Sensible

KYC, santé, vulnérabilité, mineur, position précise, biométrie autorisée, Fonds Social, alerte ou donnée institutionnelle protégée.

### D4 — Critique

Ledger, preuve financière, secret d'authentification, clé, décision privilégiée, alerte souveraine ou élément dont l'altération menace des droits ou la sécurité.

Les secrets techniques utilisent un gestionnaire dédié et ne sont pas traités comme de simples lignes de configuration.

Chaque champ sensible figure dans un registre de données avec finalité, base de traitement, accès, chiffrement, rétention et transfert autorisé.

## 9. Données personnelles et séparation

Identité conserve les coordonnées et preuves d'identité. Les autres modules utilisent l'identifiant interne et un statut minimal.

Publicité ne reçoit ni documents KYC, ni santé, ni données sociales, ni urgence. Elle consulte seulement les attributs publicitaires autorisés via Privacy et des segments protégés.

Wallet n'a pas besoin de l'intégralité du profil ; il obtient identité opérationnelle, pays, conformité et autorisation nécessaires.

Les données sensibles ne sont jamais copiées dans les payloads d'événement, journaux ou projections par commodité.

## 10. Dates et temporalité

Wasplex distingue :

- `occurred_at` : moment du fait dans le monde métier ;
- `recorded_at` : moment de son enregistrement ;
- `effective_from` et `effective_to` : période d'effet d'une règle ou relation ;
- `created_at` : création technique ;
- `updated_at` : dernière modification permise ;
- date comptable pour le Ledger ;
- date d'expiration ou de revue lorsqu'elle existe.

Les configurations, consentements, habilitations, contrats et statuts importants conservent leur histoire temporelle. Une correction tardive ne falsifie pas la date du fait.

Toutes les dates sont stockées avec fuseau ou en UTC selon le contrat et présentées dans le fuseau pertinent.

## 11. États et suppressions

Les objets importants utilisent des états métier explicites. Un booléen `is_active` ne suffit pas pour représenter proposé, actif, suspendu, expiré, révoqué ou clôturé.

Wasplex n'applique pas le soft delete permanent à toutes les tables. Cette pratique conserverait inutilement des données personnelles.

Selon la donnée :

- suppression physique après délai lorsque aucune obligation ne subsiste ;
- anonymisation irréversible vérifiée ;
- pseudonymisation ou isolement si une preuve doit rester liée ;
- conservation immuable pour Ledger et preuves critiques ;
- gel légal documenté en cas de litige ou obligation.

Une donnée supprimée des usages commerciaux ne réapparaît pas depuis une sauvegarde restaurée sans réapplication du registre de suppression.

## 12. Conservation

Une matrice versionnée définit par catégorie, finalité et pays :

- durée active ;
- durée d'archive ;
- événement déclencheur ;
- traitement en fin de vie ;
- exceptions légales ;
- responsable ;
- preuve d'exécution.

Aucune durée « illimitée » n'est acceptée sans justification supérieure.

Les sauvegardes suivent une durée propre. À leur restauration, les suppressions et révocations postérieures sont rejouées avant remise en service.

## 13. Documents, médias et preuves

Les vidéos, images, pièces KYC, justificatifs, rapports et preuves volumineuses résident dans un stockage objet compatible S3.

PostgreSQL conserve :

- identifiant ;
- propriétaire ;
- classification ;
- finalité ;
- clé d'objet non publique ;
- taille et type détecté ;
- empreinte cryptographique ;
- chiffrement ;
- état d'analyse antivirus ;
- durée de conservation ;
- liens métier autorisés.

Les objets ne possèdent pas d'URL publique permanente. L'accès utilise une URL courte signée après autorisation. Le type fourni par l'utilisateur n'est pas considéré comme fiable.

Une suppression ou mise en quarantaine concerne la base, l'objet, ses variantes et caches.

## 14. Données déduites

Toute donnée calculée conserve :

- finalité ;
- algorithme et version ;
- sources ou période ;
- date de calcul ;
- niveau de confiance ;
- date d'expiration ;
- état de contestation ;
- domaine propriétaire.

Une donnée déduite sensible interdite par la Constitution n'est pas créée simplement parce qu'elle est techniquement possible.

## 15. Projections et reporting

Une projection est une copie de lecture autorisée, reconstruisible et non souveraine.

Elle indique :

- source ;
- dernier événement appliqué ;
- fraîcheur ;
- version de schéma ;
- classification ;
- politique de reconstruction.

Elle ne reçoit aucune écriture métier directe.

Le reporting annonceur utilise agrégation, seuils et protections contre les requêtes permettant de réidentifier. Les analyses internes n'accordent pas un accès général aux données de production.

## 16. JSON et données flexibles

JSONB est autorisé pour :

- snapshots contractuels ;
- payloads versionnés ;
- métadonnées bornées ;
- réponses externes protégées ;
- attributs dont le schéma est explicitement validé.

Il ne remplace pas les colonnes structurées pour montants, identifiants, statuts, pays, dates, autorisations ou relations centrales.

Tout JSON métier possède un schéma, une version, une taille maximale et une politique de données sensibles.

## 17. Doublons et fusion

La détection d'un doublon produit une hypothèse, jamais une fusion automatique.

Une fusion exige :

- preuves suffisantes ;
- autorité ;
- plan de conflit ;
- conservation des identifiants sources comme alias ;
- traitement séparé des consentements ;
- traçabilité des KYC ;
- journalisation ;
- recours.

Les écritures Wallet ne sont ni déplacées ni réécrites par mise à jour de propriétaire. Toute consolidation économique utilise des transactions autorisées selon ADR-0003.

Une erreur de fusion possède une procédure de séparation lorsque cela reste juridiquement et techniquement possible.

## 18. Contraintes PostgreSQL

Les invariants simples sont imposés dans PostgreSQL :

- `NOT NULL` ;
- unicité ;
- clés étrangères intra-module ;
- contrôles de plage et d'état ;
- exclusions temporelles ;
- index uniques partiels ;
- types et précision ;
- restrictions de suppression ;
- équilibre et immutabilité du Ledger selon ADR-0003.

Une validation applicative utile est répétée en contrainte de base lorsque PostgreSQL peut protéger l'invariant sans ambiguïté.

Les contraintes ne réalisent pas des appels réseau ni des décisions intermodules.

## 19. Accès à la base

L'application n'utilise pas un compte propriétaire de PostgreSQL pour son trafic ordinaire.

Les migrations, opérations applicatives, lecture de reporting et administration possèdent des rôles distincts. Les schémas critiques, notamment Ledger, Gouvernance et Identité, utilisent des permissions renforcées.

L'accès SQL humain à la production est exceptionnel, nominatif, temporaire et audité. Une correction métier ne s'effectue jamais par requête SQL improvisée.

## 20. Chiffrement et recherche

Les connexions, disques, sauvegardes et objets sont chiffrés. Les champs D3/D4 nécessitant une protection supplémentaire utilisent un chiffrement applicatif ou colonne avec clés séparées et renouvelables.

Les recherches sur coordonnées utilisent, lorsque nécessaire, une forme normalisée et une empreinte dédiée. Le chiffrement ou hachage n'autorise aucun nouvel usage.

Les clés restent hors de PostgreSQL et hors du code.

## 21. Migrations

Les migrations suivent une stratégie **étendre → migrer → basculer → retirer** :

1. ajouter une structure compatible ;
2. écrire dans l'ancien et/ou le nouveau format selon plan contrôlé ;
3. migrer et vérifier les données ;
4. basculer les lectures ;
5. observer et rapprocher ;
6. retirer l'ancien format dans une livraison ultérieure.

Une livraison ne supprime pas immédiatement une colonne encore utilisée par la version précédente.

Toute migration critique possède sauvegarde, mesure de durée, plan d'arrêt, contrôles avant/après et rapport. Pour Ledger, les totaux et empreintes sont rapprochés.

Un retour applicatif ne rembobine jamais une migration économique. Les corrections utilisent une migration en avant ou une procédure métier.

## 22. Partitionnement et réplication

Wasplex ne partitionne pas chaque table par anticipation. Partitionnement, réplica de lecture ou séparation physique sont introduits après mesure d'un volume, d'une disponibilité ou d'une obligation.

Un réplica n'est jamais utilisé pour une décision exigeant une donnée garantie à jour sans contrôle de retard.

## 23. Documentation et registre

Chaque table et champ métier possède :

- description ;
- module propriétaire ;
- classification ;
- finalité ;
- contraintes ;
- relations ;
- rétention ;
- source de vérité ou projection ;
- exposition autorisée.

Les diagrammes servent à comprendre ; les migrations, contraintes et contrats restent exécutables et font autorité technique.

## 24. Tests obligatoires

Wasplex démontre notamment :

- isolement entre organisations et territoires ;
- impossibilité d'écriture intermodule ;
- absence de suppression en cascade entre domaines ;
- unicité et non-réattribution des identifiants ;
- interdiction d'utiliser téléphone ou document comme clé ;
- reconstruction des projections ;
- réapplication des suppressions après restauration ;
- respect de la matrice de conservation ;
- absence de D3/D4 dans journaux et événements interdits ;
- URL objet expirante et autorisée ;
- fusion sans réécriture du Ledger ;
- migration compatible entre deux versions applicatives ;
- rapprochement avant/après migration financière ;
- refus d'un JSON non conforme ;
- requête organisationnelle sans fuite transversale.

## 25. Conséquences

### Bénéfices

- simplicité d'exploitation d'un PostgreSQL central ;
- frontières visibles et testables ;
- réduction des copies sensibles ;
- migrations et suppressions maîtrisées ;
- évolution possible vers plusieurs déploiements ;
- modèle compatible avec confidentialité, audit et multi-pays.

### Coûts

- dictionnaire de données à maintenir ;
- contrats nécessaires pour les références intermodules ;
- projections à reconstruire ;
- procédures rigoureuses de migration et conservation.

Ces coûts sont acceptés afin que la facilité d'une requête SQL ne devienne jamais une violation des frontières de Wasplex.

## 26. Règle obligatoire

> Toute donnée Wasplex doit avoir un propriétaire, une finalité, une classification, une portée, une source, une durée et une règle de fin de vie. Une table partagée sans propriétaire est interdite.

Tout futur prompt créant une table, colonne, fichier, projection ou migration doit citer cet ADR.