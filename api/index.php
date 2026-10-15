<?php

// Punto de entrada de Laravel en Vercel (runtime vercel-php). Vercel solo admite
// funciones dentro de api/, y eso obliga a corregir tres cosas antes de arrancar
// la app. Solo se ejecuta en Vercel: en local y en los tests sigue entrando
// public/index.php directamente.

// 1. El servidor embebido de PHP resuelve cualquier /api/... a este fichero y
//    Symfony deduce "/api" como base de la app: /api/shipments/X llegaba a
//    Laravel como /shipments/X (404) y los assets salían como /api/build/...
//    Hacemos como si el script fuese public/index.php servido desde la raíz.
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

// 2. El TLS y la IP del visitante se quedan en el edge de Vercel: aquí la
//    petición llega por HTTP desde 127.0.0.1. Sin esto las URLs de los assets
//    salen con http:// (contenido mixto, el navegador las bloquea) y todos los
//    visitantes comparten IP en el limitador show-shipment. Vercel sobrescribe
//    x-real-ip en el edge, así que el cliente no puede falsearla.
$_SERVER['HTTPS'] = 'on';
if (! empty($_SERVER['HTTP_X_REAL_IP'])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
}

// 3. La integración de Neon publica la conexión como DATABASE_URL, marcada como
//    sensible: no se puede leer ni copiar a otra variable desde el panel ni la
//    CLI. Laravel la espera en DB_URL (config/database.php), así que la
//    reenviamos si no se ha definido otra.
//    Según cómo arranque vercel-php el servidor embebido, las variables pueden
//    llegar por getenv() o solo por $_SERVER/$_ENV: miramos las tres.
$env = fn (string $key) => getenv($key) ?: ($_SERVER[$key] ?? $_ENV[$key] ?? null);
$databaseUrl = $env('DB_URL') ?: $env('DATABASE_URL');

// 4. El libpq que trae vercel-php no soporta SNI, que es como Neon sabe a qué
//    endpoint va cada conexión ("Endpoint ID is not specified"). Laravel no
//    pasa `options=endpoint=...` al DSN, así que usamos la otra vía que
//    documenta Neon: anteponer "endpoint=<id>;" a la contraseña. El id es el
//    primer trozo del host sin el sufijo -pooler. https://neon.tech/sni
$parts = $databaseUrl ? parse_url($databaseUrl) : false;
if ($parts && str_ends_with($parts['host'] ?? '', '.neon.tech') && isset($parts['pass'])
    && ! str_starts_with(rawurldecode($parts['pass']), 'endpoint=')) {
    $endpoint = preg_replace('/-pooler$/', '', explode('.', $parts['host'])[0]);
    $password = rawurlencode("endpoint={$endpoint};".rawurldecode($parts['pass']));
    $databaseUrl = str_replace(":{$parts['pass']}@", ":{$password}@", $databaseUrl);
}

if ($databaseUrl) {
    putenv("DB_URL={$databaseUrl}");
    $_ENV['DB_URL'] = $_SERVER['DB_URL'] = $databaseUrl;
}
unset($env, $databaseUrl, $parts, $endpoint, $password);

require __DIR__.'/../public/index.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
