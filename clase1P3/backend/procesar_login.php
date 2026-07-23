<?php 
declare(strict_types=1);

// En producción es mejor deshabilitar la muestra de errores crudos por seguridad
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Cabeceras CORS por si la petición se realiza mediante fetch / AJAX desde el Frontend
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Incluimos la conexión a la base de datos
require_once "conexion.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Capturamos datos sea que vengan por $_POST de un <form> o por JSON desde un fetch()
    $input = json_decode(file_get_contents('php://input'), true);
    $usuarioInput  = trim($_POST['usuario'] ?? $input['usuario'] ?? '');
    $passwordInput = trim($_POST['password'] ?? $input['password'] ?? '');

    if (empty($usuarioInput) || empty($passwordInput)) {
        header("Location: ../index.php?error=vacio");
        exit();
    }

    try {
        // Consultamos el usuario de forma segura con Prepared Statements
        $stmt = $pdo->prepare("SELECT id, usuario, password_hash, rol FROM usuarios WHERE usuario = ? AND estado_activo = 1");
        $stmt->execute([$usuarioInput]);
        $usuarioDB = $stmt->fetch();

        // Verificamos la contraseña contra el hash de la BD usando password_verify
        if ($usuarioDB && password_verify($passwordInput, $usuarioDB['password_hash'])) {
            
            // Guardamos los datos del usuario en la sesión
            $_SESSION['usuario_activo'] = [
                'id'      => $usuarioDB['id'],
                'usuario' => $usuarioDB['usuario'],
                'rol'     => $usuarioDB['rol']
            ];

            // Si es petición JSON (fetch/AJAX)
            if (!empty($input)) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'estado'  => 'ok',
                    'message' => 'Login exitoso',
                    'usuario' => $usuarioDB['usuario']
                ]);
                exit();
            }

            // Redireccionamos al dashboard en formulario HTML normal
            header("Location: ../dashboard.php");
            exit();

        } else {
            // Login fallido
            if (!empty($input)) {
                header('Content-Type: application/json; charset=UTF-8');
                http_response_code(401);
                echo json_encode(['estado' => 'error', 'message' => 'Usuario o contraseña incorrectos']);
                exit();
            }

            header("Location: ../index.php?error=1");
            exit();
        }
        
    } catch (PDOException $e) {
        if (!empty($input)) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            echo json_encode(['estado' => 'error', 'message' => 'Error en la base de datos']);
            exit();
        }
        die("Error al procesar la solicitud: " . $e->getMessage());
    }
} else {
    // Si no es POST, redirigimos al login
    header("Location: ../index.php");
    exit();
}