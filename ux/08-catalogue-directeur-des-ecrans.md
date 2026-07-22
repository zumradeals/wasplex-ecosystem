# Catalogue directeur des écrans

**Statut :** spécification proposée — UX-0003  
**Légende terminal :** M mobile, D desktop, U universel  
**Statut initial :** à spécifier, sauf mention contraire

Ce catalogue fixe le premier périmètre. Un contrat peut couvrir plusieurs lignes seulement si objectifs, autorisations, états et conséquences restent réellement identiques.

## L00 — Fondations

| ID | Surface | Terminal | Risque | États/variantes clés |
|---|---|---:|---:|---|
| FND-00-01 | Catalogue de prototypes et sélecteur de fixtures | D | Q2 | thème, largeur, langue, état |
| FND-00-02 | Coquille mobile utilisateur | M | Q1 | connecté, hors ligne, session expirée |
| FND-00-03 | Coquille portail professionnel | D | Q1 | permissions réduites, maintenance |
| FND-00-04 | Navigation et lien profond | U | Q1 | autorisé, connexion requise, refusé |
| FND-00-05 | Formulaire et validation | U | Q1 | initial, invalide, sauvegardé, conflit |
| FND-00-06 | Confirmation critique | U | Q0 | frais, valeur protégée, authentification récente |
| FND-00-07 | États système | U | Q1 | chargement, vide, erreur, hors ligne, inconnu |
| FND-00-08 | Centre de notifications | U | Q1 | lu, non lu, critique, canal externe |
| FND-00-09 | Preuve et référence de dossier | U | Q1 | vérifiée, en attente, contestée |
| FND-00-10 | Tableau et file de travail | D | Q1 | filtres, pagination, lot, refus d'accès |
| FND-00-11 | Affichage montant et ventilation | U | Q0 | WP/FCFA, provisoire, réservé, net/frais |
| FND-00-12 | Superposition d'urgence nationale | U | Q0 | nouvelle, mise à jour, expirée, accusée |

## L01 — Accès, session et identité

| ID | Écran | Terminal | Risque | États/variantes clés |
|---|---|---:|---:|---|
| U-001-01 | Accueil public et proposition de valeur | U | Q2 | pays servi/non servi, faible réseau |
| U-001-02 | Choix de l'espace | U | Q1 | utilisateur, annonceur, institution |
| U-001-03 | Création de compte minimale | M | Q1 | brouillon, doublon, canal indisponible |
| U-001-04 | Vérification du canal | M | Q1 | code, renvoi, expiration, blocage |
| U-001-05 | Connexion utilisateur | U | Q1 | erreur, verrouillage, appareil partagé |
| U-001-06 | Connexion professionnelle | D | Q1 | organisation, invitation, accès expiré |
| U-002-01 | Conditions indispensables | M | Q1 | accepté, version nouvelle, refus |
| U-002-02 | Consentements facultatifs | M | Q1 | granulaire, retiré, conséquence future |
| U-002-03 | Onboarding attention et rémunération | M | Q1 | terminé, passé, reprise |
| U-002-04 | Onboarding Wallet et WasPoint | M | Q1 | trois soldes, parité, limites |
| U-017-01 | Identifier le compte | U | Q0/Q1 | canal perdu, compte ambigu |
| U-017-02 | Vérifier la récupération | U | Q0 | preuve renforcée, échec, délai |
| U-017-03 | Révoquer sessions et restaurer l'accès | U | Q0 | appareils, restrictions temporaires |
| U-017-04 | Gestion des sessions et appareils | M | Q1 | actif, révoqué, inconnu |

## L02 — Profil, coquilles et assistance

