<?php
session_start();
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
    exit;
}

$accion = $_POST['accion'] ?? '';

try {
    switch ($accion) {
        case 'login':
            login($pdo);
            break;
        case 'logout':
            logout();
            break;
        case 'nuevo_registro':
            requireLogin();
            crearRegistro($pdo);
            break;
        case 'actualizar_estado':
            requireLogin();
            actualizarEstado($pdo);
            break;
        case 'reportar_cantidad':
            requireLogin();
            reportarCantidad($pdo);
            break;
        case 'get_suggestions':
            requireLogin();
            obtenerSugerencias($pdo);
            break;
        case 'get_sku_info':
            requireLogin();
            obtenerSkuInfo($pdo);
            break;
        case 'update_credit':
            requireLogin();
            actualizarRegistro($pdo);
            break;
        case 'delete_credit':
            requireLogin();
            eliminarRegistro($pdo);
            break;
        case 'add_proveedor':
            requireLogin();
            agregarProveedor($pdo);
            break;
        case 'delete_proveedor':
            requireLogin();
            eliminarProveedor($pdo);
            break;
        case 'add_sku':
            requireLogin();
            agregarSku($pdo);
            break;
        case 'delete_sku':
            requireLogin();
            eliminarSku($pdo);
            break;
        case 'get_provider_details':
            requireLogin();
            obtenerDetallesProveedor($pdo);
            break;
        case 'confirmar_cobro':
            requireLogin();
            confirmarCobro($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Acción no reconocida']);
    }
} catch (Throwable $e) {
    error_log('Error en actions.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ocurrió un error inesperado.']);
}

function login(PDO $pdo): void
{
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Email y contraseña requeridos.']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id, nombre, email, password FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Credenciales inválidas.']);
        return;
    }

    $stored = $user['password'];
    $valid = false;
    if (password_verify($password, $stored)) {
        $valid = true;
    }
    if (!$valid) {
        // Compatibilidad con hash SHA2
        $sha = hash('sha256', $password);
        if (hash_equals($stored, $sha)) {
            $valid = true;
        }
    }

    if (!$valid) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Credenciales inválidas.']);
        return;
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'nombre' => $user['nombre'],
        'email' => $user['email'],
    ];

    echo json_encode(['ok' => true]);
}

function logout(): void
{
    session_destroy();
    echo json_encode(['ok' => true]);
}

function requireLogin(): void
{
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'No autenticado.']);
        exit;
    }
}

