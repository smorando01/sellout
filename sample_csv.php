<?php
// Genera un CSV de ejemplo con los encabezados esperados por import.php

$filename = 'sellout_ejemplo.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

$headers = [
    'SKU',
    'Nombre del producto',
    'Monto con IVA',
    'Fecha inicio período',
    'Fecha fin período',
    'Proveedor / Marca',
    'Reportada',
    'Sell Out Pago',
    'Notas',
];

$ejemplo = [
    'ABC123',
    'PRODUCTO DE MUESTRA',
    '12345.67',
    '2025-01-01',
    '2025-01-31',
    'PROVEEDOR DEMO',
    'True',
    'False',
    'EJEMPLO DE NOTA',
];

fputcsv($output, $headers);
fputcsv($output, $ejemplo);

fclose($output);
exit;
