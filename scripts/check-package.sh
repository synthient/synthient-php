#!/usr/bin/env bash
# Builds the Composer archive, installs it into a fresh consumer outside the checkout
# without development dependencies, and runs consumer-test/main.php against it.
set -euo pipefail

root=$(cd "$(dirname "$0")/.." && pwd)
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

composer validate --strict --working-dir="$root"
composer archive --working-dir="$root" --format=tar --dir="$work" --file=package
mkdir -p "$work/consumer/package"
tar -xf "$work/package.tar" -C "$work/consumer/package"
cp "$root/consumer-test/composer.json" "$root/consumer-test/main.php" "$work/consumer/"

cd "$work/consumer"
composer install --no-dev --no-interaction --no-progress
composer check-platform-reqs --no-dev
php main.php
