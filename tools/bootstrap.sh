#!/usr/bin/env bash

set -euo pipefail

ROOT="$(
  cd "$(
    dirname "${BASH_SOURCE[0]}"
  )/.." &&
  pwd
)"

cd "$ROOT"

if [[ ! -f .env ]]; then
  ./tools/init-env.sh
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

wpcli() {
  docker compose run \
    --rm \
    wpcli \
    wp \
    "$@"
}

echo "=================================================="
echo "W1 - BOOTSTRAP WORDPRESS"
echo "=================================================="

echo
echo "=== COMPOSER CORE ==="

docker compose run \
  --rm \
  composer \
  dump-autoload \
  --no-dev \
  --optimize

echo
echo "=== SUBINDO MARIADB / WORDPRESS ==="

docker compose up \
  -d \
  db \
  wordpress

echo
echo "=== AGUARDANDO WORDPRESS ==="

for attempt in $(
  seq 1 60
); do
  if wpcli core version \
      >/dev/null 2>&1; then
    echo "✅ WordPress Core disponivel."
    break
  fi

  if [[ "$attempt" -eq 60 ]]; then
    echo "❌ WordPress nao ficou disponivel."
    docker compose ps
    exit 1
  fi

  sleep 2
done

echo
echo "=== LIGANDO CODIGO CUSTOMIZADO ==="

docker compose exec \
  -T \
  wordpress \
  bash -lc '
    set -e

    rm -rf \
      /var/www/html/wp-content/plugins/facil-digital-core

    rm -rf \
      /var/www/html/wp-content/themes/facil-digital

    ln -s \
      /workspace/wp-content/plugins/facil-digital-core \
      /var/www/html/wp-content/plugins/facil-digital-core

    ln -s \
      /workspace/wp-content/themes/facil-digital \
      /var/www/html/wp-content/themes/facil-digital
  '

echo "✅ Tema e Core ligados por symlink."

echo
echo "=== INSTALACAO WORDPRESS ==="

if ! wpcli core is-installed \
    >/dev/null 2>&1; then
  wpcli core install \
    --url="$WORDPRESS_URL" \
    --title="Facil Digital+" \
    --admin_user="$WORDPRESS_ADMIN_USER" \
    --admin_password="$WORDPRESS_ADMIN_PASSWORD" \
    --admin_email="$WORDPRESS_ADMIN_EMAIL" \
    --skip-email

  echo "✅ WordPress instalado."
else
  echo "ℹ WordPress ja estava instalado."
fi

echo
echo "=== IDIOMA ==="

wpcli language core install \
  pt_BR \
  --activate \
  >/dev/null

echo "✅ pt_BR ativo."

echo
echo "=== REMOVENDO PLUGINS PADRAO DESNECESSARIOS ==="

wpcli plugin deactivate \
  hello \
  akismet \
  >/dev/null 2>&1 \
  || true

wpcli plugin delete \
  hello \
  akismet \
  >/dev/null 2>&1 \
  || true

echo "✅ Ambiente limpo."

echo
echo "=== WOOCOMMERCE 11.0.1 ==="

CURRENT_WOO="$(
  wpcli plugin get \
    woocommerce \
    --field=version \
    2>/dev/null \
    || true
)"

if [[ "$CURRENT_WOO" != "11.0.1" ]]; then
  wpcli plugin install \
    woocommerce \
    --version=11.0.1 \
    --force
fi

wpcli plugin activate \
  woocommerce

echo "✅ WooCommerce ativo."

echo
echo "=== TEMA FACIL DIGITAL+ ==="

wpcli theme activate \
  facil-digital

echo "✅ Tema ativo."

echo
echo "=== FACIL DIGITAL+ CORE ==="

wpcli plugin activate \
  facil-digital-core

echo "✅ Core ativo."

echo
echo "=== CONFIGURACAO BASE ==="

wpcli eval-file \
  /workspace/tools/wp-configure.php

echo
echo "=== REWRITE ==="

wpcli rewrite flush \
  --hard

echo
echo "=================================================="
echo "BOOTSTRAP CONCLUIDO"
echo "=================================================="

echo
echo "Site:"
echo "  $WORDPRESS_URL"

echo
echo "Admin:"
echo "  ${WORDPRESS_URL}/wp-admin/"

echo
echo "Usuario DEV:"
echo "  $WORDPRESS_ADMIN_USER"

echo
echo "Senha DEV:"
echo "  consulte localmente a variavel"
echo "  WORDPRESS_ADMIN_PASSWORD em .env"
echo
echo "Nao envie a senha para o chat."
