#!/bin/sh
set -eu

base_url=${BASE_URL:-https://localhost}
admin_login=${ADMIN_LOGIN:-hotel_admin}
admin_password=${ADMIN_PASSWORD:-HotelDemo2024}
guest_login=${GUEST_LOGIN:-guest@hotel.localhost}
guest_password=${GUEST_PASSWORD:-GuestDemo2024}
# Все cookies и полученные HTML-файлы живут только до завершения проверки.
work_dir=$(mktemp -d)
trap 'rm -rf -- "$work_dir"' EXIT

fetch() {
  name=$1
  url=$2
  code=$(curl -k -sS -o "$work_dir/$name.html" -w '%{http_code}' "$base_url$url")
  [ "$code" = '200' ] || { echo "$name: HTTP $code"; exit 1; }
  echo "$name: OK"
}

# Compose сообщает о запуске контейнера раньше, чем Nginx гарантированно принимает HTTPS.
wait_for_app() {
  attempts=0
  until curl -k -fsS --max-time 2 "$base_url/" >/dev/null 2>&1; do
    attempts=$((attempts + 1))
    [ "$attempts" -lt 60 ] || { echo 'Приложение не запустилось за 60 секунд'; exit 1; }
    sleep 1
  done
}

# Сначала проверяем публичный контур, затем два независимых сценария авторизации.
wait_for_app
fetch home /
fetch rooms /rooms.php
fetch services /services.php
fetch guest-login /login.php
fetch admin-login /admin/login.php

curl -k -sS -c "$work_dir/admin.cookies" "$base_url/admin/login.php" -o "$work_dir/admin-form.html"
token=$(sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' "$work_dir/admin-form.html" | head -1)
curl -k -sS -b "$work_dir/admin.cookies" -c "$work_dir/admin.cookies" -X POST \
  --data-urlencode "_token=$token" --data-urlencode "login=$admin_login" \
  --data-urlencode "password=$admin_password" "$base_url/admin/login.php" -o /dev/null
code=$(curl -k -sS -b "$work_dir/admin.cookies" -o "$work_dir/dashboard.html" -w '%{http_code}' "$base_url/admin/index.php")
[ "$code" = '200' ] || { echo "admin dashboard: HTTP $code"; exit 1; }
for page in bookings.php operations.php payments.php service_orders.php housekeeping.php analytics.php exports.php; do
  code=$(curl -k -sS -b "$work_dir/admin.cookies" -o "$work_dir/admin-page.html" -w '%{http_code}' "$base_url/admin/$page")
  [ "$code" = '200' ] || { echo "admin/$page: HTTP $code"; exit 1; }
done

curl -k -sS -c "$work_dir/guest.cookies" "$base_url/login.php" -o "$work_dir/guest-form.html"
token=$(sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' "$work_dir/guest-form.html" | head -1)
curl -k -sS -b "$work_dir/guest.cookies" -c "$work_dir/guest.cookies" -X POST \
  --data-urlencode "_token=$token" --data-urlencode "email=$guest_login" \
  --data-urlencode "password=$guest_password" "$base_url/login.php" -o /dev/null
code=$(curl -k -sS -b "$work_dir/guest.cookies" -o "$work_dir/account.html" -w '%{http_code}' "$base_url/account/")
[ "$code" = '200' ] || { echo "guest account: HTTP $code"; exit 1; }
code=$(curl -k -sS -b "$work_dir/guest.cookies" -o "$work_dir/booking.html" -w '%{http_code}' "$base_url/account/booking.php?id=1")
[ "$code" = '200' ] || { echo "guest booking: HTTP $code"; exit 1; }

# Отдельно проверяем однокнопочный вход, который не передаёт demo-пароль в браузер.
curl -k -sS -c "$work_dir/demo.cookies" "$base_url/login.php" -o "$work_dir/demo-form.html"
token=$(sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' "$work_dir/demo-form.html" | head -1)
curl -k -sS -b "$work_dir/demo.cookies" -c "$work_dir/demo.cookies" -X POST \
  --data-urlencode "_token=$token" --data-urlencode "action=demo-login" \
  "$base_url/login.php" -o /dev/null
code=$(curl -k -sS -b "$work_dir/demo.cookies" -o "$work_dir/demo-account.html" -w '%{http_code}' "$base_url/account/")
[ "$code" = '200' ] || { echo "guest demo login: HTTP $code"; exit 1; }

echo 'Authenticated smoke test: OK'
