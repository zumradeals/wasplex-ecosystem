# Module Live — Économie, intervalles et récompenses

**Statut :** spécification proposée — AMD-0014

## 1. Enveloppe financière

Une session rémunérée possède avant activation :

- budget brut ;
- taxes et frais externes estimés ;
- net distribuable ;
- part Wasplex ;
- part utilisateurs ;
- capacité maximale ;
- réserve pour événements en cours ;
- règles de reliquat et remboursement.

Le moteur refuse toute configuration dont l'exposition maximale dépasse le budget couvert.

## 2. Sous-enveloppes utilisateur

La part utilisateur peut être divisée entre :

- présence par intervalles ;
- interactions ;
- complétion ;
- bonus annoncés ;
- traitement d'événements incertains ou reprises.

La ventilation est administrable, versionnée et figée pour les événements déjà commencés.

## 3. Intervalle qualifié

Un intervalle possède :

- identifiant de session ;
- numéro ou fenêtre temporelle ;
- début et fin ;
- condition ;
- montant potentiel ;
- plafond applicable ;
- preuve attendue ;
- état ;
- identifiant d'idempotence ;
- version de configuration.

États minimum :

> prévu → ouvert → observé → transmis → en validation → validé, refusé ou inconnu

Un intervalle validé une fois ne peut être payé deux fois.

## 4. Calcul de principe

Pour un spectateur :

> `Gain Live = somme des intervalles validés + interactions validées + bonus de complétion + bonus autorisés`

Sous les contraintes :

- plafond du spectateur ;
- quota d'adhésion applicable ;
- enveloppe de la session ;
- maximum d'événements ;
- aucune création de valeur hors source ;
- aucun montant négatif ;
- arrondis et unité définis.

## 5. Récompenses d'interaction

Une interaction rémunérable définit avant exécution :

- action attendue ;
- audience éligible ;
- fenêtre ;
- critère de validation ;
- montant ou formule ;
- nombre maximum ;
- règles d'égalité ;
- preuve ;
- traitement des erreurs.

Une réponse peut être récompensée pour participation ou exactitude. La distinction est annoncée. Les critères subjectifs exigent modération, justification et voie de contestation adaptée.

## 6. Bonus de complétion

Le bonus de complétion récompense l'arrivée à une étape définie. Il ne transforme pas les intervalles précédents en condition rétroactive.

La session indique :

- étape requise ;
- montant ;
- tolérance réseau ;
- conditions de validation ;
- plafond ;
- cas d'interruption par Wasplex ou urgence.

Si Wasplex ou une alerte vitale interrompt la session, le traitement du bonus suit une règle annoncée et protectrice ; l'utilisateur n'est pas automatiquement considéré comme ayant abandonné.

## 7. Compteur visuel

Le compteur peut montrer un WP se déplaçant vers le Wallet après un événement. Il affiche simultanément :

- montant ;
- cause ;
- état provisoire ;
- total de session ;
- plafond lorsque utile.

L'animation :

- ne ressemble pas à un jeu d'argent ;
- respecte le mouvement réduit ;
- ne se déclenche pas avant l'événement enregistré ;
- ne transforme pas `provisoire` en `disponible` ;
- ne remplace pas la preuve textuelle.

## 8. Comptabilisation

Le système conserve le détail des événements, mais peut agréger les écritures provisoires dans le Ledger afin d'éviter une écriture par seconde.

L'agrégation reste reconstructible : session, intervalles, interactions, bonus, refus et configuration sont liés à l'écriture.

Après validation :

- le montant disponible est crédité selon ADR-0003 ;
- un refus partiel explique la ventilation ;
- une correction utilise contre-écriture ;
- un résultat externe inconnu conserve la réservation nécessaire ;
- aucune mutation silencieuse d'historique n'est permise.

## 9. Abonnements

Le niveau d'abonnement peut déterminer accès, quota, priorité, plafond ou campagnes réservées conformément à AMD-0004.

Il ne garantit ni nombre de Lives, ni durée disponible, ni revenu. Tout supplément de rémunération est financé par la campagne ou explicitement par Wasplex, jamais par l'arrivée de nouveaux abonnés.

## 10. Reliquat

Le budget non consommé demeure celui du financeur selon les règles de campagne : réutilisation, transfert, prolongation ou remboursement autorisé.

Wasplex ne s'approprie pas silencieusement les places absentes, interactions non exécutées ou intervalles non qualifiés.

