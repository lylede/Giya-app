#!/bin/sh
# Runs every time the container starts on Vercel or Render.
set -e

cd /app

# Cache config/routes/views with the real environment variables from the
# host's dashboard. Makes each request faster.
php artisan config:cache
php artisan route:cache || echo "route:cache skipped"
php artisan view:cache

# Profile pictures saved locally (only when Supabase is not configured).
php artisan storage:link 2>/dev/null || true

# Migrations are NOT run here: on Vercel several copies can start at once and
# would race. Run "php artisan migrate" from a laptop pointed at Neon instead
# (see DEPLOY.md).

exec frankenphp run --config /etc/caddy/Caddyfile
