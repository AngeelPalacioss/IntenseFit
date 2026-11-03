<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function protegerPagina($rolNecesario)
{
    if (!isset($_SESSION["rol"])) {
        header("Location: index.php");
        exit;
    }

    if ($_SESSION["rol"] != $rolNecesario) {
        if ($_SESSION["rol"] == "admin") {
            header("Location: administrador.php");
        } else {
            header("Location: cliente.php");
        }
        exit;
    }
}

function escapar($dato)
{
    return htmlspecialchars((string)$dato, ENT_QUOTES, "UTF-8");
}

function responderJson($datos, $codigo = 200)
{
    http_response_code($codigo);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
