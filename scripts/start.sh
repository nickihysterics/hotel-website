#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root_dir"

docker compose up -d --build

# Реальные опубликованные порты читаются из Compose, поэтому скрипт работает и с .env overrides.
http_port="$(docker compose port nginx 80 | awk -F: '{print $NF}')"
phpmyadmin_port="$(docker compose port phpmyadmin 80 | awk -F: '{print $NF}')"

wait_for_url() {
  local url="$1"
  local timeout="${2:-60}"
  local start="$SECONDS"

  until curl -fsS --max-time 2 "$url" >/dev/null 2>&1; do
    if (( SECONDS - start >= timeout )); then
      echo "Не удалось дождаться: $url"
      return 1
    fi
    sleep 1
  done
}

# Открытие браузера поддерживает macOS и Linux, но не является условием успешного запуска.
open_url() {
  local url="$1"

  if command -v open >/dev/null 2>&1; then
    open "$url"
    return 0
  fi

  if command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$url"
    return 0
  fi

  echo "Открой вручную: $url"
}

urls=(
  "http://localhost:${http_port}/"
  "http://localhost:${http_port}/admin/login.php"
  "http://localhost:${phpmyadmin_port}/"
)

for url in "${urls[@]}"; do
  wait_for_url "$url" 60
done

for url in "${urls[@]}"; do
  open_url "$url"
done
