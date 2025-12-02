<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/db.php';

$creditosStmt = $pdo->query("
    SELECT id, sku, producto, monto_iva, moneda, cantidad_vendida, fecha_inicio, fecha_fin, proveedor, reportada, sell_out_pago, notas
    FROM sellout_credits
    ORDER BY fecha_inicio DESC, id DESC
");
$creditos = $creditosStmt->fetchAll();

$hoy = new DateTimeImmutable('today');
$mesActualInicio = new DateTimeImmutable('first day of this month');
$mesActualFin = new DateTimeImmutable('last day of this month');

$negociacion = [];
$deuda = [];
$finalizados = [];
$kpis = ['potencial' => 0, 'deuda' => 0.0, 'recupero' => 0.0];

foreach ($creditos as $c) {
    $moneda = in_array(strtoupper($c['moneda']), ['USD', 'UYU'], true) ? strtoupper($c['moneda']) : 'UYU';
    $cantidad = (int) $c['cantidad_vendida'];
    $total = (float) $c['monto_iva'] * $cantidad;
    $c['moneda'] = $moneda;
    $c['total_calculado'] = $total;

    if ((int) $c['sell_out_pago'] === 1) {
        $finalizados[] = $c;
        $fechaFin = $c['fecha_fin'] ? new DateTimeImmutable($c['fecha_fin']) : null;
        if ($fechaFin && $fechaFin >= $mesActualInicio && $fechaFin <= $mesActualFin) {
            $kpis['recupero'] += $total;
        }
    } elseif ((int) $c['reportada'] === 1) {
        $deuda[] = $c;
        $kpis['deuda'] += $total;
    } else {
        $negociacion[] = $c;
        $kpis['potencial']++;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sell Out Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/custom.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
<div class="container-fluid py-3">
    <div class="d-flex align-items-center mb-3">
        <div>
            <div class="text-uppercase text-muted fw-semibold small">ERP Ligero</div>
            <h1 class="h4 mb-0">Sell Out Tracker</h1>
            <div class="small text-muted">Sesión: <?php echo htmlspecialchars($_SESSION['user']['nombre'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="ms-auto d-flex gap-2">
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal">Importar CSV</button>
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#maestrosModal">Maestros</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registroModal">Nuevo Registro</button>
            <a class="btn btn-outline-danger" href="logout.php">Salir</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Potencial en Negociación</div>
                    <div class="display-6 text-info"><?php echo (int) $kpis['potencial']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Deuda Exigible</div>
                    <div class="display-6 text-success"><?php echo number_format($kpis['deuda'], 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Recupero del Mes</div>
                    <div class="display-6 text-primary"><?php echo number_format($kpis['recupero'], 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills glass-pills mb-3" id="tabsSellout" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-negociacion" data-bs-toggle="pill" data-bs-target="#pane-negociacion" type="button" role="tab">Acuerdos Vigentes</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-deuda" data-bs-toggle="pill" data-bs-target="#pane-deuda" type="button" role="tab">Gestión de Cobro</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-final" data-bs-toggle="pill" data-bs-target="#pane-final" type="button" role="tab">Historial Finalizado</button>
        </li>
    </ul>

    <div class="tab-content" id="tabsSelloutContent">
        <div class="tab-pane fade show active" id="pane-negociacion" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-semibold mb-0">Acuerdos Vigentes (Negociación)</h6>
                        <small class="text-muted">Reporta la cantidad cuando cierre el periodo.</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>SKU</th>
                                <th>Producto</th>
                                <th>Monto IVA</th>
                                <th>Moneda</th>
                                <th>Fecha Fin</th>
                                <th>Proveedor</th>
                                <th>Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($negociacion as $c): ?>
                                <tr data-id="<?php echo (int) $c['id']; ?>">
                                    <td><?php echo (int) $c['id']; ?></td>
                                    <td><?php echo htmlspecialchars($c['sku'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($c['producto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo number_format((float) $c['monto_iva'], 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($c['moneda'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($c['fecha_fin'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($c['proveedor'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><button class="btn btn-sm btn-primary btn-reportar" data-id="<?php echo (int) $c['id']; ?>">Reportar Venta</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="pane-deuda" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-semibold mb-0">Gestión de Cobro (Deuda)</h6>
                        <small class="text-muted">Total a Cobrar visible en la tabla.</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>SKU</th>
                                <th>Producto</th>
                                <th>Cant. Vendida</th>
                                <th>Total a Cobrar</th>
                                <th>Fecha Fin</th>
                                <th>Proveedor</th>
                                <th>Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($deuda as $c): ?>
                                <?php $simbolo = $c['moneda'] === 'USD' ? 'U$D ' : '$ '; ?>
                                <tr data-id="<?php echo (int) $c['id']; ?>">
                                    <td><?php echo (int) $c['id']; ?></td>
                                    <td><?php echo htmlspecialchars($c['sku'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($c['producto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int) $c['cantidad_vendida']; ?></td>
                                    <td><?php echo $simbolo . number_format((float) $c['total_calculado'], 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($c['fecha_fin'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($c['proveedor'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><button class="btn btn-sm btn-primary btn-cobro" data-id="<?php echo (int) $c['id']; ?>">Confirmar Cobro</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="pane-final" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="fw-semibold mb-2">Historial Finalizado</h6>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>SKU</th>
                                <th>Producto</th>
                                <th>Cant. Vendida</th>
                                <th>Total</th>
                                <th>Fecha Fin</th>
                                <th>Proveedor</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($finalizados as $c): ?>
                                <?php $simbolo = $c['moneda'] === 'USD' ? 'U$D ' : '$ '; ?>
                                <tr>
                                    <td><?php echo (int) $c['id']; ?></td>
                                    <td><?php echo htmlspecialchars($c['sku'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($c['producto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int) $c['cantidad_vendida']; ?></td>
                                    <td><?php echo $simbolo . number_format((float) $c['total_calculado'], 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($c['fecha_fin'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($c['proveedor'], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para nuevo registro -->
<div class="modal fade" id="registroModal" tabindex="-1" aria-labelledby="registroModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="registroModalLabel">Nuevo Registro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formRegistro" novalidate>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" class="form-control" list="list_skus" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Producto</label>
                            <input type="text" name="producto" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Moneda</label>
                            <select name="moneda" class="form-select" required>
                                <option value="UYU" selected>$ (UYU)</option>
                                <option value="USD">U$D (USD)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Monto IVA</label>
                            <input type="number" name="monto_iva" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" name="fecha_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" name="fecha_fin" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Proveedor</label>
                            <input type="text" name="proveedor" class="form-control" list="list_proveedores" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notas</label>
                            <textarea name="notas" class="form-control" rows="3" placeholder="Detalle adicional"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Importar CSV -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Importar CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formImport" enctype="multipart/form-data" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Archivo CSV</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
                        <div class="form-text">
                            Encabezados: SKU;Nombre del producto;Monto con IVA;Fecha inicio período;Fecha fin período;Proveedor / Marca;Reportada;Sell Out Pago;Cantidad Vendida;Notas.
                        </div>
                        <div class="mt-2">
                            <a class="btn btn-link px-0" href="sample_csv.php" target="_blank" rel="noopener">Descargar CSV de ejemplo</a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Maestros -->
<div class="modal fade" id="maestrosModal" tabindex="-1" aria-labelledby="maestrosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="maestrosModalLabel">Maestros (SKUs / Proveedores)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formAddProv" class="mb-3">
                    <label class="form-label">Nuevo Proveedor</label>
                    <div class="input-group">
                        <input type="text" name="nombre" class="form-control" required>
                        <button class="btn btn-primary" type="submit">Agregar</button>
                        <button class="btn btn-outline-danger" type="button" id="btnDelProv">Eliminar</button>
                    </div>
                </form>
                <form id="formAddSku">
                    <label class="form-label">Nuevo SKU</label>
                    <div class="row g-2">
                        <div class="col-4">
                            <input type="text" name="sku" class="form-control" placeholder="SKU" required>
                        </div>
                        <div class="col-4">
                            <input type="text" name="producto" class="form-control" placeholder="Producto" required>
                        </div>
                        <div class="col-4">
                            <input type="text" name="proveedor" class="form-control" placeholder="Proveedor" required>
                        </div>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Agregar</button>
                        <button class="btn btn-outline-danger" type="button" id="btnDelSku">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Listas para autocompletar -->
<datalist id="list_skus"></datalist>
<datalist id="list_proveedores"></datalist>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
    $(function () {
        cargarSugerencias();
        activarMayusculas();
        autocompletarSku();

        $('#formRegistro').on('submit', async function (e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            formData.append('accion', 'nuevo_registro');
            await enviarFormData(formData, form);
        });

        $('#formImport').on('submit', async function (e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const btn = $(form).find('button[type="submit"]');
            btn.prop('disabled', true);
            try {
                const response = await fetch('import.php', {method: 'POST', body: formData});
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.message || 'No se pudo importar.');
                Swal.fire({icon: 'success', title: 'Importación completa', text: `Insertados: ${data.inserted || 0}, Omitidos: ${data.skipped || 0}`, timer: 2000, showConfirmButton: false})
                    .then(() => window.location.reload());
            } catch (error) {
                Swal.fire({icon: 'error', title: 'Error', text: error.message});
            } finally {
                btn.prop('disabled', false);
            }
        });

        $('#formAddProv').on('submit', async function (e) {
            e.preventDefault();
            const nombre = this.nombre.value.trim();
            if (!nombre) return;
            await accionSimple({accion: 'add_proveedor', nombre}, 'Proveedor agregado');
        });
        $('#btnDelProv').on('click', async function () {
            const nombre = $('#formAddProv input[name="nombre"]').val().trim();
            if (!nombre) return;
            await accionSimple({accion: 'delete_proveedor', nombre}, 'Proveedor eliminado');
        });

        $('#formAddSku').on('submit', async function (e) {
            e.preventDefault();
            const payload = {
                accion: 'add_sku',
                sku: this.sku.value.trim(),
                producto: this.producto.value.trim(),
                proveedor: this.proveedor.value.trim()
            };
            if (!payload.sku || !payload.producto || !payload.proveedor) return;
            await accionSimple(payload, 'SKU agregado');
        });
        $('#btnDelSku').on('click', async function () {
            const sku = $('#formAddSku input[name="sku"]').val().trim();
            if (!sku) return;
            await accionSimple({accion: 'delete_sku', sku}, 'SKU eliminado');
        });

        // Reportar venta (Tab negociación)
        $('.btn-reportar').on('click', async function () {
            const id = $(this).data('id');
            const {value: cantidad} = await Swal.fire({
                title: 'Cantidad vendida',
                input: 'number',
                inputLabel: 'Ingrese Cantidad Vendida en el periodo',
                inputAttributes: {min: 0, step: 1},
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar'
            });
            if (cantidad === undefined) return;
            const cantNum = parseInt(cantidad, 10);
            if (isNaN(cantNum) || cantNum < 0) {
                Swal.fire({icon: 'error', title: 'Cantidad inválida'});
                return;
            }
            try {
                const response = await fetch('actions.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `accion=reportar_cantidad&id=${id}&cantidad=${cantNum}`
                });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.message || 'No se pudo reportar.');
                Swal.fire({icon: 'success', title: 'Reportado', timer: 1000, showConfirmButton: false})
                    .then(() => window.location.reload());
            } catch (error) {
                Swal.fire({icon: 'error', title: 'Error', text: error.message});
            }
        });

        // Confirmar cobro (Tab deuda)
        $('.btn-cobro').on('click', async function () {
            const id = $(this).data('id');
            const conf = await Swal.fire({
                icon: 'question',
                title: 'Confirmar cobro',
                text: '¿Marcar este acuerdo como pagado?',
                showCancelButton: true,
                confirmButtonText: 'Sí, confirmar',
                cancelButtonText: 'Cancelar'
            });
            if (!conf.isConfirmed) return;
            try {
                const response = await fetch('actions.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `accion=confirmar_cobro&id=${id}`
                });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.message || 'No se pudo confirmar cobro.');
                Swal.fire({icon: 'success', title: 'Cobrado', timer: 1000, showConfirmButton: false})
                    .then(() => window.location.reload());
            } catch (error) {
                Swal.fire({icon: 'error', title: 'Error', text: error.message});
            }
        });

        async function enviarFormData(formData, form) {
            const submitBtn = $(form).find('button[type="submit"]');
            submitBtn.prop('disabled', true);
            try {
                const response = await fetch('actions.php', {method: 'POST', body: formData});
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.message || 'Operación fallida.');
                Swal.fire({icon: 'success', title: 'Listo', text: 'Cambios guardados', timer: 1500, showConfirmButton: false})
                    .then(() => window.location.reload());
            } catch (error) {
                Swal.fire({icon: 'error', title: 'Error', text: error.message});
            } finally {
                submitBtn.prop('disabled', false);
            }
        }

        async function accionSimple(payload, msgOk) {
            try {
                const response = await fetch('actions.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams(payload).toString()
                });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.message || 'Operación fallida.');
                Swal.fire({icon: 'success', title: msgOk, timer: 1200, showConfirmButton: false})
                    .then(() => window.location.reload());
            } catch (error) {
                Swal.fire({icon: 'error', title: 'Error', text: error.message});
            }
        }

        async function cargarSugerencias() {
            try {
                const response = await fetch('actions.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'accion=get_suggestions'
                });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.message || 'No se pudieron cargar sugerencias.');
                const $skuList = $('#list_skus').empty();
                const $provList = $('#list_proveedores').empty();
                (data.skus || []).forEach(v => $skuList.append(`<option value="${v}">`));
                (data.proveedores || []).forEach(v => $provList.append(`<option value="${v}">`));
            } catch (error) {
                console.error('Sugerencias:', error);
            }
        }

        function activarMayusculas() {
            const selector = 'input[type="text"], textarea';
            document.querySelectorAll(selector).forEach((el) => {
                el.addEventListener('input', () => {
                    const start = el.selectionStart;
                    const end = el.selectionEnd;
                    el.value = el.value.toUpperCase();
                    if (start !== null && end !== null) el.setSelectionRange(start, end);
                });
            });
        }

        function autocompletarSku() {
            const skuInput = document.querySelector('input[name="sku"]');
            if (!skuInput) return;
            skuInput.addEventListener('blur', () => consultarSku(skuInput, document.querySelector('input[name="producto"]'), document.querySelector('input[name="proveedor"]')));
        }

        async function consultarSku(skuInput, productoInput, proveedorInput) {
            const sku = (skuInput.value || '').trim();
            if (!sku) return;
            try {
                const payload = new URLSearchParams();
                payload.append('accion', 'get_sku_info');
                payload.append('sku', sku);
                const response = await fetch('actions.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: payload.toString()
                });
                const data = await response.json();
                if (!response.ok || !data.ok || !data.found) return;
                if (productoInput && (!productoInput.value || productoInput.value.trim() === '')) productoInput.value = data.data.producto || '';
                if (proveedorInput && (!proveedorInput.value || proveedorInput.value.trim() === '')) proveedorInput.value = data.data.proveedor || '';
            } catch (error) {
                console.error('Autocompletar SKU:', error);
            }
        }
    });
</script>
</body>
</html>
