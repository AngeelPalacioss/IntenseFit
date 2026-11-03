<?php
$dbhost = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "intensefit";
$port = 3306;

$conexion = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname, $port);

if (!$conexion) {
    die("No se pudo conectar con la base de datos IntenseFit.");
}

mysqli_set_charset($conexion, "utf8mb4");
?>
