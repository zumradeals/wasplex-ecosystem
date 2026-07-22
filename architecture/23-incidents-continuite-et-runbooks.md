# Incidents, continuité et runbooks

**Statut :** spécification proposée — ADR-0009

## Runbooks obligatoires

- déficit ou risque de couverture ;
- retrait inconnu ;
- divergence Ledger/prestataire ;
- compromission de compte privilégié ;
- fuite de données ;
- perte de PostgreSQL ;
- corruption de projection ;
- panne de stockage objet ;
- outbox ou worker bloqué ;
- Mobile Money indisponible ;
- Alertes institutionnelles indisponibles ;
- fausse alerte nationale ;
- certificat expirant ;
- déploiement défectueux ;
- fermeture d'un pays.

## Structure d'un runbook

- déclencheur ;
- gravité ;
- vérifications ;
- actions sûres ;
- actions interdites ;
- autorisations ;
- communication ;
- preuve ;
- escalade ;
- restauration ;
- rapprochement ;
- clôture.

## Exercices

Les exercices sont planifiés, chronométrés et suivis d'actions. Ils utilisent données synthétiques et évitent toute alerte ou valeur réelle.

## Post-incident

Les actions correctives reçoivent priorité, propriétaire et échéance. Un rapport clôturé sans correction des causes récurrentes n'est pas considéré comme un apprentissage.