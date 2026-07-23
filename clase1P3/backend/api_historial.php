<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Responder de inmediato a las peticiones OPTIONS preflight de Vercel/Navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // accion=resumen -> devuelve los 3 totalizadores para las tarjetas del historial
            $accion = $_GET['accion'] ?? '';

            if ($accion === 'resumen') {
                // leemos los filtros que puede mandar el usuario (todos son opcionales)
                $fechaInicio = $_GET['fecha_inicio'] ?? '';
                $fechaFin = $_GET['fecha_fin'] ?? '';
                $cliente = $_GET['cliente'] ?? '';
                $factura = $_GET['factura'] ?? '';

                // armamos la consulta de forma dinamica: solo agregamos las condiciones de los filtros que si vengan
                // solo contamos las facturas Pagadas en los totales porque una factura Anulada no debe sumar al total vendido
                $sql = "SELECT
                            COALESCE(SUM(v.total_factura), 0) AS total_vendido,
                            COUNT(*) AS cantidad_facturas
                        FROM ventas v
                        INNER JOIN clientes c ON c.id = v.cliente_id
                        WHERE v.estado = 'Pagada'";
                $params = [];

                // filtro por rango de fechas se compara solo la parte de la fecha
                if ($fechaInicio !== '') {
                    $sql .= " AND DATE(v.fecha_emision) >= ?";
                    $params[] = $fechaInicio;
                }
                if ($fechaFin !== '') {
                    $sql .= " AND DATE(v.fecha_emision) <= ?";
                    $params[] = $fechaFin;
                }

                // filtro por cliente sea su cedula o nombre
                if ($cliente !== '') {
                    $sql .= " AND (c.cedula LIKE ? OR c.nombre_completo LIKE ?)";
                    $params[] = "%$cliente%";
                    $params[] = "%$cliente%";
                }

                // filtro por numero de factura osea con el id de la venta
                if ($factura !== '') {
                    $sql .= " AND v.id = ?";
                    $params[] = $factura;
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $resumen = $stmt->fetch();

                $totalVendido = (float)$resumen['total_vendido'];
                $cantidadFacturas = (int)$resumen['cantidad_facturas'];

                // el ticket promedio es el total vendido dividido entre la cantidad de facturas,
                // cuidamos de no dividir entre cero si todavia no hay ninguna venta
                $ticketPromedio = $cantidadFacturas > 0 ? $totalVendido / $cantidadFacturas : 0;

                echo json_encode([
                    'total_vendido' => round($totalVendido, 2),
                    'cantidad_facturas' => $cantidadFacturas,
                    'ticket_promedio' => round($ticketPromedio, 2)
                ]);
            } elseif ($accion === 'listado') {
                // accion=listado -> devuelve las filas de la tabla principal cabeceras de venta
                // aplica los mismos filtros opcionales que el resumen pero aqui SI se incluyen
                // las facturas Anuladas el cajero necesita verlas en la tabla aunque no sumen en los totales
                $fechaInicio = $_GET['fecha_inicio'] ?? '';
                $fechaFin = $_GET['fecha_fin'] ?? '';
                $cliente = $_GET['cliente'] ?? '';
                $factura = $_GET['factura'] ?? '';

                // traemos: id de la venta, fecha, total y estado de ventas
                // el nombre del cliente mediante JOIN con clientes y el usuario (vendedor/cajero) mediante JOIN con usuarios
                // se usa LEFT JOIN en usuarios para que una venta NUNCA desaparezca del listado
                // aunque su usuario_id no encuentre coincidencia por ejemplo ventas antiguas de antes del ALTER TABLE
                $sql = "SELECT
                            v.id,
                            v.fecha_emision,
                            v.total_factura,
                            v.estado,
                            c.nombre_completo AS cliente_nombre,
                            COALESCE(u.usuario, 'N/D') AS vendedor_usuario
                        FROM ventas v
                        INNER JOIN clientes c ON c.id = v.cliente_id
                        LEFT JOIN usuarios u ON u.id = v.usuario_id
                        WHERE 1 = 1";
                $params = [];

                if ($fechaInicio !== '') {
                    $sql .= " AND DATE(v.fecha_emision) >= ?";
                    $params[] = $fechaInicio;
                }
                if ($fechaFin !== '') {
                    $sql .= " AND DATE(v.fecha_emision) <= ?";
                    $params[] = $fechaFin;
                }
                if ($cliente !== '') {
                    $sql .= " AND (c.cedula LIKE ? OR c.nombre_completo LIKE ?)";
                    $params[] = "%$cliente%";
                    $params[] = "%$cliente%";
                }
                if ($factura !== '') {
                    $sql .= " AND v.id = ?";
                    $params[] = $factura;
                }

                // las mas recientes primero
                $sql .= " ORDER BY v.fecha_emision DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $facturas = $stmt->fetchAll();

                echo json_encode($facturas);
            } elseif ($accion === 'detalle') {
                // accion=detalle -> devuelve los productos vendidos en una factura especifica para el modal "Ver Detalles"
                $ventaId = $_GET['venta_id'] ?? null;

                if (!$ventaId) {
                    http_response_code(400);
                    echo json_encode(['estado' => 'error', 'message' => 'Falta el id de la venta']);
                    break;
                }

                // cruzamos detalle_ventas con productos para saber el nombre de cada producto vendido
                $stmt = $pdo->prepare("SELECT d.cantidad, d.precio_congelado, p.nombre_producto, p.codigo_barras
                                        FROM detalle_ventas d
                                        INNER JOIN productos p ON p.id = d.producto_id
                                        WHERE d.venta_id = ?");
                $stmt->execute([$ventaId]);
                echo json_encode($stmt->fetchAll());
            } else {
                http_response_code(400);
                echo json_encode(['estado' => 'error', 'message' => 'Acción no reconocida']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['estado' => 'error', 'message' => 'Método no permitido']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'estado' => 'error',
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}