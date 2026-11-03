<?php
require_once "sesion.php";
protegerPagina("admin");
require_once "conexion.php";

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: registro-cliente.php");
    exit;
}

$nombre = trim($_POST["nombre"] ?? "");
$apellidos = trim($_POST["apellidos"] ?? "");
$telefono = trim($_POST["telefono"] ?? "");
$nacimiento = trim($_POST["nacimiento"] ?? "");
$correo = strtolower(trim($_POST["correo"] ?? ""));
$contrasena = $_POST["contrasena"] ?? "";
$idPlan = (int)($_POST["plan"] ?? 0);

if ($nombre == "" || $apellidos == "" || $nacimiento == "" || !filter_var($correo, FILTER_VALIDATE_EMAIL)
    || !preg_match("/^[0-9]{10}$/", $telefono) || strlen($contrasena) < 8
    || $idPlan <= 0) {
    $_SESSION["mensaje"] = "Revisa los datos del formulario e inténtalo de nuevo.";
    header("Location: registro-cliente.php");
    exit;
}

$consultaCorreo = mysqli_prepare($conexion, "SELECT id_usuario FROM usuarios WHERE correo = ?");
mysqli_stmt_bind_param($consultaCorreo, "s", $correo);
mysqli_stmt_execute($consultaCorreo);
$resultadoCorreo = mysqli_stmt_get_result($consultaCorreo);
$correoExiste = mysqli_num_rows($resultadoCorreo) > 0;
mysqli_stmt_close($consultaCorreo);

if ($correoExiste) {
    $_SESSION["mensaje"] = "Ya existe una cuenta con ese correo.";
    header("Location: registro-cliente.php");
    exit;
}

$consultaPlan = mysqli_prepare($conexion, "SELECT id_plan FROM planes_membresia WHERE id_plan = ? AND activo = 1");
mysqli_stmt_bind_param($consultaPlan, "i", $idPlan);
mysqli_stmt_execute($consultaPlan);
$planExiste = mysqli_stmt_get_result($consultaPlan);
$hayPlan = mysqli_num_rows($planExiste) > 0;
mysqli_stmt_close($consultaPlan);

if (!$hayPlan) {
    $_SESSION["mensaje"] = "El plan seleccionado no está disponible.";
    header("Location: registro-cliente.php");
    exit;
}

$hash = password_hash($contrasena, PASSWORD_DEFAULT);
mysqli_begin_transaction($conexion);

$insertarUsuario = mysqli_prepare($conexion, "INSERT INTO usuarios (correo, contrasena_hash, rol) VALUES (?, ?, 'cliente')");
mysqli_stmt_bind_param($insertarUsuario, "ss", $correo, $hash);
$usuarioGuardado = mysqli_stmt_execute($insertarUsuario);
$idUsuario = mysqli_insert_id($conexion);
mysqli_stmt_close($insertarUsuario);

$insertarCliente = false;
$idCliente = 0;
if ($usuarioGuardado) {
    $fechaRegistro = date("Y-m-d");
    $insertarPerfil = mysqli_prepare(
        $conexion,
        "INSERT INTO clientes (id_usuario, nombres, apellidos, telefono, fecha_nacimiento, fecha_registro)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($insertarPerfil, "isssss", $idUsuario, $nombre, $apellidos, $telefono, $nacimiento, $fechaRegistro);
    $insertarCliente = mysqli_stmt_execute($insertarPerfil);
    $idCliente = mysqli_insert_id($conexion);
    mysqli_stmt_close($insertarPerfil);
}

$insertarMembresia = false;
if ($insertarCliente) {
    $insertarPlan = mysqli_prepare(
        $conexion,
        "INSERT INTO membresias (id_cliente, id_plan, estado) VALUES (?, ?, 'pendiente')"
    );
    mysqli_stmt_bind_param($insertarPlan, "ii", $idCliente, $idPlan);
    $insertarMembresia = mysqli_stmt_execute($insertarPlan);
    mysqli_stmt_close($insertarPlan);
}

if ($usuarioGuardado && $insertarCliente && $insertarMembresia) {
    mysqli_commit($conexion);
    $_SESSION["mensaje"] = "Cliente guardado en la base de datos. Su membresía está pendiente de pago.";
} else {
    mysqli_rollback($conexion);
    $_SESSION["mensaje"] = "No se pudo guardar el cliente. Revisa los datos e inténtalo de nuevo.";
}

header("Location: administrador.php");
exit;
?>
