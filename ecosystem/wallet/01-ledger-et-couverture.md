# Wallet — Ledger et couverture

**Statut :** spécification proposée  
**Source :** `sources/2026-07-21-entretien-fondateur-15-paiements-retraits-couverture-wallet.md`

## 1. Ledger

Le Wallet repose sur un registre en partie double.

Toute opération :

- possède un identifiant idempotent ;
- équilibre débits et crédits ;
- référence sa source métier et externe ;
- utilise une devise et un compartiment ;
- est horodatée côté serveur ;
- conserve auteur, règle et version ;
- n'est jamais modifiée ou supprimée ;
- est corrigée par une nouvelle écriture compensatoire.

Le solde est une projection reconstruite du ledger, jamais la source de vérité primaire.

## 2. États de valeur

- WP provisoires : conditionnels et non utilisables ;
- WP disponibles : acquis et exigibles ;
- WP réservés : acquis mais engagés ;
- WP contestés : isolés par dossier ;
- WP débités : réglés ou consommés ;
- WP annulés : valeur conditionnelle invalidée avec preuve.

Un WP disponible ne redevient pas provisoire.

## 3. Compartiments

Au minimum :

- budgets annonceurs non consommés ;
- part utilisateur provisoire ;
- couverture des WP disponibles ;
- couverture des WP réservés ;
- retraits en transit ;
- recettes propres Wasplex ;
- Fonds Social par sous-compte ;
- pools des Cartes ;
- dépôts utilisateurs si autorisés ;
- frais, taxes et provisions ;
- créances et pertes propres de Wasplex.

Aucun mouvement inter-compartiment sans type d'opération autorisé.

## 4. Sources

Une émission disponible exige :

1. source économique encaissée ou irrévocablement confirmée ;
2. fonds placés dans le compartiment de couverture ;
3. rapprochement externe ;
4. écriture équilibrée ;
5. absence de double affectation.

Un bonus Wasplex exige le transfert préalable de ressources propres vers la couverture.

## 5. Ratio

```text
couverture nette admissible
= fonds admissibles
- sorties irrévocablement engagées non encore dénouées
- montants indisponibles non reconnus comme couverture mobilisable

obligations exigibles
= WP disponibles
+ WP réservés remboursables
+ autres droits utilisateurs exigibles

ratio = couverture nette admissible / obligations exigibles
```

Le ratio doit être supérieur ou égal à 100 %.

Si le ratio est inférieur, le système :

- arrête toute nouvelle émission aggravante ;
- bloque les transferts de couverture vers Wasplex ;
- déclenche incident critique et rapprochement ;
- protège le ledger ;
- active la gouvernance de crise ;
- informe selon la gravité et les obligations.

## 6. Qualité de couverture

La couverture est suivie par prestataire, pays, devise, disponibilité, concentration, blocage, délai de sortie et risque de contrepartie.

La diversification ne remplace pas le rapprochement central.

## 7. Rapprochement

Rapprochements automatisés fréquents et clôture quotidienne entre ledger, banques, Mobile Money, PSP, opérations en transit et relevés.

Tout écart possède montant, ancienneté, propriétaire, cause présumée et échéance.

## 8. Accès administratifs

Aucun administrateur ne modifie directement un solde.

Les opérations exceptionnelles utilisent double contrôle, justification, pièces, limites, séparation des fonctions et audit.

## 9. Protection juridique

La séparation technique et comptable est obligatoire mais ne suffit pas à elle seule contre une insolvabilité.

Avant activation par pays, Wasplex documente le détenteur légal des fonds, le contrat de garde, les droits des utilisateurs, le traitement en liquidation et les autorisations nécessaires.
