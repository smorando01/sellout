<?php
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
    exit;
}

$accion = $_POST['accion'] ?? '';

try {
    if ($accion === 'nuevo_registro') {
        crearRegistro($pdo);
    } elseif ($accion === 'actualizar_estado') {
        actualizarEstado($pdo);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Acción no reconocida']);
    }
} catch (Throwable $e) {
    error_log('Error en actions.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ocurrió un error inesperado.']);
}

function crearRegistro(PDO $pdo): void
{
    $sku = trim($_POST['sku'] ?? '');
    $producto = trim($_POST['producto'] ?? '');
    $proveedor = trim($_POST['proveedor'] ?? '');
    $notas = trim($_POST['notas'] ?? '');
    $moneda = strtoupper(trim($_POST['moneda'] ?? 'UYU'));

    $monto = str_replace(',', '.', $_POST['monto_iva'] ?? '');
    $montoValido = filter_var($monto, FILTER_VALIDATE_FLOAT);

    $fechaInicio = $_POST['fecha_inicio'] ?? '';
    $fechaFin = $_POST['fecha_fin'] ?? '';

    $fechaInicioValida = DateTime::createFromFormat('Y-m-d', $fechaInicio) !== false;
    $fechaFinValida = DateTime::createFromFormat('Y-m-d', $fechaFin) !== false;

    $monedasPermitidas = ['UYU', 'USD'];
    if ($sku === '' || $producto === '' || $proveedor === '' || $montoValido === false || !$fechaInicioValida || !$fechaFinValida || !in_array($moneda, $monedasPermitidas, true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Datos incompletos o inválidos.']);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO sellout_credits (sku, producto, monto_iva, moneda, fecha_inicio, fecha_fin, proveedor, reportada, sell_out_pago, notas)
        VALUES (:sku, :producto, :monto_iva, :moneda, :fecha_inicio, :fecha_fin, :proveedor, 0, 0, :notas)
    ");

    $stmt->execute([
        ':sku' => $sku,
        ':producto' => $producto,
        ':monto_iva' => $montoValido,
        ':moneda' => $moneda,
        ':fecha_inicio' => $fechaInicio,
        ':fecha_fin' => $fechaFin,
        ':proveedor' => $proveedor,
        ':notas' => $notas,
    ]);

    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
}

function actualizarEstado(PDO $pdo): void
{
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $campo = $_POST['campo'] ?? '';
    $valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_INT);

    $permitidos = ['reportada', 'sell_out_pago'];
    if (!$id || !in_array($campo, $permitidos, true) || ($valor !== 0 && $valor !== 1)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Parámetros inválidos.']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE sellout_credits SET {$campo} = :valor WHERE id = :id");
    $stmt->execute([
        ':valor' => $valor,
        ':id' => $id,
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Registro no encontrado.']);
        return;
    }

    echo json_encode(['ok' => true]);
}
