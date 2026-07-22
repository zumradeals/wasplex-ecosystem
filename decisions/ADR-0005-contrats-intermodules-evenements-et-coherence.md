# ADR-0005 — Contrats intermodules, événements et cohérence transactionnelle

**État :** adopté par le fondateur  
**Date :** 22 juillet 2026  
**Décideur architectural :** SIRR, sur mandat du fondateur  
**Dépendances :** Constitution v1.4, ADR-0001, ADR-0002, ADR-0003, ADR-0004

## 1. Contexte

Wasplex est un monolithe modulaire. Ses domaines partagent un déploiement et PostgreSQL, mais ne doivent ni modifier les tables d'un autre domaine ni dupliquer sa source de vérité.

Plusieurs parcours traversent pourtant plusieurs modules :

- une attention qualifiée doit produire une rémunération ;
- un vœu social doit réserver puis prélever des contributions ;
- une récompense d'alerte doit être réservée puis libérée ;
- une opération Carte doit alimenter un pool puis le Wallet ;
- un retrait doit communiquer avec un prestataire externe ;
- une institution peut recevoir une alerte limitée à sa capacité.

Un appel libre entre classes ou une écriture directe en base rendrait ces parcours opaques. À l'inverse, transformer chaque action en message asynchrone ou adopter l'event sourcing partout créerait une complexité inutile.

## 2. Décision

Wasplex adopte trois contrats explicites :

- **Commande** : demande de modification adressée à un seul module propriétaire.
- **Requête** : demande de lecture sans effet métier.
- **Événement** : fait métier immuable déjà survenu.

Les commandes et requêtes internes simples sont exécutées directement dans le processus Laravel par des interfaces typées. Les événements durables sont utilisés lorsqu'un fait doit déclencher des effets différés, informer plusieurs modules, survivre à une panne ou sortir du système.

Wasplex utilise une **outbox transactionnelle**, une **inbox idempotente** et des **processus persistants de type saga** pour les parcours intermodules longs.

Wasplex n'adopte pas l'event sourcing général. L'état métier ordinaire reste stocké dans les tables propriétaires des modules. Le Ledger demeure append-only conformément à ADR-0003.

## 3. Frontière de propriété

Chaque module :

- possède ses tables, règles, états et migrations ;
- expose des contrats publics internes ;
- contrôle ses autorisations avec ADR-0004 ;
- valide ses commandes ;
- publie ses propres événements ;
- reste seul autorisé à modifier son état.

Un autre module peut appeler un contrat, recevoir un événement ou consulter une projection autorisée. Il ne peut pas écrire directement dans ses tables, appeler ses classes internes ou reproduire sa logique.

Le partage d'une base PostgreSQL ne constitue pas un partage de propriété.

## 4. Commandes

Une commande :

- est formulée à l'impératif ;
- possède un unique module destinataire ;
- exprime une intention ;
- peut réussir, être refusée ou être acceptée pour traitement ;
- possède une clé d'idempotence lorsqu'elle peut être répétée ;
- transporte le contexte d'autorisation nécessaire ;
- référence la configuration applicable ;
- ne promet jamais un résultat externe encore inconnu.

Exemples :

- `QualifierAttention`
- `ComptabiliserRemunerationPublicitaire`
- `ReserverContributionSociale`
- `DemanderRetrait`
- `TransmettreAlerteAInstitution`
- `CrediterDistributionCarte`

Une commande n'est jamais nommée comme un événement passé.

## 5. Requêtes

Une requête :

- ne modifie aucun état métier ;
- peut lire la source propriétaire ou une projection ;
- indique la fraîcheur du résultat ;
- respecte capacité, portée, finalité et masquage ;
- ne déclenche pas silencieusement de crédit, validation ou notification.

Une lecture de projection potentiellement ancienne ne peut autoriser seule un mouvement financier ou une décision de sécurité.

## 6. Événements

Un événement :

- est formulé au passé ;
- représente un fait effectivement enregistré ;
- est immuable ;
- possède un identifiant global unique ;
- peut avoir plusieurs consommateurs ;
- ne commande pas directement la réaction d'un consommateur ;
- est versionné ;
- est publié par le module propriétaire du fait.

Exemples :

- `AttentionQualifiee.v1`
- `RemunerationComptabilisee.v1`
- `RetraitReserve.v1`
- `PaiementConfirme.v1`
- `AlerteTransmise.v1`
- `ContributionSocialeEchouee.v1`

Un événement ne signifie jamais davantage que son nom et son schéma. « Retrait transmis » ne signifie pas « retrait payé ».

## 7. Enveloppe commune

Tout événement durable contient au minimum :

- `event_id` ;
- nom et version ;
- producteur ;
- type et identifiant de l'objet ;
- version de l'objet ;
- date métier et date d'enregistrement ;
- `correlation_id` du parcours ;
- `causation_id` de l'action précédente ;
- sujet réel ou compte technique ;
- organisation éventuelle ;
- pays ou entité juridique ;
- publication de configuration ;
- classification de sensibilité ;
- empreinte du payload.

