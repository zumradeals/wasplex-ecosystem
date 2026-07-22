# Gouvernance — Audit et preuves

**Statut :** spécification adoptée — AMD-0012
## 1. Événements

Journalisation obligatoire des authentifications privilégiées, élévations, consultations sensibles, exports, rôles, configurations, campagnes, Wallet exceptionnel, antifraude, Fonds Social, Alertes, Institutions, contenus, accès techniques et urgences.

## 2. Contenu minimal

- identifiant unique ;
- acteur et rôle effectif ;
- organisation ;
- date serveur ;
- environnement et origine ;
- action et ressource ;
- finalité ou dossier ;
- règle et version ;
- approbations ;
- résultat ;
- empreintes ou différences minimisées.

Les secrets, mots de passe, OTP, clés, documents complets et données médicales ne sont pas journalisés en clair.

## 3. Protection

Les journaux critiques sont append-only ou équivalents, chaînés ou signés, répliqués et sauvegardés dans un domaine distinct.

Une correction ajoute un événement lié. La suppression administrative directe est impossible.

## 4. Consultation

Accès par capacité et finalité. Toute recherche, lecture, export ou suppression arrivée à échéance est elle-même auditée.

Un acteur voit seulement les traces nécessaires. L'auditeur ne modifie pas le système audité.

## 5. Conservation

Politique par catégorie, pays et finalité. Les preuves constitutionnelles et décisions fondatrices sont durables. Les journaux techniques ordinaires ne sont pas conservés indéfiniment sans besoin.

Expiration et destruction produisent une preuve sans réexposer le contenu détruit.

## 6. Vérification

Contrôles automatiques d'intégrité, alertes de lacune, revues périodiques et audits indépendants de la couverture Wallet, données, accès, décisions et incidents.

## 7. Preuve survivante

Fermeture, suspension ou suppression publique d'un objet ne détruit pas les preuves nécessaires. L'accès ultérieur reste limité à la finalité de conservation.
