<?php
session_start();
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso | Sell Out Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/custom.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3 text-center">Sell Out Tracker</h5>
                    <form id="formLogin">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-2">Usuarios semilla: santiago.molaguero@example.com (1234)</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
    document.getElementById('formLogin').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const payload = new URLSearchParams();
        payload.append('accion', 'login');
        payload.append('email', form.email.value.trim());
        payload.append('password', form.password.value);
        try {
            const response = await fetch('actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: payload.toString()
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'Credenciales inválidas');
            window.location.href = 'index.php';
        } catch (error) {
            Swal.fire({icon: 'error', title: 'Error', text: error.message});
        }
    });
</script>
</body>
</html>
