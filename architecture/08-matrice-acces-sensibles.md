# Matrice des accès sensibles

**Statut :** spécification proposée — ADR-0004

## Principe

Cette matrice définit des familles minimales. Les capacités exactes sont maintenues dans le catalogue versionné.

| Action | Initiateur possible | Exigence minimale | Interdiction |
|---|---|---|---|
| Approuver une configuration C1 | Gouvernance habilitée | MFA récente, simulation, approbateur distinct | Auto-approbation |
| Proposer un ajustement Wallet | Finance habilitée | Dossier, preuve, journal modèle | Modification de solde |
| Approuver un ajustement Wallet | Second habilité | Séparation des tâches | Auteur ou bénéficiaire unique |
| Rapprocher un paiement | Finance/rapprochement | Références ledger et externe | Corriger directement le ledger |
| Lire un document KYC | Identité/conformité | Finalité, dossier, champ nécessaire | Accès support général |
| Exporter des données sensibles | Habilité spécifique | Motif, périmètre, approbation, chiffrement | Réutilisation de la lecture |
| Voir une audience publicitaire | Annonceur | Résultat agrégé et seuil | Identité ou microsegment |
| Traiter une alerte | Institution habilitée | Territoire, catégorie, dossier | Recherche générale |
| Émettre une alerte nationale | Institution souveraine | Deux validations nominatives fortes | Wasplex seul ou sponsor |
| Consulter un vœu social | Fonds Social habilité | Programme et finalité | Usage publicitaire |
| Activer un partenaire Carte | Wasplex habilité | Contrat, pays, capacités | Accès Wallet implicite |
| Utiliser le bris de glace | Responsable désigné | Dommage imminent, MFA, expiration, revue | Ledger, Constitution, effacement |

## Revue périodique

Les droits critiques sont revus selon leur risque, après changement de fonction, incident, inactivité ou fin de contrat. Chaque responsable d'organisation atteste les appartenances ; Wasplex vérifie indépendamment les capacités les plus sensibles.

## Accès institutionnel

Une institution reçoit des capacités adaptées à sa mission, jamais un portail générique identique pour toutes. Police, gendarmerie, secours, santé, administration ou partenaire social peuvent donc disposer de portées différentes sans créer de nouveaux acteurs constitutionnels.

## Accès annonceur

Une organisation annonceur peut distinguer propriétaire, gestionnaire de campagne, analyste, finance et lecteur. Le rôle finance ne donne pas accès aux profils ciblés ; l'analyste ne peut pas retirer un budget.

## Accès interne Wasplex

Les domaines support, modération, conformité, finance, sécurité, administration et audit sont séparés. Un employé peut cumuler des fonctions seulement après analyse du conflit et contrôles compensatoires documentés.