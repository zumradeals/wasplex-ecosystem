# TD-0006 — Suivis différés du noyau du registre de configuration

**Statut :** ouvert
**Date :** 2026-07-25
**Origine :** W2 — noyau du registre central de configuration
**Composant :** `App\Modules\Governance\Configuration`
**Référence normative :** ADR-0002

## Décision de pilotage

Ce lot construit le noyau minimal du registre annoncé par ADR-0002 §8 :
`Definition`, `ValueVersion`, `Approval`, `Activation`, avec double contrôle
C1 (deux approbateurs distincts), immuabilité sémantique et machine d'états
appliquées par déclencheurs PL/pgSQL en défense en profondeur du service.
Volontairement différés : Simulation, SafetySwitch, prise d'effet
programmée, résolution multi-portée et toute interface d'administration.

## Éléments catalogués

### TD-0006-A — Aucune Simulation ; approbation sans analyse d'impact chiffrée

ADR-0002 §4 et §7.1 exigent une simulation (scénarios normaux/limites/extrêmes,
estimation des utilisateurs/campagnes/contrats affectés, comparaison
avant/après) avant qu'une publication C1 puisse être approuvée. Ce noyau
n'implémente aucune `Simulation` : `ConfigurationValueManager::approve()`
enregistre une décision nominative avec motif libre, sans jamais calculer ni
exiger d'impact chiffré.

**Risque :** aucun à ce stade — aucune `Definition` réelle n'a encore été
déclarée par un module métier ; aucune décision financière ne s'appuie
encore sur ce registre.
**Mesure temporaire :** le champ `justification` (ValueVersion) et `motif`
(Approval) portent la seule trace textuelle actuellement disponible.
**Porte de reprise :** avant qu'une `Definition` de niveau C1 gouverne un
paramètre à effet financier réel (prix, seuil, quota engageant un budget),
construire `Simulation` et l'imposer comme préalable obligatoire à
`approve()`.

### TD-0006-B — Aucun SafetySwitch

ADR-0002 §7.5 décrit un interrupteur de sécurité capable de suspendre une
capacité précise (motif, périmètre, durée, identité, journalisation, revue),
distinct d'un retrait normal. Non construit : la seule voie de retrait de ce
noyau est le cycle normal `active → replaced` via une nouvelle
`ValueVersion` approuvée, jamais une suspension d'urgence immédiate.

**Risque :** aucun à ce stade — aucun paramètre réel n'est encore gouverné.
**Mesure temporaire :** aucune.
**Porte de reprise :** avant tout paramètre dont l'urgence de suspension
(fraude en cours, erreur de configuration détectée) ne peut attendre le
cycle normal de revue, construire `SafetySwitch`.

### TD-0006-C — Aucune prise d'effet programmée ; cycle réduit à `draft → in_review → approved → active → replaced`

`ValueVersionState` n'inclut ni `simulated` ni `scheduled` (ADR-0002 §4) :
une activation prend toujours effet immédiatement, jamais à une date future
annoncée. Aucune colonne `effective_from`/`effective_to` sur `ValueVersion`
elle-même (seule `Definition` en porte, pour son propre cycle de vie).

**Risque :** aucun à ce stade.
**Mesure temporaire :** documentée dans les migrations de création des
tables (`configuration.value_versions`).
**Porte de reprise :** avant qu'un module exige d'annoncer un changement de
prix ou de règle à l'avance (ex. abonnements, ADR-0002 §6 : « un changement
de prix... s'applique au prochain renouvellement après information »),
ajouter la prise d'effet programmée et l'état `scheduled`.

### TD-0006-D — Aucun `Binding` ; aucun module métier ne référence encore ce registre

ADR-0002 §8 prévoit un `Binding` : la référence conservée par une offre,
campagne, mandat, pool ou opération vers la `ValueVersion` qu'elle a
utilisée. Non construit. Les modules existants (Advertising) continuent de
porter leurs propres colonnes `*_configuration_key`/`*_configuration_version`
en chaînes/entiers opaques (`CampaignVersion.pricing_configuration_key`,
`SectorClassification`, `AudienceSegmentSizeThreshold`) sans les faire
pointer vers ce nouveau registre — TD-0004-A reste donc ouvert tel quel,
inchangé par ce lot.

