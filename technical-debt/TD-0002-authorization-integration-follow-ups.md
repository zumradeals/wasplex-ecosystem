# TD-0002 — Suivis différés de l'intégration du moteur d'autorisation

**Statut :** TD-0002-A affiné et maintenu ouvert (voir raison précise ci-dessous) ; TD-0002-B clos par correction
**Date :** 2026-07-23 (créé), 2026-07-24 (reprise P003-B4)
**Origine :** revue finale de P003-B2 ; reprise P003-B4
**Composant :** `App\Modules\Governance\Authorization\Integration`

## Décision de pilotage

Comme pour TD-0001, ce registre documente une limitation connue et volontaire plutôt que de la corriger localement pendant P003-B2, dont l'objet reste l'intégration Laravel du noyau, pas une nouvelle revue exhaustive. P003-B4 reprend les deux points ci-dessous avant tout branchement futur sur une route sensible réelle (Advertising × Authorization).

## Éléments catalogués

### TD-0002-A — Résolution de la force de session limitée à deux paliers

**Reprise du 2026-07-24 (P003-B4) : vérification empirique effectuée, correction jugée impossible sans inventer un mécanisme — le point reste donc ouvert, avec une raison désormais précise plutôt qu'une hypothèse.**

`SessionAssuranceResolver` ne produit aujourd'hui que `weak` (par défaut) ou `strong` (reconfirmation de mot de passe récente, dans la fenêtre déjà configurée par `auth.password_timeout`). La reprise P003-B4 a vérifié empiriquement, en lisant le code réel de `laravel/fortify` et `laravel/passkeys` et en exécutant un véritable aller-retour HTTP à travers le pipeline de login (voir `SessionAssuranceStandardTierTest`), que :

- `TwoFactorAuthenticatedSessionController::store()` (login 2FA) et `PasskeyLoginController::store()` (login passkey) ne persistent, l'un comme l'autre, strictement rien dans la session après un login réussi, au-delà de `session()->regenerate()` ;
- `TwoFactorLoginRequest::hasValidCode()`/`validRecoveryCode()` suppriment même explicitement `login.id` de la session dès que le code est validé — la présence de cette clé de stadification ne prouve donc jamais un login 2FA réussi, et son absence est le comportement normal du succès, pas un signal exploitable ;
- l'événement `Laravel\Passkeys\Events\PasskeyVerified` est déclenché à l'identique par le login passkey et par la reconfirmation mid-session passkey (`PasskeyConfirmationController::store()`, qui doit rester `strong`) : il ne permet donc pas de distinguer les deux cas ;
- un test d'intégration exécutant un vrai login 2FA (code TOTP réellement généré et vérifié par Fortify) confirme que la session résultante résout `weak`, pas `standard` (`SessionAssuranceStandardTierTest`).

Ajouter nous-mêmes un marqueur de session (par exemple un listener applicatif sur `ValidTwoFactorAuthenticationCodeProvided` ou `PasskeyVerified`) inventerait une persistance que Fortify ne fournit pas lui-même — précisément ce que ce resolver s'interdit. La hiérarchie complète (Weak < Standard < Strong) et les conditions exactes de chaque palier sont désormais documentées dans le docblock de `SessionAssuranceResolver`, y compris cette impossibilité actuelle.

**Ce qui n'est pas affecté :** le moteur d'autorisation (`ConditionsMatcher`, `AuthorizationEngine`) traite déjà correctement `standard` comme valeur de contexte lorsqu'elle est présente — voir `SessionAssuranceFloorTest`, préexistant et toujours vert. Seule la *résolution* depuis une session HTTP réelle vers cette valeur reste bloquée.

**Risque :** inchangé — une capacité future exigeant explicitement `minimum_session_assurance = standard` ne peut toujours pas être satisfaite par une session HTTP ordinaire ; elle exige systématiquement un renforcement jusqu'à `strong`, ce qui est prudent mais potentiellement trop strict.

**Mesure temporaire :** inchangée — ne pas cataloguer de capacité réelle exigeant `standard` avant reprise de ce point ; les capacités de test restent seules concernées.