| ID | Écran | Terminal | Risque | États/variantes clés |
|---|---|---:|---:|---|
| U-003-01 | Aperçu du profil | M | Q1 | incomplet, finalité, sans score humain |
| U-003-02 | Identité de base | M | Q1 | déclarée, vérifiée, correction |
| U-003-03 | Intérêts publicitaires | M | Q1 | consentement absent/actif/retiré |
| U-003-04 | Données facultatives par finalité | M | Q1 | masquée, modifiée, supprimée |
| U-003-05 | Centre des consentements | M | Q1 | historique, retrait, preuve |
| U-003-06 | Identité/KYC | M | Q1 | non requis, requis, examen, refus, recours |
| U-018-01 | Centre d'assistance et dossiers | U | Q1 | ouvert, attente, résolu |
| U-018-02 | Créer une contestation | U | Q1 | pièces, délai, accusé |
| U-018-03 | Détail d'une contestation | U | Q1 | examen, décision, appel |
| A-001-01 | Coquille annonceur | D | Q1 | organisation active/suspendue |
| I-001-01 | Coquille institution | D | Q0/Q1 | capacité, territoire, expiration |
| W-001-01 | Coquille administration | D | Q0 | rôle limité, bris de glace |

## L03 — Feed, publicité et abonnements

| ID | Écran | Terminal | Risque | États/variantes clés |
|---|---|---:|---:|---|
| U-004-01 | Accueil Feed et session d'attention | M | Q1 | campagnes, aucune campagne, pause |
| U-004-02 | Carte préalable d'une publicité | M | Q1 | durée, gain potentiel, raison générale |
| U-004-03 | Publicité en cours | M | Q0/Q1 | progression, réseau faible, interruption |
| U-004-04 | Complétion et transmission de preuve | M | Q0 | transmise, locale, doublon, échec |
| U-004-05 | Gain en validation | M | Q0 | provisoire, examen fraude |
| U-004-06 | Résultat de rémunération | M | Q0 | disponible, refusé, inconnu, recours |
| U-004-07 | Pause utile et information | M | Q2 | conseil, communiqué, alerte |
| U-004-08 | Onglet Alertes intégré au Feed | M | Q1 | récent, sponsorisé, national |
| U-005-01 | Comparaison des niveaux | M | Q1 | gratuit, payants, indisponible |
| U-005-02 | Détail d'un abonnement | M | Q1 | quota max, non-garantie, durée |
| U-005-03 | Souscription et paiement | M | Q1 | confirmation, paiement inconnu, échec |
| U-005-04 | Abonnement actif et quotas | M | Q1 | atteint, grâce, expiration |
| U-005-05 | Changement, renouvellement et résiliation | M | Q1 | prorata, échéance, remboursement |

## L04 — Wallet et retraits

| ID | Écran | Terminal | Risque | États/variantes clés |
|---|---|---:|---:|---|
| U-006-01 | Aperçu Wallet | M | Q0 | disponible, provisoire, réservé, obsolète |
| U-006-02 | Historique du Wallet | M | Q0 | filtres, pagination, hors ligne |
| U-006-03 | Détail et preuve d'une écriture | M | Q0 | source, ventilation, correction liée |
| U-006-04 | Actions Wallet activées | M | Q0/Q1 | retrait, dépôt, transfert, indisponible |
| U-007-01 | Montant et destination du retrait | M | Q0 | limites, destination, KYC requis |
| U-007-02 | Récapitulatif brut, frais et net | M | Q0 | authentification récente, confirmation |
| U-007-03 | Retrait transmis et suivi | M | Q0 | réservé, en cours, confirmé |
| U-007-04 | Retrait à résultat inconnu | M | Q0 | aucune relance, référence, assistance |
| U-007-05 | Retrait échoué ou annulé | M | Q0 | libération, frais, recours |

## L05 — Alertes, restitutions et urgences

