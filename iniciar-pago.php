<?php
require_once "sesion.php";
protegerPagina("cliente");
require_once "conexion.php";

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: membresias.php");
    exit;
}

$idPlan = (int)($_POST["id_plan"] ?? 0);
$idCliente = (int)$_SESSION["id_cliente"];
$consultaPlan = mysqli_prepare(
    $conexion,
    "SELECT precio FROM planes_membresia WHERE id_plan = ? AND activo = 1"
);
mysqli_stmt_bind_param($consultaPlan, "i", $idPlan);
mysqli_stmt_execute($consultaPlan);
$resultadoPlan = mysqli_stmt_get_result($consultaPlan);
$plan = mysqli_fetch_assoc($resultadoPlan);
mysqli_stmt_close($consultaPlan);

if (!$plan) {
    $_SESSION["mensaje"] = "El plan seleccionado no está disponible.";
    header("Location: membresias.php");
    exit;
}

mysqli_begin_transaction($conexion);
$buscarMembresia = mysqli_prepare(
    $conexion,
    "SELECT id_membresia FROM membresias
     WHERE id_cliente = ? AND id_plan = ? AND estado = 'pendiente'
     ORDER BY id_membresia DESC LIMIT 1 FOR UPDATE"
);
mysqli_stmt_bind_param($buscarMembresia, "ii", $idCliente, $idPlan);
mysqli_stmt_execute($buscarMembresia);
$resultadoMembresia = mysqli_stmt_get_result($buscarMembresia);
$membresiaExistente = mysqli_fetch_assoc($resultadoMembresia);
mysqli_stmt_close($buscarMembresia);

$idMembresia = 0;
$membresiaGuardada = true;
if ($membresiaExistente) {
    $idMembresia = (int)$membresiaExistente["id_membresia"];
    $buscarPago = mysqli_prepare(
        $conexion,
        "SELECT id_pago FROM pagos WHERE id_membresia = ? AND estado = 'pendiente'
         ORDER BY id_pago DESC LIMIT 1"
    );
    mysqli_stmt_bind_param($buscarPago, "i", $idMembresia);
    mysqli_stmt_execute($buscarPago);
    $resultadoPagoPendiente = mysqli_stmt_get_result($buscarPago);
    $pagoExistente = mysqli_fetch_assoc($resultadoPagoPendiente);
    mysqli_stmt_close($buscarPago);

    if ($pagoExistente) {
        mysqli_commit($conexion);
        header("Location: pago.php?id_pago=" . (int)$pagoExistente["id_pago"]);
        exit;
    }
} else {
    $consultaMembresia = mysqli_prepare(
        $conexion,
        "INSERT INTO membresias (id_cliente, id_plan, estado) VALUES (?, ?, 'pendiente')"
    );
    mysqli_stmt_bind_param($consultaMembresia, "ii", $idCliente, $idPlan);
    $membresiaGuardada = mysqli_stmt_execute($consultaMembresia);
    $idMembresia = mysqli_insert_id($conexion);
    mysqli_stmt_close($consultaMembresia);
}

$pagoGuardado = false;
$idPago = 0;
if ($membresiaGuardada) {
    $monto = (float)$plan["precio"];
    $consultaPago = mysqli_prepare(
        $conexion,
        "INSERT INTO pagos (id_membresia, monto, moneda, estado) VALUES (?, ?, 'MXN', 'pendiente')"
    );
    mysqli_stmt_bind_param($consultaPago, "id", $idMembresia, $monto);
    $pagoGuardado = mysqli_stmt_execute($consultaPago);
    $idPago = mysqli_insert_id($conexion);
    mysqli_stmt_close($consultaPago);
}

if ($membresiaGuardada && $pagoGuardado) {
    mysqli_commit($conexion);
    header("Location: pago.php?id_pago=" . $idPago);
} else {
    mysqli_rollback($conexion);
    $_SESSION["mensaje"] = "No se pudo iniciar el pago. Inténtalo de nuevo.";
    header("Location: cliente.php");
}
exit;
?>
