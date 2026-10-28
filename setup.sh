#!/usr/bin/env bash
# First-time setup for SLiMS with Docker Compose.
# Creates .env with random database passwords, builds and starts the stack,
# then prints the initial admin password. Safe to re-run: an existing .env
# is never overwritten.
set -eu

cd "$(dirname "$0")"

die() { echo "ERROR: $*" >&2; exit 1; }

command -v docker >/dev/null 2>&1 || die "docker not found. Install Docker Engine first."
docker compose version >/dev/null 2>&1 || die "docker compose v2 not found. Install the compose plugin."
docker info >/dev/null 2>&1 || die "cannot talk to the Docker daemon. Is it running, and is ${USER:-this user} in the docker group?"

random_pass() {
    LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32
}

# Replace KEY=... in .env, or append it when the key is missing.
set_env() {
    if grep -q "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        printf '%s=%s\n' "$1" "$2" >>.env
    fi
}

if [ -f .env ]; then
    echo ".env already exists, keeping it."
else
    [ -f .env.example ] || die ".env.example not found."
    umask 077
    cp .env.example .env
    set_env DB_PASS "$(random_pass)"
    set_env DB_ROOT_PASS "$(random_pass)"

    read -r -p "HTTP port on this host [8881]: " port
    set_env HTTP_PORT "${port:-8881}"

    read -r -p "Cloudflare Tunnel token (empty = no tunnel): " token
    if [ -n "$token" ]; then
        set_env COMPOSE_PROFILES tunnel
        set_env CLOUDFLARE_TUNNEL_TOKEN "$token"
        set_env HTTP_BIND 127.0.0.1
    fi
    chmod 600 .env
    echo ".env created with random DB_PASS and DB_ROOT_PASS."
fi

echo "Building and starting containers..."
docker compose up -d --build

echo "Waiting for slims to become healthy (max 300s)..."
cid=$(docker compose ps -q slims)
for _ in $(seq 1 60); do
    health=$(docker inspect -f '{{.State.Health.Status}}' "$cid" 2>/dev/null || echo unknown)
    [ "$health" = healthy ] && break
    sleep 5
done
[ "$health" = healthy ] || die "slims is '$health' after 300s. Check: ./slims.sh logs slims"

echo ""
docker compose ps
echo ""
if docker compose logs slims 2>/dev/null | grep -A3 "ADMIN CREDENTIALS"; then
    echo "Change this password right after the first login."
else
    echo "No new admin password in the log: the admin password was set before."
fi
echo "Daily operations: ./slims.sh help"
