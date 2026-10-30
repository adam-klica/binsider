#!/bin/sh
# Deploy theme + core plugin to the live site on Hostinger, from git:
# the server pulls origin/main into its clone (~/git/binsider) and syncs it into wp-content.
# Commit and push first — only what is on origin/main gets deployed.
#   ./scripts/deploy.sh           # sync code, lint, purge cache, health check
#   ./scripts/deploy.sh --setup   # same, plus the one-time live setup (plugins, language, settings)
#   ./scripts/deploy.sh --starter # same, plus the one-time starter articles import (skips already imported ones)
# Uploads, wp-config.php and the database are never touched by the sync.
set -eu
cd "$(dirname "$0")/.."

SSH_KEY="${SSH_KEY:-$HOME/.ssh/espam_deploy}"
# Server address lives in .deploy.env (gitignored, see .deploy.env.example) — this repo is public.
[ -f .deploy.env ] || { echo ".deploy.env missing (copy .deploy.env.example)" >&2; exit 1; }
. ./.deploy.env
: "${SSH_HOST:?set in .deploy.env}" "${SSH_PORT:?set in .deploy.env}"
SITE_URL="${SITE_URL:-https://binsider.me}"
WP_ROOT='domains/binsider.me/public_html'
GIT_DIR='git/binsider'

SSH="ssh -i $SSH_KEY -p $SSH_PORT -o BatchMode=yes"
# The site runs PHP 8.3 (hPanel), but the SSH shell's default `php` is 8.2 — run WP-CLI and lint with 8.3.
PHP_BIN=/opt/alt/php83/usr/bin/php
remote() { $SSH "$SSH_HOST" "php() { $PHP_BIN \"\$@\"; }; wp() { $PHP_BIN /usr/local/bin/wp \"\$@\"; }; cd ~/$WP_ROOT && $*"; }

[ -f theme/binsider/style.css ] || { echo "theme/binsider missing" >&2; exit 1; }
[ -f plugin/binsider-core/binsider-core.php ] || { echo "plugin/binsider-core missing" >&2; exit 1; }

git diff --quiet HEAD && [ -z "$(git status --porcelain)" ] || { echo "Uncommitted changes — commit and push first" >&2; exit 1; }
git fetch -q origin main
[ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ] || { echo "HEAD is not origin/main — push (or pull) first" >&2; exit 1; }

echo "== Pull + sync on server ($(git rev-parse --short HEAD))"
RSYNC="rsync -rlpt --delete --exclude=.DS_Store --exclude=node_modules"
$SSH "$SSH_HOST" "set -e; git -C ~/$GIT_DIR pull -q --ff-only origin main; git -C ~/$GIT_DIR log -1 --format='server at %h %s'
  $RSYNC ~/$GIT_DIR/theme/binsider/ ~/$WP_ROOT/wp-content/themes/binsider/
  $RSYNC ~/$GIT_DIR/plugin/binsider-core/ ~/$WP_ROOT/wp-content/plugins/binsider-core/"

echo "== PHP lint on server"
remote 'find wp-content/themes/binsider wp-content/plugins/binsider-core -name "*.php" -print0 | xargs -0 -n1 '"$PHP_BIN"' -l | grep -v "^No syntax errors" || true'

if [ "${1:-}" = "--setup" ]; then
  echo "== One-time setup"
  remote '
    set -e
    wp language core install bs_BA --activate || true
    for p in wordpress-seo post-views-counter contact-form-7 limit-login-attempts-reloaded litespeed-cache; do
      wp plugin is-installed "$p" || wp plugin install "$p"
      wp plugin is-active "$p" || wp plugin activate "$p"
    done
    wp language plugin install --all bs_BA || true
    wp plugin delete hello akismet 2>/dev/null || true
    # Hostinger onboarding wizard can switch the theme to its AI theme on admin login.
    wp plugin deactivate hostinger-easy-onboarding 2>/dev/null || true
    wp option update hostinger_show_onboarding 0
    wp plugin is-active binsider-core || wp plugin activate binsider-core
    wp theme activate binsider
    wp option update blogname "Bošnjački Insajder"
    wp option update blogdescription "Nezavisni portal Bošnjaka Crne Gore, regiona i dijaspore"
    wp option update timezone_string "Europe/Podgorica"
    wp option update date_format "j. F Y."
    wp option update time_format "H:i"
    wp option update start_of_week 1
    wp option update posts_per_page 12
    wp option update default_comment_status closed
    wp option update default_ping_status closed
    wp rewrite structure "/%category%/%postname%/" --hard
    wp rewrite flush --hard
  '
fi

if [ "${1:-}" = "--starter" ]; then
  # One-time: import the starter articles + photos, then remove the starter plugin (posts stay).
  # Author "redakcija" (role author) gets a random password that is never shown or stored — it is only a byline.
  echo "== Starter articles"
  $SSH "$SSH_HOST" "$RSYNC ~/$GIT_DIR/plugin/binsider-starter/ ~/$WP_ROOT/wp-content/plugins/binsider-starter/"
  remote '
    set -e
    wp user get redakcija >/dev/null 2>&1 || wp user create redakcija redakcija@binsider.me --role=author \
      --user_pass="$(head -c 24 /dev/urandom | base64)" --display_name="Redakcija" \
      --description="Redakcija portala Bošnjački Insajder." --porcelain >/dev/null
    wp plugin activate binsider-starter
    wp eval "[ \$n, \$left ] = binsider_starter_import_batch(); echo \"Imported: \$n, remaining: \$left\", PHP_EOL;"
    wp plugin deactivate binsider-starter
    wp plugin delete binsider-starter
  '
fi

echo "== Server rules (.htaccess block from binsider-core)"
remote 'wp eval "echo binsider_write_htaccess() ? \"ok\" : \"FAILED\", PHP_EOL;"'

echo "== Purge cache"
remote 'wp litespeed-purge all 2>/dev/null || wp cache flush'

echo "== Health check"
# Hostinger's onboarding wizard (hostinger-easy-onboarding) once switched a live theme to its AI theme — fail loudly.
remote 'wp eval "\$t = get_stylesheet(); \$p = is_plugin_active(\"binsider-core/binsider-core.php\"); echo \$t, \" / binsider-core \", \$p ? \"active\" : \"INACTIVE\", PHP_EOL; exit( \"binsider\" === \$t && \$p ? 0 : 1 );"' \
  || { echo "ERROR: wrong theme or binsider-core inactive on the live site" >&2; exit 1; }
if curl -fsS -o /dev/null -w "%{http_code} $SITE_URL/\n" "$SITE_URL/"; then
  echo "DEPLOY OK"
else
  echo "WARNING: $SITE_URL/ not reachable from here (DNS/SSL?) — code is deployed, check the site manually." >&2
fi
