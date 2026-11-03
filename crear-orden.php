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
$idPago = (int)($entrada["id_pago"] ?? 0);
$idCliente = (int)$_SESSION["id_cliente"];
$consulta = mysqli_prepare(
    $conexion,
    "SELECT pg.id_pago, pg.monto, pg.moneda, pg.estado, pg.paypal_order_id
     FROM pagos AS pg
     INNER JOIN membresias AS m ON m.id_membresia = pg.id_membresia
     WHERE pg.id_pago = ? AND m.id_cliente = ?"
);
mysqli_stmt_bind_param($consulta, "ii", $idPago, $idCliente);
mysqli_stmt_execute($consulta);
$resultado = mysqli_stmt_get_result($consulta);
$pago = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($consulta);

if (!$pago || $pago["estado"] != "pendiente") {
    responderJson(array("error" => "El pago ya no está pendiente o no pertenece a tu cuenta."), 400);
}
if (!defined("PAYPAL_CLIENT_ID") || strpos(PAYPAL_CLIENT_ID, "PEGA_AQUI_") === 0
    || !defined("PAYPAL_CLIENT_SECRET") || strpos(PAYPAL_CLIENT_SECRET, "PEGA_AQUI_") === 0) {
    responderJson(array("error" => "Configura tus credenciales Sandbox en paypal-config.php."), 503);
}

try {
    if (!empty($pago["paypal_order_id"])) {
        $ordenAnterior = paypalSolicitud(
            "GET",
            "/v2/checkout/orders/" . rawurlencode($pago["paypal_order_id"])
        );
        if (($ordenAnterior["status"] ?? "") == "COMPLETED"
            || ($ordenAnterior["status"] ?? "") == "APPROVED") {
            responderJson(array(
                "error" => "Hay una orden aprobada. Usa el botón para verificar el pago antes de iniciar otro." 
            ), 409);
        }
        if (($ordenAnterior["status"] ?? "") == "CREATED") {
            responderJson(array("id" => $pago["paypal_order_id"]));
        }
    }

    $orden = paypalSolicitud("POST", "/v2/checkout/orders", array(
        "intent" => "CAPTURE",
        "purchase_units" => array(array(
            "reference_id" => (string)$idPago,
            "custom_id" => (string)$idPago,
            "description" => "Membresía IntenseFit, pago #" . $idPago,
            "amount" => array(
                "currency_code" => PAYPAL_MONEDA,
                "value" => number_format((float)$pago["monto"], 2, ".", "")
            )
        )),
        "application_context" => array(
            "brand_name" => "IntenseFit",
            "user_action" => "PAY_NOW"
        )
    ));
    if (empty($orden["id"])) {
        throw new Exception("PayPal no devolvió el número de orden.");
    }

    $guardar = mysqli_prepare(
        $conexion,
        "UPDATE pagos SET paypal_order_id = ? WHERE id_pago = ? AND estado = 'pendiente'"
    );
    mysqli_stmt_bind_param($guardar, "si", $orden["id"], $idPago);
    mysqli_stmt_execute($guardar);
    mysqli_stmt_close($guardar);
    responderJson(array("id" => $orden["id"]));
} catch (Throwable $error) {
    responderJson(array("error" => "No se pudo crear la orden de PayPal. Revisa la configuración Sandbox."), 502);
}
?>
