# API REST, versionnement et sécurité

**Statut :** spécification d'application — ADR-0007 adopté

## Convention d'endpoint

- préfixe `/api/v{major}` ;
- noms de ressources stables ;
- identifiants publics ADR-0006 ;
- corps JSON bornés et validés ;
- erreurs `application/problem+json` ;
- corrélation retournée au client ;
- aucune fuite de stack, SQL ou secret.

## Écriture

Les commandes financières et critiques exigent idempotence. Une création retourne la ressource ou l'intention créée ; un traitement différé retourne un état consultable.

Les mises à jour utilisent version de ressource ou précondition afin d'éviter l'écrasement concurrent.

## Lecture

Les collections utilisent curseurs opaques, limite maximale et filtres autorisés. Les champs sensibles sont absents ou masqués selon ADR-0004.

Une projection expose sa date de fraîcheur.

## Authentification

| Client | Mécanisme initial |
|---|---|
| Web/PWA | Session serveur et CSRF |
| Android | Autorisation avec PKCE et jetons courts |
| Partenaire faible risque | Identité technique et secret rotatif |
| Finance/institution sensible | Clé asymétrique ou certificat, plus portée |
| Webhook entrant | Signature du corps, horodatage et anti-rejeu |

Le mécanisme exact reste remplaçable sans changer les capacités métier.

## Changement

Toute modification de contrat passe par revue du propriétaire, sécurité, données et consommateurs. La CI compare les spécifications OpenAPI et signale les ruptures.

## Cache

Les ressources publiques peuvent utiliser un cache contrôlé. Les réponses personnelles, financières, institutionnelles ou d'autorisation sont privées ou non mises en cache selon leur risque.