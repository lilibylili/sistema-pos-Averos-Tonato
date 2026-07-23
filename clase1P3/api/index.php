<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/..' . $uri;

// 1. Si el archivo existe físicamente en el disco
if (file_exists($file) && !is_dir($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    
    // SI ES UN ARCHIVO PHP -> EJECUTARLO
    if ($ext === 'php') {
        require $file;
        exit();
    }

    // Recurso estático
    $mimeTypes = [
        'css'  => 'text/css; charset=UTF-8',
        'js'   => 'application/javascript; charset=UTF-8',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon'
    ];

    if (isset($mimeTypes[$ext])) {
        header("Content-Type: " . $mimeTypes[$ext]);
    }

    readfile($file);
    exit();
}

// 2. Ruta raíz por defecto
if ($uri === '/' || $uri === '' || $uri === '/index.php') {
    require __DIR__ . '/../index.php';
    exit();
}

// 3. Rutas sin extensión explícita
if (file_exists($file . '.php')) {
    require $file . '.php';
    exit();
}

http_response_code(404);
echo "404 - Página no encontrada";