function crearRegistro(PDO $pdo): void
{
    $sku = normalize_upper($_POST['sku'] ?? '');
    $producto = normalize_upper($_POST['producto'] ?? '');
    $proveedor = normalize_upper($_POST['proveedor'] ?? '');
    $notas = normalize_upper($_POST['notas'] ?? '');
    $moneda = normalize_upper($_POST['moneda'] ?? 'UYU');

    $monto = str_replace(',', '.', $_POST['monto_iva'] ?? '');
    $montoValido = filter_var($monto, FILTER_VALIDATE_FLOAT);

    $fechaInicio = $_POST['fecha_inicio'] ?? '';
    $fechaFin = $_POST['fecha_fin'] ?? '';

    $fechaInicioValida = DateTime::createFromFormat('Y-m-d', $fechaInicio) !== false;
    $fechaFinValida = DateTime::createFromFormat('Y-m-d', $fechaFin) !== false;

    $cantidad = filter_input(INPUT_POST, 'cantidad_vendida', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
    $monedasPermitidas = ['UYU', 'USD'];
    if ($sku === '' || $producto === '' || $proveedor === '' || $montoValido === false || !$fechaInicioValida || !$fechaFinValida || !in_array($moneda, $monedasPermitidas, true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Datos incompletos o inválidos.']);
        return;
    }

    upsertProveedor($pdo, $proveedor);
    upsertSku($pdo, $sku, $producto, $proveedor);

    $stmt = $pdo->prepare("
        INSERT INTO sellout_credits (sku, producto, monto_iva, moneda, cantidad_vendida, fecha_inicio, fecha_fin, proveedor, reportada, sell_out_pago, notas, user_id)
        VALUES (:sku, :producto, :monto_iva, :moneda, 0, :fecha_inicio, :fecha_fin, :proveedor, 0, 0, :notas, :user_id)
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
        ':user_id' => (int) $_SESSION['user']['id'],
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

    if ($campo === 'reportada' && $valor === 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Usa la acción reportar_cantidad para reportar.']);
        return;
    }

    $sets = "{$campo} = :valor";
    $params = [
        ':valor' => $valor,
        ':id' => $id,
    ];

    if ($campo === 'reportada' && $valor === 0) {
        $sets .= ", cantidad_vendida = 0";
    }

    $stmt = $pdo->prepare("UPDATE sellout_credits SET {$sets} WHERE id = :id");
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Registro no encontrado.']);
        return;
    }

    echo json_encode(['ok' => true]);
}

function reportarCantidad(PDO $pdo): void
{
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
    if (!$id || $cantidad === false || $cantidad < 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Cantidad inválida.']);
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE sellout_credits
        SET cantidad_vendida = :cantidad, reportada = 1
        WHERE id = :id
    ");
    $stmt->execute([
        ':cantidad' => $cantidad,
        ':id' => $id,
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Registro no encontrado.']);
        return;
    }

    echo json_encode(['ok' => true]);
}

function obtenerSugerencias(PDO $pdo): void
{
    $skusStmt = $pdo->query("SELECT DISTINCT sku FROM catalog_skus ORDER BY sku ASC");
    $proveedoresStmt = $pdo->query("SELECT DISTINCT nombre FROM catalog_proveedores ORDER BY nombre ASC");

    $skus = array_values(array_filter(array_map('normalize_upper', array_column($skusStmt->fetchAll(), 'sku'))));
    $proveedores = array_values(array_filter(array_map('normalize_upper', array_column($proveedoresStmt->fetchAll(), 'nombre'))));

    echo json_encode([
        'ok' => true,
        'skus' => $skus,
        'proveedores' => $proveedores,
    ]);
}

function obtenerSkuInfo(PDO $pdo): void
{
    $sku = normalize_upper($_POST['sku'] ?? '');
    if ($sku === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'SKU requerido']);
        return;
    }

    $stmt = $pdo->prepare("SELECT sku, producto, proveedor FROM catalog_skus WHERE sku = :sku LIMIT 1");
    $stmt->execute([':sku' => $sku]);
    $row = $stmt->fetch();
    if (!$row) {
        echo json_encode(['ok' => true, 'found' => false]);
        return;
    }

    echo json_encode([
        'ok' => true,
        'found' => true,
        'data' => [
            'sku' => normalize_upper($row['sku']),
            'producto' => normalize_upper($row['producto']),
            'proveedor' => normalize_upper($row['proveedor']),
        ],
    ]);
}

function actualizarRegistro(PDO $pdo): void
{
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'ID inválido']);
        return;
    }

    $sku = normalize_upper($_POST['sku'] ?? '');
    $producto = normalize_upper($_POST['producto'] ?? '');
    $proveedor = normalize_upper($_POST['proveedor'] ?? '');
    $notas = normalize_upper($_POST['notas'] ?? '');
    $moneda = normalize_upper($_POST['moneda'] ?? 'UYU');

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

    upsertProveedor($pdo, $proveedor);
    upsertSku($pdo, $sku, $producto, $proveedor);

    $stmt = $pdo->prepare("
        UPDATE sellout_credits
        SET sku = :sku,
            producto = :producto,
            monto_iva = :monto_iva,
            moneda = :moneda,
            cantidad_vendida = :cantidad_vendida,
            fecha_inicio = :fecha_inicio,
            fecha_fin = :fecha_fin,
            proveedor = :proveedor,
            notas = :notas,
            user_id = :user_id
        WHERE id = :id
    ");
    $stmt->execute([
        ':sku' => $sku,
        ':producto' => $producto,
        ':monto_iva' => $montoValido,
        ':moneda' => $moneda,
        ':cantidad_vendida' => $cantidad,
        ':fecha_inicio' => $fechaInicio,
        ':fecha_fin' => $fechaFin,
        ':proveedor' => $proveedor,
        ':notas' => $notas,
        ':user_id' => (int) $_SESSION['user']['id'],
        ':id' => $id,
    ]);

    echo json_encode(['ok' => true]);
}

