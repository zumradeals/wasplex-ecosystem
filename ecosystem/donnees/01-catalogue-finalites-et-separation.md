# Données — Catalogue, finalités et séparation

**Statut :** spécification proposée  
**Source :** `sources/2026-07-21-entretien-fondateur-13-donnees-consentement-profilage.md`

## 1. Registre obligatoire

Aucun champ de données ne peut entrer en production sans fiche de registre contenant :

- nom et description ;
- personne ou objet concerné ;
- source déclarée, observée ou déduite ;
- finalité primaire et usages secondaires autorisés ;
- base de traitement applicable ;
- caractère obligatoire ou facultatif ;
- sensibilité ;
- durée active, archivage et suppression ;
- services propriétaires et sous-traitants ;
- pays de stockage et transferts ;
- droits et exceptions ;
- journalisation requise.

Une finalité future indéterminée n'est pas une finalité valide.

## 2. Domaines séparés

Au minimum, les domaines suivants sont séparés logiquement et par habilitations :

- identité de compte ;
- KYC et biométrie éventuelle ;
- profil publicitaire ;
- Wallet et opérations ;
- Fonds Social ;
- Alertes et santé ;
- Institutions ;
- Cartes et partenaires ;
- antifraude et sécurité ;
- consentements et droits ;
- analytique agrégée.

Une donnée sensible ne migre pas vers le domaine publicitaire par simple configuration.

## 3. Niveaux de localisation

- territoire déclaré ;
- zone approximative ;
- position précise ponctuelle ;
- suivi temporaire explicite ;
- dernière position de sécurité.

Chaque niveau possède sa propre finalité. La publicité n'active jamais un suivi précis permanent.

## 4. Données déduites

Toute donnée déduite conserve :

- sources utilisées ;
- modèle ou règle et version ;
- date de calcul ;
- finalité ;
- durée de validité ;
- degré d'incertitude ;
- méthode de contestation ;
- décisions qu'elle peut ou ne peut pas influencer.

Les inférences sensibles commerciales sont interdites, même si elles semblent statistiquement possibles.

## 5. Photos et biométrie

Une photo stockée pour un dossier n'autorise aucune extraction biométrique.

La création ou comparaison d'un gabarit facial, vocal, digital ou comportemental constitue une capacité distincte, désactivée par défaut, exigeant étude, base légale, autorisation applicable, sécurité renforcée et décision constitutionnelle ou de gouvernance compétente.

## 6. Conservation

La suppression signifie selon le cas :

- effacement ;
- anonymisation irréversible vérifiée ;
- archivage restreint lorsque requis ;
- détachement des systèmes commerciaux.

Les durées sont définies par catégorie et pays, jamais par un délai universel arbitraire.

## 7. Pseudonymisation

Un identifiant remplacé ou chiffré ne rend pas automatiquement les données anonymes.

Toute donnée pouvant être reliée à une personne par Wasplex ou un tiers autorisé reste soumise aux protections personnelles.
