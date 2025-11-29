<?php
// Carga de variables desde .env (preferido), variables de entorno o valores por defecto.
$defaults = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'sellout_db',
    'DB_USER' => 'usuario_db',
    'DB_PASS' => 'password_db',
];

$envPath = __DIR__ . '/.env';
$envConfig = [];

if (is_readable($envPath)) {
    $parsed = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if ($parsed !== false) {
        $envConfig = $parsed;
    }
}

// Prioridad: variable de entorno > .env > valores por defecto.
$db_host = getenv('DB_HOST') !== false ? getenv('DB_HOST') : ($envConfig['DB_HOST'] ?? $defaults['DB_HOST']);
$db_name = getenv('DB_NAME') !== false ? getenv('DB_NAME') : ($envConfig['DB_NAME'] ?? $defaults['DB_NAME']);
$db_user = getenv('DB_USER') !== false ? getenv('DB_USER') : ($envConfig['DB_USER'] ?? $defaults['DB_USER']);
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($envConfig['DB_PASS'] ?? $defaults['DB_PASS']);

$db_host = trim((string) $db_host);
$db_name = trim((string) $db_name);
$db_user = trim((string) $db_user);
$db_pass = (string) $db_pass; // Puede estar vacío

if ($db_host === '' || $db_name === '' || $db_user === '') {
    error_log('Faltan credenciales de base de datos. Configura .env o variables de entorno.');
    http_response_code(500);
    exit('Faltan credenciales de base de datos.');
}

$dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";

$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $pdo_options);
} catch (PDOException $e) {
    // No exponemos detalles de conexión en producción
    error_log('Error de conexión a la base de datos: ' . $e->getMessage());
    http_response_code(500);
    exit('Error de conexión a la base de datos.');
}
