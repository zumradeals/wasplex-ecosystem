# TD-0002 — Suivis différés de l'intégration du moteur d'autorisation

**Statut :** différé et accepté pour poursuivre l'intégration
**Date :** 2026-07-23
**Origine :** revue finale de P003-B2
**Composant :** `App\Modules\Governance\Authorization\Integration`

## Décision de pilotage

Comme pour TD-0001, ce registre documente une limitation connue et volontaire plutôt que de la corriger localement pendant P003-B2, dont l'objet reste l'intégration Laravel du noyau, pas une nouvelle revue exhaustive.

## Éléments catalogués

### TD-0002-A — Résolution de la force de session limitée à deux paliers

`SessionAssuranceResolver` ne produit aujourd'hui que `weak` (par défaut) ou `strong` (reconfirmation de mot de passe récente, dans la fenêtre déjà configurée par `auth.password_timeout`). Aucun signal fiable et déjà présent dans le code ne distingue un palier `standard` propre à une session HTTP ordinaire (par exemple : connexion via un second facteur réellement vérifié pendant cette session précise, ou preuve d'appareil approuvé).

**Risque :** une capacité future exigeant explicitement `minimum_session_assurance = standard` ne pourra jamais être satisfaite par une session HTTP ordinaire telle que résolue aujourd'hui ; elle exigera systématiquement un renforcement jusqu'à `strong`, ce qui est prudent mais potentiellement trop strict.

**Mesure temporaire :** ne pas cataloguer de capacité réelle exigeant `standard` avant reprise de ce point ; les capacités de test restent seules concernées.

**Porte de reprise :** avant tout branchement du moteur sur une route sensible réelle exigeant explicitement `standard` (rejoint la porte de reprise de TD-0001).

### TD-0002-B — Absence de canal de récupération d'une élévation de session

`AuthorizationGate` et l'adaptateur HTTP distinguent `step_up_required`, mais aucun mécanisme ne permet encore à un client de déclencher réellement la reconfirmation Fortify puis de rejouer automatiquement la requête d'origine. Le module appelant doit aujourd'hui reconstruire lui-même ce parcours.

**Risque :** une intégration future pourrait être tentée de contourner l'étape en réessayant silencieusement avec une session non réellement renforcée.

**Mesure temporaire :** toute UI future de renforcement de session doit repartir d'une nouvelle résolution complète du sujet (aucune réutilisation d'un `AuthorizationResult` déjà obtenu comme preuve).

**Porte de reprise :** avant la première route sensible réelle utilisant `step_up_required` en production.

## Porte de reprise commune

Les deux points ci-dessus doivent être réévalués avant, au plus tard, la première des échéances déjà fixées par TD-0001 : branchement réel sur une route sensible, activation d'un espace administrateur ou institutionnel, traitement financier ou de données restreintes, ou audit précédant le lancement public.

## Règle du registre

Toute nouvelle dette technique acceptée reçoit un identifiant `TD-NNNN`, un risque explicite, une mesure temporaire et une porte de reprise. Une dette documentée peut différer une correction ; elle ne peut jamais supprimer une garantie constitutionnelle ni autoriser silencieusement un risque en production.
