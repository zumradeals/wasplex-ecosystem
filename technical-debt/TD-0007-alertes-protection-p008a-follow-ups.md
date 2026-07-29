# TD-0007 — Suivis différés du module Alertes et Protection (P008-A)

**Statut :** ouvert
**Date :** 2026-07-29
**Origine :** P008-A — première tranche réelle du module Alertes (déclaration communautaire, SOS anonyme, routage institutionnel, correspondance, restitution, portail admin, frontière Santé)
**Composant :** `App\Modules\Alerts`
**Référence normative :** AMD-0007, AMD-0016 (Constitution v1.7 article 23), `ecosystem/alertes/01` à `03`, `ecosystem/sante/00`

## Décision de pilotage

Ce lot livre un premier parcours de bout en bout du module Alertes (déclaration, modération, publication, SOS anonyme, routage institutionnel, correspondance, restitution) avec une frontière Santé strictement cloisonnée (jamais construite vers une donnée réelle). Les éléments ci-dessous sont connus, documentés et volontairement différés — aucun ne compromet une garantie constitutionnelle du lot livré.

## Éléments catalogués

### TD-0007-A — Le worker de transmission institutionnelle n'est planifié nulle part

`alerts:transmit-dispatches` (le cascade `created` → `transmitted` du modèle outbox, architecture/10) existe et fonctionne (couvert par `CaseDispatchServiceTest`), mais aucun `Schedule::command(...)` ni entrée cron ne l'invoque — un contrôle du dépôt entier confirme qu'aucun mécanisme de planification n'est câblé nulle part dans l'application à ce jour, pas seulement pour Alertes.

**Risque :** en production sans planification externe, une transmission institutionnelle créée reste indéfiniment à l'état `created` (jamais honnêtement présentée comme `transmitted` — voir `SosSheet`/`DispatchDecisionController`, aucun mensonge d'état), mais l'institution ne reçoit jamais rien tant que la commande n'est pas exécutée manuellement.
**Mesure temporaire :** exécution manuelle (`php artisan alerts:transmit-dispatches`), déjà utilisée pour ce dossier de validation.
**Porte de reprise :** avant l'activation d'un espace institutionnel réel en production, câbler une planification (cron système ou `Schedule::command('alerts:transmit-dispatches')->everyMinute()`) — décision d'infrastructure distincte, hors périmètre code de ce lot.

### TD-0007-B — Aucun filtrage territorial des alertes publiées

`AlertsOverviewController::index` documente explicitement l'absence de filtrage territorial : `PublicAlertFeedProjection::published()` retourne toutes les alertes publiées, sans restriction par pays/zone, aucune géolocalisation devinée depuis l'IP ou l'appareil.

**Risque :** aucun à ce stade — un seul pays (CI) est utilisé dans ce lot ; aucune donnée n'est faussée, seulement non filtrée.
**Mesure temporaire :** aucune ; `country_code` existe déjà sur chaque dossier et publication, prêt à être filtré.
**Porte de reprise :** avant l'ouverture à plusieurs territoires, ajouter le filtrage par `country_code`/zone à `PublicAlertFeedProjection` et à l'écran mobile.

### TD-0007-C — Aucune interface de restitution

`RestitutionService` et la machine d'états `RestitutionState` existent et sont couverts par `RestitutionServiceTest`, mais aucun contrôleur HTTP ni écran ne les expose : la programmation et la confirmation d'une restitution physique restent aujourd'hui possibles uniquement via le service directement (test ou tinker), jamais depuis un parcours réel.

**Risque :** aucun à ce stade — aucun flux de production ne dépend de cet écran ; la machine d'états elle-même est saine et testée.
**Mesure temporaire :** aucune.
**Porte de reprise :** lot dédié une fois qu'un premier dossier communautaire réel atteint l'état `matched` et nécessite une restitution suivie.

### TD-0007-D — Aucune interface d'attribution des capacités institutionnelles

Les grants institutionnels (`alert_case.acknowledge`/`accept`/`process`/`resolve`/`transfer`, scopés organisation + catégories) sont aujourd'hui émis uniquement via `GrantManager` en tinker/test, jamais depuis un écran admin. L'écran admin « Accès » livré précédemment reste en lecture seule (TD antérieur, non spécifique à ce lot).

**Risque :** aucun à ce stade — cohérent avec l'état actuel de tout le catalogue de capacités (aucun octroi self-service en production).
**Mesure temporaire :** aucune.
**Porte de reprise :** même porte de reprise générale que TD-0001/TD-0002 (activation d'un espace administrateur ou institutionnel réel).

## Porte de reprise générale

Compatible avec la porte de reprise déjà posée par `TD-0001` à `TD-0006` :

1. branchement réel du moteur d'autorisation sur les routes sensibles ;
2. activation d'un espace administrateur ou institutionnel en production ;
3. traitement d'opérations financières ou de données personnelles restreintes ;
4. audit de sécurité précédant le lancement public complet.

## Règle du registre

Toute nouvelle dette technique acceptée reçoit un identifiant `TD-NNNN`, un risque explicite, une mesure temporaire et une porte de reprise. Une dette documentée peut différer une correction ; elle ne peut jamais supprimer une garantie constitutionnelle ni autoriser silencieusement un risque en production.
