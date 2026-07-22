# Alertes — Cycle, sécurité et résolution

**Statut :** spécification métier fondatrice  
**Source :** `sources/2026-07-21-entretien-fondateur-11-alertes-securite-restitutions.md`  
**Dépendances :** Constitution v1.5, Institutions affiliées, sponsorisation, AMD-0007 adopté

## 1. Sous-domaines séparés

- alertes communautaires ;
- disparitions et protection des personnes ;
- signalements de sécurité ;
- SOS et routage institutionnel ;
- correspondances ;
- restitution de biens ;
- réunification ou orientation de personnes ;
- récompenses de biens ;
- sponsorisation et diffusion éditoriale ;
- alertes nationales critiques et diffusion souveraine.

Ils partagent un registre de dossiers mais pas nécessairement les mêmes états, données ou permissions.

## 2. Création sans authentification

Un SOS peut être soumis sans session utilisateur avec données minimales, limites anti-abus et indication `non vérifié`.

Cela exige toujours un canal réseau fonctionnel. En mode hors ligne, l'application conserve au mieux un brouillon et propose les appels ou SMS officiels disponibles. Elle n'affiche jamais `transmise` sans accusé technique.

## 3. Machine d'états SOS

| État | Preuve |
|---|---|
| created | écriture serveur et horodatage |
| transmitted | destinataire, canal et sortie confirmée |
| received | accusé technique ou humain |
| accepted | acte nominatif d'une institution habilitée |
| processing | mise à jour opérationnelle |
| resolved | clôture justifiée et confirmations requises |

États latéraux : `unanswered`, `refused`, `transferred`, `cancelled`, `expired`, `impossible`, `disputed`, `closed_unresolved`.

Une transition invalide est rejetée côté serveur. Une correction ajoute un événement ; elle ne réécrit pas l'historique.

## 4. Publicité et pauses

Une pause utile ne compte ni comme publicité accomplie ni comme quota consommé et ne crédite aucun WP par défaut.

Urgence, communiqué, alerte organique, emplacement sponsorisé et conseil utilisent des files de priorité distinctes. Le paiement ne modifie jamais la gravité.

## 5. Visibilité et données

La visibilité est imposée par politique de catégorie, puis éventuellement réduite par le déclarant ; elle ne peut pas être élargie au-delà du maximum sûr.

La réponse publique est un objet de diffusion minimisé distinct du dossier source. Localisation exacte, téléphone, document complet, médical, témoin et preuve de propriété restent hors de la vue publique.

## 6. Disparitions

Toute publication de mineur, personne vulnérable ou adulte potentiellement en fuite exige une revue renforcée.

Le système conserve le motif et le lien du déclarant, vérifie les risques de garde, violence ou traque et peut limiter la diffusion aux institutions.

Une personne retrouvée est mise en sécurité et réunifiée ou orientée selon l'autorité légitime. Elle n'est jamais « restituée » par un code destiné aux biens.

## 7. Correspondances et restitution de biens

Le moteur produit des candidats, jamais une décision finale sensible.

Les caractéristiques secrètes du bien ne sont pas révélées au proposant. Après validation, un code à usage unique et expirant est émis. La remise et la réception sont deux événements distincts.

## 8. Récompenses

Seuls les dossiers de biens autorisés peuvent porter une récompense.

États : `funding_pending`, `reserved`, `eligible_for_release`, `released`, `refunded`, `blocked`, `disputed`.

Aucune récompense n'est affichée avant réservation réussie. Elle reste bloquée jusqu'à restitution et fin du délai de contestation.

Aucune récompense pour une personne, un SOS ou l'exécution normale d'une mission publique.

## 9. Sponsorisation

Le boost est distinct de la récompense :

- boost : paiement pour portée ;
- récompense : somme promise au restituteur d'un bien.

Les deux utilisent des comptes et cycles séparés.

## 10. Alertes nationales critiques

Une alerte nationale critique concerne un danger majeur pour une population ou un territoire : catastrophe, attaque, menace militaire, risque sanitaire extrême, évacuation ou autre événement officiellement qualifié.

### Autorité et émission

- seuls des comptes nominatifs d'une institution souveraine explicitement habilitée peuvent préparer l'alerte ;
- l'émission exige une authentification renforcée et deux validations nominatives distinctes ;
- le territoire, la période, la catégorie, les consignes, la source officielle et les langues doivent être définis ;
- la création, les validations, l'émission, les modifications, l'expiration et la révocation sont auditées ;
- aucun rôle Wasplex ordinaire ne peut fabriquer ou élever une alerte à ce niveau.

### Comportement dans Wasplex

Lorsqu'une alerte valide est reçue :

1. la publicité ou le contenu en cours est interrompu ;
2. aucun événement publicitaire interrompu n'est frauduleusement marqué comme accompli ;
3. l'alerte se superpose à toute interface Wasplex ;
4. elle présente des consignes courtes, authentifiées, accessibles et traduisibles ;
5. l'utilisateur peut acquitter sa lecture sans empêcher les mises à jour critiques ;
6. l'alerte reste consultable dans un historique officiel.

### Comportement hors de Wasplex

L'application Android peut utiliser les mécanismes de notification prioritaires autorisés, mais le système d'exploitation, les permissions de l'utilisateur et le constructeur conservent le contrôle final. La version Web ne peut pas garantir l'affichage lorsque le navigateur est fermé.

Une diffusion à tous les téléphones d'un territoire, y compris sans Wasplex installé, relève d'un partenariat souverain avec les opérateurs et des dispositifs de diffusion cellulaire d'urgence. Wasplex complète ces dispositifs ; il ne prétend pas les remplacer.

### Interdictions

Une alerte nationale critique :

- n'est jamais sponsorisée ;
- ne contient aucune publicité ;
- n'accorde aucun WP ;
- ne dépend d'aucun abonnement ;
- ne sert ni au ciblage commercial ni à la collecte opportuniste de données ;
- ne peut être testée sur la population sans signalement explicite d'exercice.

## 11. Conservation et audit

Le retrait public masque la diffusion mais ne détruit pas automatiquement dossier, preuves, événements ou journaux. Les durées varient par catégorie, finalité et obligation.

Toute consultation sensible, modification, transmission, correspondance, remise, paiement et clôture est auditée.

## 12. Administration

Sont configurables et versionnés : catégories, preuves, visibilité, durée, territoire, priorité, cadence Feed, limites, sponsorisation, récompenses, rétention et institutions destinataires.

Les invariants de sécurité ne sont pas modifiables par simple configuration.
