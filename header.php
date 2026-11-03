<?php
require_once "sesion.php";

$nombrePanel = $nombrePanel ?? "IntenseFit";
$paginaActiva = $paginaActiva ?? "";
?>
<header class="encabezado">
    <div class="barra-superior contenedor">
        <a class="marca" href="<?php echo $_SESSION["rol"] == "admin" ? "administrador.php" : "cliente.php"; ?>">INTENSE<span>FIT</span></a>
        <p><?php echo escapar($nombrePanel); ?></p>
        <div class="usuario-menu">
            <span><?php echo escapar($_SESSION["nombre"]); ?></span>
            <a class="salir" href="cerrar.php">Cerrar sesión</a>
        </div>
    </div>
</header>
<nav class="navegacion">
    <div class="contenedor enlaces-nav">
        <?php if ($_SESSION["rol"] == "admin") { ?>
            <a class="<?php echo $paginaActiva == "admin" ? "activo" : ""; ?>" href="administrador.php">Clientes y membresías</a>
            <a class="<?php echo $paginaActiva == "registro" ? "activo" : ""; ?>" href="registro-cliente.php">Registrar cliente</a>
        <?php } else { ?>
            <a class="<?php echo $paginaActiva == "cliente" ? "activo" : ""; ?>" href="cliente.php">Mi cuenta</a>
            <a class="<?php echo $paginaActiva == "planes" ? "activo" : ""; ?>" href="membresias.php">Membresías</a>
        <?php } ?>
    </div>
</nav>
