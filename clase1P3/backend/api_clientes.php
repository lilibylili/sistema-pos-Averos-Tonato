<?php
declare(strict_types=1);

// Cabeceras necesarias para el intercambio de JSON y CORS
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Responder rápido a las peticiones preflight de Vercel/Navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir la conexion
require_once 'conexion.php';
require_once 'includes/validaciones.php';

$method = $_SERVER['REQUEST_METHOD'];

// Capturamos el cuerpo de la peticion para el POST
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            // buscamos clientes por cedula o nombre completo (para el buscador en vivo del POS y de clientes.php)
            $search = $_GET['search'] ?? '';

            // el "cliente fiel del año" es el que mas ha gastado (ventas Pagada) en el año actual;
            // lo calculamos con una subconsulta y comparamos el id de cada cliente contra ese resultado
            $sql = "SELECT
                        c.id,
                        c.cedula,
                        c.nombre_completo,
                        c.correo,
                        c.telefono,
                        c.puntos_acumulados,
                        COALESCE((SELECT SUM(v.total_factura) FROM ventas v
                                  WHERE v.cliente_id = c.id AND v.estado = 'Pagada' AND YEAR(v.fecha_emision) = YEAR(NOW())), 0) AS total_anio,
                        (c.id = (
                            SELECT v2.cliente_id
                            FROM ventas v2
                            WHERE v2.estado = 'Pagada' AND YEAR(v2.fecha_emision) = YEAR(NOW())
                            GROUP BY v2.cliente_id
                            ORDER BY SUM(v2.total_factura) DESC
                            LIMIT 1
                        )) AS es_cliente_fiel
                    FROM clientes c
                    WHERE c.cedula LIKE ? OR c.nombre_completo LIKE ?
                    ORDER BY c.nombre_completo";

            // solo limitamos resultados cuando el usuario esta escribiendo una busqueda especifica (como en pos.php);
            // si el search viene vacio (como al abrir clientes.php) mostramos la lista completa
            if ($search !== '') {
                $sql .= " LIMIT 10";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute(["%$search%", "%$search%"]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'POST':
            // registramos un cliente nuevo directamente desde el POS cuando la busqueda no encuentra coincidencias

            $cedula = trim($input['cedula'] ?? '');
            $nombre = trim($input['nombre_completo'] ?? '');
            $telefono = trim($input['telefono'] ?? '');
            $correo = trim($input['correo'] ?? '') ?: null;

            // validamos TODO en el servidor, sin confiar en que el frontend ya lo valido (por seguridad)
            if (!validarCedulaEcuador($cedula)) {
                http_response_code(400);
                echo json_encode(['estado' => 'error', 'message' => 'La cédula ingresada no es válida (debe ser una cédula ecuatoriana real)']);
                break;
            }
            if (!validarSoloLetras($nombre)) {
                http_response_code(400);
                echo json_encode(['estado' => 'error', 'message' => 'El nombre solo puede contener letras y espacios']);
                break;
            }
            if (!validarTelefonoEcuador($telefono)) {
                http_response_code(400);
                echo json_encode(['estado' => 'error', 'message' => 'El teléfono no tiene un formato ecuatoriano válido (celular: 09XXXXXXXX, convencional: 0XXXXXXXX)']);
                break;
            }

            // evitamos registrar dos veces la misma cedula
            $stmtExiste = $pdo->prepare("SELECT id FROM clientes WHERE cedula = ?");
            $stmtExiste->execute([$cedula]);
            if ($stmtExiste->fetch()) {
                http_response_code(409);
                echo json_encode(['estado' => 'error', 'message' => 'Ya existe un cliente registrado con esa cédula']);
                break;
            }

            $sql = "INSERT INTO clientes (cedula, nombre_completo, correo, telefono) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cedula, $nombre, $correo, $telefono]);

            // devolvemos el id que genero la BD para poder asignar el cliente de una vez a la venta
            echo json_encode([
                'estado' => 'ok',
                'id' => (int)$pdo->lastInsertId(),
                'cedula' => $cedula,
                'nombre_completo' => $nombre
            ]);
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