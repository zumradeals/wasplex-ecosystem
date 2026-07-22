# Gouvernance — Configurations et changements

**Statut :** spécification proposée

## 1. Objet versionné

Une configuration possède identifiant, version, schéma, ancienne et nouvelle représentation sûres, motif, auteur, approbateurs, impact, dates, territoire, modules et statut.

L'état applicable à une date passée est reconstructible.

## 2. Classes

### Ordinaire

Texte ou présentation sans impact important. Autorisation simple et audit.

### Financière sensible

Frais, seuil, plafond, quota rémunéré, prix, contribution, pool ou PSP. Simulation, double validation, date future, notification et transition.

### Critique

Couverture Wallet, KYC majeur, accès aux données, pouvoirs institutionnels, urgence, Fonds Social. Analyses juridique, économique, sécurité et technique ; décision collégiale et conservation durable.

### Constitutionnelle

Aucun écran ordinaire. Amendement identifié, motivation, impacts, examen, procédure d'adoption, nouvelle version et archivage permanent.

## 3. Non-rétroactivité

L'opération utilise la version active à son instant de référence.

Gains acquis, campagnes financées et services déjà payés ne sont pas défavorablement recalculés, sauf obligation supérieure ou correction de fraude propre prouvée.

## 4. Analyse d'impact

Avant activation sensible :

- utilisateurs et contrats ;
- finances et couverture ;
- données et consentements ;
- sécurité et fraude ;
- territoires et réglementation ;
- performances et régression ;
- migration et retour.

Une inconnue majeure bloque l'activation ou réduit son périmètre.

## 5. Activation

Auteur différent de l'approbateur pour changement sensible. Délai d'attente et date future par défaut.

Urgence technique : activation immédiate possible, auto-expiration ou confirmation rapide, puis revue.

## 6. Retour

Le retour crée une nouvelle version. Il ne supprime ni opérations ni écritures effectuées sous la version retirée.

Une migration compensatoire suit les règles du ledger.

## 7. Secrets

Les configurations secrètes sont chiffrées et référencées sans valeur en clair dans l'audit. Une rotation conserve la preuve du changement, jamais le secret antérieur lisible.
