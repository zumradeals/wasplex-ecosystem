# ADR-0001 — Architecture technique initiale de Wasplex

**État :** proposé à la validation du fondateur  
**Date :** 22 juillet 2026

## Contexte

Wasplex doit servir un marché mobile, parfois peu connecté et peu technique, tout en garantissant la traçabilité d'un système économique, social et sécuritaire sensible. Une architecture distribuée prématurée augmenterait coûts, compétences nécessaires et modes de panne.

## Décision proposée

### 1. Forme

Wasplex adopte un **monolithe modulaire** comme architecture initiale : une application centrale, des frontières métier strictes, des contrats internes explicites et aucune écriture directe dans les données d'un autre module.

Les microservices ne sont pas adoptés au lancement.

### 2. Stack officiel

- **Backend :** PHP avec Laravel.
- **Base principale :** PostgreSQL.
- **Interface :** React avec TypeScript, relié à Laravel par Inertia.
- **Construction front-end :** Vite.
- **Web :** responsive mobile-first et PWA.
- **Desktop :** mêmes composants et règles, présentation adaptée aux rôles professionnels.
- **Traitements différés :** files de tâches Laravel ; le pilote peut commencer simplement, avec un moteur dédié lorsque la charge le justifie.
- **Cache et sessions :** mécanisme interchangeable ; Redis n'est introduit que lorsque disponibilité, concurrence ou volume le justifient.
- **Médias et preuves :** stockage objet compatible S3, séparé de la base relationnelle.
- **Android :** projet ultérieur consommant les mêmes contrats applicatifs ; il ne devient jamais une seconde logique métier.

Les versions exactes sont maintenues dans un registre technique supporté et mises à jour par décision d'exploitation ; elles ne sont pas constitutionnelles.

### 3. Déploiement

Une application unique ne signifie pas un serveur unique. La production sépare au minimum application, PostgreSQL, stockage objet et sauvegardes. Les composants peuvent être gérés ou auto-hébergés selon coût et compétence, à condition de respecter couverture, restauration, chiffrement et audit.

Supabase n'appartient pas au stack officiel.

### 4. Accès

Le Web mobile est le canal universel. La PWA complète ce canal. Le desktop sert les tâches complexes. Android natif vient après stabilisation, pour les capacités matérielles qui apportent une valeur démontrée.

### 5. Données et transactions

PostgreSQL est la source transactionnelle principale. Le Wallet utilise un ledger en partie double. Les échanges intermodules critiques sont idempotents, corrélés et persistés avec une outbox transactionnelle. Les projections et caches ne deviennent jamais sources de vérité.

### 6. Résilience

Avant production, chaque domaine possède RPO, RTO, stratégie de sauvegarde, test de restauration, procédure de lecture seule et ordre de reprise. Les valeurs sont configurées dans les politiques d'exploitation, pas codées en dur.

### 7. Limites

Aucun outil, framework ou hébergeur ne peut contourner la Constitution. Une simplification technique ne justifie jamais la perte de preuve, la création de valeur sans source, l'accès transversal aux données ou la diminution de la sécurité.

## Conséquences

Cette décision minimise le coût initial et la complexité opérationnelle tout en gardant des frontières permettant une évolution future. Tout futur prompt de développement devra citer cette décision et le module concerné.

## Documents normatifs associés

- `architecture/01-canaux-acces-et-mode-degrade.md`
- `architecture/02-monolithe-modulaire-et-frontieres.md`
- `architecture/03-resilience-securite-et-exploitation.md`

La validation du fondateur rendra cet ADR officiel sans transformer les choix de versions logicielles en principes constitutionnels.