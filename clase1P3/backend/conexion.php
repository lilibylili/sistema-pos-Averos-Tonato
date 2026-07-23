<?php
declare(strict_types=1);

    // Credenciales de la Base de Datos en Aiven
    $host = "mysql-d4f4243-sistemapos2026.b.aivencloud.com";
    $port = 19850;
    $user = "avnadmin";
    $password = "AVNS_netR1nIv-tPpr5xTYeo";
    $database = "defaultdb";
    $charset = "utf8mb4";

    // Incluimos el puerto en el DSN
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=$charset";

    // Configuraciones de PDO adaptadas para Vercel Serverless + Aiven
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        // En Vercel desactivamos la verificación del CA local para permitir la conexión SSL con Aiven
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

try {
    // Crear la instancia de PDO
    $pdo = new PDO($dsn, $user, $password, $options);

} catch (PDOException $e) {
    // Responder con JSON y código 500
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de conexión a la base de datos',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
