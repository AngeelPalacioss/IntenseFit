<?php
require_once "sesion.php";
protegerPagina("cliente");
require_once "conexion.php";
$nombrePanel = "Membresías";
$paginaActiva = "planes";
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
    <title>IntenseFit | Planes de membresía</title>
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
                <p class="etiqueta">MEMBRESÍAS</p>
                <h1>Elige tu plan</h1>
                <p>Selecciona una duración para iniciar el pago.</p>
            </div>
        </div>
        <section class="planes-pago">
            <?php while ($plan = mysqli_fetch_assoc($consultaPlanes)) { ?>
                <article class="plan-pago">
                    <p class="etiqueta"><?php echo escapar($plan["duracion_meses"] == 1 ? "MENSUAL" : "TRIMESTRAL"); ?></p>
                    <h2><?php echo escapar($plan["nombre"]); ?></h2>
                    <p class="precio">$<?php echo number_format((float)$plan["precio"]); ?> <span>MXN</span></p>
                    <ul>
                        <li>Acceso al gimnasio</li>
                        <li>Vigencia de <?php echo escapar($plan["duracion_meses"]); ?> mes(es)</li>
                    </ul>
                    <form action="iniciar-pago.php" method="post">
                        <input type="hidden" name="id_plan" value="<?php echo escapar($plan["id_plan"]); ?>">
                        <button class="boton" type="submit">Continuar</button>
                    </form>
                </article>
            <?php } ?>
        </section>
    </main>
    <?php include "footer.php"; ?>
</body>
</html>
