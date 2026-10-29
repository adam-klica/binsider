#!/bin/sh
# Idempotent local setup. Run from the project root:
#   ./scripts/wp.sh setup
# Runs inside the WP-CLI container (see docker-compose.yml, service "cli").
set -eu

: "${ADMIN_USER:?}" "${ADMIN_PASS:?}" "${ADMIN_EMAIL:?}"
URL="${SITE_URL:-http://localhost:8080}"

if ! wp core is-installed 2>/dev/null; then
  wp core install --url="$URL" --title="Bošnjački Insajder" \
    --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" \
    --admin_email="$ADMIN_EMAIL" --skip-email
fi

# Language: Bosnian
wp language core install bs_BA --activate || true

# Third-party plugins. LiteSpeed Cache: page cache only works on Hostinger's LiteSpeed server,
# locally it still runs CSS/JS/HTML minification, so the theme is tested with it.
for p in wordpress-seo post-views-counter contact-form-7 limit-login-attempts-reloaded litespeed-cache; do
  wp plugin is-installed "$p" || wp plugin install "$p"
  wp plugin is-active "$p" || wp plugin activate "$p"
done
wp language plugin install --all bs_BA || true

# Remove bundled extras
wp plugin delete hello akismet 2>/dev/null || true

# Our code (present once written)
if [ -f wp-content/plugins/binsider-core/binsider-core.php ]; then
  wp plugin is-active binsider-core || wp plugin activate binsider-core
fi
if [ -f wp-content/themes/binsider/style.css ]; then
  wp theme activate binsider
  for t in twentytwentythree twentytwentyfour; do wp theme delete "$t" 2>/dev/null || true; done
fi

# Settings
wp option update blogdescription "Nezavisni portal Bošnjaka Crne Gore, regiona i dijaspore"
wp option update timezone_string "Europe/Podgorica"
wp option update date_format "j. F Y."
wp option update time_format "H:i"
wp option update start_of_week 1
wp option update posts_per_page 12
wp option update default_comment_status closed
wp option update default_ping_status closed
wp option update blog_public 1
wp rewrite structure '/%category%/%postname%/' --hard
wp rewrite flush --hard

echo "SETUP OK"
