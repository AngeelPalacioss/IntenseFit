<?php
require_once "sesion.php";
protegerPagina("admin");
require_once "conexion.php";
$nombrePanel = "Registrar cliente";
$paginaActiva = "registro";
$planes = [];
$consultaPlanes = mysqli_query($conexion, "SELECT id_plan, nombre, precio FROM planes_membresia WHERE activo = 1 ORDER BY duracion_meses");
while ($plan = mysqli_fetch_assoc($consultaPlanes)) {
    $planes[] = $plan;
}
$mensaje = $_SESSION["mensaje"] ?? "";
unset($_SESSION["mensaje"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IntenseFit | Registrar cliente</title>
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
                <p class="etiqueta">NUEVA CUENTA</p>
                <h1>Registrar cliente</h1>
                <p>Captura sus datos y crea el acceso al portal.</p>
            </div>
        </div>
        <?php if ($mensaje != "") { ?>
            <p class="mensaje-error"><?php echo escapar($mensaje); ?></p>
        <?php } ?>
        <form class="formulario-registro" action="guardar-cliente.php" method="post">
            <fieldset>
                <legend>Datos personales</legend>
                <div class="campos-registro">
                    <div class="campo-registro">
                        <label for="nombre">Nombre(s)</label>
                        <input type="text" id="nombre" name="nombre" autocomplete="given-name" required>
                    </div>
                    <div class="campo-registro">
                        <label for="apellidos">Apellidos</label>
                        <input type="text" id="apellidos" name="apellidos" autocomplete="family-name" required>
                    </div>
                    <div class="campo-registro">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" pattern="[0-9]{10}" title="Escribe 10 dígitos" required>
                    </div>
                    <div class="campo-registro">
                        <label for="nacimiento">Fecha de nacimiento</label>
                        <input type="date" id="nacimiento" name="nacimiento" required>
                    </div>
                </div>
            </fieldset>
            <fieldset>
                <legend>Datos para iniciar sesión</legend>
                <div class="campos-registro">
                    <div class="campo-registro campo-completo">
                        <label for="correo">Correo electrónico</label>
                        <input type="email" id="correo" name="correo" autocomplete="email" required>
                    </div>
                    <div class="campo-registro">
                        <label for="contrasena">Contraseña inicial</label>
                        <input type="password" id="contrasena" name="contrasena" autocomplete="new-password" minlength="8" required>
                    </div>
                    <div class="campo-registro">
                        <label for="plan">Membresía inicial</label>
                        <select id="plan" name="plan" required>
                            <option value="">Selecciona un plan</option>
                            <?php foreach ($planes as $plan) { ?>
                                <option value="<?php echo escapar($plan["id_plan"]); ?>">
                                    <?php echo escapar($plan["nombre"]); ?> · $<?php echo number_format($plan["precio"]); ?> MXN
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </fieldset>
            <div class="acciones-formulario">
                <a class="boton boton-secundario" href="administrador.php">Cancelar</a>
                <button class="boton" type="submit">Guardar cliente</button>
            </div>
        </form>
        <p class="nota">La cuenta y su primera membresía se guardarán en la base de datos. La membresía quedará pendiente hasta que se confirme el pago.</p>
    </main>
    <?php include "footer.php"; ?>
</body>
</html>
