# AMD-0008 — Les Cartes Wasplex : participation économique réelle

**État :** adopté et intégré à la Constitution v0.9  
**Date de proposition :** 2026-07-21  
**Source :** `sources/2026-07-21-entretien-fondateur-12-cartes-wasplex.md`

## Article proposé

1. Une Carte Wasplex est un droit personnel d'accès à des services, opérations partenaires et, lorsque les conditions sont réunies, à un programme de participation économique.
2. Elle ne constitue ni une action, ni une valeur mobilière, ni une fraction du capital, ni une monnaie, ni un placement, ni une promesse de rendement.
3. Le détenteur d'une Carte Wasplex n'est pas actionnaire de Wasplex du seul fait de cette acquisition.
4. La carte est virtuelle par défaut. Un support physique facultatif et payant peut matérialiser les mêmes droits sans créer un droit économique supplémentaire.
5. L'accès peut être conditionné à un niveau d'abonnement déclaré éligible par une configuration versionnée. Les libellés commerciaux, notamment Premium, Élite ou Master, ne constituent jamais des clés d'autorisation et ne doivent pas être codés en dur. L'expiration de l'abonnement ne supprime ni la carte payée, ni les gains définitivement acquis.
6. Le prix de la carte rémunère des services identifiables et ne finance pas la redistribution des détenteurs antérieurs.
7. Aucune rémunération ne dépend du recrutement, du parrainage ou de l'arrivée permanente de nouveaux acquéreurs.
8. Seuls des revenus économiques externes, traçables, encaissés, validés et explicitement affectés peuvent alimenter une redistribution.
9. Après retrait des taxes, frais externes directement imputables, remboursements et annulations, le revenu net partageable du programme est réparti à parts égales : 50 % pour Wasplex et 50 % pour la communauté éligible.
10. La part communautaire peut combiner un avantage direct lié à l'opération et un pool collectif selon une formule annoncée avant l'opération, versionnée et non rétroactive.
11. Une même valeur ne peut être distribuée deux fois. Les revenus publicitaires restent soumis exclusivement au partage constitutionnel publicitaire applicable.
12. Une période sans revenu éligible peut produire une distribution nulle ; aucun minimum n'est garanti.
13. Chaque carte, opération, commission, frais, part Wasplex, montant de pool et crédit Wallet est rapprochable et auditable.
14. Les partenaires sont vérifiés, contractuellement agréés et limités aux données et capacités nécessaires à chaque opération.
15. La carte ne devient un instrument autonome de paiement, de retrait, de transfert ou de monnaie électronique qu'après satisfaction des exigences réglementaires et activation avec des prestataires habilités.
16. Les gains disponibles survivent à l'expiration, la suspension ou la fermeture de la carte, sauf fraude prouvée, erreur manifeste, décision judiciaire ou obligation légale.
17. Toute fermeture de produit ou de pool exige arrêt ordonné, information, règlement des opérations, traitement des services non fournis et état de clôture auditable.
18. Produits, prix, durées, services, coefficients, plafonds, partenaires et cycles sont administrables, versionnés et auditables sans pouvoir contredire ces invariants.

## Motivation

Cet amendement conserve l'ambition de redistribution populaire de Wasplex tout en fondant les versements sur une activité économique réelle plutôt que sur les achats futurs de nouveaux membres.

Il protège le vocabulaire, la solvabilité, la traçabilité et la simplicité du programme.

## Décision d'adoption

Le fondateur a validé cet amendement le 2026-07-21.

## Effet de l'adoption

Cet article gouverne toutes les Cartes Wasplex virtuelles ou physiques, leurs pools, leurs opérations partenaires et leurs redistributions.

## Clarification de mise en œuvre — consolidation v1.5

L'autorisation stable est la capacité `cards.acquire`. Elle est accordée lorsque l'abonnement actif appartient à la liste versionnée des offres éligibles (`cards.eligible_subscription_offer_ids`) et que les autres conditions sont remplies. Les noms commerciaux restent modifiables sans changer le code ni les droits historiques.
