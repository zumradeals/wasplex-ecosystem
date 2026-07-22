# Fonds Social — Pacte, financement et réalisation

**Statut :** spécification métier fondatrice  
**Source :** `sources/2026-07-21-entretien-fondateur-08-fonds-social.md`  
**Dépendances :** Constitution v1.5, Wallet immuable, AMD-0005 adopté, validation juridique

## 1. Double éligibilité

Le dépôt d'un vœu exige un abonnement publicitaire appartenant à une liste administrable de niveaux éligibles, puis une adhésion sociale distincte et active. L'accès au Fonds n'est pas une garantie de financement.

## 2. Flux séparés

Comptes distincts :

- recette de service de l'adhésion sociale ;
- fraction annoncée affectée à la réserve ;
- apport personnel réservé ;
- contributions collectives réservées par vœu ;
- réserve générale ;
- réserve d'urgence protégée ;
- paiement fournisseur ;
- frais explicites ;
- reliquats et restitutions.

Aucun de ces flux n'est confondu avec la trésorerie ordinaire ou les budgets publicitaires.

## 3. Calcul d'un appel

`apport = valeur_validée × taux_apport`

`besoin_collectif = valeur_validée - apport - aides_externes_affectées`

`contribution_théorique = besoin_collectif / participants_effectivement_éligibles`

Avant tout débit, une simulation exclut le demandeur et vérifie mandats, soldes, plafonds et suspensions. La contribution finale ne dépasse jamais le solde disponible ni les plafonds acceptés.

La plateforme réserve atomiquement les montants. Si la couverture n'est pas suffisante, elle utilise seulement une réserve autorisée, prolonge, redimensionne ou reporte le vœu. Elle ne recalcule pas silencieusement un montant supérieur sur les autres membres.

## 4. Mandat encadré

Le mandat comporte minimum et maximum par appel, plafonds par période, catégories, délai d'information et effets de révocation. Le membre peut choisir un plafond personnel compatible avec le minimum de son programme.

Révoquer le mandat empêche les appels futurs et suspend normalement les nouveaux dépôts, sans annuler les opérations irrévocablement engagées.

## 5. Réciprocité équitable

Trois résultats sont distincts :

- `honoré` : contribution exigible exécutée ;
- `protégé` : plafond atteint, solde insuffisant, erreur technique ou suspension non fautive ;
- `refusé` : opposition non justifiée à un appel conforme malgré capacité suffisante.

Seul un refus imputable peut diminuer la réciprocité. La pauvreté de solde n'est ni fraude ni faute morale. Les règles sont transparentes, versionnées, contestables et permettent une réhabilitation.

## 6. Éligibilité et priorisation

Le filtre ordinaire combine identité, adhésions actives, mandat, ancienneté, apport, réciprocité, justificatifs, quotas, faisabilité, capacité du Fonds et absence de fraude.

Le classement combine gravité, urgence, vulnérabilité, impact, ancienneté, préparation, aides antérieures et faisabilité.

Les avantages commerciaux ne peuvent jamais faire passer un vœu ordinaire devant une urgence vitale validée. Toute correction humaine est motivée et auditée.

## 7. Urgences

Le circuit d'urgence conserve identité, preuve médicale ou humanitaire, devis, fournisseur et contrôle anti-fraude. Il peut réduire ou supprimer l'apport et assouplir ancienneté ou réciprocité. Il ne supprime jamais les contrôles essentiels.

## 8. Réalisation

Le paiement direct et fractionné au fournisseur vérifié est prioritaire. L'assistance monétaire directe est exceptionnelle, plafonnée, justifiée, traçable et contrôlée après versement.

Les vœux de trésorerie libre sont interdits.

## 9. Apport, dépassement et reliquat

L'apport reste la propriété économique du demandeur tant qu'aucun engagement irréversible n'est pris. Toute déduction correspond à un coût réel, annoncé et prouvé.

Aucun dépassement n'est payé sans nouvelle autorisation et nouvelle couverture.

La destination du reliquat est définie avant l'appel. Il ne devient pas automatiquement une somme libre pour le bénéficiaire.

## 10. Prestataires

Un fournisseur ponctuellement vérifié n'est pas automatiquement un partenaire stratégique ni une nouvelle catégorie d'acteur. Identité, capacité, devis et destination de paiement sont contrôlés.

Les paiements importants suivent des jalons. Non-livraison : gel du solde, enquête, recours, remboursement recherché et exclusion éventuelle.

## 11. Transparence

Les membres consultent les agrégats financiers, l'état du vœu, les frais et la preuve générale de réalisation. Identité, coordonnées, dossier médical et justificatifs privés du bénéficiaire restent protégés.

## 12. Conditions préalables au lancement

- avis juridique sur la qualification du mécanisme ;
- dispositif autorisé de garde et paiement ;
- politique KYC/LBC-FT ;
- séparation comptable et rapprochement ;
- gouvernance des décisions sociales et médicales ;
- politique de conflits d'intérêts ;
- tests de solvabilité et de charge ;
- recours et audit indépendant.
