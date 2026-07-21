# Wallet — Retraits et finalité

**Statut :** spécification proposée

## 1. Configuration d'un canal

Chaque canal précise pays, devise, PSP, bénéficiaires autorisés, KYC, minimum, plafonds, frais, taxes, délais, disponibilité et point d'irréversibilité.

Les valeurs sont versionnées et affichées avant confirmation.

## 2. Cycle

États minimaux :

- `draft` ;
- `awaiting_user_confirmation` ;
- `under_review` ;
- `reserved` ;
- `submitted` ;
- `provider_accepted` ;
- `paid` ;
- `failed_confirmed` ;
- `cancelled` ;
- `refunded` ;
- `disputed` ;
- `unknown_reconciliation`.

Chaque état correspond à une preuve. `paid` exige confirmation fiable ou rapprochement.

## 3. Réservation

Après confirmation, les WP disponibles sont réservés atomiquement. L'opération échoue si la disponibilité a changé.

Succès : réserve débitée définitivement.  
Échec confirmé : réserve libérée.  
État inconnu : réserve maintenue jusqu'au rapprochement afin d'éviter le double paiement.

## 4. Authentification

Identité, moyen bénéficiaire et facteurs sont proportionnés au montant et au risque conformément à l'article 16.

Un nouveau moyen, appareil, destinataire ou retrait inhabituel peut déclencher délai de sécurité et revue.

## 5. Bénéficiaire

Par défaut, retrait vers un moyen vérifié rattaché à l'utilisateur.

Un tiers bénéficiaire n'est activé que si légalement autorisé, explicitement confirmé, plafonné et non assimilable à un transfert anonyme.

## 6. Frais

Avant confirmation :

- débit WP total ;
- frais PSP ;
- frais Wasplex autorisés ;
- taxes ;
- conversion ;
- montant net reçu.

Un frais externe irréversible après échec est traité selon la règle préacceptée et sa preuve.

## 7. Erreur de destination

L'utilisateur confirme une destination lisible et masquée de façon sûre.

Après irréversibilité, récupération non garantie si Wasplex a exécuté exactement l'instruction confirmée. Wasplex fournit les preuves et assiste la contestation.

Une erreur de Wasplex ou du PSP n'est pas supportée par l'utilisateur, sous réserve de l'enquête et du cadre applicable.

## 8. File d'exécution

Les retraits validés suivent une politique publique, normalement ordre de validation par canal, avec exceptions uniquement légales, techniques ou de sécurité documentées.

Aucun avantage lié à l'abonnement, l'influence, la proximité ou la richesse.

## 9. Annulation

Possible avant le point d'irréversibilité. Après, seule une procédure de rappel, remboursement, médiation ou décision autorisée peut inverser économiquement l'effet.

## 10. Délai et information

Délai indicatif, limite ordinaire, cause générale de retard, état des fonds, canal alternatif et mises à jour sont visibles. Wasplex ne promet pas l'instantanéité d'un tiers.
