<?php
require __DIR__ . '/db.php';

// Datos de tabla
$creditosStmt = $pdo->query("
    SELECT id, sku, producto, monto_iva, moneda, fecha_inicio, fecha_fin, proveedor, reportada, sell_out_pago, notas
    FROM sellout_credits
    ORDER BY fecha_inicio DESC, id DESC
");
$creditos = $creditosStmt->fetchAll();

$totales = [
    'UYU' => ['pendiente' => 0],
    'USD' => ['pendiente' => 0],
    'casos_abiertos' => 0,
];

foreach ($creditos as $credito) {
    $moneda = strtoupper($credito['moneda'] ?? '');
    if (!in_array($moneda, ['UYU', 'USD'], true)) {
        $moneda = 'UYU';
    }
    if (!isset($totales[$moneda])) {
        $totales[$moneda] = ['pendiente' => 0];
    }
    if ((int) $credito['sell_out_pago'] === 0) {
        $totales[$moneda]['pendiente'] += (float) $credito['monto_iva'];
        $totales['casos_abiertos']++;
    }
}

$hoy = new DateTimeImmutable('today');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seguimiento de Créditos Sell Out</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex align-items-center mb-3">
        <div>
            <div class="text-uppercase text-muted fw-semibold small">Seguimiento</div>
            <h1 class="h3 mb-0">Créditos Sell Out</h1>
        </div>
        <div class="ms-auto d-flex gap-2">
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
                Importar CSV
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registroModal">
                Nuevo Registro
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total Pendiente UYU ($)</div>
                    <div class="display-6 text-success">
                        <?php echo number_format((float) ($totales['UYU']['pendiente'] ?? 0), 2, ',', '.'); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total Pendiente USD (U$D)</div>
                    <div class="display-6 text-info">
                        <?php echo number_format((float) ($totales['USD']['pendiente'] ?? 0), 2, ',', '.'); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Cantidad de Casos Abiertos</div>
                    <div class="display-6 text-primary">
                        <?php echo (int) $totales['casos_abiertos']; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="creditsTable" class="table table-striped align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>SKU</th>
                        <th>Producto</th>
                        <th>Moneda</th>
                        <th>Monto IVA</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Proveedor</th>
                        <th>Reportada</th>
                        <th>Sell Out Pago</th>
                        <th>Notas</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($creditos as $credito): ?>
                        <?php
                        $monedaRow = strtoupper($credito['moneda'] ?? '');
                        if (!in_array($monedaRow, ['UYU', 'USD'], true)) {
                            $monedaRow = 'UYU';
                        }
                        $fechaFin = $credito['fecha_fin'] ? new DateTimeImmutable($credito['fecha_fin']) : null;
                        $vencido = $fechaFin && $fechaFin < $hoy && !(int) $credito['sell_out_pago'];
                        ?>
                        <tr class="<?php echo $vencido ? 'table-danger' : ''; ?>"
                            data-id="<?php echo (int) $credito['id']; ?>"
                            data-fecha-fin="<?php echo htmlspecialchars((string) $credito['fecha_fin'], ENT_QUOTES, 'UTF-8'); ?>">
                            <td><?php echo (int) $credito['id']; ?></td>
                            <td><?php echo htmlspecialchars((string) $credito['sku'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $credito['producto'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $monedaRow, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php
                                $simbolo = $monedaRow === 'USD' ? 'U$D ' : '$ ';
                                echo $simbolo . number_format((float) $credito['monto_iva'], 2, ',', '.');
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) $credito['fecha_inicio'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $credito['fecha_fin'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $credito['proveedor'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center">
                                <input type="checkbox"
                                       class="form-check-input toggle-estado"
                                       data-campo="reportada"
                                       <?php echo $credito['reportada'] ? 'checked' : ''; ?>>
                            </td>
                            <td class="text-center">
                                <input type="checkbox"
                                       class="form-check-input toggle-estado"
                                       data-campo="sell_out_pago"
                                       <?php echo $credito['sell_out_pago'] ? 'checked' : ''; ?>>
                            </td>
                            <td><?php echo htmlspecialchars((string) $credito['notas'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
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

<!-- Modal para importar CSV -->
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
                            Encabezados esperados: SKU, Nombre del producto, Monto con IVA, Fecha inicio período, Fecha fin período, Proveedor / Marca, Reportada, Sell Out Pago, Notas.
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

<!-- Listas para autocompletar -->
<datalist id="list_skus"></datalist>
<datalist id="list_proveedores"></datalist>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
    $(function () {
        const dt = $('#creditsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            }
        });

        cargarSugerencias();
        activarMayusculas();

        // Envío del formulario de nuevo registro
        $('#formRegistro').on('submit', async function (e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            formData.append('accion', 'nuevo_registro');

            const submitBtn = $(form).find('button[type="submit"]');
            submitBtn.prop('disabled', true);

            try {
                const response = await fetch('actions.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'No se pudo guardar el registro.');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Guardado',
                    text: 'El registro se guardó correctamente.',
                    timer: 1800,
                    showConfirmButton: false
                }).then(() => window.location.reload());
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            } finally {
                submitBtn.prop('disabled', false);
            }
        });

        // Importación masiva CSV
        $('#formImport').on('submit', async function (e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const submitBtn = $(form).find('button[type="submit"]');

            submitBtn.prop('disabled', true);
            try {
                const response = await fetch('import.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'No se pudo importar el archivo.');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Importación completa',
                    text: `Insertados: ${data.inserted || 0}, Omitidos: ${data.skipped || 0}`,
                    timer: 2200,
                    showConfirmButton: false
                }).then(() => window.location.reload());
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            } finally {
                submitBtn.prop('disabled', false);
            }
        });

        // Actualización de estado por checkbox
        $('#creditsTable').on('change', '.toggle-estado', async function () {
            const checkbox = this;
            const $row = $(checkbox).closest('tr');
            const id = $row.data('id');
            const campo = $(checkbox).data('campo');
            const valor = checkbox.checked ? 1 : 0;

            const payload = new URLSearchParams();
            payload.append('accion', 'actualizar_estado');
            payload.append('id', id);
            payload.append('campo', campo);
            payload.append('valor', valor);

            try {
                const response = await fetch('actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: payload.toString()
                });
                const data = await response.json();

                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'No se pudo actualizar.');
                }

                aplicarEstiloVencido($row);
            } catch (error) {
                checkbox.checked = !checkbox.checked;
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            }
        });

        // Marca filas vencidas en tiempo real
        function aplicarEstiloVencido($row) {
            const fechaFin = $row.data('fecha-fin');
            const pagado = $row.find('input[data-campo="sell_out_pago"]').is(':checked');
            if (!fechaFin) {
                return;
            }

            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const fin = new Date(fechaFin);
            fin.setHours(0, 0, 0, 0);

            if (!pagado && fin < hoy) {
                $row.addClass('table-danger');
            } else {
                $row.removeClass('table-danger');
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
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'No se pudieron cargar sugerencias.');
                }
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
                    if (start !== null && end !== null) {
                        el.setSelectionRange(start, end);
                    }
                });
            });
        }
    });
</script>
</body>
</html>
