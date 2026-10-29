#!/bin/sh
# Local demo content (never shipped). Run: ./scripts/wp.sh demo
set -eu
: "${EDITOR_USER:?}" "${EDITOR_PASS:?}"

if ! wp user get "$EDITOR_USER" >/dev/null 2>&1; then
  wp user create "$EDITOR_USER" urednik@binsider.test --role=editor \
    --user_pass="$EDITOR_PASS" --display_name="Amra Hodžić" \
    --description="Novinarka i urednica. Prati politiku, društvo i dijasporu."
fi
if ! wp user get redakcija >/dev/null 2>&1; then
  wp user create redakcija redakcija@binsider.test --role=author \
    --user_pass="$(head -c 24 /dev/urandom | base64)" --display_name="Redakcija" \
    --description="Redakcija portala Bošnjački Insajder."
fi

wp eval-file /demo/demo.php
echo "DEMO OK"
