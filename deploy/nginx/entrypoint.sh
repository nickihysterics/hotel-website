#!/bin/sh
set -e

CERT_DIR=/etc/nginx/certs
CERT_FILE="$CERT_DIR/localhost.crt"
KEY_FILE="$CERT_DIR/localhost.key"
CONF_FILE="$CERT_DIR/openssl.cnf"

if [ ! -f "$CERT_FILE" ] || [ ! -f "$KEY_FILE" ]; then
  mkdir -p "$CERT_DIR"
  cat > "$CONF_FILE" <<'CONFIG'
[req]
distinguished_name=req_distinguished_name
x509_extensions=v3_req
prompt=no

[req_distinguished_name]
CN=localhost

[v3_req]
subjectAltName=@alt_names

[alt_names]
DNS.1=localhost
DNS.2=www.localhost
DNS.3=hotel.localhost
DNS.4=www.hotel.localhost
CONFIG

  openssl req -x509 -nodes -newkey rsa:2048 -days 3650 \
    -keyout "$KEY_FILE" \
    -out "$CERT_FILE" \
    -config "$CONF_FILE" \
    -extensions v3_req

  rm -f "$CONF_FILE"
fi

exec "$@"
