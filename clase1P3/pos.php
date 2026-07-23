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
    <title>Punto de Venta - Sistema POS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fronted/css/dashboard.css">
    <link rel="stylesheet" href="fronted/css/pos.css">
</head>
<body>
    <div class="d-flex">
        <?php include __DIR__ . '/backend/includes/sidebar.php'; ?>
        <div id="content" class="w-100" style="margin-left: 280px">
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 p-3">
                <button type="button" class="btn btn-outline-secondary me-3" id="btn-toggle-sidebar" onclick="toggleSidebar()" title="Mostrar/Ocultar menú">
                    ☰
                </button>
                <span class="navbar-brand mb-0 h4 text-secondary">Punto de Venta (Caja)</span>
            </nav>

            <div class="container-fluid px-4">
                <div class="row">

                    <!-- Columna Izquierda (70%) - Zona de Operacion -->
                    <div class="col-lg-8">

                        <!-- Buscador rapido / lector de codigo de barras -->
                        <div class="card shadow-sm mb-3">
                            <div class="card-body">
                                <label for="input-scanner" class="form-label fw-bold">Buscador / Lector de Código de Barras</label>
                                <div class="d-flex gap-2">
                                    <input type="text" id="input-scanner" class="form-control" placeholder="Escanee el producto, escriba el nombre/código o presione Enter" autocomplete="off" autofocus>
                                    <button type="button" class="btn btn-verde text-nowrap" onclick="abrirCamara()" title="Escanear con la cámara">📷 Cámara</button>
                                </div>

                                <!-- resultados de la busqueda en vivo por codigo o nombre -->
                                <div id="resultados-producto" class="list-group mt-2"></div>
                            </div>
                        </div>

                        <!-- Carrito de compras -->
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Producto</th>
                                            <th>Precio</th>
                                            <th class="text-center">Cantidad</th>
                                            <th>Subtotal</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="cuerpo-carrito">
                                        <tr id="fila-vacio">
                                            <td colspan="5" class="text-center text-muted py-4">El carrito está vacío. Escanee un producto para comenzar.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha (30%) - Panel de Facturacion -->
                    <div class="col-lg-4">

                        <!-- Asignacion de cliente -->
                        <div class="card shadow-sm mb-3">
                            <div class="card-body">
                                <label for="input-cliente" class="form-label fw-bold">Cliente</label>

                                <div class="d-flex gap-2 mb-2">
                                    <input type="text" id="input-cliente" class="form-control" placeholder="Buscar por cédula o nombre..." autocomplete="off">
                                    <button type="button" class="btn btn-outline-secondary text-nowrap" onclick="usarConsumidorFinal()">Consumidor Final</button>
                                </div>

                                <!-- resultados de la busqueda en vivo -->
                                <div id="resultados-cliente" class="list-group"></div>

                                <!-- formulario rapido para registrar un cliente que no existe en el sistema -->
                                <div id="form-nuevo-cliente" class="border rounded p-2 mt-2 d-none">
                                    <small class="text-muted d-block mb-2">Cliente no encontrado. Puede registrarlo aquí:</small>
                                    <input type="text" id="nuevo-cliente-cedula" class="form-control form-control-sm mb-2" placeholder="Cédula (10 dígitos)" inputmode="numeric" maxlength="10">
                                    <input type="text" id="nuevo-cliente-nombre" class="form-control form-control-sm mb-2" placeholder="Nombre completo">
                                    <input type="text" id="nuevo-cliente-telefono" class="form-control form-control-sm mb-2" placeholder="Teléfono (ej. 0991234567)" inputmode="numeric" maxlength="10">
                                    <input type="email" id="nuevo-cliente-correo" class="form-control form-control-sm mb-2" placeholder="Correo (opcional)">
                                    <button type="button" class="btn btn-verde btn-sm w-100" onclick="registrarClienteNuevo()">Guardar y usar en esta venta</button>
                                </div>

                                <!-- cliente actualmente seleccionado -->
                                <div class="alert alert-success py-2 mt-2 mb-0" id="cliente-seleccionado">
                                    👤 Consumidor Final
                                </div>

                                <!-- se muestra solo si el cliente asignado es el Cliente Fiel del Año -->
                                <div id="panel-cliente-fiel" class="d-none border rounded p-2 mt-2 bg-warning bg-opacity-25">
                                    <span class="badge-cliente-fiel d-block mb-2">🏆 Cliente Fiel del Año</span>
                                    <label for="input-descuento" class="form-label small mb-1">Descuento a aplicar (%)</label>
                                    <input type="number" id="input-descuento" class="form-control form-control-sm" placeholder="0" min="0" max="100" step="1">
                                </div>
                            </div>
                        </div>

                        <!-- Resumen de totales -->
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Resumen de Venta</h6>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Subtotal</span>
                                    <span id="resumen-subtotal">$0.00</span>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">IVA (15%)</span>
                                    <span id="resumen-iva">$0.00</span>
                                </div>

                                <div class="d-flex justify-content-between mb-2 d-none" id="fila-resumen-descuento">
                                    <span class="text-muted">Descuento</span>
                                    <span id="resumen-descuento" class="text-danger">-$0.00</span>
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between fs-4 fw-bold">
                                    <span>Total</span>
                                    <span id="resumen-total">$0.00</span>
                                </div>
                            </div>
                        </div>

                        <!-- Panel de pago -->
                        <div class="card shadow-sm mt-3">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Panel de Pago</h6>

                                <label for="input-pago" class="form-label">Paga con</label>
                                <input type="number" id="input-pago" class="form-control mb-3" placeholder="0.00" step="0.01" min="0">

                                <div class="d-flex justify-content-between fs-5">
                                    <span class="fw-bold">Cambio (vuelto)</span>
                                    <span id="resumen-vuelto" class="fw-bold">$0.00</span>
                                </div>
                            </div>
                        </div>

                        <!-- Boton para procesar la venta -->
                        <button type="button" id="btn-procesar-venta" class="btn btn-verde btn-lg w-100 mt-3 fw-bold py-3" onclick="procesarVenta()">
                            ✅ Procesar Venta
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Overlay para escanear con la camara del dispositivo -->
    <div id="overlay-camara" class="d-none">
        <div class="camara-caja">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="m-0 fw-bold">Escanear código de barras</h6>
                <button type="button" class="btn-close" onclick="cerrarCamara()"></button>
            </div>
            <div id="lector-camara"></div>
            <small class="text-muted d-block mt-2">Apunte la cámara al código de barras del producto</small>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
    <script src="fronted/js/validaciones.js"></script>
    <script src="fronted/js/pos.js"></script>
</body>
</html>
