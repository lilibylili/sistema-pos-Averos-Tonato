<?php
    /*session_start();
    if(!isset($_SESSION['usuario_activo'])){
        header("Location: dashboard.php");
        exit();
    }*/
?>

<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acesso al sistema</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    </head>
<body class = "bg-light d-flex justify-content-center align-items-center vh-100">
    <div class="card p-4 shadow" style="width: 100%; max-width: 400px;">

    <div class="text-center mb4">
        <h3 class="text-primary">Sistema POS</h3>
        <p class="text-muted">Ingreses sus credenciales</p>
    </div>

    <?php
        if(isset($_GET['error'])): ?>
            <div class="alert alert-danger" role="alert">
                Usuario o contraseña incorrectos
            </div>
            
    <?php endif; ?>

    <form action="backend/procesar_login.php" method="POST">
        <div class="mb-3">
            <label for="usuario" class="form-label">Usuario</label>
            <input type="text" name="usuario"  class="form-control" required autocomplete="off">
        </div>
        <div class="mb-4">
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 fw-bold">Ingresar</button>
    </form>
    </div>
