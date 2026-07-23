# TD-0001 — Suivis différés du noyau d’autorisation

**Statut :** clos — les 4 points repris et corrigés par P003-B3
**Date :** 2026-07-23  
**Origine :** revue finale de P003-B1, PR #5  
**Composant :** `App\Modules\Governance\Authorization`  
**Référence examinée :** `24d6901`
**Référence de clôture :** branche `claude/p003b3-authorization-core-hardening` (non fusionnée à la date de rédaction de cette mise à jour)

## Décision de pilotage

Wasplex privilégie désormais la progression méthodique vers une conception complète plutôt que la recherche d’une perfection locale avant chaque lot.

Les éléments ci-dessous sont connus, documentés et volontairement différés. Ils ne bloquent pas la conception des prochains modules. Ils devront toutefois être réévalués avant que le moteur d’autorisation protège effectivement des parcours sensibles en production, notamment l’administration, les institutions, les opérations financières et les données à accès restreint.

Ce document n’invalide pas P003-B1. Il constitue le registre de sa dette technique connue.

## Éléments catalogués

### TD-0001-A — Autorité de l’auteur lors de l’activation d’un grant — **clos**

`GrantManager::activate()` reçoit actuellement un auteur en paramètre alors que le grant possède déjà un auteur enregistré. Une future consolidation devra empêcher toute substitution incohérente et définir précisément les relations interdites entre sujet, auteur, acteur d’activation et approbateur.

**Risque :** contournement possible de la séparation des tâches si une future intégration appelle incorrectement ce service.  
**Mesure temporaire :** ne pas exposer directement ce service à une route ou à une entrée client ; P003-B2 devra construire ses acteurs exclusivement depuis l’identité authentifiée et les données persistées.

**Correction (P003-B3) :** `activate()` refuse désormais tout auteur transmis différent de `grant->author_person_account_link_id` (`AuthorSubstitutionRefusedException`), et refuse un approbateur identique au sujet du grant, y compris en délégation (auteur ≠ sujet). La matrice complète des relations interdites est documentée en commentaire de méthode et couverte par `AuthorApproverMatrixTest`.

### TD-0001-B — Provenance avec plusieurs grants candidats — **clos**

La résolution ordinaire est déterministe, mais les décisions `step_up_required` et `approval_required` devront être consolidées afin d’identifier sans ambiguïté le grant et la politique réellement retenus lorsqu’il existe plusieurs candidats.

**Risque :** décision prudente correcte mais provenance ou explication potentiellement liée à l’ordre des candidats.  
**Mesure temporaire :** ne pas utiliser ces résultats pour exécuter automatiquement une opération sensible ou créer une approbation irréversible avant consolidation.

**Correction (P003-B3) :** `AuthorizationEngine` collecte désormais tous les candidats `step_up_required`/`approval_required` puis choisit celui dont l’UUID est le plus petit (`chooseDeterministicCandidate()`, même règle que la résolution multi-grants ordinaire) — jamais l’ordre de retour SQL. Le grant retenu est attaché au résultat via l’obligation `matched_grant`, testé par `MultiGrantProvenanceTest` pour sa stabilité entre appels identiques.

### TD-0001-C — Mutation par déplacement d’une liaison de catalogue — **clos**

Les déclencheurs protégeant `capability_purposes` et `role_template_capabilities` devront vérifier séparément l’ancien et le nouveau parent lors d’un `UPDATE`. L’usage actuel de `COALESCE(NEW..., OLD...)` peut ne contrôler que le nouveau parent.

**Risque :** modification indirecte d’un catalogue actif par déplacement de liaison.  
**Mesure temporaire :** les futurs services d’administration ne doivent jamais mettre à jour une liaison existante ; toute évolution doit créer une nouvelle version et une nouvelle liaison.

**Correction (P003-B3) :** migration `2026_07_23_100012` — les deux fonctions trigger vérifient désormais séparément `OLD` et `NEW` sur `UPDATE` ; un déplacement depuis un parent actif est refusé quel que soit l’état du nouveau parent. Démontré par `SemanticImmutabilityTest::test_role_template_capabilities_move_from_an_active_parent_is_refused` et `test_capability_purposes_move_from_an_active_capability_is_refused`.

### TD-0001-D — Immutabilité historique complète des définitions retirées — **clos**

L’immuabilité des finalités et des définitions déjà retirées devra être harmonisée avec celle des capacités, politiques et rôles modèles. Les règles concernant `effective_to` devront également être précisées.

**Risque :** reconstruction historique incomplète en cas de modification directe après retrait.  
**Mesure temporaire :** aucune interface d’administration ne doit permettre l’édition d’une définition active ou retirée ; seules de nouvelles versions doivent être créées.

**Correction (P003-B3) :** migration `2026_07_23_100012` — déclencheur d’immutabilité ajouté pour `purpose_definitions` (symétrique à `capability_definitions`) ; les quatre déclencheurs (`capability_definitions`, `policy_versions` déjà inconditionnel, `role_templates`, `purpose_definitions`) refusent désormais toute mutation sémantique dès que `OLD.state IN ('active', 'retired')`, seul `effective_to` restant modifiable sur une ligne retirée. Démontré table par table par `SemanticImmutabilityTest`.

## Porte de reprise

Tous les points ci-dessus sont clos. La porte de reprise ci-dessous reste néanmoins la référence pour toute dette future du même registre (voir aussi `technical-debt/TD-0002-authorization-integration-follow-ups.md`) :

1. branchement réel du moteur sur les routes sensibles ;
2. activation d’un espace administrateur ou institutionnel en production ;
3. traitement d’opérations financières ou de données personnelles restreintes ;
4. audit de sécurité précédant le lancement public complet.

## Règle du registre

Toute nouvelle dette technique acceptée reçoit un identifiant `TD-NNNN`, un risque explicite, une mesure temporaire et une porte de reprise. Une dette documentée peut différer une correction ; elle ne peut jamais supprimer une garantie constitutionnelle ni autoriser silencieusement un risque en production.