| ID | Écran | Terminal | Risque | États/variantes clés |
|---|---|---:|---:|---|
| U-012-01 | Centre Wasplex Alertes | M | Q1 | actif, résolu, catégories |
| U-012-02 | Choix du type de déclaration | M | Q1 | perdu, trouvé, disparu, retrouvé |
| U-012-03 | Formulaire de déclaration | M | Q1 | brouillon, hors ligne, média refusé |
| U-012-04 | Prévisualisation de visibilité | M | Q1 | public, protégé, institution seulement |
| U-012-05 | Suivi de ma déclaration | M | Q1 | modération, publiée, correspondance, clôturée |
| U-013-01 | Correspondances proposées | M | Q1 | probable, masquée, expirée |
| U-013-02 | Validation d'une correspondance | U | Q1 | refus, accord, institution requise |
| U-013-03 | Code et rendez-vous de restitution | M | Q1 | valide, expiré, utilisé |
| U-013-04 | Confirmation de restitution | U | Q1 | identité vérifiée, litige, clôture |
| U-015-01 | Configurer la sponsorisation | M | Q1 | portée, durée, coût, estimation |
| U-015-02 | Préfinancer et suivre le boost | M | Q1 | réservé, actif, arrêté, reliquat |
| U-014-01 | Choix de la nature du SOS | M | Q0 | incendie, accident, santé, menace |
| U-014-02 | Saisie minimale et localisation | M | Q0 | permission refusée, GPS ancien |
| U-014-03 | SOS en cours de transmission | M | Q0 | préparation, envoi, appel direct |
| U-014-04 | SOS transmis, reçu ou non transmis | M | Q0 | accusé, échec, hors ligne, référence |
| U-016-01 | Alerte nationale superposée | U | Q0 | instruction, autorité, territoire, heure |
| U-016-02 | Détail et historique d'alerte nationale | U | Q0 | mise à jour, expiration, accusé |

## L06 — Annonceurs et campagnes

| ID | Écran | Terminal | Risque | États/variantes clés |
|---|---|---:|---:|---|
| A-001-02 | Demande/création d'organisation | D | Q1 | vérification, refus, activation |
| A-001-03 | Membres et accès organisation | D | Q1 | invitation, capacité, révocation |
| A-002-01 | Tableau de bord campagnes | D | Q1 | brouillon, active, suspendue, terminée |
| A-002-02 | Objectif et événement qualifié | D | Q1 | format, preuve, prix de base |
| A-002-03 | Construction de l'audience | D | Q1 | segment agrégé, seuil confidentialité |
| A-002-04 | Niveau d'adhésion et supplément | D | Q1 | estimation, rareté, non-disponible |
| A-002-05 | Création publicitaire | D | Q1 | téléversement, aperçu, destination |
| A-002-06 | Budget, volume et calendrier | D | Q0/Q1 | taxes, frais, maximum couvert |
| A-002-07 | Récapitulatif et préfinancement | D | Q0 | ventilation, paiement, résultat inconnu |
| A-002-08 | Modération et activation | D | Q1 | examen, correction, refus, appel |
| A-003-01 | Portefeuille publicitaire | D | Q0 | disponible, réservé, consommé |
| A-003-02 | Transfert, prolongation ou remboursement | D | Q0 | frais non récupérables, inconnu |
| A-004-01 | Rapport de campagne | D | Q1 | agrégé, seuil, données insuffisantes |
| A-004-02 | Preuves et facturation | D | Q1 | événement, invalidation, facture |

## L07 — Institutions

| ID | Écran | Terminal | Risque | États/variantes clés |
|---|---|---:|---:|---|
| I-001-02 | Activation d'un compte nominatif | D | Q0/Q1 | invitation, preuve, expiration |
| I-001-03 | Capacités, territoire et durée | D | Q0 | actif, limité, suspendu |
| I-002-01 | Tableau de bord institutionnel | D | Q0/Q1 | files autorisées, urgences |
| I-002-02 | Détail d'un dossier | D | Q0/Q1 | données minimales, journal d'accès |
| I-002-03 | Prise en charge d'un SOS | D | Q0 | reçu, assigné, escaladé, clôturé |
| I-002-04 | Correspondance et restitution | D | Q1 | vérification, code, litige |
| I-003-01 | Recherche finalisée | D | Q0/Q1 | motif, portée, seuil, aucun résultat |
| I-003-02 | Résultats autorisés | D | Q0/Q1 | masqués, accès justifié, export interdit |
| I-003-03 | Avis de recherche | D | Q1 | brouillon, validé, diffusé, retiré |
| I-003-04 | Historique et justification d'actions | D | Q0 | filtre, preuve, anomalie |

## L08 — Fonds Social

