<?php
require_once "sesion.php";
protegerPagina("cliente");
require_once "conexion.php";

$nombrePanel = "Mi cuenta";
$paginaActiva = "cliente";
$mensaje = $_SESSION["mensaje"] ?? "";
unset($_SESSION["mensaje"]);
$idCliente = (int)$_SESSION["id_cliente"];

$consultaCliente = mysqli_prepare(
    $conexion,
    "SELECT c.id_cliente, c.nombres, c.apellidos, c.fecha_registro, u.correo,
            m.estado, m.fecha_inicio, m.fecha_fin, p.nombre AS nombre_plan
     FROM clientes AS c
     INNER JOIN usuarios AS u ON u.id_usuario = c.id_usuario
     LEFT JOIN membresias AS m ON m.id_membresia = (
         SELECT MAX(m2.id_membresia)
         FROM membresias AS m2
         WHERE m2.id_cliente = c.id_cliente
     )
     LEFT JOIN planes_membresia AS p ON p.id_plan = m.id_plan
     WHERE c.id_cliente = ?"
);
mysqli_stmt_bind_param($consultaCliente, "i", $idCliente);
mysqli_stmt_execute($consultaCliente);
$resultadoCliente = mysqli_stmt_get_result($consultaCliente);
$cliente = mysqli_fetch_assoc($resultadoCliente);
mysqli_stmt_close($consultaCliente);

if (!$cliente) {
    $_SESSION["mensaje"] = "No se encontró el perfil del cliente.";
    header("Location: cerrar.php");
    exit;
}

$estado = $cliente["estado"] ?? "sin membresía";
if ($estado == "activa" && !empty($cliente["fecha_fin"])) {
    if ($cliente["fecha_fin"] < date("Y-m-d")) {
        $estado = "vencida";
    } elseif ($cliente["fecha_fin"] <= date("Y-m-d", strtotime("+14 days"))) {
        $estado = "por vencer";
    }
}

$consultaPlanes = mysqli_query(
    $conexion,
    "SELECT id_plan, nombre, duracion_meses, precio
     FROM planes_membresia WHERE activo = 1 ORDER BY duracion_meses"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IntenseFit | Mi cuenta</title>
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
                <p class="etiqueta">MI CUENTA</p>
                <h1>Hola, <?php echo escapar($cliente["nombres"] . " " . $cliente["apellidos"]); ?></h1>
                <p>Consulta el estado actual de tu cuenta en IntenseFit.</p>
            </div>
            <a class="boton boton-secundario" href="membresias.php">Ver planes</a>
        </div>

        <?php if ($mensaje != "") { ?><p class="mensaje-exito"><?php echo escapar($mensaje); ?></p><?php } ?>

        <section class="resumen-tarjetas">
            <article class="tarjeta">
                <p class="etiqueta">MEMBRESÍA ACTUAL</p>
                <h2><?php echo escapar($cliente["nombre_plan"] ?? "Sin membresía"); ?></h2>
                <p>Estado: <strong><?php echo escapar(ucfirst($estado)); ?></strong></p>
            </article>
            <article class="tarjeta">
                <p class="etiqueta">VIGENCIA</p>
                <h2><?php echo escapar($cliente["fecha_fin"] ?? "Pendiente de pago"); ?></h2>
                <p>Fecha de vencimiento</p>
            </article>
            <article class="tarjeta">
                <p class="etiqueta">CLIENTE DESDE</p>
                <h2><?php echo escapar($cliente["fecha_registro"]); ?></h2>
                <p><?php echo escapar($cliente["correo"]); ?></p>
            </article>
        </section>

        <section class="seccion">
            <div class="encabezado-seccion">
                <div><p class="etiqueta">RENOVACIÓN</p><h2>Elige o renueva tu membresía</h2></div>
            </div>
            <div class="planes-pago">
                <?php while ($plan = mysqli_fetch_assoc($consultaPlanes)) { ?>
                    <article class="plan-pago">
                        <p class="etiqueta"><?php echo escapar($plan["duracion_meses"] == 1 ? "MENSUAL" : "TRIMESTRAL"); ?></p>
                        <h2><?php echo escapar($plan["nombre"]); ?></h2>
                        <p class="precio">$<?php echo number_format((float)$plan["precio"]); ?> <span>MXN</span></p>
                        <p>Acceso al gimnasio durante <?php echo escapar($plan["duracion_meses"]); ?> mes(es).</p>
                        <form action="iniciar-pago.php" method="post">
                            <input type="hidden" name="id_plan" value="<?php echo escapar($plan["id_plan"]); ?>">
                            <button class="boton" type="submit">Seleccionar plan</button>
                        </form>
                    </article>
                <?php } ?>
            </div>
            <p class="nota">La rutina y las citas las agregaremos en una etapa posterior.</p>
        </section>
    </main>
    <?php include "footer.php"; ?>
</body>
</html>
