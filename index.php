<?php
require_once "sesion.php";
require_once "conexion.php";

if (isset($_SESSION["rol"])) {
    if ($_SESSION["rol"] == "admin") {
        header("Location: administrador.php");
    } else {
        header("Location: cliente.php");
    }
    exit;
}

$mensaje = $_SESSION["mensaje"] ?? "";
unset($_SESSION["mensaje"]);
$consultaAdmin = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'admin'");
$administradorExiste = false;
if ($consultaAdmin) {
    $resultadoAdmin = mysqli_fetch_assoc($consultaAdmin);
    $administradorExiste = $resultadoAdmin["total"] > 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IntenseFit | Iniciar sesión</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Krub:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="pagina-login">
    <main class="login contenedor">
        <section class="login-info">
            <p class="marca">INTENSE<span>FIT</span></p>
            <h1>Tu espacio para entrenar mejor.</h1>
            <p>Consulta tu membresía y mantén al día tu cuenta de IntenseFit.</p>
        </section>
        <section class="login-formulario">
            <h2>Iniciar sesión</h2>
            <p class="texto-secundario">Ingresa con tu cuenta</p>
            <?php if ($mensaje != "") { ?>
                <p class="mensaje-error"><?php echo escapar($mensaje); ?></p>
            <?php } ?>
            <form action="procesar.php" method="post">
                <div class="campo">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" autocomplete="username" required>
                </div>
                <div class="campo">
                    <label for="contrasena">Contraseña</label>
                    <input type="password" id="contrasena" name="contrasena" autocomplete="current-password" required>
                </div>
                <button class="boton" type="submit">Entrar</button>
            </form>
            <?php if (!$administradorExiste) { ?>
                <div class="configuracion-inicial">
                    <h3>Primera configuración</h3>
                    <p>Crea la cuenta administradora para empezar a registrar clientes.</p>
                    <a class="enlace-rojo" href="crear-admin.php">Crear cuenta administradora</a>
                </div>
            <?php } ?>
        </section>
    </main>
</body>
</html>
