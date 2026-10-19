#!/usr/bin/env bash
# Build de Vercel (buildCommand de vercel.json). Instala las dependencias de
# Composer, compila los assets con Vite y, solo en producción, aplica las
# migraciones pendientes contra Neon.
#
# Las migraciones se ejecutan aquí porque la URL de Neon es una variable
# sensible: solo existe dentro de Vercel y no se puede leer ni copiar a otra
# máquina. Nunca se hace migrate:fresh ni db:seed completo (DatabaseSeeder crea
# usuarios y envíos de prueba); solo migraciones pendientes y los seeders de
# roles y de cuentas iniciales, ambos idempotentes. Si algo falla, el build
# falla y sigue activo el despliegue anterior.
set -euo pipefail

# vendor/ tiene que existir antes de Vite: app.css importa flux.css y escanea
# las plantillas de Flux desde vendor/livewire/flux. Sin ellas el build pasa,
# pero el CSS sale sin el layout de Flux (sidebar y contenido descolocados).
# La imagen de build de Vercel no trae PHP; la función sí (vercel-php), pero
# ese PHP no está disponible durante el build.
dnf install -y -q php8.4-cli php8.4-pgsql php8.4-mbstring php8.4-xml unzip
curl -sS https://getcomposer.org/installer | php -- --install-dir=/tmp --quiet
# Este PHP solo sirve para el build y artisan migrate: no hace falta que tenga
# todas las extensiones que piden los paquetes (las tiene la función).
php /tmp/composer.phar install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress --ignore-platform-reqs
php artisan package:discover --ansi
npm run build

if [ "${VERCEL_ENV:-}" != "production" ]; then
    echo "VERCEL_ENV=${VERCEL_ENV:-desconocido}: no se ejecutan migraciones."
    exit 0
fi

# Las migraciones van sin el pooler de Neon (PgBouncer en modo transacción no
# encaja bien con DDL); en runtime api/index.php usa DATABASE_URL, con pooler.
export DB_URL="${DATABASE_URL_UNPOOLED:-$DATABASE_URL}"

php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
# Cuentas iniciales; las contraseñas vienen de SEED_*_PASSWORD y las cuentas
# que ya existen no se tocan.
php artisan db:seed --class=ProductionUsersSeeder --force
