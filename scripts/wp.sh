#!/bin/sh
# Helper: ./scripts/wp.sh setup | demo | <any wp-cli args>
set -eu
cd "$(dirname "$0")/.."

# Local test credentials (generated once, never shipped)
CRED=.local-credentials
if [ ! -f "$CRED" ]; then
  {
    echo "ADMIN_USER=admin"
    echo "ADMIN_PASS=$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 20)"
    echo "ADMIN_EMAIL=admin@binsider.test"
    echo "EDITOR_USER=urednik"
    echo "EDITOR_PASS=$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 20)"
  } >"$CRED"
  chmod 600 "$CRED"
fi
set -a; . "./$CRED"; set +a

ENVS="-e ADMIN_USER -e ADMIN_PASS -e ADMIN_EMAIL -e EDITOR_USER -e EDITOR_PASS"
case "${1:-}" in
  setup) docker compose --progress quiet run --rm $ENVS --entrypoint sh cli /scripts/setup.sh ;;
  demo)  docker compose --progress quiet run --rm $ENVS --entrypoint sh cli /scripts/demo-content.sh ;;
  *)     docker compose --progress quiet run --rm -T cli "$@" ;;
esac
