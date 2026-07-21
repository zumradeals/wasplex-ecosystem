# Identité — Niveaux et preuves

**Statut :** spécification proposée  
**Source :** `sources/2026-07-21-entretien-fondateur-14-identite-kyc-antifraude.md`

## 1. Axes séparés

Le compte conserve des états distincts :

- `account_state` : invité, actif, suspendu, fermé ;
- `contact_assurance` : non confirmé, confirmé ;
- `identity_assurance` : non déclarée, déclarée, vérifiée, renforcée ;
- `uniqueness_assurance` : inconnue, probable, suffisante, contestée ;
- `session_assurance` : faible, standard, forte ;
- `organization_status` : aucun, représentant en attente, habilité, suspendu.

Aucun niveau total unique ne remplace ces axes.

## 2. Capacités

Une politique de capacité précise :

- niveau minimal par axe ;
- pays et âge ;
- montant, fréquence et cumul ;
- facteurs d'authentification ;
- appareil nouveau ou approuvé ;
- délai de sécurité ;
- contrôles complémentaires.

Les noms commerciaux et seuils sont configurables ; les distinctions de preuve ne le sont pas.

## 3. Accès progressif

### Invité

Informations publiques, numéros d'urgence, SOS minimal et création de compte. Aucun solde durable ni opération financière.

### Canal confirmé

Publicités ordinaires autorisées, WP provisoires plafonnés et fonctions communautaires de base.

### Identité déclarée

Profil déclaré plus complet, sans accès automatique aux opérations exigeant une personne vérifiée.

### Identité vérifiée

Retraits, moyens de paiement nominatifs et mécanismes exigeant l'unicité, dans les plafonds du pays et du risque.

### Identité renforcée

Opérations élevées, inhabituelles, récupération complexe, mandat social important ou capacité institutionnelle critique.

Une Carte Wasplex exige le niveau défini par sa version produit ; elle n'exige pas automatiquement le niveau maximal.

## 4. Preuves

Chaque vérification conserve source, contrôleur, date, expiration, pays, résultat, motifs, documents et version de politique.

Les preuves possibles sont combinées selon le risque. Téléphone, e-mail, appareil, IP et document isolé ont chacun des limites.

## 5. Parcours alternatif

Le parcours sans document officiel :

- ne donne pas automatiquement toutes les capacités ;
- utilise plusieurs preuves indépendantes ;
- impose une revue humaine ;
- ouvre des plafonds progressifs ;
- expire ou est réexaminé ;
- n'utilise pas un témoin communautaire comme pouvoir de contrôle.

## 6. Appareils partagés

Plusieurs comptes sont possibles avec sessions séparées et PIN individuels.

Le système peut empêcher des événements publicitaires simultanés incompatibles et renforcer les retraits, sans déclarer automatiquement tous les comptes frauduleux.

## 7. Doublons

Un doublon peut être fermé, relié ou consolidé après vérification. Les écritures financières ne sont jamais copiées comme un simple historique : chaque ledger reste intact et toute migration produit des écritures de transfert et preuves d'origine.

## 8. Gains pré-KYC

Un plafond limite les WP provisoires avant identité vérifiée.

Atteindre le plafond arrête les nouveaux événements rémunérables concernés ; cela ne détruit pas le solde existant. Une procédure explique vérification, contestation, clôture et traitement légal des montants.
