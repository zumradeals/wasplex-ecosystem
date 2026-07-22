# Registre des composants fondamentaux

**État :** adopté comme registre initial — L00-A  
**Dépendances :** DS-0001, UX-0001, UX-0002, UX-0003

## 1. Niveaux

### Primitives

Éléments sémantiques simples : texte, icône, séparation, surface, focus.

### Contrôles

Éléments interactifs : bouton, lien, champ, sélection, onglet, téléversement.

### Composants de vérité

Éléments portant un état ou une valeur : statut, montant, preuve, hors ligne, résultat inconnu.

### Compositions

Assemblages réutilisables : coquille, navigation, formulaire, tableau, dialogue critique, alerte nationale.

## 2. Registre initial

| ID | Composant | Niveau | Risque max | États minimum |
|---|---|---|---:|---|
| CMP-001 | Texte et titre | primitive | Q3 | principal, secondaire, inverse |
| CMP-002 | Icône | primitive | Q2 | décorative, informative, action |
| CMP-003 | Surface et section | primitive | Q2 | canvas, surface, raised |
| CMP-004 | Bouton | contrôle | Q1 | défaut, survol, focus, pressé, chargement, désactivé |
| CMP-005 | Lien | contrôle | Q1 | interne, externe, profond, indisponible |
| CMP-006 | Champ texte/nombre | contrôle | Q1 | vide, rempli, invalide, désactivé, lecture seule |
| CMP-007 | Téléphone et code OTP | contrôle | Q1 | saisie, expiration, renvoi, verrouillage |
| CMP-008 | Sélection et choix multiple | contrôle | Q1 | ouvert, choisi, vide, indisponible |
| CMP-009 | Case, radio et interrupteur | contrôle | Q1 | actif, inactif, indéterminé, désactivé |
| CMP-010 | Téléversement | contrôle | Q1 | vide, progression, contrôle, refus, succès |
| CMP-011 | Onglets et filtres | contrôle | Q2 | actif, badge, débordement |
| CMP-012 | Recherche et pagination | contrôle | Q1 | chargement, aucun résultat, page invalide |
| CMP-013 | Statut littéral | vérité | Q0 | success, warning, danger, info, pending, unknown |
| CMP-014 | Montant WP/FCFA | vérité | Q0 | disponible, provisoire, réservé, masqué |
| CMP-015 | Ventilation financière | vérité | Q0 | brut, frais, taxes, net, part |
| CMP-016 | Ligne d'historique | vérité | Q0 | confirmé, attente, correction, inconnu |
| CMP-017 | Preuve et référence | vérité | Q0 | vérifiée, attente, contestée, expirée |
| CMP-018 | Bannière hors ligne | vérité | Q0/Q1 | ancienne donnée, action bloquée, reprise |
| CMP-019 | Résultat inconnu | vérité | Q0 | en vérification, aucune relance, assistance |
| CMP-020 | Vide explicatif | vérité | Q2 | normal, filtré, non éligible |
| CMP-021 | Erreur récupérable | vérité | Q1 | réessayer, revenir, assistance |
| CMP-022 | Refus/inéligibilité | vérité | Q1 | motif, condition, recours |
| CMP-023 | Notification | composition | Q1 | info, finance, sécurité, urgence |
| CMP-024 | Dialogue de confirmation | composition | Q0 | récapitulatif, auth récente, abandon |
| CMP-025 | Formulaire avec reprise | composition | Q1 | brouillon, conflit, session expirée |
| CMP-026 | Navigation mobile | composition | Q1 | actif, badge, hors ligne, lien profond |
| CMP-027 | Navigation professionnelle | composition | Q1 | réduite, capacité limitée, mobile |
| CMP-028 | Tableau/file de travail | composition | Q1 | chargement, vide, sélection, erreur |
| CMP-029 | Dossier et chronologie | composition | Q1 | événements, pièce, décision, recours |
| CMP-030 | Barre de progression d'attention | composition | Q0/Q1 | prête, cours, interrompue, complétée |
| CMP-031 | Carte d'offre/adhésion | composition | Q1 | gratuite, payante, active, indisponible |
| CMP-032 | Carte d'alerte | composition | Q1 | ordinaire, sponsorisée, résolue, critique |
| CMP-033 | Panneau SOS | composition | Q0 | préparation, transmis, reçu, non transmis |
| CMP-034 | Superposition nationale | composition | Q0 | nouvelle, mise à jour, expirée, accusée |
| CMP-035 | Recours et assistance | composition | Q1 | disponible, délai, dossier existant |

## 3. Contrat minimal de composant

Chaque composant documente :

- rôle et cas d'usage ;
- cas interdits ;
- anatomie ;
- propriétés typées ;
- contenu et vocabulaire ;
- états ;
- interaction clavier/tactile ;
- focus ;
- accessibilité ;
- comportement responsive ;
- thèmes ;
- fixtures ;
- tests ;
- dépendances ;
- écrans consommateurs.

## 4. Règles financières

Les composants CMP-014 à CMP-019 ne calculent aucune valeur métier. Ils reçoivent montants, devise/unité, état, horodatage et références depuis une source autoritative simulée ou réelle.

Ils doivent distinguer :

- acquis et provisoire ;
- propriété et disponibilité ;
- réservation et débit ;
- transmission et confirmation ;
- erreur et résultat inconnu ;
- écriture originale et contre-écriture.

## 5. Règles d'urgence

CMP-032 à CMP-034 :

- n'utilisent pas l'identité publicitaire d'une urgence ;
- affichent source, heure, territoire et instruction lorsque applicables ;
- conservent un appel direct ou une action sûre ;
- disent « non transmis » hors ligne ;
- n'utilisent aucune animation comme preuve ;
- restent accessibles avec mouvement réduit ;
- permettent de retrouver une alerte critique active.

## 6. Promotion d'un nouveau composant

Un élément devient fondamental s'il est réutilisé, sémantiquement stable, critique ou nécessaire à l'accessibilité. La promotion exige :

- nom non ambigu ;
- contrat ;
- exemples ;
- états ;
- tests ;
- revue DS-0001 ;
- absence de règle métier cachée.

Un composant spécifique à une seule page reste local jusqu'à preuve de réutilisation.

