# Gates qualité et Definition of Done

**Statut :** spécification proposée — ADR-0008

## Avant développement

Une tâche est prête lorsque finalité, règles, données, acteur, autorisations, états, risques, dépendances et acceptation sont suffisamment définis.

## Avant fusion

- diff compréhensible ;
- tests locaux et CI ;
- aucune violation architecturale ;
- migration compatible ;
- contrats validés ;
- revue indépendante ;
- documentation liée ;
- aucun secret ;
- risque accepté.

## Avant production

- artefact immuable ;
- staging réussi ;
- sauvegarde et restauration pertinentes ;
- configuration approuvée ;
- migrations vérifiées ;
- observabilité active ;
- runbook ;
- responsables disponibles ;
- plan de retour ou compensation ;
- smoke tests sûrs.

## Après production

- vérification de santé ;
- contrôle des métriques métier ;
- rapprochement financier si nécessaire ;
- surveillance renforcée ;
- clôture ou incident ;
- conservation des preuves.

## Indicateurs

Wasplex suit notamment :

- défauts échappés ;
- temps de correction ;
- tests instables ;
- migrations échouées ;
- incidents par domaine ;
- écart simulation/réalité ;
- taux de rollback ;
- restauration testée ;
- couverture des exigences critiques.

Un indicateur ne devient pas une cible manipulable au détriment de la qualité réelle.