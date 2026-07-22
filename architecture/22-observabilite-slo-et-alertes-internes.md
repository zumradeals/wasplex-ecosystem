# Observabilité, SLO et alertes internes

**Statut :** spécification proposée — ADR-0009

## Tableaux de bord minimaux

### Direction opérationnelle

Disponibilité par capacité, incidents, prestataires, coûts, croissance et risques.

### Finance

Couverture, rapprochements, retraits inconnus, écarts, ancienneté et flux par canal.

### Publicité

Campagnes actives, budget, événements qualifiés, fraudes, files et rémunérations.

### Sécurité

Authentifications, privilèges, exports, bris de glace, secrets et incidents.

### Alertes

SOS reçus, transmissions, accusés, délais, institutions indisponibles et notifications nationales.

## Qualité d'une alerte

Une alerte est utile si elle indique :

- ce qui est cassé ;
- impact probable ;
- première vérification ;
- responsable ;
- action sûre ;
- escalade ;
- lien vers preuve.

Une métrique sans décision associée reste informative et ne doit pas réveiller une personne.

## Cardinalité et confidentialité

Les métriques n'utilisent pas téléphone, e-mail, ID utilisateur, campagne libre ou message comme étiquette.

Les recherches individuelles passent par journaux protégés et capacités ADR-0004.

## SLO

Les SLO mesurent l'expérience d'une capacité, pas seulement un serveur. Un serveur disponible avec retraits bloqués ne signifie pas que le service de retrait est disponible.

Les dépendances externes sont mesurées séparément et intégrées à la communication utilisateur.