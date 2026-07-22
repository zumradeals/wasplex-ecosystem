# Matrice des intégrations

**Statut :** spécification proposée — ADR-0007

| Intégration | Sens | Données maximales | Preuve requise | Interdiction principale |
|---|---|---|---|---|
| Android Wasplex | bidirectionnel | données de l'utilisateur autorisé | session et appareil | vérité métier locale |
| Mobile Money | bidirectionnel | paiement minimal et bénéficiaire | référence, signature, rapprochement | relance aveugle |
| Banque | bidirectionnel | règlement et couverture | relevé et rapprochement | assimilation webhook = règlement |
| Annonceur | bidirectionnel | campagne et agrégats | organisation et capacité | identité d'audience |
| Institution | bidirectionnel | dossier et champs finalisés | capacité, territoire, motif | recherche générale |
| Alerte nationale | entrant | message souverain minimal | identité forte, double validation, anti-rejeu | clé API simple |
| Partenaire Carte | bidirectionnel | droit/opération nécessaire | contrat, consentement, rapprochement | accès au solde complet |
| SMS/e-mail | sortant et statuts | destination et modèle minimal | référence de remise | contenu sensible excessif |
| Stockage objet | bidirectionnel | objet chiffré et métadonnées | empreinte et autorisation | URL publique permanente |
| Géolocalisation | requête limitée | coordonnées nécessaires | finalité et consentement/base | enrichissement publicitaire interdit |

## Activation

Chaque ligne réellement activée reçoit :

- pays ;
- propriétaire Wasplex ;
- responsable externe ;
- contrat ;
- données ;
- capacités ;
- clés ;
- limites ;
- SLO ;
- plan d'incident ;
- plan de sortie.

Une intégration non inscrite dans ce registre ne passe pas en production.