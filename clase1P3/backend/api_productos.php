<?php
declare(strict_types=1);

// Cabeceras necesarias para el intercambio de JSON y CORS
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Responder rápido a las peticiones preflight de Vercel/Navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir la conexión
require_once 'conexion.php';

// Captura el método de petición GET, POST, PUT, DELETE
$method = $_SERVER['REQUEST_METHOD'];

// Capturamos el cuerpo de la petición para el POST y PUT
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            // LEER los productos: búsqueda o listado general
            $search = $_GET['search'] ?? '';
            $sql = "SELECT * FROM productos WHERE nombre_producto LIKE ? OR codigo_barras LIKE ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(["%$search%", "%$search%"]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'POST':
            // Insertamos un nuevo producto
            $sql = "INSERT INTO productos (codigo_barras, nombre_producto, precio_actual, stock_disponible) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $input['codigo'] ?? '', 
                $input['nombre'] ?? '', 
                $input['precio'] ?? 0, 
                $input['stock'] ?? 0
            ]);
            
            echo json_encode(['estado' => 'ok', 'message' => 'Producto agregado correctamente']);
            break;

        case 'PUT':
            // Actualizamos un producto existente
            $id = $input['id'] ?? 0;
            $sql = "UPDATE productos SET codigo_barras = ?, nombre_producto = ?, precio_actual = ?, stock_disponible = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $input['codigo'] ?? '', 
                $input['nombre'] ?? '', 
                $input['precio'] ?? 0, 
                $input['stock'] ?? 0,
                $id
            ]);
            
            echo json_encode(['estado' => 'ok', 'message' => 'Producto actualizado correctamente']);
            break;

        case 'DELETE':
            // Eliminamos un producto
            $id = $_GET['id'] ?? $input['id'] ?? 0;
            $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['estado' => 'ok', 'message' => 'Producto eliminado correctamente']);
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
    exit();
}