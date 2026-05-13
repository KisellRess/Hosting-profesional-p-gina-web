<?php
// ─── Diagnóstico de errores ───
ini_set('display_errors', 0);
ini_set('log_errors', 1);
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
    $accion  = $_POST['accion'] ?? '';

    // Prefijo del dueño para nombres de usuario seguros (ej: u1_ventas)
    $owner_prefix = "u" . $user_id . "_";

    switch ($accion) {
        case 'crear_staff':
            // Sanitización: Convertir espacios en guiones bajos y eliminar caracteres no alfanuméricos
            $raw_name       = str_replace(' ', '_', strtolower($_POST['nombre_staff'] ?? ''));
            $nombre_elegido = preg_replace('/[^a-z0-9_]/', '', $raw_name);
            $password       = $_POST['pass_staff'] ?? '';

            if (strlen($nombre_elegido) < 3 || strlen($password) < 6) {
                header('Location: panel.php?error=datos_invalidos');
                exit;
            }

            $ftp_user_final = $db->real_escape_string($owner_prefix . $nombre_elegido);
            $ftp_pass_db    = $db->real_escape_string($password);

            // 1. Verificar si el nombre de usuario ya existe en la tabla de extras
            $check = $db->query("SELECT id FROM ftp_cuentas_extra WHERE ftp_user = '$ftp_user_final'");
            if ($check && $check->num_rows > 0) {
                header('Location: panel.php?error=usuario_existente');
                exit;
            }

            // 2. Obtener el nombre de usuario del dueño para poblar owner_ftp
            $owner_q = $db->query("SELECT ftp_user FROM usuarios WHERE id = $user_id");
            $owner_data = $owner_q->fetch_assoc();
            $owner_ftp = $db->real_escape_string($owner_data['ftp_user'] ?? '');

            if (empty($owner_ftp)) {
                header('Location: panel.php?error=owner_ftp_missing');
                exit;
            }

            // 3. Insertar en cola de trámites con owner_ftp relleno
            $sql = "INSERT INTO ftp_cuentas_extra (user_id, ftp_user, ftp_pass, owner_ftp, estado) 
                    VALUES ($user_id, '$ftp_user_final', '$ftp_pass_db', '$owner_ftp', 'Tramitando')";

            if ($db->query($sql)) {
                header('Location: panel.php?ok=staff_pendiente');
            } else {
                error_log("[procesar_staff] Error SQL INSERT: " . $db->error . " | user_id=$user_id");
                header('Location: panel.php?error=db_error');
            }
            break;

        case 'borrar_staff':
            $staff_id = (int)($_POST['staff_id'] ?? 0);
            if ($staff_id <= 0) {
                header('Location: panel.php?error=staff_id_invalido');
                exit;
            }

            // Solo puede borrar si le pertenece
            $db->query("DELETE FROM ftp_cuentas_extra WHERE id = $staff_id AND user_id = $user_id");
            // El worker de Python verá que el registro desaparece y podrá eliminar el usuario del SO
            header('Location: panel.php?ok=staff_eliminado');
            break;

        default:
            header('Location: panel.php');
            break;
    }

} catch (Exception $e) {
    error_log("[procesar_staff] Excepción inesperada: " . $e->getMessage());
    header('Location: panel.php?error=staff_excepcion');
} finally {
    if (isset($db) && $db instanceof mysqli) {
        $db->close();
    }
}
exit;
?>