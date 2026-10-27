#!/bin/bash
# build-release.sh: build the distribution archive for a release tag.
#
#   tools/release/build-release.sh <tag> [--image]
#
#   <tag>    git tag or ref to package, e.g. v9.8.0-bulian.1
#   --image  also build the Docker image slims-bulian:<tag> and save it as
#            slims-bulian-<tag>-docker.tar.gz (needs internet for apt/composer)
#
# Output in dist/<tag>/:
#   slims-bulian-<tag>.tar.gz   code + vendor/ (composer install --no-dev),
#                               for native Apache/Nginx installs
#   SHA256SUMS
# Requires git, composer and sha256sum.
set -eu

TAG=${1:?usage: build-release.sh <tag> [--image]}
IMAGE=0; [ "${2:-}" = "--image" ] && IMAGE=1
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
OUT="$ROOT/dist/$TAG"
NAME="slims-bulian-$TAG"
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

cd "$ROOT"
git rev-parse --verify --quiet "$TAG^{commit}" >/dev/null || { echo "unknown tag or ref: $TAG" >&2; exit 1; }
mkdir -p "$OUT"

echo "== exporting $TAG"
git archive --format=tar --prefix="$NAME/" "$TAG" | tar -x -C "$WORK"

echo "== composer install --no-dev"
composer install --working-dir="$WORK/$NAME" --no-dev --optimize-autoloader --no-interaction --no-progress

# Development-only content that should not ship to clients
rm -rf "$WORK/$NAME/tools" "$WORK/$NAME/.github"

echo "== packaging"
tar -C "$WORK" -czf "$OUT/$NAME.tar.gz" "$NAME"

if [ "$IMAGE" = 1 ]; then
  echo "== docker image"
  docker build -t "slims-bulian:$TAG" "$WORK/$NAME"
  docker save "slims-bulian:$TAG" | gzip > "$OUT/$NAME-docker.tar.gz"
fi

(cd "$OUT" && sha256sum ./*.tar.gz > SHA256SUMS)
echo "== done: $OUT"
cat "$OUT/SHA256SUMS"
