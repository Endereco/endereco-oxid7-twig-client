#!/usr/bin/env bash
set -euo pipefail

versions=(7.1 7.2 7.3 7.4 7.5)

base_dir="shops"
cache_dir="$PWD/.composer-cache"

mkdir -p "$base_dir"
mkdir -p "$cache_dir"

for version in "${versions[@]}"; do
  target_dir="$base_dir/$version"

  echo "======================================="
  echo "Installing OXID $version"
  echo "======================================="

  rm -rf -- "$target_dir"

  # Composer-Version je OXID-Version
  case "$version" in
    7.1|7.2)
      composer_image="composer:2.7.7"
      ;;
    7.3)
      composer_image="composer:2.8.8"
      ;;
    7.4)
      composer_image="composer:2.8.8"
      ;;
    7.5)
      composer_image="composer:2.8.8"
      ;;
    *)
      echo "Unknown version $version"
      exit 1
      ;;
  esac

  echo "Using $composer_image"

  docker run --rm \
    -v "$PWD:/app" \
    -v "$cache_dir:/tmp/composer-cache" \
    -e COMPOSER_CACHE_DIR=/tmp/composer-cache \
    -w /app \
    "$composer_image" \
    composer create-project --no-dev --ignore-platform-reqs \
      oxid-esales/oxideshop-project "$target_dir" "dev-b-${version}-ce"

done

echo ""
echo "All OXID 7.x installations completed successfully."