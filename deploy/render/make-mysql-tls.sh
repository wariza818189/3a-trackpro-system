#!/usr/bin/env bash
# Usage: bash deploy/render/make-mysql-tls.sh EXACT_INTERNAL_HOST NEW_OUTSIDE_REPO_DIR
set -Eeuo pipefail
umask 077
host=${1:?exact MySQL internal hostname required}
out=${2:?new absolute directory outside repository required}
[[ $host =~ ^[a-zA-Z0-9][a-zA-Z0-9.-]*$ && $out == /* && ! -e $out ]]
out=$(realpath -m -- "$out")
repo=$(git rev-parse --show-toplevel)
case "$out/" in "$repo/"*) echo 'Output must be outside the repository.' >&2; exit 1;; esac
mkdir -m 700 "$out"
openssl req -x509 -newkey rsa:3072 -nodes -sha256 -days 365 \
    -subj '/CN=TrackPro Demo MySQL CA' -keyout "$out/mysql-ca.key" -out "$out/mysql-ca.pem" \
    -addext 'basicConstraints=critical,CA:TRUE' -addext 'keyUsage=critical,keyCertSign,cRLSign'
openssl req -newkey rsa:3072 -nodes -sha256 -subj "/CN=$host" \
    -keyout "$out/mysql-server.key" -out "$out/mysql-server.csr"
printf 'subjectAltName=DNS:%s\nbasicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature,keyEncipherment\nextendedKeyUsage=serverAuth\n' "$host" > "$out/server.ext"
openssl x509 -req -sha256 -days 90 -in "$out/mysql-server.csr" \
    -CA "$out/mysql-ca.pem" -CAkey "$out/mysql-ca.key" -CAcreateserial \
    -extfile "$out/server.ext" -out "$out/mysql-server.pem"
openssl verify -CAfile "$out/mysql-ca.pem" -verify_hostname "$host" "$out/mysql-server.pem"
echo 'Keep CA key offline. Upload only server key, server certificate and CA to MySQL; CA only to web.'
