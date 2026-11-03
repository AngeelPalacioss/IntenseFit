<?php
require_once "sesion.php";
protegerPagina("admin");
require_once "conexion.php";

$nombrePanel = "Administración";
$paginaActiva = "admin";
$mensaje = $_SESSION["mensaje"] ?? "";
unset($_SESSION["mensaje"]);

$resultadoTotal = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM clientes");
$totalClientes = mysqli_fetch_assoc($resultadoTotal)["total"];
$resultadoActivos = mysqli_query(
    $conexion,
    "SELECT COUNT(*) AS total FROM membresias
     WHERE estado = 'activa' AND fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE()"
);
$clientesActivos = mysqli_fetch_assoc($resultadoActivos)["total"];
$resultadoProximas = mysqli_query(
    $conexion,
    "SELECT COUNT(*) AS total FROM membresias
     WHERE estado = 'activa' AND fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)"
);
$porVencer = mysqli_fetch_assoc($resultadoProximas)["total"];
$resultadoVentas = mysqli_query(
    $conexion,
    "SELECT COUNT(*) AS total, COALESCE(SUM(monto), 0) AS ingresos
     FROM pagos WHERE estado = 'pagado'"
);
$resumenVentas = mysqli_fetch_assoc($resultadoVentas);
$ventasConfirmadas = $resumenVentas["total"];
$ingresos = $resumenVentas["ingresos"];

$consultaClientes = mysqli_query(
    $conexion,
    "SELECT c.id_cliente, c.nombres, c.apellidos, c.fecha_registro, u.correo,
            p.nombre AS nombre_plan, m.estado, m.fecha_fin
     FROM clientes AS c
     INNER JOIN usuarios AS u ON u.id_usuario = c.id_usuario
     LEFT JOIN membresias AS m ON m.id_membresia = (
         SELECT MAX(m2.id_membresia)
         FROM membresias AS m2
         WHERE m2.id_cliente = c.id_cliente
     )
     LEFT JOIN planes_membresia AS p ON p.id_plan = m.id_plan
     ORDER BY c.id_cliente DESC"
);

$consultaPagos = mysqli_query(
    $conexion,
    "SELECT pg.id_pago, pg.monto, pg.estado, pg.fecha_pago,
            CONCAT(c.nombres, ' ', c.apellidos) AS cliente,
            pl.nombre AS plan
     FROM pagos AS pg
     INNER JOIN membresias AS m ON m.id_membresia = pg.id_membresia
     INNER JOIN clientes AS c ON c.id_cliente = m.id_cliente
     INNER JOIN planes_membresia AS pl ON pl.id_plan = m.id_plan
     ORDER BY pg.id_pago DESC LIMIT 10"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IntenseFit | Administración</title>
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
                <p class="etiqueta">GESTIÓN DEL GIMNASIO</p>
                <h1>Clientes y membresías</h1>
                <p>Consulta los registros y revisa las membresías de IntenseFit.</p>
            </div>
            <a class="boton" href="registro-cliente.php">Registrar cliente</a>
        </div>

        <?php if ($mensaje != "") { ?>
            <p class="mensaje-exito"><?php echo escapar($mensaje); ?></p>
        <?php } ?>

        <section class="resumen-tarjetas">
            <article class="tarjeta">
                <p class="etiqueta">CLIENTES REGISTRADOS</p>
                <h2><?php echo escapar($totalClientes); ?></h2>
                <p>Guardados en la base de datos</p>
            </article>
            <article class="tarjeta">
                <p class="etiqueta">MEMBRESÍAS ACTIVAS</p>
                <h2><?php echo escapar($clientesActivos); ?></h2>
                <p><?php echo escapar($porVencer); ?> por vencer en 14 días</p>
            </article>
            <article class="tarjeta">
                <p class="etiqueta">VENTAS CONFIRMADAS</p>
                <h2><?php echo escapar($ventasConfirmadas); ?></h2>
                <p>Total: $<?php echo number_format((float)$ingresos, 2); ?> MXN</p>
            </article>
        </section>

        <section class="seccion">
            <div class="encabezado-seccion">
                <div><p class="etiqueta">DIRECTORIO</p><h2>Clientes</h2></div>
            </div>
            <div class="tabla-contenedor">
                <table>
                    <thead><tr><th>ID</th><th>Cliente</th><th>Correo</th><th>Desde</th><th>Plan</th><th>Estado</th><th>Vence</th></tr></thead>
                    <tbody>
                        <?php if (mysqli_num_rows($consultaClientes) == 0) { ?>
                            <tr><td colspan="7">Todavía no hay clientes registrados.</td></tr>
                        <?php } ?>
                        <?php while ($cliente = mysqli_fetch_assoc($consultaClientes)) {
                            $estado = $cliente["estado"] ?? "sin membresía";
                            if ($estado == "activa" && $cliente["fecha_fin"] < date("Y-m-d")) {
                                $estado = "vencida";
                            } elseif ($estado == "activa" && $cliente["fecha_fin"] <= date("Y-m-d", strtotime("+14 days"))) {
                                $estado = "por vencer";
                            }
                        ?>
                            <tr>
                                <td><?php echo escapar($cliente["id_cliente"]); ?></td>
                                <td><?php echo escapar($cliente["nombres"] . " " . $cliente["apellidos"]); ?></td>
                                <td><?php echo escapar($cliente["correo"]); ?></td>
                                <td><?php echo escapar($cliente["fecha_registro"]); ?></td>
                                <td><?php echo escapar($cliente["nombre_plan"] ?? "Sin plan"); ?></td>
                                <td><span class="estado <?php echo $estado == "activa" ? "" : "estado-aviso"; ?>"><?php echo escapar(ucfirst($estado)); ?></span></td>
                                <td><?php echo escapar($cliente["fecha_fin"] ?? "Pendiente"); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="seccion">
            <div class="encabezado-seccion"><div><p class="etiqueta">MOVIMIENTOS</p><h2>Pagos recientes</h2></div></div>
            <div class="tabla-contenedor">
                <table>
                    <thead><tr><th>Pago</th><th>Cliente</th><th>Plan</th><th>Monto</th><th>Estado</th><th>Fecha</th></tr></thead>
                    <tbody>
                        <?php if (mysqli_num_rows($consultaPagos) == 0) { ?>
                            <tr><td colspan="6">Todavía no hay pagos registrados.</td></tr>
                        <?php } ?>
                        <?php while ($pago = mysqli_fetch_assoc($consultaPagos)) { ?>
                            <tr>
                                <td><?php echo escapar($pago["id_pago"]); ?></td>
                                <td><?php echo escapar($pago["cliente"]); ?></td>
                                <td><?php echo escapar($pago["plan"]); ?></td>
                                <td>$<?php echo number_format((float)$pago["monto"], 2); ?> MXN</td>
                                <td><span class="estado <?php echo $pago["estado"] == "pagado" ? "" : "estado-aviso"; ?>"><?php echo escapar(ucfirst($pago["estado"])); ?></span></td>
                                <td><?php echo escapar($pago["fecha_pago"] ?? "Pendiente"); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <?php include "footer.php"; ?>
</body>
</html>