function eliminarRegistro(PDO $pdo): void
{
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'ID inválido']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM sellout_credits WHERE id = :id");
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Registro no encontrado.']);
        return;
    }
    echo json_encode(['ok' => true]);
}

function agregarProveedor(PDO $pdo): void
{
    $nombre = normalize_upper($_POST['nombre'] ?? '');
    if ($nombre === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Nombre requerido']);
        return;
    }
    upsertProveedor($pdo, $nombre);
    echo json_encode(['ok' => true]);
}

function eliminarProveedor(PDO $pdo): void
{
    $nombre = normalize_upper($_POST['nombre'] ?? '');
    if ($nombre === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Nombre requerido']);
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM catalog_proveedores WHERE nombre = :nombre");
    $stmt->execute([':nombre' => $nombre]);
    echo json_encode(['ok' => true]);
}

function agregarSku(PDO $pdo): void
{
    $sku = normalize_upper($_POST['sku'] ?? '');
    $producto = normalize_upper($_POST['producto'] ?? '');
    $proveedor = normalize_upper($_POST['proveedor'] ?? '');

    if ($sku === '' || $producto === '' || $proveedor === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Datos incompletos.']);
        return;
    }

    upsertProveedor($pdo, $proveedor);
    upsertSku($pdo, $sku, $producto, $proveedor);
    echo json_encode(['ok' => true]);
}

function eliminarSku(PDO $pdo): void
{
    $sku = normalize_upper($_POST['sku'] ?? '');
    if ($sku === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'SKU requerido']);
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM catalog_skus WHERE sku = :sku");
    $stmt->execute([':sku' => $sku]);
    echo json_encode(['ok' => true]);
}

function obtenerDetallesProveedor(PDO $pdo): void
{
    $proveedor = normalize_upper($_POST['proveedor'] ?? '');
    $estado = $_POST['estado'] ?? '';
    if ($proveedor === '' || !in_array($estado, ['pendiente', 'pagado'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Parámetros inválidos']);
        return;
    }

    $filtro = $estado === 'pagado'
        ? 'sell_out_pago = 1'
        : 'sell_out_pago = 0 AND reportada = 1';

    $stmt = $pdo->prepare("
        SELECT sku, producto, fecha_fin AS fecha, monto_iva, cantidad_vendida, moneda
        FROM sellout_credits
        WHERE proveedor = :proveedor AND {$filtro}
    ");
    $stmt->execute([':proveedor' => $proveedor]);
    $rows = $stmt->fetchAll();
    $items = [];
    foreach ($rows as $r) {
        $total = (float) $r['monto_iva'] * (int) $r['cantidad_vendida'];
        $items[] = [
            'sku' => normalize_upper($r['sku']),
            'producto' => normalize_upper($r['producto']),
            'fecha' => $r['fecha'],
            'monto' => $total,
            'moneda' => normalize_upper($r['moneda']),
        ];
    }

    echo json_encode(['ok' => true, 'items' => $items]);
}

function confirmarCobro(PDO $pdo): void
{
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'ID inválido']);
        return;
    }

    if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Adjunta un comprobante válido.']);
        return;
    }

    $file = $_FILES['comprobante'];
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Formato no permitido. Usa PDF/JPG/PNG.']);
        return;
    }

    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    $uniqueName = uniqid('comp_', true) . '.' . $ext;
    $destPath = $uploadsDir . '/' . $uniqueName;
    $publicPath = 'uploads/' . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'No se pudo guardar el archivo.']);
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE sellout_credits
        SET sell_out_pago = 1, comprobante_file = :file
        WHERE id = :id AND reportada = 1 AND sell_out_pago = 0
    ");
    $stmt->execute([':id' => $id, ':file' => $publicPath]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'No se pudo confirmar cobro (verifica estado).']);
        return;
    }

    echo json_encode(['ok' => true, 'file' => $publicPath]);
}

function normalize_upper(string $value): string
{
    return mb_strtoupper(trim($value), 'UTF-8');
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
