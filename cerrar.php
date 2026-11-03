<?php
require_once "sesion.php";
unset($_SESSION["id_usuario"]);
unset($_SESSION["id_cliente"]);
unset($_SESSION["nombre"]);
unset($_SESSION["correo"]);
unset($_SESSION["rol"]);
unset($_SESSION["cliente_actual"]);
session_regenerate_id(true);
header("Location: index.php");
exit;
?>
