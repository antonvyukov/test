#!/usr/bin/env bash
# Idempotent dependency refresh for the Laravel + Filament app.
set -euo pipefail

# System packages: PHP + extensions Laravel 13 / Filament 5 need, plus Composer.
sudo apt-get update
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
  php-cli php-mbstring php-xml php-bcmath php-intl php-gd php-sqlite3 php-zip php-curl \
  unzip composer

# PHP dependencies (vendor/).
composer install --no-interaction --prefer-dist

# Application environment file (gitignored) and app key.
[ -f .env ] || cp .env.example .env
grep -qE '^APP_KEY=.+' .env || php artisan key:generate --force

# SQLite database file (gitignored). Migrations/seeding run at startup.
mkdir -p database
[ -f database/database.sqlite ] || touch database/database.sqlite
