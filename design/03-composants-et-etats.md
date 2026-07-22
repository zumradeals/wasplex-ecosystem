# Composants et états

**Statut :** spécification proposée — DS-0001

## Principe

Un composant encapsule accessibilité, style et comportement général. La règle métier reste dans le module propriétaire.

Un `TransactionStatus` sait présenter « réservé » ; il ne décide pas qu'un retrait est réservé.

## États financiers

| État | Présentation |
|---|---|
| provisoire | info + « En validation » |
| disponible | succès + « Disponible » |
| réservé | pending + opération liée |
| transmis | info + « Transmis » |
| inconnu | neutral/pending + « Vérification en cours » |
| payé | succès + preuve/date |
| échoué | danger + prochaine action |
| compensé | neutral + référence de correction |

## États d'alerte

| État | Présentation |
|---|---|
| brouillon | neutre |
| publiée | info |
| transmise | info, sans prétendre prise en charge |
| reçue | info renforcée |
| prise en charge | succès probant |
| résolue | succès |
| retirée | neutre avec raison |
| expirée | neutre |
| critique nationale | surface dédiée prioritaire |

## Confirmation

Une confirmation critique présente :

- action ;
- objet ;
- montant ou portée ;
- frais ;
- conséquence ;
- réversibilité ;
- identité/destination ;
- bouton précis ;
- annulation sûre.

Les confirmations répétitives inutiles sont évitées ; celles aux points d'irréversibilité sont obligatoires.

## Messages temporaires

Un toast confirme une action secondaire. Il ne porte pas seul une erreur financière, un SOS ou une décision nécessitant consultation ultérieure.

Les états importants restent visibles dans la page et l'historique.