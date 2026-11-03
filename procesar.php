<?php
require_once "sesion.php";
require_once "conexion.php";

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: index.php");
    exit;
}

$correo = strtolower(trim($_POST["correo"] ?? ""));
$contrasena = $_POST["contrasena"] ?? "";

$consulta = mysqli_prepare(
    $conexion,
    "SELECT u.id_usuario, u.correo, u.contrasena_hash, u.rol,
            c.id_cliente, c.nombres, c.apellidos
     FROM usuarios AS u
     LEFT JOIN clientes AS c ON c.id_usuario = u.id_usuario
     WHERE u.correo = ?"
);
mysqli_stmt_bind_param($consulta, "s", $correo);
mysqli_stmt_execute($consulta);
$resultado = mysqli_stmt_get_result($consulta);
$usuario = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($consulta);

if (!$usuario || !password_verify($contrasena, $usuario["contrasena_hash"])) {
    $_SESSION["mensaje"] = "El correo o la contraseña no coinciden.";
    header("Location: index.php");
    exit;
}

if ($usuario["rol"] == "cliente" && $usuario["id_cliente"] == null) {
    $_SESSION["mensaje"] = "La cuenta no tiene un perfil de cliente asignado.";
    header("Location: index.php");
    exit;
}

session_regenerate_id(true);
$_SESSION["id_usuario"] = $usuario["id_usuario"];
$_SESSION["nombre"] = $usuario["rol"] == "cliente"
    ? $usuario["nombres"] . " " . $usuario["apellidos"]
    : "Administración";
$_SESSION["correo"] = $usuario["correo"];
$_SESSION["rol"] = $usuario["rol"];

if ($usuario["rol"] == "cliente") {
    $_SESSION["id_cliente"] = $usuario["id_cliente"];
    header("Location: cliente.php");
} else {
    header("Location: administrador.php");
}
exit;
?>
