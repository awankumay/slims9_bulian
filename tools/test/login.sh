#!/bin/bash
# login.sh <base_url> <password> <cookiejar>
# Logs in as admin through the login form (with its CSRF token).
# Prints login_post/admin_home status codes and ADMIN_LOGGED_IN=yes|no.
set -u
B=$1; P=$2; C=$3; rm -f "$C"
H=$(curl -s -c "$C" -b "$C" "$B/index.php?p=login")
TN=$(echo "$H" | grep -oE 'name="_csrf_token_[^"]+" value="[^"]+"' | head -1)
N=$(echo "$TN" | sed -E 's/name="([^"]+)".*/\1/'); V=$(echo "$TN" | sed -E 's/.*value="([^"]+)"/\1/')
curl -s -c "$C" -b "$C" -o /dev/null -w "login_post=%{http_code}\n" -X POST "$B/index.php?p=login" \
  --data-urlencode "userName=admin" --data-urlencode "passWord=$P" --data-urlencode "$N=$V" --data-urlencode "logMeIn=Login"
BODY=$(curl -s -c "$C" -b "$C" "$B/admin/index.php" -w "\n__CODE__%{http_code}")
echo "admin_home=${BODY##*__CODE__}"
echo "$BODY" | grep -qi "logout\|Keluar\|Log Out" && echo "ADMIN_LOGGED_IN=yes" || echo "ADMIN_LOGGED_IN=no"
