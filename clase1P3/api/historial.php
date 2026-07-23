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
    <title>Historial de Facturas - Sistema POS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fronted/css/dashboard.css">
    <link rel="stylesheet" href="fronted/css/historial.css">
</head>
<body>
    <div class="d-flex">
        <?php include __DIR__ . '/backend/includes/sidebar.php'; ?>
        <div id="content" class="w-100" style="margin-left: 280px">
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 p-3">
                <button type="button" class="btn btn-outline-secondary me-3" id="btn-toggle-sidebar" onclick="toggleSidebar()" title="Mostrar/Ocultar menú">
                    ☰
                </button>
                <span class="navbar-brand mb-0 h4 text-secondary">Historial de Facturas</span>
            </nav>

            <div class="container-fluid px-4">

                <!-- Filtros de busqueda avanzada -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Filtros de Búsqueda</h6>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label for="filtro-fecha-inicio" class="form-label small text-muted mb-1">Fecha Inicio</label>
                                <input type="date" id="filtro-fecha-inicio" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label for="filtro-fecha-fin" class="form-label small text-muted mb-1">Fecha Fin</label>
                                <input type="date" id="filtro-fecha-fin" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label for="filtro-cliente" class="form-label small text-muted mb-1">Cliente (cédula o nombre)</label>
                                <input type="text" id="filtro-cliente" class="form-control" placeholder="Ej. Juan Perez" autocomplete="off">
                                <div id="resultados-filtro-cliente" class="list-group position-absolute" style="z-index: 1000;"></div>
                            </div>
                            <div class="col-md-2">
                                <label for="filtro-factura" class="form-label small text-muted mb-1">N° Factura</label>
                                <input type="text" id="filtro-factura" class="form-control" placeholder="Ej. 5">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-verde w-100" onclick="aplicarFiltros()">🔎 Filtrar</button>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-outline-secondary w-100" onclick="limpiarFiltros()" title="Limpiar filtros">✖</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tarjetas de totalizadores -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <small class="text-muted text-uppercase fw-bold">Total Vendido</small>
                                <h3 class="mt-2 mb-0" id="tarjeta-total-vendido" style="color: var(--verde-oscuro);">$0.00</h3>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <small class="text-muted text-uppercase fw-bold">Cantidad de Facturas</small>
                                <h3 class="mt-2 mb-0" id="tarjeta-cantidad-facturas" style="color: var(--verde-oscuro);">0</h3>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <small class="text-muted text-uppercase fw-bold">Ticket Promedio</small>
                                <h3 class="mt-2 mb-0" id="tarjeta-ticket-promedio" style="color: var(--verde-oscuro);">$0.00</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla principal (cabeceras de venta) -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Facturas</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>N° Factura</th>
                                        <th>Fecha y Hora</th>
                                        <th>Cliente</th>
                                        <th>Vendedor / Cajero</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-historial-body">
                                    <!-- Las filas se pintan dinamicamente desde historial.js -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal para ver el detalle de productos de una factura -->
    <div class="modal fade" id="modalDetalleFactura" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetalleTitulo">Detalle de Factura</h5>
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
                        <tbody id="modalDetalleCuerpo">
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
    <script src="fronted/js/historial.js"></script>
</body>
</html>
