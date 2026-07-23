<?php
declare(strict_types=1);

// Cabeceras CORS
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Responder rápido a las peticiones preflight de Vercel/Navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

require_once 'conexion.php';

// Esta pantalla solo acepta POST para procesar la venta
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['estado' => 'error', 'message' => 'Método no permitido']);
    exit();
}

// Obtenemos el id del cajero con sesión activa
$usuarioId = $_SESSION['usuario_activo']['id'] ?? null;

if (!$usuarioId) {
    http_response_code(401);
    echo json_encode(['estado' => 'error', 'message' => 'Debe iniciar sesión para procesar una venta']);
    exit();
}

// Leemos el carrito, el cliente y el total desde la petición JSON
$input = json_decode(file_get_contents('php://input'), true);

$clienteId = $input['cliente_id'] ?? null;
$total = $input['total'] ?? null;
$items = $input['items'] ?? [];

// Validaciones básicas antes de tocar la base de datos
if (!$clienteId || !$total || empty($items)) {
    http_response_code(400);
    echo json_encode(['estado' => 'error', 'message' => 'Faltan datos para procesar la venta (cliente, total o productos)']);
    exit();
}

try {
    // Iniciamos la transacción: o se guarda todo o no se guarda nada
    $pdo->beginTransaction();

    // 1. Insertamos la cabecera de la venta (estado fica "Pagada" por defecto)
    $stmtVenta = $pdo->prepare("INSERT INTO ventas (cliente_id, usuario_id, total_factura) VALUES (?, ?, ?)");
    $stmtVenta->execute([$clienteId, $usuarioId, $total]);
    $ventaId = (int)$pdo->lastInsertId();

    // Preparamos las consultas repetitivas del carrito
    $stmtDetalle = $pdo->prepare("INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_congelado) VALUES (?, ?, ?, ?)");
    $stmtStock = $pdo->prepare("UPDATE productos SET stock_disponible = stock_disponible - ? WHERE id = ? AND stock_disponible >= ?");

    // 2. Recorremos cada línea del carrito
    foreach ($items as $item) {
        $productoId = $item['producto_id'];
        $cantidad = $item['cantidad'];
        $precio = $item['precio'];

        // Insertamos el detalle
        $stmtDetalle->execute([$ventaId, $productoId, $cantidad, $precio]);

        // Descontamos stock si hay suficiente
        $stmtStock->execute([$cantidad, $productoId, $cantidad]);

        // Si rowCount es 0, no había suficiente stock
        if ($stmtStock->rowCount() === 0) {
            throw new Exception("Stock insuficiente para el producto con id {$productoId}");
        }
    }

    // 3. Sistema de puntos: bono de 100 puntos por superar $2000 en el año
    $puntosGanados = 0;

    $stmtTotalAnio = $pdo->prepare("SELECT COALESCE(SUM(total_factura), 0) AS total_anio
                                     FROM ventas
                                     WHERE cliente_id = ? AND estado = 'Pagada' AND YEAR(fecha_emision) = YEAR(NOW())");
    $stmtTotalAnio->execute([$clienteId]);
    $totalAnioCliente = (float)$stmtTotalAnio->fetchColumn();

    $stmtCliente = $pdo->prepare("SELECT anio_ultimo_bono FROM clientes WHERE id = ?");
    $stmtCliente->execute([$clienteId]);
    $anioUltimoBono = $stmtCliente->fetchColumn();

    $anioActual = (int)date('Y');

    if ($totalAnioCliente >= 2000 && (int)$anioUltimoBono !== $anioActual) {
        $puntosGanados = 100;

        $stmtBono = $pdo->prepare("UPDATE clientes SET puntos_acumulados = puntos_acumulados + ?, anio_ultimo_bono = ? WHERE id = ?");
        $stmtBono->execute([$puntosGanados, $anioActual, $clienteId]);
    }

    // Confirmamos los cambios de forma permanente
    $pdo->commit();

    echo json_encode([
        'estado' => 'ok',
        'message' => 'Venta procesada correctamente',
        'venta_id' => $ventaId,
        'puntos_ganados' => $puntosGanados
    ]);

} catch (Exception $e) {
    // En caso de error, deshacemos todos los cambios
    $pdo->rollBack();

    http_response_code(500);
    echo json_encode([
        'estado' => 'error',
        'message' => 'No se pudo procesar la venta: ' . $e->getMessage()
    ]);
}