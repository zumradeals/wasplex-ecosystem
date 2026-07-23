# Fonds Social — Pacte, financement et réalisation

**Statut :** spécification métier fondatrice  
**Source :** `sources/2026-07-21-entretien-fondateur-08-fonds-social.md`, `sources/2026-07-23-clarification-fondateur-20-fonds-social-modele-economique.md`  
**Dépendances :** Constitution v1.5, Wallet immuable, AMD-0005 et AMD-0015 adoptés, validation juridique

## 1. Double éligibilité

Le dépôt d'un vœu exige un abonnement publicitaire appartenant à une liste administrable de niveaux éligibles, puis une adhésion sociale distincte et active. L'accès au Fonds n'est pas une garantie de financement.

## 2. Flux séparés

Comptes distincts :

- recette de service de l'adhésion sociale ;
- fraction annoncée affectée à la réserve ;
- apport personnel réservé ;
- contributions collectives réservées par vœu ;
- frais d'orchestration Wasplex ;
- fonds souverain de garantie ;
- réserve d'urgence protégée ;
- paiement fournisseur ;
- frais externes directement imputables ;
- reliquats et restitutions.

Aucun de ces flux n'est confondu avec la trésorerie ordinaire, les budgets publicitaires ou les fonds utilisateurs. Les frais d'orchestration constituent un revenu Wasplex seulement après satisfaction des conditions de réalisation. Le fonds souverain demeure un compartiment communautaire affecté et n'est pas un revenu libre de Wasplex.

## 3. Calcul d'un appel et prélèvement composite

`apport = valeur_validée × taux_apport`

`besoin_collectif = valeur_validée - apport - aides_externes_affectées`

`contribution_théorique = besoin_collectif / participants_effectivement_éligibles`

Pour chaque participant éligible, la version active calcule ensuite séparément :

`débit_total = contribution_vœu + frais_wasplex + dotation_souveraine + frais_externes`

Avant tout débit, une simulation exclut le demandeur et vérifie mandats, soldes, plafonds, suspensions, couverture du vœu et destinations comptables. Le débit final ne dépasse jamais le solde disponible ni le plafond total accepté.

### Configuration de référence

Pour un vœu de 100 000 FCFA, un apport de 30 000 FCFA et 700 participants :

| Destination par participant | Montant de référence | Total |
|---|---:|---:|
| Contribution au vœu | 100 FCFA | 70 000 FCFA |
| Frais d'orchestration Wasplex | 60 FCFA | 42 000 FCFA |
| Fonds souverain de garantie | 40 FCFA | 28 000 FCFA |
| **Débit total** | **200 FCFA** | **140 000 FCFA** |

Apport inclus, l'appel mobilise 170 000 FCFA : 100 000 FCFA réalisent le vœu, 42 000 FCFA rémunèrent Wasplex et 28 000 FCFA renforcent le fonds souverain.

Les montants 100/60/40 constituent une configuration de référence, jamais une constante codée en dur. Chaque composante possède son propre montant ou taux, minimum, maximum, plafond, règle d'arrondi, date d'effet et version.

La plateforme réserve atomiquement les composantes. Si la couverture n'est pas suffisante, elle utilise seulement une réserve autorisée, prolonge, redimensionne ou reporte le vœu. Elle ne recalcule pas silencieusement un montant supérieur sur les autres membres.

## 4. Mandat encadré

Le mandat comporte minimum et maximum par appel, plafond total par période, catégories, délai d'information, ventilation du prélèvement composite et effets de révocation. Le membre peut choisir un plafond personnel compatible avec le minimum de son programme.

Le consentement couvre le débit total — contribution au vœu, frais Wasplex, dotation souveraine et frais externes — et non la seule part destinée au bénéficiaire.

Révoquer le mandat empêche les appels futurs et suspend normalement les nouveaux dépôts, sans annuler les opérations irrévocablement engagées.

## 5. Réciprocité équitable

