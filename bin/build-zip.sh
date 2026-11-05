#!/usr/bin/env bash
# Builds an installable zip with only what runs on the server.
set -euo pipefail
cd "$(dirname "$0")/.."
version=$(grep -m1 "Version:" cheppi-sellers.php | awk '{print $NF}')
php tests/run.php
npm run build
stage=$(mktemp -d)
mkdir -p "$stage/cheppi-sellers"
cp -r cheppi-sellers.php uninstall.php README.md CHANGELOG.md src templates languages "$stage/cheppi-sellers/"
mkdir -p "$stage/cheppi-sellers/assets"
cp -r assets/dist "$stage/cheppi-sellers/assets/"
mkdir -p dist
rm -f "dist/cheppi-sellers-$version.zip"
(cd "$stage" && zip -qr "$OLDPWD/dist/cheppi-sellers-$version.zip" cheppi-sellers)
rm -rf "$stage"
echo "dist/cheppi-sellers-$version.zip"
