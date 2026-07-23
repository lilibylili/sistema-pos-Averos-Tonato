<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responder rápido a preflight de Vercel
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['estado' => 'error', 'message' => 'Método no permitido']);
    exit();
}


$input = json_decode(file_get_contents('php://input'), true);
$ventaId = $input['venta_id'] ?? null;

if (!$ventaId) {
    http_response_code(400);
    echo json_encode(['estado' => 'error', 'message' => 'Falta el id de la venta']);
    exit();
}

try {
    //iniciamos la transaccion: o se anula la venta y se devuelve todo el stock, o no se hace nada
    $pdo->beginTransaction();

    //verificamos que la venta exista y que todavia no este anulada ya que no tiene sentido anular dos veces ni devolver el stock dos veces
    $stmtVenta = $pdo->prepare("SELECT estado FROM ventas WHERE id = ? FOR UPDATE");
    $stmtVenta->execute([$ventaId]);
    $venta = $stmtVenta->fetch();

    if (!$venta) {
        throw new Exception('La venta no existe');
    }
    if ($venta['estado'] === 'Anulada') {
        throw new Exception('Esta factura ya se encuentra anulada');
    }

    //1. cambiamos el estado de la venta a Anulada osea que NUNCA se hace una eliminación de una venta real
    $stmtEstado = $pdo->prepare("UPDATE ventas SET estado = 'Anulada' WHERE id = ?");
    $stmtEstado->execute([$ventaId]);

    //2. recorremos el detalle de esa venta para devolver el stock de cada producto al inventario
    $stmtDetalle = $pdo->prepare("SELECT producto_id, cantidad FROM detalle_ventas WHERE venta_id = ?");
    $stmtDetalle->execute([$ventaId]);
    $items = $stmtDetalle->fetchAll();

    $stmtDevolverStock = $pdo->prepare("UPDATE productos SET stock_disponible = stock_disponible + ? WHERE id = ?");
    foreach ($items as $item) {
        $stmtDevolverStock->execute([$item['cantidad'], $item['producto_id']]);
    }

    //si todo salio bien, confirmamos los cambios de forma permanente
    $pdo->commit();

    echo json_encode([
        'estado' => 'ok',
        'message' => 'Factura anulada y stock devuelto correctamente'
    ]);

} catch (Exception $e) {
    //si algo fallo, deshacemos todo y ni se anula la venta ni se devuelve el stock
    $pdo->rollBack();

    http_response_code(500);
    echo json_encode([
        'estado' => 'error',
        'message' => 'No se pudo anular la venta: ' . $e->getMessage()
    ]);
}