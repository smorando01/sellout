<?php
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Archivo CSV no recibido o con error.']);
    exit;
}

$tmpPath = $_FILES['csv_file']['tmp_name'];
if (!is_uploaded_file($tmpPath)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'No se pudo procesar el archivo subido.']);
    exit;
}

$handle = fopen($tmpPath, 'r');
if ($handle === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo abrir el archivo.']);
    exit;
}

$firstLine = fgets($handle);
if ($firstLine === false) {
    fclose($handle);
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'El archivo CSV está vacío.']);
    exit;
}

$delimiter = detect_delimiter($firstLine);
$headers = array_map('normalize_header', str_getcsv($firstLine, $delimiter));

$map = [
    'sku' => 'sku',
    'nombre del producto' => 'producto',
    'monto con iva' => 'monto_iva',
    'fecha inicio período' => 'fecha_inicio',
    'fecha fin período' => 'fecha_fin',
    'proveedor / marca' => 'proveedor',
    'reportada' => 'reportada',
    'sell out pago' => 'sell_out_pago',
    'notas' => 'notas',
];

$indexes = [];
foreach ($headers as $idx => $header) {
    if (isset($map[$header])) {
        $indexes[$map[$header]] = $idx;
    }
}

$camposObligatorios = ['sku', 'producto', 'monto_iva', 'fecha_inicio', 'fecha_fin', 'proveedor'];
foreach ($camposObligatorios as $campo) {
    if (!isset($indexes[$campo])) {
        fclose($handle);
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => "Falta la columna requerida: {$campo}"]);
        exit;
    }
}

$inserted = 0;
$skipped = 0;

try {
    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare("
        INSERT INTO sellout_credits (sku, producto, monto_iva, moneda, fecha_inicio, fecha_fin, proveedor, reportada, sell_out_pago, notas)
        VALUES (:sku, :producto, :monto_iva, :moneda, :fecha_inicio, :fecha_fin, :proveedor, :reportada, :sell_out_pago, :notas)
    ");

    $dupStmt = $pdo->prepare("
        SELECT id FROM sellout_credits
        WHERE sku = :sku AND producto = :producto AND monto_iva = :monto_iva
          AND fecha_inicio = :fecha_inicio AND fecha_fin = :fecha_fin AND proveedor = :proveedor
        LIMIT 1
    ");

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $sku = normalize_upper($row[$indexes['sku']] ?? '');
        $producto = normalize_upper($row[$indexes['producto']] ?? '');
        $monto = normalize_decimal($row[$indexes['monto_iva']] ?? '');
        $fechaInicio = trim($row[$indexes['fecha_inicio']] ?? '');
        $fechaFin = trim($row[$indexes['fecha_fin']] ?? '');
        $proveedor = normalize_upper($row[$indexes['proveedor']] ?? '');
        $reportada = parse_bool($row[$indexes['reportada']] ?? 'false');
        $pagado = parse_bool($row[$indexes['sell_out_pago']] ?? 'false');
        $notas = isset($indexes['notas']) ? normalize_upper($row[$indexes['notas']] ?? '') : '';

        if ($sku === '' || $producto === '' || $proveedor === '' || $monto === null || !valid_date($fechaInicio) || !valid_date($fechaFin)) {
            $skipped++;
            continue;
        }

        $dupStmt->execute([
            ':sku' => $sku,
            ':producto' => $producto,
            ':monto_iva' => $monto,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin,
            ':proveedor' => $proveedor,
        ]);
        if ($dupStmt->fetch()) {
            $skipped++;
            continue;
        }

        // Guarda catálogos
        upsertProveedor($pdo, $proveedor);
        upsertSku($pdo, $sku, $producto, $proveedor);

        $insertStmt->execute([
            ':sku' => $sku,
            ':producto' => $producto,
            ':monto_iva' => $monto,
            ':moneda' => 'UYU',
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin,
            ':proveedor' => $proveedor,
            ':reportada' => $reportada,
            ':sell_out_pago' => $pagado,
            ':notas' => $notas,
        ]);
        $inserted++;
    }

    $pdo->commit();
    fclose($handle);

    echo json_encode(['ok' => true, 'inserted' => $inserted, 'skipped' => $skipped]);
} catch (Throwable $e) {
    fclose($handle);
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error importando CSV: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al importar. Revisa el archivo y vuelve a intentar.']);
}

function normalize_upper(string $value): string
{
    return mb_strtoupper(trim($value), 'UTF-8');
}

function normalize_header(string $value): string
{
    return mb_strtolower(trim($value), 'UTF-8');
}

function parse_bool($value): int
{
    $v = mb_strtolower(trim((string) $value), 'UTF-8');
    return in_array($v, ['true', '1', 'si', 'sí', 'yes'], true) ? 1 : 0;
}

function normalize_decimal($value): ?float
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }
    // Elimina separadores de miles y usa punto como decimal
    $clean = str_replace('.', '', $raw);
    $clean = str_replace(',', '.', $clean);
    $num = filter_var($clean, FILTER_VALIDATE_FLOAT);
    return $num === false ? null : $num;
}

function valid_date(string $value): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt && $dt->format('Y-m-d') === $value;
}

function detect_delimiter(string $line): string
{
    $comma = substr_count($line, ',');
    $semicolon = substr_count($line, ';');
    return $semicolon > $comma ? ';' : ',';
}

function upsertProveedor(PDO $pdo, string $proveedor): void
{
    $stmt = $pdo->prepare("
        INSERT INTO catalog_proveedores (nombre) VALUES (:nombre)
        ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)
    ");
    $stmt->execute([':nombre' => $proveedor]);
}

function upsertSku(PDO $pdo, string $sku, string $producto, string $proveedor): void
{
    $stmt = $pdo->prepare("
        INSERT INTO catalog_skus (sku, producto, proveedor)
        VALUES (:sku, :producto, :proveedor)
        ON DUPLICATE KEY UPDATE
            producto = VALUES(producto),
            proveedor = VALUES(proveedor)
    ");
    $stmt->execute([
        ':sku' => $sku,
        ':producto' => $producto,
        ':proveedor' => $proveedor,
    ]);
}
