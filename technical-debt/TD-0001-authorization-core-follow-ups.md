# TD-0001 — Suivis différés du noyau d’autorisation

**Statut :** différé et accepté pour poursuivre la conception  
**Date :** 2026-07-23  
**Origine :** revue finale de P003-B1, PR #5  
**Composant :** `App\Modules\Governance\Authorization`  
**Référence examinée :** `24d6901`

## Décision de pilotage

Wasplex privilégie désormais la progression méthodique vers une conception complète plutôt que la recherche d’une perfection locale avant chaque lot.

Les éléments ci-dessous sont connus, documentés et volontairement différés. Ils ne bloquent pas la conception des prochains modules. Ils devront toutefois être réévalués avant que le moteur d’autorisation protège effectivement des parcours sensibles en production, notamment l’administration, les institutions, les opérations financières et les données à accès restreint.

Ce document n’invalide pas P003-B1. Il constitue le registre de sa dette technique connue.

## Éléments catalogués

### TD-0001-A — Autorité de l’auteur lors de l’activation d’un grant

`GrantManager::activate()` reçoit actuellement un auteur en paramètre alors que le grant possède déjà un auteur enregistré. Une future consolidation devra empêcher toute substitution incohérente et définir précisément les relations interdites entre sujet, auteur, acteur d’activation et approbateur.

**Risque :** contournement possible de la séparation des tâches si une future intégration appelle incorrectement ce service.  
**Mesure temporaire :** ne pas exposer directement ce service à une route ou à une entrée client ; P003-B2 devra construire ses acteurs exclusivement depuis l’identité authentifiée et les données persistées.

### TD-0001-B — Provenance avec plusieurs grants candidats

La résolution ordinaire est déterministe, mais les décisions `step_up_required` et `approval_required` devront être consolidées afin d’identifier sans ambiguïté le grant et la politique réellement retenus lorsqu’il existe plusieurs candidats.

**Risque :** décision prudente correcte mais provenance ou explication potentiellement liée à l’ordre des candidats.  
**Mesure temporaire :** ne pas utiliser ces résultats pour exécuter automatiquement une opération sensible ou créer une approbation irréversible avant consolidation.

### TD-0001-C — Mutation par déplacement d’une liaison de catalogue

Les déclencheurs protégeant `capability_purposes` et `role_template_capabilities` devront vérifier séparément l’ancien et le nouveau parent lors d’un `UPDATE`. L’usage actuel de `COALESCE(NEW..., OLD...)` peut ne contrôler que le nouveau parent.

**Risque :** modification indirecte d’un catalogue actif par déplacement de liaison.  
**Mesure temporaire :** les futurs services d’administration ne doivent jamais mettre à jour une liaison existante ; toute évolution doit créer une nouvelle version et une nouvelle liaison.

### TD-0001-D — Immutabilité historique complète des définitions retirées

L’immuabilité des finalités et des définitions déjà retirées devra être harmonisée avec celle des capacités, politiques et rôles modèles. Les règles concernant `effective_to` devront également être précisées.

**Risque :** reconstruction historique incomplète en cas de modification directe après retrait.  
**Mesure temporaire :** aucune interface d’administration ne doit permettre l’édition d’une définition active ou retirée ; seules de nouvelles versions doivent être créées.

## Porte de reprise

La reprise de TD-0001 est obligatoire avant la première des échéances suivantes :

1. branchement réel du moteur sur les routes sensibles ;
2. activation d’un espace administrateur ou institutionnel en production ;
3. traitement d’opérations financières ou de données personnelles restreintes ;
4. audit de sécurité précédant le lancement public complet.

Elle peut être réalisée après la conception fonctionnelle des modules, mais avant leur activation sensible en production.

## Règle du registre

Toute nouvelle dette technique acceptée reçoit un identifiant `TD-NNNN`, un risque explicite, une mesure temporaire et une porte de reprise. Une dette documentée peut différer une correction ; elle ne peut jamais supprimer une garantie constitutionnelle ni autoriser silencieusement un risque en production.
