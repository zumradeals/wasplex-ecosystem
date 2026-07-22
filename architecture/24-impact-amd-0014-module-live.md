# Impact architectural d'AMD-0014 — Module Live

**Statut :** spécification d'application — AMD-0014 adopté  
**Décisions concernées :** ADR-0001 à ADR-0009, UX-0001 à UX-0003, DS-0001

## Frontière

Live est un module métier du monolithe modulaire. Il possède les sessions, leur programmation, leurs versions, la présence technique, les interactions, la modération en direct et les preuves de qualification. Il ne possède ni budget annonceur, ni solde, ni identité complète, ni consentement publicitaire.

## Contrats minimaux

- Publicité réserve l'enveloppe et référence la campagne lorsqu'un Live est publicitaire.
- Autorisations décide si le diffuseur, le modérateur et l'organisation disposent de la capacité et de la finalité nécessaires.
- Live qualifie un intervalle ou une interaction sans créditer de valeur.
- Wallet accepte ou refuse une instruction financée et idempotente.
- Alertes peut interrompre ou superposer une alerte nationale selon AMD-0007 et AMD-0014.
- Notifications informe sans constituer une preuve financière.

## Données et confidentialité

La télémétrie est minimisée. Une présence technique n'est pas une attention qualifiée par défaut. Les signaux Live ne nourrissent le profil publicitaire qu'avec finalité, base et consentement compatibles ; les données sensibles de chat ou de réaction ne deviennent jamais un segment implicite.

## Échecs

La coupure réseau, le rejeu, la reconnexion, le changement d'appareil et le résultat inconnu sont des états normaux à tester. La reprise ne recrée aucun intervalle déjà compté. Une interruption de sécurité préserve les preuves et les droits réellement acquis.

## Exploitation

Le fournisseur vidéo transporte le média mais ne décide ni l'éligibilité, ni la preuve économique, ni le montant. Un plan de sortie doit permettre de remplacer ce fournisseur sans réécrire le domaine Live.

