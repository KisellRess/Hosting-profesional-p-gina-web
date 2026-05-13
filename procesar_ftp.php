<?php
// ─── Diagnóstico de errores (quitar en producción estable) ───
ini_set('display_errors', 0);        // No mostrar errores al usuario final
ini_set('log_errors', 1);            // Sí guardarlos en el log de Apache/PHP
error_reporting(E_ALL);

require_once 'sessions.php';
require_auth();
require_once 'conexiones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel.php');
    exit;
}

try {
    $db = getConexion();
    $user_id = (int)$_SESSION['user_id'];

    // Sanitización básica
    // Sanitización: Convertir espacios en guiones bajos y eliminar caracteres no alfanuméricos
    $raw_ftp_user = str_replace(' ', '_', strtolower(trim($_POST['ftp_user'] ?? '')));
    $ftp_user_s   = $db->real_escape_string(preg_replace('/[^a-z0-9_]/', '', $raw_ftp_user));
    $ftp_pass_s   = $db->real_escape_string(trim($_POST['ftp_pass'] ?? ''));

    // Validar que el usuario no esté vacío
    if (empty($ftp_user_s)) {
        header('Location: panel.php?error=ftp_vacio');
        exit;
    }

    // Construir la consulta SQL dinámica
    if (empty($ftp_pass_s)) {
        // Solo actualizar el nombre de usuario
        $sql = "UPDATE usuarios SET ftp_user = '$ftp_user_s' WHERE id = $user_id";
    } else {
        // Actualizar ambos campos
        $sql = "UPDATE usuarios SET ftp_user = '$ftp_user_s', ftp_pass = '$ftp_pass_s' WHERE id = $user_id";
    }

    if ($db->query($sql)) {
        header('Location: panel.php?ok=ftp_pendiente');
    } else {
        error_log("[procesar_ftp] Error SQL UPDATE: " . $db->error . " | user_id=$user_id");
        header('Location: panel.php?error=ftp_error');
    }

} catch (Exception $e) {
    error_log("[procesar_ftp] Excepción inesperada: " . $e->getMessage());
    header('Location: panel.php?error=ftp_excepcion');
} finally {
    if (isset($db) && $db instanceof mysqli) {
        $db->close();
    }
}
exit;
?>