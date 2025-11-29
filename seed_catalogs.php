<?php
/**
 * Semilla masiva para catálogos de SKUs y proveedores.
 * Usa un CSV con encabezados: sku, producto, proveedor
 * Ejemplo de uso en CLI: php seed_catalogs.php catalog_seed.csv
 */

require __DIR__ . '/db.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ejecuta este script desde la línea de comandos.\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Uso: php seed_catalogs.php archivo.csv\n");
    exit(1);
}

$file = $argv[1];
if (!is_readable($file)) {
    fwrite(STDERR, "No puedo leer el archivo: {$file}\n");
    exit(1);
}

if (($handle = fopen($file, 'r')) === false) {
    fwrite(STDERR, "No se pudo abrir el archivo: {$file}\n");
    exit(1);
}

$delimiter = detect_delimiter(fgets($handle));
rewind($handle);

$headers = fgetcsv($handle, 0, $delimiter);
if ($headers === false) {
    fwrite(STDERR, "El CSV está vacío.\n");
    exit(1);
}

$headers = array_map('normalize_header', $headers);
$idxSku = array_search('sku', $headers, true);
$idxProd = array_search('producto', $headers, true);
$idxProv = array_search('proveedor', $headers, true);

if ($idxSku === false || $idxProd === false || $idxProv === false) {
    fwrite(STDERR, "Encabezados requeridos: sku, producto, proveedor.\n");
    exit(1);
}

$insertSku = $pdo->prepare("
    INSERT INTO catalog_skus (sku, producto, proveedor)
    VALUES (:sku, :producto, :proveedor)
    ON DUPLICATE KEY UPDATE producto = VALUES(producto), proveedor = VALUES(proveedor)
");
$insertProv = $pdo->prepare("
    INSERT INTO catalog_proveedores (nombre)
    VALUES (:nombre)
    ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)
");

$insertedSku = 0;
$skipped = 0;

$pdo->beginTransaction();
while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $sku = normalize_upper($row[$idxSku] ?? '');
    $producto = normalize_upper($row[$idxProd] ?? '');
    $proveedor = normalize_upper($row[$idxProv] ?? '');

    if ($sku === '' || $producto === '' || $proveedor === '') {
        $skipped++;
        continue;
    }

    $insertProv->execute([':nombre' => $proveedor]);
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

function normalize_upper(string $value): string
{
    return mb_strtoupper(trim($value), 'UTF-8');
}

function normalize_header(string $value): string
{
    return mb_strtolower(trim($value), 'UTF-8');
}

function detect_delimiter(string $line): string
{
    $comma = substr_count($line, ',');
    $semicolon = substr_count($line, ';');
    return $semicolon > $comma ? ';' : ',';
}
