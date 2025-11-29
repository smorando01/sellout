<?php
// Configuración de conexión: ajústala según tu hosting cPanel
$db_host = 'localhost';
$db_name = 'sellout_db';
$db_user = 'usuario_db';
$db_pass = 'password_db';

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
