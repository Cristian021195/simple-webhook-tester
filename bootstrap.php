<?php
declare(strict_types=1);

// Cargar Composer
require_once __DIR__ . '/vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Cargar configuración
$config = require __DIR__ . '/config.php';

// Inicializar sesión
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => !empty($_SERVER['HTTPS'])
]);

session_start();

function require_auth(array $c): void {
    if ($c['auth_user'] === '' && $c['auth_pass'] === '') return;

    $u = $_SERVER['PHP_AUTH_USER'] ?? '';
    $p = $_SERVER['PHP_AUTH_PW'] ?? '';

    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if ($u === '' && stripos($h, 'basic ') === 0) {
        [$u, $p] = array_pad(
            explode(':', (string)base64_decode(substr($h, 6)), 2),
            2,
            ''
        );
    }

    if (hash_equals($c['auth_user'], $u) && hash_equals($c['auth_pass'], $p)) return;

    header('WWW-Authenticate: Basic realm="Webhook tester"');
    http_response_code(401);
    exit('Autenticación requerida');
}

function csrf_token(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}