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
    $db->query("ALTER TABLE usuarios MODIFY COLUMN estado_servicio ENUM('Pendiente','Activo','Para_Borrar','Para_Modificar') DEFAULT 'Pendiente'");
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

    // 1. Obtener valores actuales para ver si realmente hay cambios
    $res_current = $db->query("SELECT ftp_user, ftp_pass FROM usuarios WHERE id = $user_id LIMIT 1");
    if ($res_current && $row_curr = $res_current->fetch_assoc()) {
        $curr_user = $row_curr['ftp_user'] ?? '';
        $curr_pass = $row_curr['ftp_pass'] ?? '';
    } else {
        header('Location: panel.php?error=usuario_no_encontrado');
        exit;
    }

    $name_changed = ($curr_user !== $ftp_user_s);
    $pass_changed = (!empty($ftp_pass_s) && $curr_pass !== $ftp_pass_s);

    if (!$name_changed && !$pass_changed) {
        // No hay cambios reales, redirigimos directamente sin lanzar el python
        header('Location: panel.php?ok=ftp_pendiente');
        exit;
    }

    // Construir la consulta SQL dinámica
    if (empty($ftp_pass_s)) {
        // Solo actualizar el nombre de usuario
        $sql = "UPDATE usuarios SET ftp_user = '$ftp_user_s', creado_en_so = 0, estado_servicio = 'Para_Modificar' WHERE id = $user_id";
    } else {
        // Actualizar ambos campos
        $sql = "UPDATE usuarios SET ftp_user = '$ftp_user_s', ftp_pass = '$ftp_pass_s', creado_en_so = 0, estado_servicio = 'Para_Modificar' WHERE id = $user_id";
    }

    if ($db->query($sql)) {
        // Registrar logs de acciones
        $log_dir = "/opt/tfg/scripts/logs";
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        $log_file = "$log_dir/acciones.log";
        $date_str = date('Y-m-d H:i:s');
        $log_msg = "[$date_str] [FTP_CAMBIO] Usuario (ID: $user_id) actualizó su FTP a: $ftp_user_s. Cambios: Nombre=" . ($name_changed ? 'SI' : 'NO') . ", Pass=" . ($pass_changed ? 'SI' : 'NO') . "\n";
        @file_put_contents($log_file, $log_msg, FILE_APPEND);

        // Alerta admin
        $u_nom = $db->real_escape_string($_SESSION['usuario'] ?? 'Usuario');
        $motivo = "Usuario '$u_nom' actualizó sus credenciales FTP (User: $ftp_user_s)";
        $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($user_id, '$u_nom', '$motivo', '⚙️', 0, NOW())");

        // Ejecutar inmediatamente el backend de usuarios para sincronizar las credenciales FTP y Linux al instante usando la ruta absoluta
        shell_exec("sudo python3 /opt/tfg/scripts/crear_usuarios.py > /dev/null 2>&1");

        header('Location: panel.php?status=provisioning');
    } else {
        $log_dir = "/opt/tfg/scripts/logs";
        $log_file = "$log_dir/acciones.log";
        $date_str = date('Y-m-d H:i:s');
        $log_msg = "[$date_str] [FTP_ERROR] Error SQL al actualizar FTP para usuario (ID: $user_id): " . $db->error . "\n";
        @file_put_contents($log_file, $log_msg, FILE_APPEND);

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