<?php
declare(strict_types=1);
session_start();

if(!isset($_SESSION['usuario_activo'])){
    header('Location: index.php');
    exit();
}

$usuario = $_SESSION['usuario_activo'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Sistema POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="fronted/css/dashboard.css">
</head>
<body>
    <div class="d-flex">
        <?php include 'backend/includes/sidebar.php'; ?>
            <div id="content" class="w-100" style="margin-left: 280px;">
                <nav class="navbar-expand-lg navbar-light bg-white shadow-sm mb-4 p-3">
                    <div class="container-fluid d-flex justify-content-between">
                        <span class="navbar-brand mb-0 h4 text-secondary">Dashboard General</span>
                            <div>
                                <span class="me-4 fw-bold" style="color:var(--verde-oscruro);">
                                    👤 <?php echo strtoupper($usuario['usuario']) . ' | Rol: ' . ucfirst($usuario['rol']); ?>
                                </span>
                                <a href="backend/logout.php" class="btn btn-sm btn-outline-danger fw-bold">Cerrar Sesión</a>
                            </div>
                    </div>
                </nav>

                <div class="container-fluid px-4">
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow-sm border-0 border-top border-4" style="border-color: var(--verde-medio);">
                                <div class="card-body py-5 text-center bg-white rounded">
                                    <h2 style="color: var(--verde-oscuro);">¡Bienvenido, <?php echo ucfirst($usuario['usuario']); ?>!</h2>
                                    <p class="text-muted fs-5 mt-3">Seleccione una opción del menú lateral para operar el sistema de ventas:</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
    </div>
</body>
</html>