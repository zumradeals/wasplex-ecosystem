# Identité — Authentification et récupération

**Statut :** spécification adoptée — AMD-0010
## 1. Facteurs

- connaissance : mot de passe ou PIN ;
- possession : appareil approuvé, clé, application d'authentification ;
- inhérence : biométrie, lorsqu'autorisée.

Deux éléments du même type ne constituent pas deux facteurs indépendants.

La biométrie locale peut déverrouiller une clé de l'appareil sans être transmise à Wasplex.

## 2. Secrets

- mots de passe et PIN ne sont jamais stockés en clair ;
- les vérificateurs utilisent un mécanisme de dérivation adapté ;
- OTP et liens expirent, sont à usage unique et limités en tentatives ;
- codes de secours sont hachés, révocables et affichés une seule fois ;
- journaux ne contiennent aucun secret complet ;
- sessions et jetons sont révocables individuellement.

## 3. Authentification adaptative

Le risque considère montant, type d'opération, appareil, ancienneté, localisation approximative, destinataire, fréquence, récupération récente et signaux de compromission.

Une hausse de risque demande une preuve supplémentaire ; elle ne prononce pas une fraude.

## 4. Confirmation d'opérations

Réauthentification ou confirmation renforcée pour :

- retrait et transfert selon seuil ;
- nouveau destinataire ;
- ajout ou changement de moyen de paiement ;
- changement d'identité, numéro ou facteurs ;
- récupération ;
- activation d'une carte selon le risque ;
- adhésion ou hausse d'un mandat social ;
- vœu important ;
- capacité institutionnelle critique.

Une baisse ou révocation de mandat peut être simplifiée et reste traçable.

## 5. Perte d'appareil

Depuis un canal sûr, l'utilisateur peut fermer toutes les sessions, désapprouver l'appareil et bloquer les opérations sensibles.

La perte ne supprime ni compte ni gains.

## 6. Récupération

La récupération utilise plusieurs preuves et un niveau au moins équivalent au risque du compte.

Ne suffisent jamais seuls pour un compte de forte valeur :

- OTP SMS ;
- e-mail ;
- contact de confiance ;
- questions personnelles ;
- connaissance de transactions ;
- ancien appareil non approuvé.

Une récupération sensible déclenche révocation des sessions, notification sur anciens canaux, délai de sécurité et limites temporaires.

## 7. Contact de confiance

Le contact confirme au plus un contexte. Il ne voit ni solde, ni données KYC, ni profil et ne peut prendre le contrôle ou autoriser une opération.

## 8. Changement de numéro

Confirmation ancien et nouveau canaux lorsque possible. Sinon, récupération renforcée. Après changement, appareils et facteurs sont réévalués et les opérations à risque temporairement limitées.

## 9. Visibilité

L'utilisateur voit appareils, sessions, dernières connexions approximatives et événements sensibles. Il peut révoquer une session ou tout fermer.

## 10. Notifications

Connexion nouvelle, changement de secret ou identité, moyen de paiement, retrait, transfert important, Carte Wasplex, mandat social, KYC, récupération et révocation déclenchent une notification utile et un canal de signalement.
