<?php
require_once 'sessions.php';
require_once 'conexiones.php';
require_auth();

$conexion = getConexion();
$email_session = $conexion->real_escape_string($_SESSION['email']);

// Actualizamos el estado y guardamos la fecha/hora actual
// NOTA: Asegúrate de haber añadido la columna fecha_cancelacion DATETIME a tu tabla usuarios
if ($conexion->query($query)) {
    // --- ALERTA ADMIN: Solicitud borrado cuenta ---
    $u_id = (int)($_SESSION['user_id'] ?? 0);
    if ($u_id > 0) {
        $check_alert = $conexion->query("SELECT id FROM alertas_admin WHERE user_id = $u_id AND motivo = 'Solicitud borrado cuenta' AND reconocida = 0 LIMIT 1");
        if ($check_alert->num_rows === 0) {
            $u_nom = $conexion->real_escape_string($_SESSION['usuario'] ?? 'Usuario');
            $conexion->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($u_id, '$u_nom', 'Solicitud borrado cuenta', '⚠️', 0, NOW())");
        }
    }
    // Actualizamos la sesión para que el panel se refresque con los nuevos datos
    $_SESSION['estado_servicio'] = 'Cancelado';
    header("Location: panel.php?msg=cancelacion_pendiente");
} else {
    header("Location: panel.php?error=pago_error");
}
$conexion->close();
exit;