**Porte de reprise :** avant de rouvrir ce point, une décision distincte doit choisir *comment* produire un signal `standard` fiable sans l'inventer côté application — par exemple : une future version de Fortify persistant elle-même un tel marqueur, ou une décision explicite (ADR) d'ajouter un mécanisme applicatif dédié (listener + session key versionnés et documentés) reconnu comme une extension légitime plutôt qu'une déduction. Rejoint la porte de reprise de TD-0001 : avant tout branchement du moteur sur une route sensible réelle exigeant explicitement `standard`.

### TD-0002-B — Absence de canal de récupération d'une élévation de session

**Corrigé le 2026-07-24 (P003-B4), dans les limites suivantes.**

`AuthorizationGate` et l'adaptateur HTTP distinguent `step_up_required`. La réponse HTTP structurée (`AuthorizationFailureResponder::forOutcome()`) porte désormais deux champs non sensibles supplémentaires, uniquement pour cette décision :

- `required_session_assurance` : le palier effectivement exigé (`standard` ou `strong`), déjà calculé par `ConditionsMatcher`/`AuthorizationEngine` comme le plus exigeant entre la capacité et le grant (P003-B1.1 §1) — jamais une nouvelle donnée déduite ici, seulement exposée ;
- `step_up_action` : un indice non sensible du type d'action Fortify attendue (`password_confirmation` pour `strong` ; `two_factor_or_passkey_verification` pour `standard`, théorique tant que TD-0002-A reste ouverte).

Ni le grant, ni la politique, ni aucun autre détail interne ne sont exposés (`StepUpRecoveryTest::test_step_up_required_response_for_a_strong_capability_indicates_password_confirmation` vérifie l'absence des fragments `grant`/`policy`/`stable_key`/`capability_definition`, comme pour un refus ordinaire).

L'impossibilité de rejouer une décision antérieure est garantie par construction (`AuthorizationGate::authorize()`/`::evaluate()` n'acceptent qu'une `AuthorizationRequest` fraîche — aucune méthode n'accepte un `AuthorizationResult` ou une exception d'issue comme preuve, vérifié par réflexion dans `test_authorization_gate_never_accepts_a_previous_result_as_input`) et démontrée par test comportemental (`test_a_genuine_session_reinforcement_only_succeeds_through_a_fresh_reevaluation`, `test_a_revoked_grant_is_never_masked_by_a_previous_step_up_attempt`) : une révocation survenue entre une première tentative et un renforcement réel de session n'est observée que parce que la seconde tentative repasse entièrement par `AuthenticatedSubjectHttpResolver` puis `AuthorizationGate`.

**Explicitement hors périmètre (inchangé) :** la construction d'un véritable parcours UI de renforcement — redirection vers l'action Fortify appropriée, retour, rejeu automatique de la requête d'origine — reste à la charge du module appelant, au moment où une vraie route sensible l'exigera. Aucune route, aucun écran, aucune capacité métier réelle exigeant `standard` n'a été ajouté par cette correction.

**Risque résiduel :** aucun nouveau risque introduit ; le risque original (une intégration future tentée de contourner l'étape en réessayant silencieusement) reste couvert par la garantie de construction ci-dessus, qui ne dépend d'aucune discipline d'appelant.

**Porte de reprise :** avant la première route sensible réelle utilisant `step_up_required` en production, le module appelant doit construire son propre parcours de renforcement (redirection Fortify réelle, retour, rejeu de la requête d'origine) au-dessus de ce canal, en respectant la garantie de non-rejeu ci-dessus.

## Porte de reprise commune

Les deux points ci-dessus doivent être réévalués avant, au plus tard, la première des échéances déjà fixées par TD-0001 : branchement réel sur une route sensible, activation d'un espace administrateur ou institutionnel, traitement financier ou de données restreintes, ou audit précédant le lancement public.

## Règle du registre

Toute nouvelle dette technique acceptée reçoit un identifiant `TD-NNNN`, un risque explicite, une mesure temporaire et une porte de reprise. Une dette documentée peut différer une correction ; elle ne peut jamais supprimer une garantie constitutionnelle ni autoriser silencieusement un risque en production.