| ID | Écran | Terminal | Risque | États/variantes clés |
|---|---|---:|---:|---|
| U-008-01 | Présentation et porte d'éligibilité | M | Q1 | gratuit, abonnement éligible, expiré |
| U-008-02 | Comparaison des programmes sociaux | M | Q1 | plafond, apport, contribution, risques |
| U-008-03 | Mandat de contribution | M | Q1 | plafond, fréquence, seuil, révocation |
| U-008-04 | Adhésion et paiement | M | Q1 | actif, inconnu, échec, grâce |
| U-008-05 | Tableau de bord du Fonds | M | Q1 | contributions, engagement, appels |
| U-008-06 | Détail d'une contribution | M | Q1 | due, prélevée, insuffisante, contestée |
| U-009-01 | Éligibilité à déclarer un vœu | M | Q1 | ancienneté, réciprocité, conflit |
| U-009-02 | Catégorie, priorité et urgence | M | Q1 | santé, essentiel, projet, hors périmètre |
| U-009-03 | Besoin, partenaire et justificatifs | M | Q1 | partenaire, hors partenaire, complément |
| U-009-04 | Apport et besoin restant | M | Q0/Q1 | calendrier, insuffisant, prêt |
| U-009-05 | Soumission et décision | M | Q1 | examen, accepté, refusé, recours |
| U-009-06 | Collecte communautaire | M | Q0/Q1 | appel, couverture, reliquat |
| U-009-07 | Réalisation et clôture du vœu | M | Q1 | fournisseur, preuve, reliquat, litige |

## L09 — Cartes Wasplex

| ID | Écran | Terminal | Risque | États/variantes clés |
|---|---|---:|---:|---|
| U-010-01 | Porte d'éligibilité Cartes | M | Q1 | niveau requis, expiré, accessible |
| U-010-02 | Catalogue des Cartes | M | Q1 | prix, durée, services, aucune garantie |
| U-010-03 | Détail d'une Carte et de son pool | M | Q1 | 50/50, distribution réelle, zéro possible |
| U-010-04 | Acquisition et paiement | M | Q0/Q1 | confirmé, inconnu, échec |
| U-010-05 | Émission et activation virtuelle | M | Q1 | créée, inactive, active, suspendue |
| U-011-01 | Aperçu de ma Carte | M | Q1 | identifiant masqué, statut, services |
| U-011-02 | Opération chez un partenaire | M | Q0/Q1 | autorisée, refusée, inconnue |
| U-011-03 | Historique et détail d'opération | M | Q0/Q1 | preuve, correction, contestation |
| U-011-04 | Distribution de pool | M | Q0/Q1 | période, montant réel, zéro, preuve |
| U-011-05 | Commander un support physique | M | Q1 | frais, adresse, production, livraison |
| U-011-06 | Suspendre, renouveler ou fermer | M | Q1 | confirmation, droits restants |

## L10 — Administration et gouvernance

| ID | Écran | Terminal | Risque | États/variantes clés |
|---|---|---:|---:|---|
| W-001-02 | Files de responsabilité | D | Q0/Q1 | capacité, priorité, assignation |
| W-001-03 | Catalogue des configurations | D | Q0 | active, future, expirée, brouillon |
| W-001-04 | Éditeur de configuration C1 | D | Q0 | schéma, simulation, impact |
| W-001-05 | Revue et double contrôle C1 | D | Q0 | séparation, refus, approbation |
| W-001-06 | Publication et retour arrière | D | Q0 | planifiée, active, rollback, incident |
| W-002-01 | Recherche Ledger et rapprochement | D | Q0 | écart, preuve, origine |
| W-002-02 | Ajustement par contre-écriture | D | Q0 | motif, double contrôle, publié |
| W-003-01 | File de modération publicitaire | D | Q1 | assignée, escaladée, délai |
| W-003-02 | Revue d'une création/campagne | D | Q1 | approuvée, correction, refus |
| W-004-01 | Console incidents | D | Q0 | ouvert, commandement, résolu |
| W-004-02 | Activation bris de glace | D | Q0 | justification, durée, surveillance |
| W-004-03 | Journal d'audit | D | Q0 | recherche, corrélation, export contrôlé |
| W-004-04 | Gestion des accès privilégiés | D | Q0 | demande, approbation, révocation |

## Contrôle du catalogue

Avant chaque lot, les lignes concernées sont vérifiées contre la Constitution, les décisions, les contrats métier et les flux intermodules. Les écrans nouvellement découverts sont ajoutés avant leur maquettage ; ils ne restent pas cachés dans un prompt.

