<?php
declare(strict_types=1);
session_start();

if(!isset($_SESSION['usuario_activo'])){
    header("Location: index.php");
    exit();
}

$usuario = $_SESSION['usuario_activo'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Sistema POS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fronted/css/dashboard.css">
    <link rel="stylesheet" href="fronted/css/sidebar-toggle.css">
    <link rel="stylesheet" href="fronted/css/clientes.css">
</head>
<body>
    <div class="d-flex">
        <?php include __DIR__ . '/backend/includes/sidebar.php'; ?>
        <div id="content" class="w-100" style="margin-left: 280px">
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 p-3">
                <button type="button" class="btn btn-outline-secondary me-3" id="btn-toggle-sidebar" onclick="toggleSidebar()" title="Mostrar/Ocultar menú">
                    ☰
                </button>
                <span class="navbar-brand mb-0 h4 text-secondary">Gestión de Clientes</span>
            </nav>

            <div class="container-fluid px-4">

                <!-- Buscador -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <label for="input-buscar-cliente" class="form-label fw-bold">Buscar Cliente</label>
                        <input type="text" id="input-buscar-cliente" class="form-control" placeholder="Buscar por cédula o nombre..." autocomplete="off">
                    </div>
                </div>

                <!-- Tabla de clientes -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Cédula</th>
                                    <th>Nombre</th>
                                    <th>Teléfono</th>
                                    <th>Correo</th>
                                    <th class="text-end">Comprado este año</th>
                                    <th class="text-center">Puntos</th>
                                    <th class="text-center">Distinción</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo-tabla-clientes">
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Cargando clientes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal: historial de facturas de un cliente -->
    <div class="modal fade" id="modalHistorialCliente" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalHistorialTitulo">Historial de Compras</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>N° Factura</th>
                                <th>Fecha</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Ver</th>
                            </tr>
                        </thead>
                        <tbody id="modalHistorialCuerpo">
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: detalle de productos de una factura especifica (dentro del historial del cliente) -->
    <div class="modal fade" id="modalDetalleFacturaCliente" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetalleFacturaClienteTitulo">Detalle de Factura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">P. Unit</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="modalDetalleFacturaClienteCuerpo">
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="fronted/js/sidebar-toggle.js"></script>
    <script src="fronted/js/clientes.js"></script>
</body>
</html>
