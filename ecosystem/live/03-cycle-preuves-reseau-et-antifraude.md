# Module Live — Cycle, preuves, réseau et antifraude

**Statut :** spécification adoptée — AMD-0014

## 1. Cycle de session

États minimum :

> brouillon → soumis → en conformité → approuvé → programmé → salle ouverte → en direct → interrompu ou terminé → en rapprochement → clôturé

États complémentaires : refusé, suspendu, annulé, reporté, résultat technique inconnu.

Chaque transition possède acteur autorisé, motif, horodatage, configuration et preuve.

## 2. Préparation

Avant programmation :

- diffuseur et présentateurs vérifiés ;
- finalité classifiée ;
- droits sur le contenu et les intervenants documentés ;
- audience et pays autorisés ;
- création/modération approuvée ;
- budget couvert ;
- capacité et fournisseur vidéo connus ;
- interactions testées ;
- procédure d'incident ;
- accessibilité et faible débit ;
- replay et conservation décidés ;
- alertes prioritaires testées.

## 3. Entrée du spectateur

États :

> invité ou découvert → éligibilité vérifiée → conditions affichées → consentement/entrée → salle d'attente → connecté → participant → quitté, déconnecté ou terminé

L'éligibilité à la session est distincte de la qualification de chaque événement rémunéré.

## 4. Preuves proportionnées

Les signaux possibles comprennent :

- réception effective des segments de diffusion ;
- lecteur actif et visible selon format ;
- continuité raisonnable de session ;
- réponses et interactions ;
- signaux serveur ;
- cohérence d'appareil et de session ;
- contrôle de présence ponctuel et non abusif ;
- détection de répétition ou automatisation.

Wasplex minimise les signaux. Caméra, microphone, biométrie ou surveillance intrusive ne sont jamais activés par défaut comme preuve d'attention.

## 5. Coupure et reprise

Lors d'une coupure :

1. le client conserve l'identifiant de session et les événements non confirmés ;
2. les intervalles confirmés restent acquis ;
3. l'intervalle courant devient interrompu ou inconnu ;
4. la reconnexion utilise une clé de reprise ;
5. le serveur déduplique ;
6. le délai de grâce s'applique selon configuration ;
7. l'interface indique littéralement ce qui est conservé ;
8. aucun faux crédit disponible n'est affiché.

Un mode audio seul ou faible débit peut continuer la qualification si la campagne l'a prévu avant diffusion.

## 6. Défaillance de la plateforme

Wasplex distingue :

- panne diffuseur ;
- panne fournisseur vidéo ;
- panne de preuve ;
- panne Wallet ;
- panne client ;
- panne réseau locale ;
- interruption de sécurité ;
- interruption par alerte nationale.

Les règles de compensation sont configurées et financées. Une panne Wasplex ne transforme pas automatiquement les spectateurs honnêtes en absents.

## 7. Antifraude

Risques :

- sessions automatisées ;
- multi-comptes ;
- appareils simultanés incompatibles ;
- relecture présentée comme direct ;
- falsification d'interaction ;
- collusion diffuseur-spectateur ;
- réutilisation d'identifiants ;
- génération artificielle de places ;
- réponses partagées lorsqu'une exactitude est rémunérée.

Les réponses combinent limites, déduplication, analyse de cohérence, vérification progressive et revue humaine. Aucun signal isolé opaque ne confisque automatiquement des gains acquis.

## 8. Modération en direct

Capacités :

- masquer ou refuser une interaction ;
- ralentir ou fermer les questions ;
- couper un intervenant ;
- afficher un avertissement ;
- suspendre la rémunération de nouveaux événements ;
- interrompre le flux ;
- préserver les preuves ;
- escalader vers sécurité ou institution autorisée.

L'arrêt de nouveaux événements ne réécrit pas les événements antérieurs. Leur validation suit les preuves et la bonne foi.

## 9. Fin et rapprochement

À la fin :

- la session cesse d'ouvrir des intervalles ;
- les événements en cours sont figés ;
- preuves et doublons sont rapprochés ;
- les gains provisoires sont agrégés ;
- les refus sont motivés ;
- le reliquat est calculé ;
- les rapports agrégés sont produits ;
- les données sont conservées ou supprimées selon finalité ;
- le replay est créé seulement si autorisé.

La clôture n'est définitive qu'après équilibre financier et traitement des résultats inconnus.

