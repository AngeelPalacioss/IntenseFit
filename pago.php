<?php
require_once "sesion.php";
protegerPagina("cliente");
require_once "conexion.php";

$idPago = (int)($_GET["id_pago"] ?? 0);
$idCliente = (int)$_SESSION["id_cliente"];
$consulta = mysqli_prepare(
    $conexion,
    "SELECT pg.id_pago, pg.monto, pg.moneda, pg.estado, pg.paypal_order_id,
            m.id_membresia, p.nombre AS nombre_plan, p.duracion_meses
     FROM pagos AS pg
     INNER JOIN membresias AS m ON m.id_membresia = pg.id_membresia
     INNER JOIN planes_membresia AS p ON p.id_plan = m.id_plan
     WHERE pg.id_pago = ? AND m.id_cliente = ?"
);
mysqli_stmt_bind_param($consulta, "ii", $idPago, $idCliente);
mysqli_stmt_execute($consulta);
$resultado = mysqli_stmt_get_result($consulta);
$pago = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($consulta);

if (!$pago) {
    $_SESSION["mensaje"] = "No se encontró el pago seleccionado.";
    header("Location: cliente.php");
    exit;
}

$paypalDisponible = false;
$paypalClientId = "";
if (file_exists(__DIR__ . "/paypal-config.php")) {
    require_once "paypal-config.php";
    if (defined("PAYPAL_CLIENT_ID") && strpos(PAYPAL_CLIENT_ID, "PEGA_AQUI_") !== 0
        && defined("PAYPAL_CLIENT_SECRET") && strpos(PAYPAL_CLIENT_SECRET, "PEGA_AQUI_") !== 0) {
        $paypalDisponible = true;
        $paypalClientId = PAYPAL_CLIENT_ID;
    }
}

$nombrePanel = "Pago de membresía";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IntenseFit | Pago</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Krub:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include "header.php"; ?>
    <main class="contenedor contenido">
        <div class="titulo-pagina">
            <div>
                <p class="etiqueta">RESUMEN DE COMPRA</p>
                <h1><?php echo escapar($pago["nombre_plan"]); ?></h1>
                <p>Pago número <?php echo escapar($pago["id_pago"]); ?></p>
            </div>
        </div>
        <section class="seccion pago-resumen">
            <h2>Total: $<?php echo number_format((float)$pago["monto"], 2); ?> <?php echo escapar($pago["moneda"]); ?></h2>
            <p>Vigencia del plan: <?php echo escapar($pago["duracion_meses"]); ?> mes(es).</p>
            <p>Estado: <strong><?php echo escapar(ucfirst($pago["estado"])); ?></strong></p>
            <?php if ($pago["estado"] == "pagado") { ?>
                <div class="aviso-pago">
                    <h2>Pago confirmado</h2>
                    <p>Tu membresía está activa. Puedes descargar el recibo de este pago.</p>
                </div>
                <a class="boton" href="recibo.php?id_pago=<?php echo (int)$pago["id_pago"]; ?>">Descargar recibo PDF</a>
            <?php } else { ?>
            <div class="aviso-pago">
                <h2>Pago pendiente</h2>
                <p>La membresía no se activa ni cuenta como venta hasta que PayPal confirme el pago.</p>
            </div>
            <?php if ($paypalDisponible) { ?>
                <div id="paypal-button-container" class="paypal-boton"></div>
                <p id="mensaje-paypal" class="nota" aria-live="polite">El pago se realiza en modo de prueba de PayPal Sandbox.</p>
                <?php if (!empty($pago["paypal_order_id"])) { ?>
                    <button type="button" id="verificar-pago" class="boton boton-secundario">
                        Verificar pago anterior
                    </button>
                <?php } ?>
            <?php } else { ?>
                <div class="aviso-pago">
                    <h2>Falta configurar PayPal Sandbox</h2>
                    <p>Abre <strong>paypal-config.php</strong> y agrega tu Client ID y Secret de prueba. El archivo ya está excluido de Git.</p>
                </div>
            <?php } ?>
            <?php } ?>
            <a class="boton boton-secundario" href="cliente.php">Volver a mi cuenta</a>
        </section>
    </main>
    <?php include "footer.php"; ?>
    <?php if ($paypalDisponible && $pago["estado"] == "pendiente") { ?>
        <script src="https://www.paypal.com/sdk/js?client-id=<?php echo rawurlencode($paypalClientId); ?>&currency=<?php echo rawurlencode(PAYPAL_MONEDA); ?>"></script>
        <script>
            const mensajePayPal = document.getElementById("mensaje-paypal");
            const botonVerificarPago = document.getElementById("verificar-pago");
            if (botonVerificarPago) {
                botonVerificarPago.addEventListener("click", async function () {
                    botonVerificarPago.disabled = true;
                    mensajePayPal.textContent = "Verificando el pago con PayPal...";
                    try {
                        const respuesta = await fetch("capturar-orden.php", {
                            method: "POST",
                            headers: {"Content-Type": "application/json"},
                            body: JSON.stringify({order_id: "<?php echo escapar($pago["paypal_order_id"]); ?>"})
                        });
                        const datos = await respuesta.json();
                        if (!respuesta.ok) throw new Error(datos.error || "No se pudo verificar el pago.");
                        window.location.href = datos.recibo;
                    } catch (error) {
                        mensajePayPal.textContent = error.message || "No se pudo verificar el pago.";
                        botonVerificarPago.disabled = false;
                    }
                });
            }
            paypal.Buttons({
                createOrder: async function () {
                    const respuesta = await fetch("crear-orden.php", {
                        method: "POST",
                        headers: {"Content-Type": "application/json"},
                        body: JSON.stringify({id_pago: <?php echo (int)$pago["id_pago"]; ?>})
                    });
                    const datos = await respuesta.json();
                    if (!respuesta.ok) throw new Error(datos.error || "No se pudo crear el pago.");
                    return datos.id;
                },
                onApprove: async function (datosOrden) {
                    mensajePayPal.textContent = "Confirmando el pago...";
                    const respuesta = await fetch("capturar-orden.php", {
                        method: "POST",
                        headers: {"Content-Type": "application/json"},
                        body: JSON.stringify({order_id: datosOrden.orderID})
                    });
                    const datos = await respuesta.json();
                    if (!respuesta.ok) throw new Error(datos.error || "No se pudo confirmar el pago.");
                    window.location.href = datos.recibo;
                },
                onCancel: function () {
                    mensajePayPal.textContent = "El pago se canceló. Puedes intentarlo de nuevo cuando quieras.";
                },
                onError: function (error) {
                    mensajePayPal.textContent = error.message || "Ocurrió un error al procesar el pago.";
                }
            }).render("#paypal-button-container");
        </script>
    <?php } ?>
</body>
</html>
