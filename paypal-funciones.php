<?php

function paypalPedirToken()
{
    $curl = curl_init(PAYPAL_API_BASE . "/v1/oauth2/token");
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ":" . PAYPAL_CLIENT_SECRET,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => "grant_type=client_credentials",
        CURLOPT_HTTPHEADER => array("Accept: application/json", "Accept-Language: es_MX"),
        CURLOPT_TIMEOUT => 25
    ));
    $respuesta = curl_exec($curl);
    $codigo = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($respuesta === false || $codigo < 200 || $codigo >= 300) {
        throw new Exception("No se pudo conectar con PayPal Sandbox. " . $error);
    }
    $datos = json_decode($respuesta, true);
    if (empty($datos["access_token"])) {
        throw new Exception("PayPal no devolvió un token de acceso.");
    }
    return $datos["access_token"];
}

function paypalSolicitud($metodo, $ruta, $datos = null)
{
    $token = paypalPedirToken();
    $curl = curl_init(PAYPAL_API_BASE . $ruta);
    $encabezados = array(
        "Authorization: Bearer " . $token,
        "Content-Type: application/json",
        "Accept: application/json"
    );
    $opciones = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => $encabezados,
        CURLOPT_TIMEOUT => 25
    );
    if ($datos !== null) {
        $opciones[CURLOPT_POSTFIELDS] = json_encode($datos);
    }
    curl_setopt_array($curl, $opciones);
    $respuesta = curl_exec($curl);
    $codigo = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    $resultado = json_decode((string)$respuesta, true);
    if ($respuesta === false || $codigo < 200 || $codigo >= 300) {
        $mensaje = $resultado["message"] ?? $error;
        throw new Exception("PayPal rechazó la solicitud. " . $mensaje);
    }
    return $resultado ?: array();
}

?>
