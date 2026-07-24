# Déploiement de production Wasplex

## Contexte

Jusqu'à ce lot, aucune procédure de déploiement n'existait sur ce dépôt ni
sur le serveur : le seul déploiement effectif (commit de bootstrap
`382247c`, le 2026-07-23) a été fait manuellement, commande par commande,
sans trace reproductible (pas de crontab, pas de systemd timer, pas de
script). `deploy.sh` remplace cette procédure implicite par une séquence
versionnée, explicite et qui s'arrête au premier échec.

Ce script ne s'auto-exécute jamais. Il n'est déclenché par aucun cron, aucun
hook, aucun webhook. Il se lance à la main, par une personne autorisée, après
lecture de son contenu — conformément à CLAUDE.md §5 (« aucun déploiement,
migration de production, activation pays, traitement réel ou communication
externe sans instruction distincte et explicite »).

## Prérequis

- Exécuté en tant qu'utilisateur disposant des droits d'écriture sur
  `/var/www/html/wasplex-ecosystem` et du droit `sudo systemctl reload
  php8.3-fpm` (le serveur actuel fait tourner ce script en `root`).
- `git`, `composer`, `npm`, `php`, `systemctl` disponibles dans `PATH`.
- Le `.env` de production existe déjà et n'est pas touché par ce script :
  aucune étape ne le lit, l'écrit ou le régénère.

## Usage

```bash
sudo bash deployment/deploy.sh
```

Peut être lancé depuis n'importe quel répertoire courant ; tous les chemins
utilisés sont fixes (`/var/www/html/wasplex-ecosystem`), pas relatifs au
répertoire d'appel.

## Ce que fait chaque étape, et pourquoi

1. **Vérifier le checkout et la branche** — refuse de continuer si le script
   n'est pas exécuté contre le checkout de production réel, sur `main`.
   Empêche une exécution accidentelle dans un worktree de développement.
2. **`git fetch origin && git status --short --branch`** — affiché, jamais
   silencieux : on veut voir l'état exact (retard/avance, fichiers modifiés)
   avant de toucher à quoi que ce soit.
3. **`git pull --ff-only origin main`** — refuse explicitement si ce n'est
   pas un fast-forward. Jamais de merge ni de rebase automatique en
   production : une divergence doit être résolue à la main, jamais absorbée
   silencieusement par le script.
4. **`composer install --no-dev --optimize-autoloader --no-interaction`** —
   dépendances PHP de production uniquement, autoloader optimisé.
5. **`npm ci`** — installation Node reproductible à partir de
   `package-lock.json` (jamais `npm install`, qui peut faire dériver les
   versions).
6. **`php artisan wayfinder:generate --with-form`** — régénère les routes et
   actions typées consommées par le frontend, cohérentes avec les routes
   backend qui viennent d'arriver (ex. `advertising.campaigns.store`).
7. **`npm run build`** — build des assets frontend de production.
8. **`php artisan migrate:status`** — affiché avant toute exécution :
   c'est le seul moyen de savoir, avant que ça se produise réellement, quelles
   migrations vont s'exécuter sur `wasplex`.
9. **`php artisan migrate --force`** — la seule commande de tout ce script
   qui touche réellement la base de production. `--force` est nécessaire
   car `APP_ENV=production` ; c'est un choix explicite, pas un contournement
   accidentel d'une protection.
10. **`php artisan config:clear`** — vide le cache de config sans le
    reconstruire. Volontairement pas `config:cache` : la configuration
    bouge encore à chaque lot ; figer un cache de config à ce stade
    créerait un risque de configuration périmée après le prochain
    déploiement de code sans nouveau cache. À reconsidérer quand la
    configuration se stabilisera.
11. **`sudo systemctl reload php8.3-fpm`** — `reload`, jamais `restart` :
    les workers PHP-FPM terminent les requêtes en cours puis sont
    remplacés ; aucune requête n'est coupée en vol.
12. **`git log -1`** — confirmation finale et non ambiguë du commit
    réellement en place après le déploiement.

## Ce que ce script ne fait pas

- Ne touche pas au DNS, à la configuration TLS, ni à UFW.
- Ne modifie pas `.env`.
- Ne redémarre pas Nginx (aucune modification de sa configuration n'est
  faite par ce script).
- Ne fait aucun rollback automatique : en cas d'échec (`set -euo pipefail`
  arrête immédiatement à la première commande en erreur), l'état du
  checkout et de la base reflète tout ce qui s'est exécuté avec succès
  jusqu'à l'étape qui a échoué. Un rollback reste une décision humaine,
  pas une action automatisée par ce script.
