<?php
require_once "sesion.php";
protegerPagina("cliente");
require_once "conexion.php";
require_once "fpdf/fpdf.php";

$idPago = (int)($_GET["id_pago"] ?? 0);
$idCliente = (int)$_SESSION["id_cliente"];
$consulta = mysqli_prepare(
    $conexion,
    "SELECT pg.id_pago, pg.monto, pg.moneda, pg.paypal_order_id, pg.paypal_capture_id,
            pg.fecha_pago, u.correo, c.nombres, c.apellidos, pl.nombre AS nombre_plan,
            m.fecha_inicio, m.fecha_fin
     FROM pagos AS pg
     INNER JOIN membresias AS m ON m.id_membresia = pg.id_membresia
     INNER JOIN clientes AS c ON c.id_cliente = m.id_cliente
     INNER JOIN usuarios AS u ON u.id_usuario = c.id_usuario
     INNER JOIN planes_membresia AS pl ON pl.id_plan = m.id_plan
     WHERE pg.id_pago = ? AND m.id_cliente = ? AND pg.estado = 'pagado'"
);
mysqli_stmt_bind_param($consulta, "ii", $idPago, $idCliente);
mysqli_stmt_execute($consulta);
$pago = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
mysqli_stmt_close($consulta);
if (!$pago) {
    $_SESSION["mensaje"] = "El recibo solo está disponible después de confirmar el pago.";
    header("Location: cliente.php");
    exit;
}

function textoPdf($texto)
{
    return iconv("UTF-8", "ISO-8859-1//TRANSLIT", (string)$texto);
}

$pdf = new FPDF();
$pdf->SetTitle("Recibo IntenseFit #" . $idPago);
$pdf->SetAuthor("IntenseFit");
$pdf->AddPage();
$pdf->SetFillColor(31, 31, 31);
$pdf->Rect(0, 0, 210, 42, "F");
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont("Helvetica", "B", 21);
$pdf->SetXY(18, 14);
$pdf->Cell(0, 10, "INTENSEFIT", 0, 1);
$pdf->SetFont("Helvetica", "", 10);
$pdf->SetX(18);
$pdf->Cell(0, 7, "RECIBO DE PAGO DE MEMBRESIA", 0, 1);
$pdf->SetTextColor(35, 35, 35);
$pdf->SetXY(18, 55);
$pdf->SetFont("Helvetica", "B", 13);
$pdf->Cell(0, 9, "Pago #" . $idPago, 0, 1);
$pdf->SetFont("Helvetica", "", 11);
$pdf->Ln(5);

$detalles = array(
    array("Cliente", $pago["nombres"] . " " . $pago["apellidos"]),
    array("Correo", $pago["correo"]),
    array("Membresia", $pago["nombre_plan"]),
    array("Importe pagado", "$" . number_format((float)$pago["monto"], 2) . " " . $pago["moneda"]),
    array("Fecha de pago", $pago["fecha_pago"] ? date("d/m/Y H:i", strtotime($pago["fecha_pago"])) : "-"),
    array("Vigencia", date("d/m/Y", strtotime($pago["fecha_inicio"])) . " al " . date("d/m/Y", strtotime($pago["fecha_fin"]))),
    array("Orden PayPal", $pago["paypal_order_id"] ?: "-"),
    array("Referencia de pago", $pago["paypal_capture_id"] ?: "-"),
    array("Estado", "PAGADO")
);
foreach ($detalles as $fila) {
    $pdf->SetFont("Helvetica", "B", 10);
    $pdf->Cell(48, 10, textoPdf($fila[0]), 0, 0);
    $pdf->SetFont("Helvetica", "", 10);
    $pdf->MultiCell(125, 10, textoPdf($fila[1]), 0, "L");
    $pdf->SetDrawColor(225, 225, 225);
    $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
}
$pdf->Ln(12);
$pdf->SetFont("Helvetica", "I", 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(0, 6, textoPdf("Recibo generado por IntenseFit. El pago se procesó en el entorno de pruebas de PayPal Sandbox."));
$pdf->Output("I", "recibo-intensefit-" . $idPago . ".pdf");
?>
