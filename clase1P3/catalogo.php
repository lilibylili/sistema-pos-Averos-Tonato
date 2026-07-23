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
    <title>Catálogo de Productos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fronted/css/dashboard.css">
    <style>
        .btn-verde{
            background-color: var(--verde-oscuro);
            color:white;
        }
        .btn-verde:hover{
            background-color : var(--verde-medio); 
            color:white;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include __DIR__ . '/backend/includes/sidebar.php'; ?>
        <div id="content" class="w-100" style="margin-left: 280px">
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 p-3">
                    <span class="navbar-brand mb-0 h4 text-secondary">Dashboard General </span>

            </nav>
             <div class="container-fluid px-4">
            <div class="d-flex justify-content-between mb-4">
                <input type="text" id="input-busqueda" class="fomr-control w-25" placeholder="🔎 Buscar por código o nombre">
                <button class="btn btn-verde" onclick="abrirModal()"> + Nuevo Producto</button>
            </div>

            <div class="card shadow-sm">
                <div class="card body">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpo tabla">
                    </tbody>
                </table>
            </div>
        </div>

        </div>
    </div>
</div>

    <!--modal para agregar producto-->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitulo">Gestionar Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="prod-id">
                <div class="mb-3">
                    <label>Código de Barras</label>
                    <input type="text" id="prod-codigo" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Nombre</label>
                    <input type="text" id="prod-nombre" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Precio</label>
                    <input type="number" id="prod-precio" class="form-control" step="0.01">
                </div>
                <div class="mb-3">
                    <label>Stock</label>
                    <input type="number" id="prod-stock" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-verde" onclick="guardarProducto()">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>