Le payload contient le minimum nécessaire. Les documents KYC, secrets, données médicales, coordonnées complètes et preuves volumineuses restent dans leur domaine ; l'événement transporte une référence protégée.

## 8. Transactions locales

Une transaction PostgreSQL appartient à un module et protège la modification de son état.

Lorsqu'un fait doit être publié, le module écrit dans la même transaction :

1. son nouvel état ;
2. l'événement dans sa table outbox.

Si la transaction échoue, aucun des deux n'existe. Si elle réussit, l'événement pourra être publié même après une panne du processus.

Wasplex n'étend pas une transaction SQL à plusieurs modules ou à un prestataire externe. Il utilise un processus métier persistant et des compensations.

## 9. Outbox transactionnelle

L'outbox est append-only. Un dispatcher lit les événements non publiés, les remet aux consommateurs puis enregistre leur progression.

La publication peut se produire plusieurs fois. La conception ne dépend donc jamais d'une livraison « exactement une fois ».

L'outbox conserve :

- événement et version ;
- transaction source ;
- disponibilité prévue ;
- nombre de tentatives ;
- dernier résultat ;
- date de publication ;
- politique de conservation.

Un événement n'est jamais abandonné silencieusement.

## 10. Inbox idempotente

Chaque consommateur durable possède une inbox avec une contrainte unique sur :

> consommateur + event_id

Lors d'un traitement, l'inbox et l'effet métier sont enregistrés dans la même transaction locale. Une répétition retourne le résultat déjà obtenu ou ne produit aucun nouvel effet.

L'idempotence s'applique aussi aux commandes critiques avec :

- clé ;
- périmètre ;
- empreinte de la demande ;
- résultat précédent.

La même clé avec un contenu différent est refusée.

## 11. Ordre et concurrence

Wasplex ne garantit aucun ordre global des événements.

L'ordre peut être exigé pour un même objet grâce à son identifiant et sa version. Un consommateur recevant une version future avant la précédente la met en attente, reconstruit l'état autorisé ou ouvre un incident selon le contrat.

Les commandes concurrentes utilisent version optimiste, contraintes uniques ou verrouillage ciblé. Elles ne reposent pas sur l'espoir que deux clics n'arriveront jamais ensemble.

## 12. Sagas et processus persistants

Une saga coordonne un parcours qui :

- traverse plusieurs modules ;
- attend une réponse externe ;
- comporte plusieurs points d'irréversibilité ;
- doit reprendre après panne ;
- nécessite compensation ou intervention humaine.

La saga possède un état persistant, une corrélation, des délais, des transitions et un responsable métier. Elle ne conserve pas de transaction SQL ouverte.

Exemples :

- rémunération publicitaire ;
- retrait externe ;
- réalisation d'un vœu social ;
- restitution avec récompense ;
- distribution d'un pool Carte ;
- transmission institutionnelle critique.

Une saga n'est créée que lorsqu'un simple appel synchrone ne suffit pas.

## 13. Compensation

Une compensation est une nouvelle opération métier qui neutralise ou corrige un effet encore réversible.

Elle :

- possède sa propre autorisation ;
- référence l'effet compensé ;
- conserve l'historique ;
- respecte le Ledger ;
- peut échouer et être reprise ;
- ne prétend pas effacer une action externe irréversible.

Exemples :

- libérer une réservation après échec confirmé ;
- annuler une allocation non consommée ;
- restituer un budget disponible ;
- retirer une notification non encore délivrée.

Un paiement confirmé ne devient pas « non payé » par changement d'état. Il exige, si possible, un remboursement distinct.

## 14. Erreurs, reprises et quarantaine

Les erreurs sont classées :

- métier définitive ;
- technique temporaire ;
- externe inconnue ;
- contrat incompatible ;
- sécurité ou autorisation ;
- incohérence nécessitant intervention.

Les erreurs temporaires sont reprises avec délai progressif et limite. Après épuisement, le message rejoint une quarantaine visible avec propriétaire, gravité et procédure de reprise.

Une reprise manuelle réutilise l'identifiant original. Elle ne copie pas le message pour contourner l'idempotence.

## 15. Vérité des états

Chaque état exposé correspond à une preuve :

- demandé ;
- accepté ;
- réservé ;
- transmis ;
- confirmé ;
- échoué ;
- inconnu ;
- compensé.

L'interface ne déduit pas « payé », « envoyé à la police », « récompensé » ou « réalisé » d'un simple événement intermédiaire.

Le module propriétaire reste l'autorité de son état. Une projection d'un autre module indique sa date et peut être corrigée par nouvel événement.

## 16. Contrats et versions

Les contrats sont documentés, testés et versionnés.

Une évolution compatible peut ajouter un champ facultatif. Une suppression, modification de sens, d'unité, de format ou d'obligation exige une nouvelle version.

