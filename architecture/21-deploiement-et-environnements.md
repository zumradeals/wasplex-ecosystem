# Déploiement et environnements

**Statut :** spécification proposée — ADR-0009

## Composants initiaux

- proxy HTTPS ;
- application Laravel/React ;
- workers Laravel ;
- scheduler unique avec verrou ;
- PostgreSQL ;
- stockage objet ;
- sauvegardes séparées ;
- collecte d'observabilité ;
- gestionnaire de secrets.

L'application, les workers et le scheduler utilisent la même image avec commandes de démarrage distinctes.

## Pipeline

1. validation du changement ;
2. tests ADR-0008 ;
3. construction ;
4. analyse et empreinte ;
5. déploiement staging ;
6. tests staging ;
7. approbation ;
8. migration compatible ;
9. déploiement production ;
10. smoke tests ;
11. observation ;
12. clôture.

## Mise en production

Le manifeste précise version, image, migrations, configurations, flags, contrats, propriétaire et fenêtre.

Le déploiement ne modifie pas simultanément une configuration C1 sauf plan explicitement approuvé.

## Retour

Le retour applicatif utilise l'artefact précédent seulement si le schéma reste compatible. Une migration irréversible nécessite correction en avant.

Les effets métier déjà produits sont compensés par leurs domaines, jamais par restauration aveugle de base.

## Pilote et croissance

Le pilote peut utiliser une topologie économique avec risques explicités. L'activation de fonds réels, d'Alertes critiques ou d'un nouveau pays déclenche une revue de capacité et de redondance.