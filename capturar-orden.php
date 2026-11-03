<?php
require_once "sesion.php";
protegerPagina("cliente");

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    responderJson(array("error" => "Solicitud no válida."), 405);
}
if (!file_exists(__DIR__ . "/paypal-config.php")) {
    responderJson(array("error" => "Falta configurar PayPal Sandbox en paypal-config.php."), 503);
}
require_once __DIR__ . "/paypal-config.php";
require_once "conexion.php";
require_once "paypal-funciones.php";

$entrada = json_decode(file_get_contents("php://input"), true);
$ordenId = trim((string)($entrada["order_id"] ?? ""));
$idCliente = (int)$_SESSION["id_cliente"];
if ($ordenId == "") {
    responderJson(array("error" => "Falta el número de orden."), 400);
}

$consulta = mysqli_prepare(
    $conexion,
    "SELECT pg.id_pago, pg.id_membresia, pg.monto, pg.moneda, pg.estado,
            m.id_plan, pl.duracion_meses
     FROM pagos AS pg
     INNER JOIN membresias AS m ON m.id_membresia = pg.id_membresia
     INNER JOIN planes_membresia AS pl ON pl.id_plan = m.id_plan
     WHERE pg.paypal_order_id = ? AND m.id_cliente = ?"
);
mysqli_stmt_bind_param($consulta, "si", $ordenId, $idCliente);
mysqli_stmt_execute($consulta);
$resultado = mysqli_stmt_get_result($consulta);
$pago = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($consulta);
if (!$pago || $pago["estado"] != "pendiente") {
    responderJson(array("error" => "No se encontró un pago pendiente para esta orden."), 400);
}
if (!defined("PAYPAL_CLIENT_ID") || strpos(PAYPAL_CLIENT_ID, "PEGA_AQUI_") === 0
    || !defined("PAYPAL_CLIENT_SECRET") || strpos(PAYPAL_CLIENT_SECRET, "PEGA_AQUI_") === 0) {
    responderJson(array("error" => "Configura tus credenciales Sandbox en paypal-config.php."), 503);
}

$transaccionIniciada = false;
try {
    $orden = paypalSolicitud("GET", "/v2/checkout/orders/" . rawurlencode($ordenId));
    if (($orden["status"] ?? "") == "COMPLETED") {
        // Si PayPal completó el pago pero el portal no guardó la respuesta,
        // usamos la orden completada para volver a sincronizar la base de datos.
        $captura = $orden;
    } else {
        // PayPal espera un objeto JSON vacío para capturar la orden: {} (no []).
        try {
            paypalSolicitud(
                "POST",
                "/v2/checkout/orders/" . rawurlencode($ordenId) . "/capture",
                new stdClass()
            );
        } catch (Throwable $errorCaptura) {
            // Una respuesta interrumpida puede ocurrir después de capturar.
            // Consultamos la orden antes de informar que el pago falló.
            $orden = paypalSolicitud("GET", "/v2/checkout/orders/" . rawurlencode($ordenId));
            if (($orden["status"] ?? "") != "COMPLETED") {
                throw $errorCaptura;
            }
        }
        // La respuesta GET contiene la orden persistida y los datos de captura.
        $captura = paypalSolicitud("GET", "/v2/checkout/orders/" . rawurlencode($ordenId));
    }
    $unidad = $captura["purchase_units"][0] ?? array();
    $detalle = $unidad["payments"]["captures"][0] ?? array();
    $monto = $detalle["amount"] ?? array();
    if (($captura["status"] ?? "") != "COMPLETED"
        || (string)($unidad["custom_id"] ?? "") != (string)$pago["id_pago"]
        || ($monto["currency_code"] ?? "") != $pago["moneda"]
        || number_format((float)($monto["value"] ?? 0), 2, ".", "") != number_format((float)$pago["monto"], 2, ".", "")
        || empty($detalle["id"])) {
        throw new Exception("La respuesta de PayPal no coincide con el pago esperado.");
    }

    mysqli_begin_transaction($conexion);
    $transaccionIniciada = true;
    $bloquear = mysqli_prepare(
        $conexion,
        "SELECT id_pago, estado FROM pagos WHERE id_pago = ? FOR UPDATE"
    );
    mysqli_stmt_bind_param($bloquear, "i", $pago["id_pago"]);
    mysqli_stmt_execute($bloquear);
    $estadoActual = mysqli_fetch_assoc(mysqli_stmt_get_result($bloquear));
    mysqli_stmt_close($bloquear);

    if (!$estadoActual || $estadoActual["estado"] != "pendiente") {
        mysqli_rollback($conexion);
        $transaccionIniciada = false;
        responderJson(array("error" => "El pago ya fue procesado."), 409);
    }

    $inicio = new DateTimeImmutable("today");
    $buscarActiva = mysqli_prepare(
        $conexion,
        "SELECT fecha_fin FROM membresias WHERE id_cliente = ? AND estado = 'activa'
         AND fecha_fin >= CURDATE() ORDER BY fecha_fin DESC LIMIT 1 FOR UPDATE"
    );
    mysqli_stmt_bind_param($buscarActiva, "i", $idCliente);
    mysqli_stmt_execute($buscarActiva);
    $membresiaActiva = mysqli_fetch_assoc(mysqli_stmt_get_result($buscarActiva));
    mysqli_stmt_close($buscarActiva);
    if ($membresiaActiva) {
        $inicio = (new DateTimeImmutable($membresiaActiva["fecha_fin"]))->modify("+1 day");
    }
    $fin = $inicio->modify("+" . (int)$pago["duracion_meses"] . " months")->modify("-1 day");
    $fechaInicio = $inicio->format("Y-m-d");
    $fechaFin = $fin->format("Y-m-d");
    $idCaptura = $detalle["id"];
    $idPago = (int)$pago["id_pago"];
    $actualizarPago = mysqli_prepare(
        $conexion,
        "UPDATE pagos SET estado = 'pagado', paypal_capture_id = ?, fecha_pago = NOW()
         WHERE id_pago = ? AND estado = 'pendiente'"
    );
    mysqli_stmt_bind_param($actualizarPago, "si", $idCaptura, $idPago);
    $pagoActualizado = mysqli_stmt_execute($actualizarPago) && mysqli_stmt_affected_rows($actualizarPago) == 1;
    mysqli_stmt_close($actualizarPago);
    $activar = mysqli_prepare(
        $conexion,
        "UPDATE membresias SET fecha_inicio = ?, fecha_fin = ?, estado = 'activa' WHERE id_membresia = ?"
    );
    mysqli_stmt_bind_param($activar, "ssi", $fechaInicio, $fechaFin, $pago["id_membresia"]);
    $membresiaActualizada = mysqli_stmt_execute($activar);
    mysqli_stmt_close($activar);

    if (!$pagoActualizado || !$membresiaActualizada) {
        mysqli_rollback($conexion);
        $transaccionIniciada = false;
        throw new Exception("No se pudo actualizar la base de datos.");
    }
    mysqli_commit($conexion);
    $transaccionIniciada = false;
    responderJson(array("recibo" => "recibo.php?id_pago=" . $idPago));
} catch (Throwable $error) {
    if ($transaccionIniciada) {
        mysqli_rollback($conexion);
    }
    error_log("IntenseFit: error al confirmar pago #" . (int)($pago["id_pago"] ?? 0) . ": " . $error->getMessage());
    responderJson(array("error" => "No se pudo confirmar el pago. Si PayPal muestra un cobro, contacta al gimnasio antes de volver a intentarlo."), 502);
}
?>