Les producteurs maintiennent une période de compatibilité. Les consommateurs ignorent les champs inconnus lorsque le contrat l'autorise, mais refusent une version majeure non supportée.

Une version d'événement ne réécrit jamais les anciens événements.

## 17. Sécurité et données

L'événement ne contourne jamais ADR-0004. Le consommateur vérifie que le producteur est autorisé et que l'effet demandé appartient à son contrat.

Le contexte distingue :

- personne initiatrice ;
- organisation ;
- compte technique exécutant ;
- délégation ;
- finalité.

Les files, outbox, inbox, journaux et quarantaines n'exposent pas de secrets. Les données sensibles utilisent chiffrement, référence, contrôle d'accès et durée de conservation adaptée.

## 18. Intégrations externes

Les banques, opérateurs Mobile Money, SMS, e-mail, stockage, géolocalisation et systèmes institutionnels sont isolés derrière des adaptateurs anticorruption.

Un adaptateur :

- traduit le modèle Wasplex vers le protocole externe ;
- normalise les réponses ;
- conserve les références et preuves ;
- applique délais, idempotence et authentification ;
- ne décide pas du droit métier ;
- ne modifie pas directement les modules.

Un webhook est une entrée externe non fiable jusqu'à authentification, déduplication, validation et rapprochement.

## 19. Flux de référence — attention publicitaire

1. Publicité enregistre la qualification et `AttentionQualifiee.v1`.
2. La saga de rémunération crée `ComptabiliserRemunerationPublicitaire`.
3. Wallet vérifie source, enveloppe, configuration et idempotence.
4. Wallet poste le journal équilibré.
5. Wallet publie `RemunerationComptabilisee.v1`.
6. Publicité met à jour sa projection et clôt l'événement.
7. Notifications informe éventuellement l'utilisateur sans devenir source du solde.

Un échec Wallet ne conduit jamais Publicité à créditer elle-même le compte.

## 20. Flux de référence — Fonds Social

1. Fonds Social valide un appel selon mandat et configuration.
2. Il commande la réservation au Wallet pour chaque participation.
3. Wallet retourne réservée, insuffisante, refusée ou en examen.
4. Fonds Social décide si le seuil de réalisation est atteint.
5. La réalisation commande les mouvements définitifs autorisés.
6. Les échecs libèrent les réservations par commandes compensatoires.
7. Chaque état et chaque montant restent rapprochables.

Le Fonds Social ne reçoit jamais un accès direct aux soldes modifiables.

## 21. Flux de référence — Alertes et Institutions

1. Alertes enregistre le dossier et sa diffusion autorisée.
2. La transmission institutionnelle vérifie capacité, territoire, finalité et données minimales.
3. L'adaptateur transmet ou met en file.
4. L'accusé externe est authentifié.
5. Alertes publie l'état littéral atteint.
6. Une absence d'accusé maintient l'état inconnu ou en attente.

Une notification technique ne constitue pas une prise en charge institutionnelle.

## 22. Observabilité

Chaque parcours partage :

- correlation ID ;
- causation ID ;
- étape ;
- durée ;
- tentative ;
- résultat ;
- propriétaire ;
- configuration.

Les métriques couvrent retard de l'outbox, doublons, quarantaines, sagas bloquées, versions incompatibles et écarts de projection.

Une alerte opérationnelle possède une procédure et un responsable.

## 23. Tests obligatoires

Wasplex démontre notamment :

- aucune écriture intermodule directe ;
- état et outbox atomiques ;
- événement dupliqué sans double effet ;
- commande répétée avec résultat stable ;
- clé réutilisée avec contenu différent refusée ;
- worker repris après panne au milieu du traitement ;
- ordre inversé géré par version d'objet ;
- saga reprise après redémarrage ;
- compensation conservant l'historique ;
- version incompatible mise en quarantaine ;
- données sensibles absentes des payloads interdits ;
- webhook falsifié rejeté ;
- projection reconstruite depuis ses événements ;
- état utilisateur conforme à la preuve disponible.

## 24. Conséquences

### Bénéfices

- domaines réellement séparés dans un monolithe simple ;
- aucun effet perdu après validation locale ;
- absence de double crédit malgré les répétitions ;
- parcours longs reprenables ;
- états honnêtes et auditables ;
- extraction future d'un module possible sans la préparer prématurément.

### Coûts

- contrats et versions à maintenir ;
- outbox, inbox et sagas à superviser ;
- cohérence parfois différée entre projections ;
- obligation de concevoir les compensations.

Ces coûts sont acceptés pour les parcours qui déplacent de la valeur, engagent une institution ou dépendent d'un prestataire. Les appels internes ordinaires restent synchrones.

## 25. Règle obligatoire

> Un module demande, constate ou consulte ; il ne contourne jamais le propriétaire d'un état. Tout effet différé doit être idempotent, durable, reprenable et honnête sur son niveau de preuve.

Tout futur prompt traversant plusieurs modules doit fournir commandes, événements, versions, idempotence, transaction locale, erreurs, compensation et source de vérité.