Trois résultats sont distincts :

- `honoré` : prélèvement composite exigible exécuté ;
- `protégé` : plafond atteint, solde insuffisant, erreur technique ou suspension non fautive ;
- `refusé` : opposition non justifiée à un appel conforme malgré capacité suffisante.

Seul un refus imputable peut diminuer la réciprocité. La pauvreté de solde n'est ni fraude ni faute morale. Les règles sont transparentes, versionnées, contestables et permettent une réhabilitation.

Les frais Wasplex et la dotation souveraine n'altèrent pas séparément la réputation : seule l'exécution cohérente de l'appel accepté est évaluée.

## 6. Éligibilité et priorisation

Le filtre ordinaire combine identité, adhésions actives, mandat, ancienneté, apport, réciprocité, justificatifs, quotas, faisabilité, capacité du Fonds et absence de fraude.

Le classement combine gravité, urgence, vulnérabilité, impact, ancienneté, préparation, aides antérieures et faisabilité.

Les avantages commerciaux ne peuvent jamais faire passer un vœu ordinaire devant une urgence vitale validée. Toute correction humaine est motivée et auditée.

## 7. Urgences

Le circuit d'urgence conserve identité, preuve médicale ou humanitaire, devis, fournisseur et contrôle anti-fraude. Il peut réduire ou supprimer l'apport et assouplir ancienneté ou réciprocité. Il ne supprime jamais les contrôles essentiels.

## 8. Réalisation

Le paiement direct et fractionné au fournisseur vérifié est prioritaire. L'assistance monétaire directe est exceptionnelle, plafonnée, justifiée, traçable et contrôlée après versement.

Les vœux de trésorerie libre sont interdits.

Les frais d'orchestration deviennent acquis à Wasplex et la dotation souveraine devient définitive selon les conditions probantes de réalisation de la version active. Une simple réservation ne constitue ni un revenu acquis ni une dotation définitivement disponible.

## 9. Apport, dépassement et reliquat

L'apport reste la propriété économique du demandeur tant qu'aucun engagement irréversible n'est pris. Toute déduction correspond à un coût réel, annoncé et prouvé.

Le coût économique total peut dépasser la valeur du vœu par application annoncée des frais Wasplex et de la dotation souveraine. Aucun dépassement imprévu n'est payé sans nouvelle autorisation et nouvelle couverture.

Le reliquat prévu par la règle active rejoint le fonds souverain de garantie. Une somme erronée, non autorisée ou prélevée au-delà d'un plafond n'est jamais un reliquat : elle est libérée, corrigée ou restituée par une opération traçable.

## 10. Prestataires

Un fournisseur ponctuellement vérifié n'est pas automatiquement un partenaire stratégique ni une nouvelle catégorie d'acteur. Identité, capacité, devis et destination de paiement sont contrôlés.

Les paiements importants suivent des jalons. Non-livraison : gel du solde, enquête, recours, remboursement recherché et exclusion éventuelle.

## 11. Transparence

Les membres consultent les agrégats financiers, l'état du vœu, la contribution au vœu, les frais Wasplex, la dotation souveraine, les frais externes et la preuve générale de réalisation. Identité, coordonnées, dossier médical et justificatifs privés du bénéficiaire restent protégés.

Chaque opération conserve la publication de configuration qui l'a gouvernée. Une modification future ne réécrit ni le prélèvement, ni le revenu Wasplex, ni la réserve, ni le reliquat antérieurs.

## 12. Conditions préalables au lancement

- avis juridique sur la qualification du mécanisme et du fonds souverain ;
- dispositif autorisé de garde, collecte, débit et paiement ;
- politique KYC/LBC-FT ;
- séparation comptable et rapprochement ;
- gouvernance des décisions sociales et médicales ;
- gouvernance de l'emploi du fonds souverain ;
- politique de conflits d'intérêts ;
- tests de solvabilité, d'arrondi et de charge ;
- recours et audit indépendant.
