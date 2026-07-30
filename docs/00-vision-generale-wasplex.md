# Vision générale de Wasplex

## 1. Rôle de Wasplex

Wasplex est une plateforme qui organise plusieurs services autour d’un même compte utilisateur et d’un même Wallet :

- publicité participative et rémunérée ;
- abonnements et capacités utilisateur ;
- Wallet et WasPoints ;
- Fonds social ;
- alertes, objets perdus, personnes disparues et protection ;
- cartes et opérations avec des partenaires ;
- espaces annonceur, administration et institutions autorisées.

La plateforme doit rester compréhensible : chaque module possède un objectif concret, un parcours complet et des flux financiers séparés.

## 2. Acteurs

### Utilisateur

Il crée un compte, choisit ses consentements, complète les informations utiles aux services qu’il souhaite utiliser, consulte le Feed, peut participer volontairement aux publicités, gérer son Wallet, adhérer au Fonds social, publier ou consulter des alertes et utiliser des offres partenaires.

### Annonceur

Il crée une campagne, choisit une audience autorisée, obtient une estimation et un devis, finance la campagne, suit les événements validés et reçoit des rapports agrégés. Il ne reçoit pas la liste ni les coordonnées des utilisateurs ciblés.

### Wasplex

Wasplex configure les offres et tarifs, protège les données, vérifie les acteurs, organise la diffusion, valide les événements, tient les comptes, traite les dossiers et administre les différents modules.

### Institution ou partenaire

Une institution ou un partenaire intervient uniquement dans les services qui lui sont ouverts : traitement d’une alerte, réalisation d’un vœu, offre commerciale, vérification ou paiement. Son accès reste limité à son rôle réel.

## 3. Principes de fonctionnement

- Une donnée n’est collectée que pour un service identifiable.
- Un annonceur choisit des critères ; Wasplex réalise la correspondance sans exposer les profils individuels.
- Une somme affichée comme gagnée doit correspondre à une somme réellement traçable dans le Wallet.
- Chaque flux financier possède une origine, une destination et un motif.
- Les prix, noms de plans, quotas et paramètres commerciaux sont gérés depuis l’administration et ne sont pas codés en dur.
- Les valeurs de démonstration ne sont jamais présentées comme des prix commerciaux.
- Un service payant n’est jamais présenté comme une garantie de revenu.
- Une interface n’est considérée comme terminée que lorsque son moteur métier fonctionne réellement.

## 4. Séparation des flux

Les sources d’argent ne doivent pas être mélangées :

- budgets publicitaires ;
- revenus propres de Wasplex ;
- droits utilisateur en WasPoints ;
- paiements d’abonnement ;
- cotisations et réserve du Fonds social ;
- récompenses liées aux alertes ;
- opérations et commissions partenaires ;
- remboursements, taxes et frais externes.

Une même somme ne peut pas être distribuée deux fois.

## 5. Administration

L’administration doit permettre de configurer les éléments commerciaux sans redéploiement du code, notamment :

- abonnements et types économiques ;
- quotas et plafonds ;
- prix publicitaires et coefficients ;
- formats et durées ;
- secteurs et critères de ciblage ;
- plans du Fonds social ;
- catégories d’alertes ;
- cartes, partenaires et offres ;
- dates d’effet et états actifs ou inactifs.

Toute modification économique doit conserver l’ancienne version pour expliquer les opérations déjà réalisées.

## 6. Statut des documents métier

Les fichiers de `docs/` décrivent directement le produit attendu. Lorsqu’un point n’est pas encore décidé, il est marqué comme tel afin que le développement ne le remplace pas par une hypothèse silencieuse.