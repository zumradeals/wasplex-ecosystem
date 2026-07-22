# Entretien fondateur 18 — Architecture d'accès et garanties minimales

**Date :** 22 juillet 2026  
**Statut :** source fondatrice corrigée et structurée  
**Origine :** réponses du fondateur au questionnaire d'architecture

## Intention

Wasplex doit rester utilisable sur des appareils modestes, des réseaux instables, des forfaits limités, des appareils partagés et dans un contexte électrique imparfait, sans réduire la sécurité des opérations sensibles.

## Décisions exprimées

- Web mobile responsive comme accès universel.
- PWA comme prolongement installable du Web.
- Desktop pour annonceurs, administration, conformité, finance, institutions, partenaires et audit.
- Android natif seulement après validation du socle et pour des capacités réellement justifiées.
- Une seule logique métier, une seule vérité économique et des états identiques entre canaux.
- Frontières strictes entre Identité, Publicité, Wallet, Abonnements, Fonds Social, Alertes, Institutions et Cartes Wasplex.
- Sauvegardes chiffrées, séparées, immuables lorsque nécessaire et régulièrement restaurées en test.
- Reprise ordonnée des services critiques avant les fonctions de confort.

## Corrections architecturales apportées

1. La séparation des domaines sera d'abord réalisée dans un **monolithe modulaire**, non dans des microservices.
2. Une base PostgreSQL commune peut être utilisée, mais chaque module possède ses tables et aucun module n'écrit directement dans celles d'un autre.
3. Les échanges critiques emploient commandes explicites, événements persistés, identifiants d'idempotence et journal transactionnel de sortie.
4. Un solde local ancien reste consultable mais n'autorise jamais une dépense.
5. Un SOS hors ligne peut être préparé ou mis en file ; il n'est jamais présenté comme transmis sans accusé serveur.
6. Une publicité rémunérée ne peut être validée entièrement hors ligne. Une reprise est possible si la session et les preuves restent vérifiables.
7. GPS, caméra et notifications sont demandés au moment utile, pour une finalité annoncée, et non comme autorisations générales.
8. Paiements, transferts et fonctions financières restent soumis aux portes d'activation réglementaires déjà adoptées.
9. Les objectifs RPO, RTO et de disponibilité deviennent obligatoires avant production, mais leurs valeurs opérationnelles ne sont pas constitutionnelles.
10. Notifications, médias, intégrations, conformité et configuration sont des capacités transversales sans devenir de nouveaux acteurs officiels.

Cette source nourrit l'ADR-0001 sans remplacer la Constitution.