<?php
// ─── Diagnóstico de errores (quitar en producción estable) ───
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
    $db->query("ALTER TABLE modulo_mysql MODIFY COLUMN estado ENUM('Pendiente','Activo','Para_Borrar','Para_Modificar') DEFAULT 'Pendiente'");
    $user_id = (int)$_SESSION['user_id'];

    // 1. BLOQUEO POR PROCESO DE BAJA / SOLICITUD DE BORRADO ACTIVA
    // Consultamos el estado del servicio del usuario en la tabla usuarios
    $user_sql = "SELECT estado_servicio FROM usuarios WHERE id = ?";
    $stmt_user = $db->prepare($user_sql);
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $res_user = $stmt_user->get_result();
    $user_data = $res_user->fetch_assoc();
    $stmt_user->close();

    if (!$user_data || $user_data['estado_servicio'] === 'Para_Borrar') {
        header('Location: panel.php?error=servicio_baja');
        exit;
    }

    // 2. COMPROBACIÓN DE EXISTENCIA DE BASE DE DATOS
    $check_sql = "SELECT id, db_name, db_user FROM modulo_mysql WHERE user_id = ?";
    $stmt_check = $db->prepare($check_sql);
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    $db_existente = $res_check->fetch_assoc();
    $stmt_check->close();

    $raw_pass = trim($_POST['db_pass'] ?? '');

    if ($db_existente) {
        // --- CASO ACTUALIZACIÓN (Solo contraseña permitida) ---
        $prefijo = "u" . $user_id . "_";
        
        $raw_name_post = strtolower(trim($_POST['db_name'] ?? ''));
        if (strpos($raw_name_post, $prefijo) === 0) {
            $submitted_name = $prefijo . preg_replace('/[^a-z0-9_]/', '', substr($raw_name_post, strlen($prefijo)));
        } else {
            $submitted_name = !empty($raw_name_post) ? ($prefijo . preg_replace('/[^a-z0-9_]/', '', $raw_name_post)) : '';
        }

        $raw_user_post = strtolower(trim($_POST['db_user'] ?? ''));
        if (strpos($raw_user_post, $prefijo) === 0) {
            $submitted_user = $prefijo . preg_replace('/[^a-z0-9_]/', '', substr($raw_user_post, strlen($prefijo)));
        } else {
            $submitted_user = !empty($raw_user_post) ? ($prefijo . preg_replace('/[^a-z0-9_]/', '', $raw_user_post)) : '';
        }

        if ((!empty($submitted_name) && $submitted_name !== $db_existente['db_name']) ||
            (!empty($submitted_user) && $submitted_user !== $db_existente['db_user'])) {
            header('Location: panel.php?error=edicion_capada');
            exit;
        }

        if (empty($raw_pass)) {
            // Si ya tiene DB y no ingresa nueva contraseña, no hacemos cambios
            header('Location: panel.php?status=sin_cambios');
            exit;
        }

        // Actualizamos única y exclusivamente db_pass y colocamos el estado en 'Para_Modificar'
        $sql = "UPDATE modulo_mysql SET db_pass = ?, estado = 'Para_Modificar' WHERE user_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("si", $raw_pass, $user_id);
        $es_creacion = false;
    } else {
        // --- CASO CREACIÓN (Primer registro) ---
        // Para creación nueva, sí son obligatorios el nombre de base de datos y usuario
        $raw_name = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['db_name'] ?? ''));
        $raw_user = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['db_user'] ?? ''));

        if (empty($raw_name) || empty($raw_user)) {
            header('Location: panel.php?error=mysql_vacio');
            exit;
        }

        // Tarea 6: Sin prefijo automático, solo añadir sufijo si ya existe
        $db_name_s = $raw_name;
        $db_user_s = $raw_user;

        $check_db = $db->query("SELECT id FROM modulo_mysql WHERE db_name = '" . $db->real_escape_string($raw_name) . "'");
        if ($check_db && $check_db->num_rows > 0) {
            $db_name_s = $raw_name . "_u" . $user_id;
        }

        $check_user = $db->query("SELECT id FROM modulo_mysql WHERE db_user = '" . $db->real_escape_string($raw_user) . "'");
        if ($check_user && $check_user->num_rows > 0) {
            $db_user_s = $raw_user . "_u" . $user_id;
        }

        $pass_crear = empty($raw_pass) ? null : $raw_pass;

        $sql = "INSERT INTO modulo_mysql (user_id, db_name, db_user, db_pass, estado) VALUES (?, ?, ?, ?, 'Pendiente')";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("isss", $user_id, $db_name_s, $db_user_s, $pass_crear);
        $es_creacion = true;
    }

    if ($stmt->execute()) {
        $stmt->close();

        // Obtener nombre del usuario para alertas/logs
        $owner_q = $db->query("SELECT nombre FROM usuarios WHERE id = $user_id");
        $owner_data = $owner_q->fetch_assoc();
        $u_nom = $db->real_escape_string($owner_data['nombre'] ?? 'Usuario');

        // Registrar logs de acciones
        $log_dir = "/opt/tfg/scripts/logs";
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        $log_file = "$log_dir/acciones.log";
        $date_str = date('Y-m-d H:i:s');

        if ($es_creacion) {
            $log_msg = "[$date_str] [MYSQL_CREAR] Usuario '$u_nom' (ID: $user_id) activó MySQL. DB: $db_name_s, User: $db_user_s\n";
            @file_put_contents($log_file, $log_msg, FILE_APPEND);

            $motivo = "Usuario '$u_nom' activó base de datos MySQL (DB: $db_name_s, User: $db_user_s)";
            $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($user_id, '$u_nom', '$motivo', '🗄️', 0, NOW())");
        } else {
            $log_msg = "[$date_str] [MYSQL_EDITAR] Usuario '$u_nom' (ID: $user_id) actualizó su contraseña de MySQL\n";
            @file_put_contents($log_file, $log_msg, FILE_APPEND);

            $motivo = "Usuario '$u_nom' modificó la contraseña de su base de datos MySQL";
            $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($user_id, '$u_nom', '$motivo', '🗄️', 0, NOW())");
        }

        // Ejecutar inmediatamente el worker con ruta absoluta
        shell_exec("sudo python3 /opt/tfg/scripts/mysql_worker.py > /dev/null 2>&1");
        header('Location: panel.php?status=provisioning');
    } else {
        $log_dir = "/opt/tfg/scripts/logs";
        $log_file = "$log_dir/acciones.log";
        $date_str = date('Y-m-d H:i:s');
        $log_msg = "[$date_str] [MYSQL_ERROR] Error SQL al procesar MySQL para usuario ID $user_id: " . $stmt->error . "\n";
        @file_put_contents($log_file, $log_msg, FILE_APPEND);

        error_log("[procesar_mysql] Error SQL execute: " . $stmt->error . " | user_id=$user_id");
        if (isset($stmt)) $stmt->close();
        header('Location: panel.php?error=db_error');
    }

} catch (Exception $e) {
    error_log("[procesar_mysql] Excepción inesperada: " . $e->getMessage());
    header('Location: panel.php?error=db_excepcion');
} finally {
    if (isset($db) && $db instanceof mysqli) {
        $db->close();
    }
}
exit;
?>