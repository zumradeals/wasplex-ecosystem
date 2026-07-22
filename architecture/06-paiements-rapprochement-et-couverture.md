# Paiements, rapprochement et couverture

**Statut :** spécification d'application — ADR-0003 adopté

## Adaptateurs prestataires

Chaque opérateur Mobile Money, banque ou processeur est isolé derrière un adaptateur. Le domaine Wallet utilise un contrat commun et ne dépend pas des libellés propres au prestataire.

L'adaptateur traduit sans décider du droit économique.

## États normalisés

Les réponses externes sont traduites en :

- préparé ;
- envoyé ;
- accepté ;
- confirmé payé ;
- confirmé échoué ;
- inconnu ;
- annulé ;
- remboursé ;
- contesté.

Un statut externe inconnu n'est jamais converti par défaut en échec.

## Webhooks et interrogation

Les webhooks sont authentifiés et dédupliqués. Une interrogation périodique complète les événements manquants. Les relevés de règlement constituent la troisième source de contrôle.

La répétition d'un appel utilise la même référence lorsque le prestataire le permet. Si ce n'est pas garanti, aucune relance de paiement n'est faite avant résolution du résultat précédent.

## Rapprochement

Chaque journée ou cycle produit :

- total du ledger par prestataire ;
- total déclaré par l'API ;
- total réellement réglé ;
- frais et taxes ;
- liste des écarts ;
- ancienneté des résultats inconnus ;
- approbation de clôture.

Les seuils de gravité sont configurés par ADR-0002. Un écart financier critique bloque les sorties concernées sans masquer les autres fonctions sûres.

## Couverture

La couverture est calculée par devise et entité juridique. Une devise excédentaire ne couvre pas silencieusement une autre devise.

Les actifs admissibles sont réellement contrôlés, disponibles, rapprochés et non grevés. Les montants contestés ou chez un prestataire indisponible sont décotés ou exclus selon une politique approuvée.

## Rétrofacturations

Une rétrofacturation ne débite pas automatiquement l'utilisateur honnête. Elle ouvre un dossier déterminant source, responsabilité, couverture et recours. Toute créance ou correction est explicite et auditée.

## Continuité

En panne de prestataire :

- aucun droit WP n'est supprimé ;
- les nouvelles sorties sur le canal peuvent être suspendues ;
- les retraits inconnus restent réservés ;
- un canal alternatif n'est utilisé qu'après contrôle du risque de double paiement ;
- les utilisateurs voient un état exact.

## Activation réglementaire

Dépôt, transfert, paiement partenaire et tout rôle assimilable à un service financier restent désactivés par pays jusqu'à validation juridique, contractuelle et opérationnelle. La présence de comptes comptables ne vaut pas autorisation commerciale.