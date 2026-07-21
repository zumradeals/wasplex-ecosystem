# WasPoint et états du Wallet

**Statut :** spécification métier issue de la doctrine fondatrice  
**Dépendances :** `CONSTITUTION.md`, AMD-0003 proposé, validation juridique locale

## 1. Définition fonctionnelle

Le WP est l'unité dans laquelle Wasplex représente les droits économiques internes des utilisateurs. La doctrine de produit ne le présente ni comme monnaie, ni comme cryptomonnaie, ni comme investissement.

Cette qualification fonctionnelle ne préjuge pas de sa qualification légale. Celle-ci dépend notamment de l'émission contre remise de fonds, de la garantie de remboursement, de la transférabilité et de l'acceptation du WP par des tiers.

## 2. Parité et responsabilité économique

La parité de référence est 1 WP = 1 FCFA. Tout WP disponible et retirable constitue donc une obligation économique mesurable de Wasplex envers son titulaire.

Wasplex ne doit rendre disponible aucun WP sans identifier :

- l'événement qui a créé le droit ;
- la source financière correspondante ;
- la version des règles appliquées ;
- les écritures comptables ;
- l'état des fonds nécessaires à son règlement.

## 3. Les trois états

| État | Nature | Utilisable | Retirable |
|---|---|---:|---:|
| Provisoire | Droit conditionnel en cours de contrôle | Non | Non |
| Disponible | Droit définitivement validé | Oui | Oui, sous réserve des règles de retrait |
| Réservé | Part du disponible bloquée pour une opération déterminée | Non | Non, hors opération concernée |

Un montant réservé reste attribué à l'utilisateur. La réservation ne constitue ni un revenu de Wasplex ni une confiscation.

## 4. Transitions obligatoires

- événement qualifié → inscription provisoire ;
- validation complète → transfert du provisoire vers le disponible ;
- rejet → annulation comptable motivée du provisoire ;
- demande de retrait, paiement ou engagement → transfert du disponible vers le réservé ;
- opération réussie → règlement définitif du réservé ;
- opération échouée ou annulée → libération du réservé vers le disponible.

Chaque transition doit être atomique, idempotente, horodatée, motivée et rattachée à un identifiant métier unique.

## 5. Droits et corrections

Un WP disponible ne peut être retiré unilatéralement et silencieusement. Toute correction autorisée doit prendre la forme d'une écriture de contrepassation traçable, fondée sur une règle annoncée, une fraude établie, une décision judiciaire ou une obligation légale.

Les WP définitivement acquis n'expirent pas par simple inactivité.

## 6. Fonctions du Wallet

Le socle autorise : réception, consultation des trois états, historique et retrait par un canal autorisé.

Dépôt de fonds, transfert entre utilisateurs, paiement auprès de tiers et conversion ne doivent être activés qu'après validation juridique et choix du dispositif réglementaire ou du prestataire agréé adapté.

## 7. Affichage minimal

Le Wallet présente séparément :

- total provisoire ;
- total disponible ;
- total réservé ;
- opérations en cours ;
- historique avec montant, état, origine, date et référence ;
- frais et délai avant confirmation d'une opération.

## 8. Invariants

1. Aucune création ou destruction de valeur sans écriture équilibrée.
2. Aucune dépense supérieure au disponible.
3. Aucune double validation du même événement.
4. Aucun solde calculé à partir d'un historique modifiable.
5. Toute somme disponible et retirable doit être rapprochable de sa couverture financière.
6. Les paramètres sont administrables, versionnés et auditables ; ils ne sont pas codés en dur.