**Risque :** aucun nouveau — TD-0004-A documentait déjà cette dette.
**Mesure temporaire :** aucune.
**Porte de reprise :** avant toute interface d'administration éditant un
paramètre Publicité, migrer `SectorClassification`/`AudienceSegmentSizeThreshold`
vers de véritables `Definition`/`ValueVersion` de ce registre plutôt que
leurs tables locales actuelles, puis introduire `Binding` pour que
`CampaignVersion` référence la `ValueVersion` réellement utilisée à son
activation.

### TD-0006-E — Résolution à portée unique ; hiérarchie ADR-0002 §5 non implémentée

Une seule `ValueVersion` active par `Definition`, globalement
(`value_versions_one_active_per_definition`) : aucune portée pays,
contrat, produit ou campagne. La hiérarchie complète de résolution
(invariants → loi du pays → publication globale → publication nationale →
version produit → contrat → paramètres de l'opération, ADR-0002 §5) n'est
pas modélisée.

**Risque :** aucun à ce stade — aucun module n'a encore besoin d'une
résolution différenciée par pays ou par contrat.
**Mesure temporaire :** documentée dans
`ConfigurationResolver`/la migration de création de `value_versions`.
**Porte de reprise :** avant qu'un module exige une règle différente par
pays (matrice nationale, ADR-0002 §3.5) ou par contrat, étendre `ValueVersion`
d'une portée structurée (sur le modèle de `ScopePayload` de
Governance/Authorization) et la résolution en conséquence.

### TD-0006-F — Aucune interface d'administration ; aucune capacité `configuration.*`

Comme TD-0004-E et TD-0003-A avant lui : aucune route, aucun contrôleur,
aucune capacité Governance/Authorization n'expose ce module. Toute
interaction passe par `ConfigurationValueManager`/`ConfigurationResolver`
appelés directement (tests, ou futur code métier), jamais par une requête
HTTP ni un écran.

**Risque :** identique à TD-0003-A/TD-0004-E.
**Mesure temporaire :** aucune route, aucune façade alternative.
**Porte de reprise :** créer et faire vérifier des capacités
`configuration.propose`/`configuration.approve`/`configuration.activate`
via `AuthorizationGate` avant toute route réelle, puis l'écran de
prévisualisation exigé par ADR-0002 §7.2.

### TD-0006-G — Niveau C3 : approbateur distinct exigé, choix conservateur non explicitement imposé par l'ADR

ADR-0002 §3.4 dit « une approbation simple peut suffire » pour le niveau
C3, sans trancher explicitement si l'auteur peut s'auto-activer.
`ConfigurationLevel::requiredApprovals()` exige ici, par choix assumé, un
approbateur distinct de l'auteur pour C3 comme pour C2 — jamais
d'auto-activation à aucun niveau, même principe que `GrantManager`.

**Risque :** aucun — ce choix est plus restrictif que le minimum exigé par
l'ADR, jamais moins protecteur.
**Mesure temporaire :** documentée sur l'enum lui-même.
**Porte de reprise :** si un besoin réel justifiait un jour qu'un C3 purement
cosmétique (texte d'aide, ordre d'affichage) soit auto-publiable par son
auteur, cela exigerait une clarification explicite de Koné/SIRR avant de
relâcher cette contrainte (CLAUDE.md §7).

## Porte de reprise générale

Compatible avec la porte de reprise déjà posée par `TD-0001` à `TD-0005` :

1. branchement réel du moteur d'autorisation sur les routes sensibles ;
2. activation d'un espace administrateur ou institutionnel en production ;
3. traitement d'opérations financières ou de données personnelles restreintes ;
4. audit de sécurité précédant le lancement public complet.

## Règle du registre

Toute nouvelle dette technique acceptée reçoit un identifiant `TD-NNNN`, un
risque explicite, une mesure temporaire et une porte de reprise. Une dette
documentée peut différer une correction ; elle ne peut jamais supprimer une
garantie constitutionnelle ni autoriser silencieusement un risque en
production.
