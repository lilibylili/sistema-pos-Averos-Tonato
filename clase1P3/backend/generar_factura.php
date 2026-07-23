<?php
declare(strict_types=1);

require_once 'conexion.php';
require_once 'lib/fpdf/fpdf.php';

// Función auxiliar para convertir caracteres a ISO-8859-1 de forma compatible con PHP 8.2+
function a_iso(string $texto): string {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto) ?: $texto;
}

// Recibimos el id de la venta que se acaba de procesar
$ventaId = $_GET['venta_id'] ?? null;

if (!$ventaId) {
    die('Falta el id de la venta');
}

// Obtenemos la cabecera de la venta junto con los datos del cliente
$stmt = $pdo->prepare("SELECT v.id, v.total_factura, v.fecha_emision, c.cedula, c.nombre_completo
                        FROM ventas v
                        INNER JOIN clientes c ON c.id = v.cliente_id
                        WHERE v.id = ?");
$stmt->execute([$ventaId]);
$venta = $stmt->fetch();

if (!$venta) {
    die('Venta no encontrada');
}

// Obtenemos el detalle de productos de esa venta
$stmtDetalle = $pdo->prepare("SELECT d.cantidad, d.precio_congelado, p.nombre_producto, p.codigo_barras
                               FROM detalle_ventas d
                               INNER JOIN productos p ON p.id = d.producto_id
                               WHERE d.venta_id = ?");
$stmtDetalle->execute([$ventaId]);
$items = $stmtDetalle->fetchAll();

// Recalculamos el subtotal y el iva a partir del detalle 
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['precio_congelado'] * $item['cantidad'];
}
$iva = $subtotal * 0.15;
$total = $subtotal + $iva;

// Numero de factura con el formato 001-001-000000001
$numeroFactura = '001-001-' . str_pad((string)$venta['id'], 9, '0', STR_PAD_LEFT);

// Generamos el PDF con formato tipo ticket usando la libreria FPDF
$pdf = new FPDF('P', 'mm', [80, 160]); // Ancho 80mm
$pdf->SetAutoPageBreak(true, 5);
$pdf->AddPage();
$pdf->SetMargins(5, 5, 5);

// Encabezado con el nombre del sistema
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 5, 'SISTEMA POS', 0, 1, 'C');
$pdf->Cell(0, 5, a_iso('Liliana Averos | Michael Tonato'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 4, a_iso('Gestión Comercial'), 0, 1, 'C');
$pdf->Cell(0, 4, 'RUC: 9999999999001', 0, 1, 'C');
$pdf->Cell(0, 4, 'Pichincha, Ecuador', 0, 1, 'C');
$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T'); // Línea separadora
$pdf->Ln(3);

// Datos de la factura
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 4, 'FACTURA No. ' . $numeroFactura, 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 4, 'Fecha: ' . date('d/m/Y H:i', strtotime($venta['fecha_emision'])), 0, 1, 'L');
$pdf->Cell(0, 4, a_iso('Cliente: ' . $venta['nombre_completo']), 0, 1, 'L');
$pdf->Cell(0, 4, a_iso('Cédula: ' . $venta['cedula']), 0, 1, 'L');
$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(3);

// Encabezado de la tabla de productos
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(8, 4, 'Cant', 0, 0, 'L');
$pdf->Cell(37, 4, a_iso('Descripción'), 0, 0, 'L');
$pdf->Cell(12, 4, 'P.Unit', 0, 0, 'R');
$pdf->Cell(13, 4, 'Total', 0, 1, 'R');
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(2);

// Detalle de cada producto comprado
$pdf->SetFont('Arial', '', 8);
foreach ($items as $item) {
    $subtotalLinea = $item['precio_congelado'] * $item['cantidad'];

    $pdf->Cell(8, 4, (string)$item['cantidad'], 0, 0, 'L');
    $pdf->Cell(37, 4, a_iso(mb_substr($item['nombre_producto'], 0, 20)), 0, 0, 'L');
    $pdf->Cell(12, 4, number_format((float)$item['precio_congelado'], 2), 0, 0, 'R');
    $pdf->Cell(13, 4, number_format($subtotalLinea, 2), 0, 1, 'R');
}

$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(3);

// Totales de la factura
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(50, 4, 'Subtotal', 0, 0, 'L');
$pdf->Cell(20, 4, '$' . number_format($subtotal, 2), 0, 1, 'R');

$pdf->Cell(50, 4, 'IVA (15%)', 0, 0, 'L');
$pdf->Cell(20, 4, '$' . number_format($iva, 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(50, 6, 'TOTAL', 0, 0, 'L');
$pdf->Cell(20, 6, '$' . number_format($total, 2), 0, 1, 'R');

$pdf->Ln(3);
$pdf->SetFont('Arial', '', 7);
$pdf->Cell(0, 3, 'Gracias por su compra', 0, 1, 'C');
$pdf->Cell(0, 3, 'Documento sin validez tributaria', 0, 1, 'C');
$pdf->Cell(0, 3, a_iso('Proyecto académico - ESPE'), 0, 1, 'C');

// Limpiamos cualquier salida previa en el búfer para evitar errores de FPDF
if (ob_get_length()) {
    ob_end_clean();
}

// Mostramos el PDF directamente en el navegador
$pdf->Output('I', 'Factura_' . $numeroFactura . '.pdf');
