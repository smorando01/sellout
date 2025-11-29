<?php
/**
 * Semilla masiva para catálogos de SKUs y proveedores.
 * Usa un CSV con encabezados: sku, producto, proveedor
 * Ejemplo de uso en CLI: php seed_catalogs.php catalog_seed.csv
 */

require __DIR__ . '/db.php';

// CLI: php seed_catalogs.php archivo.csv
// Web (solo si sabes lo que haces): seed_catalogs.php?file=archivo.csv
$isCli = PHP_SAPI === 'cli';
$file = null;
if ($isCli) {
    if ($argc < 2) {
        fwrite(STDERR, "Uso: php seed_catalogs.php archivo.csv\n");
        exit(1);
    }
    $file = $argv[1];
} else {
    $file = $_GET['file'] ?? '';
    if ($file === '') {
        http_response_code(400);
        exit('Falta parámetro ?file=archivo.csv');
    }
}

if (!is_readable($file)) {
    $msg = "No puedo leer el archivo: {$file}";
    if ($isCli) {
        fwrite(STDERR, $msg . "\n");
        exit(1);
    }
    http_response_code(400);
    exit($msg);
}

if (($handle = fopen($file, 'r')) === false) {
    $msg = "No se pudo abrir el archivo: {$file}";
    if ($isCli) {
        fwrite(STDERR, $msg . "\n");
        exit(1);
    }
    http_response_code(500);
    exit($msg);
}

$delimiter = ';'; // Solo se admite ;
rewind($handle);

$headers = fgetcsv($handle, 0, $delimiter);
if ($headers === false) {
    $msg = "El CSV está vacío.";
    if ($isCli) {
        fwrite(STDERR, $msg . "\n");
        exit(1);
    }
    http_response_code(400);
    exit($msg);
}

$headers = array_map('normalize_header', $headers);
$idxSku = array_search('sku', $headers, true);
$idxProd = array_search('producto', $headers, true);

if ($idxSku === false || $idxProd === false) {
    $msg = "Encabezados requeridos: sku, producto. (proveedor se carga manual)";
    if ($isCli) {
        fwrite(STDERR, $msg . "\n");
        exit(1);
    }
    http_response_code(400);
    exit($msg);
}

$insertSku = $pdo->prepare("
    INSERT INTO catalog_skus (sku, producto, proveedor)
    VALUES (:sku, :producto, :proveedor)
    ON DUPLICATE KEY UPDATE producto = VALUES(producto)
");
$selectProvExisting = $pdo->prepare("SELECT proveedor FROM catalog_skus WHERE sku = :sku LIMIT 1");

$insertedSku = 0;
$skipped = 0;

$pdo->beginTransaction();
while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $sku = normalize_upper($row[$idxSku] ?? '');
    $producto = normalize_upper($row[$idxProd] ?? '');

    if ($sku === '' || $producto === '') {
        $skipped++;
        continue;
    }

    $selectProvExisting->execute([':sku' => $sku]);
    $provRow = $selectProvExisting->fetch();
    $proveedor = $provRow ? normalize_upper($provRow['proveedor']) : 'PENDIENTE';

    $insertSku->execute([
        ':sku' => $sku,
        ':producto' => $producto,
        ':proveedor' => $proveedor,
    ]);
    $insertedSku++;
}
$pdo->commit();

fclose($handle);

echo "Insertados/actualizados SKUs: {$insertedSku}\n";
echo "Saltados (faltan datos): {$skipped}\n";

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

function normalize_upper(string $value): string
{
    return mb_strtoupper(trim($value), 'UTF-8');
}

function normalize_header(string $value): string
{
    // Elimina BOM, comillas y espacios extra
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value); // BOM UTF-8
    $value = trim($value);
    $value = trim($value, "\"'");
    return mb_strtolower($value, 'UTF-8');
}
