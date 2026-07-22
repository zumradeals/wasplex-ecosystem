# Institutions affiliées — Affiliation, capacités et responsabilités

**Statut :** spécification métier fondatrice  
**Source :** `sources/2026-07-21-entretien-fondateur-09-institutions-affiliees.md`  
**Dépendances :** Constitution v1.5, AMD-0006 adopté, validation juridique et sectorielle

## 1. Modèle

Une organisation affiliée possède :

- identité légale et catégorie ;
- territoires et domaines de compétence ;
- représentant légal et contacts ;
- niveau de vérification et de risque ;
- convention, dates et état d'affiliation ;
- capacités institutionnelles versionnées ;
- utilisateurs institutionnels nominatifs ;
- comptes techniques d'intégration distincts ;
- historique, incidents et restrictions.

Un prestataire ponctuel sans accès durable reste un fournisseur vérifié et ne reçoit pas de portail institutionnel.

## 2. États d'affiliation

États minimaux : `draft`, `under_review`, `approved`, `active`, `restricted`, `suspended`, `expired`, `revoked`.

Chaque transition conserve motif, responsable Wasplex habilité, preuve, date et portée. Une suspension peut être limitée à une capacité ou un territoire.

## 3. Comptes institutionnels

Aucun compte humain partagé pour les opérations sensibles. Chaque compte est rattaché à une personne et une organisation.

Création par invitation après approbation ; mot de passe temporaire à usage unique ou lien d'activation expirant ; changement obligatoire ; MFA selon risque ; récupération contrôlée ; révocation immédiate au départ.

Un administrateur institutionnel gère les propositions de membres sans pouvoir s'attribuer une capacité que Wasplex n'a pas accordée à l'organisation.

## 4. Habilitation par capacité et portée

Une habilitation combine :

`organisation + utilisateur + capacité + finalité + territoire + catégorie + durée + base d'accès`

Elle est refusée si l'un de ces éléments manque pour une opération sensible.

Capacités exemples : `sos.receive`, `sos.acknowledge`, `sos.accept`, `case.read`, `case.search_scoped`, `match.validate`, `notice.publish`, `return.verify`, `quote.submit`, `payment.receive`, `delivery.confirm`.

Une capacité financière n'est activée que pour une organisation légalement autorisée.

## 5. Données minimales

La vue institutionnelle est construite par capacité, non à partir des tables complètes. Les champs non nécessaires sont absents, pas seulement masqués visuellement.

L'accès sensible est dossier par dossier, temporaire et révocable. Toute élévation exceptionnelle exige un motif et une approbation.

La fonction anciennement nommée « Base de données » devient « Recherche de dossiers autorisés ». Elle impose catégorie, territoire, période ou identifiant et motif. Aucune exploration transversale.

## 6. Preuves et statuts

Un statut opérationnel n'est modifié que par un événement probant :

| Statut | Preuve minimale |
|---|---|
| Créée | identifiant et horodatage serveur |
| Transmise | destinataire, canal et confirmation de sortie |
| Reçue | accusé technique ou humain |
| Acceptée | identité institutionnelle et engagement |
| En traitement | action opérationnelle déclarée et horodatée |
| Résolue | preuve de clôture et confirmations requises |

La transmission n'est pas une réception ; la réception n'est pas une acceptation ; l'acceptation n'est pas une intervention réussie.

## 7. Audit

Chaque consultation et mutation sensible enregistre identité humaine ou technique, organisation, capacité, finalité, dossier, données consultées, appareil ou intégration, adresse réseau, instant et résultat.

Les journaux sont append-only, protégés et inaccessibles en modification à l'institution concernée.

## 8. Restitution

Le code de restitution est aléatoire, à usage unique, expirant, lié à un dossier et à une organisation ou zone autorisée.

La personne habilitée vérifie l'identité physique selon la procédure. La remise et la réception sont confirmées séparément. Toute contestation conserve les preuves et empêche l'effacement du dossier.

## 9. Incident

En cas d'abus ou compromission : suspension proportionnée ou immédiate, révocation de sessions et clés, gel des actes critiques, conservation des preuves, analyse d'impact, notifications requises et enquête.

Les dossiers actifs sont réaffectés sans perdre leur historique.

## 10. Interface

Le portail n'utilise jamais les expressions « portail agents » ou « espace agents ». Libellés admis :

- Portail des institutions Wasplex ;
- Espace institutionnel ;
- Utilisateurs institutionnels habilités ;
- Recherche de dossiers autorisés.

L'interface affiche organisation, utilisateur connecté, capacités actives, territoire et échéance d'habilitation.
