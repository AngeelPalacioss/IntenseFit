<?php
require_once "sesion.php";
require_once "conexion.php";

$mensaje = "";
$administradorExiste = false;
$consultaAdmin = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'admin'");
if ($consultaAdmin) {
    $resultadoAdmin = mysqli_fetch_assoc($consultaAdmin);
    $administradorExiste = $resultadoAdmin["total"] > 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !$administradorExiste) {
    $correo = strtolower(trim($_POST["correo"] ?? ""));
    $contrasena = $_POST["contrasena"] ?? "";
    $confirmar = $_POST["confirmar"] ?? "";

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Escribe un correo válido.";
    } elseif (strlen($contrasena) < 8) {
        $mensaje = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($contrasena != $confirmar) {
        $mensaje = "Las contraseñas no coinciden.";
    } else {
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $consulta = mysqli_prepare($conexion, "INSERT INTO usuarios (correo, contrasena_hash, rol) VALUES (?, ?, 'admin')");
        mysqli_stmt_bind_param($consulta, "ss", $correo, $hash);

        if (mysqli_stmt_execute($consulta)) {
            $_SESSION["mensaje"] = "La cuenta administradora quedó creada. Ya puedes iniciar sesión.";
            mysqli_stmt_close($consulta);
            header("Location: index.php");
            exit;
        } else {
            $mensaje = "No se pudo crear la cuenta. Revisa si ese correo ya está registrado.";
        }
        mysqli_stmt_close($consulta);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IntenseFit | Crear administrador</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Krub:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="pagina-login">
    <main class="login contenedor">
        <section class="login-info">
            <p class="marca">INTENSE<span>FIT</span></p>
            <h1>Configurar administración</h1>
            <p>Crea la primera cuenta para gestionar clientes y membresías.</p>
        </section>
        <section class="login-formulario">
            <h2>Cuenta administradora</h2>
            <?php if ($administradorExiste) { ?>
                <p class="mensaje-error">Ya existe una cuenta administradora. El acceso inicial está cerrado.</p>
                <a class="boton" href="index.php">Ir al inicio de sesión</a>
            <?php } else { ?>
                <?php if ($mensaje != "") { ?><p class="mensaje-error"><?php echo escapar($mensaje); ?></p><?php } ?>
                <form action="crear-admin.php" method="post">
                    <div class="campo">
                        <label for="correo">Correo electrónico</label>
                        <input type="email" id="correo" name="correo" required>
                    </div>
                    <div class="campo">
                        <label for="contrasena">Contraseña</label>
                        <input type="password" id="contrasena" name="contrasena" minlength="8" required>
                    </div>
                    <div class="campo">
                        <label for="confirmar">Confirmar contraseña</label>
                        <input type="password" id="confirmar" name="confirmar" minlength="8" required>
                    </div>
                    <button class="boton" type="submit">Crear cuenta</button>
                </form>
            <?php } ?>
        </section>
    </main>
</body>
</html>
