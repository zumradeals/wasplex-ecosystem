#!/usr/bin/env bash
#
# Déploiement de production Wasplex — voir deployment/README.md pour le
# détail de chaque étape et les raisons des choix faits ici.
#
# Usage : sudo bash deployment/deploy.sh
# (exécuté depuis n'importe quel répertoire ; les chemins sont fixes)

set -euo pipefail

PROJECT_ROOT="/var/www/html/wasplex-ecosystem"
APP_DIR="$PROJECT_ROOT/apps/platform"
ARTISAN="php $APP_DIR/artisan"
FPM_SERVICE="php8.3-fpm"

step() {
    echo
    echo "==> [$1/12] $2"
}

# --- Prérequis : échouer tôt et clairement plutôt que sur une commande
# introuvable au milieu du déploiement. ---
for cmd in git composer npm php systemctl sudo; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "ERREUR : commande requise introuvable dans PATH : $cmd" >&2
        exit 1
    fi
done

# --- 1. Vérifier le checkout et la branche ---
step 1 "Vérification du checkout de production et de la branche"

REPO_ROOT="$(git -C "$PROJECT_ROOT" rev-parse --show-toplevel 2>/dev/null || true)"
if [[ "$REPO_ROOT" != "$PROJECT_ROOT" ]]; then
    echo "ERREUR : $PROJECT_ROOT n'est pas la racine d'un dépôt Git (trouvé : '${REPO_ROOT:-aucun}')." >&2
    exit 1
fi

CURRENT_BRANCH="$(git -C "$PROJECT_ROOT" branch --show-current)"
if [[ "$CURRENT_BRANCH" != "main" ]]; then
    echo "ERREUR : le checkout de production doit être sur 'main' (trouvé : '$CURRENT_BRANCH')." >&2
    exit 1
fi
echo "OK : $PROJECT_ROOT, branche main"

# --- 2. Fetch + état affiché ---
step 2 "git fetch origin && git status --short --branch"
git -C "$PROJECT_ROOT" fetch origin
git -C "$PROJECT_ROOT" status --short --branch

# --- 3. Pull fast-forward uniquement, refus explicite sinon ---
step 3 "git pull --ff-only origin main"
if ! git -C "$PROJECT_ROOT" pull --ff-only origin main; then
    echo "ERREUR : le pull n'est pas un fast-forward." >&2
    echo "Refus explicite : ce script ne fait jamais de merge ni de rebase automatique en production." >&2
    echo "Résoudre manuellement (état de main, divergence) avant de relancer ce script." >&2
    exit 1
fi

# --- 4. Dépendances PHP ---
step 4 "composer install --no-dev --optimize-autoloader --no-interaction"
composer install --no-dev --optimize-autoloader --no-interaction --working-dir="$APP_DIR"

# --- 5. Dépendances Node ---
step 5 "npm ci"
npm ci --prefix "$APP_DIR"

# --- 6. Génération Wayfinder (routes/actions typées pour le frontend) ---
step 6 "php artisan wayfinder:generate --with-form"
$ARTISAN wayfinder:generate --with-form

# --- 7. Build des assets frontend ---
step 7 "npm run build"
npm run build --prefix "$APP_DIR"

# --- 8. État des migrations, affiché avant toute exécution ---
step 8 "php artisan migrate:status (aperçu avant exécution)"
$ARTISAN migrate:status

# --- 9. Seule commande qui touche réellement la base wasplex ---
step 9 "php artisan migrate --force"
$ARTISAN migrate --force

# --- 10. Vider le cache de config (pas de config:cache : trop tôt, la
# configuration bouge encore à chaque lot) ---
step 10 "php artisan config:clear"
$ARTISAN config:clear

# --- 11. Reload PHP-FPM, jamais restart : ne coupe pas les requêtes en cours ---
step 11 "sudo systemctl reload $FPM_SERVICE"
sudo systemctl reload "$FPM_SERVICE"

# --- 12. Confirmation du commit réellement déployé ---
step 12 "Commit déployé"
git -C "$PROJECT_ROOT" log -1

echo
echo "Déploiement terminé sans erreur."
