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
    
    // Asegurar compatibilidad de estados en la tabla ftp_cuentas_extra
    $db->query("ALTER TABLE ftp_cuentas_extra MODIFY COLUMN estado ENUM('Pendiente','Tramitando','Activo','Error','Para_Borrar','Para_Modificar') DEFAULT 'Pendiente'");
    
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

            // Tarea 6: Sin prefijo automático u[ID]_, solo añadir sufijo _u[ID] si ya existe
            $nombre_elegido_safe = $db->real_escape_string($nombre_elegido);
            $check_st = $db->query("SELECT id FROM ftp_cuentas_extra WHERE ftp_user = '$nombre_elegido_safe'");
            $check_usr = $db->query("SELECT id FROM usuarios WHERE ftp_user = '$nombre_elegido_safe'");
            
            if (($check_st && $check_st->num_rows > 0) || ($check_usr && $check_usr->num_rows > 0)) {
                $ftp_user_final = $nombre_elegido_safe . "_u" . $user_id;
            } else {
                $ftp_user_final = $nombre_elegido_safe;
            }

            $ftp_pass_db    = $db->real_escape_string($password);

            // 1. Verificar si el nombre de usuario ya existe en la tabla de extras (con la nueva validación de arriba, esto es un fallback de seguridad)
            $check = $db->query("SELECT id FROM ftp_cuentas_extra WHERE ftp_user = '$ftp_user_final'");
            if ($check && $check->num_rows > 0) {
                header('Location: panel.php?error=usuario_existente');
                exit;
            }

            // 2. Obtener el nombre de usuario del dueño para poblar owner_ftp
            $owner_q = $db->query("SELECT ftp_user, nombre FROM usuarios WHERE id = $user_id");
            $owner_data = $owner_q->fetch_assoc();
            $owner_ftp = $db->real_escape_string($owner_data['ftp_user'] ?? '');
            $u_nom = $db->real_escape_string($owner_data['nombre'] ?? 'Usuario');

            if (empty($owner_ftp)) {
                header('Location: panel.php?error=owner_ftp_missing');
                exit;
            }

            // 3. Insertar en cola de trámites con owner_ftp relleno
            $sql = "INSERT INTO ftp_cuentas_extra (user_id, ftp_user, ftp_pass, owner_ftp, estado) 
                    VALUES ($user_id, '$ftp_user_final', '$ftp_pass_db', '$owner_ftp', 'Pendiente')";

            if ($db->query($sql)) {
                // Registrar logs de acciones
                $log_dir = "/opt/tfg/scripts/logs";
                if (!is_dir($log_dir)) {
                    @mkdir($log_dir, 0755, true);
                }
                $log_file = "$log_dir/acciones.log";
                $date_str = date('Y-m-d H:i:s');
                $log_msg = "[$date_str] [STAFF_CREAR] Usuario '$u_nom' (ID: $user_id) solicitó la creación del staff '$ftp_user_final'\n";
                @file_put_contents($log_file, $log_msg, FILE_APPEND);

                // Alerta admin
                $motivo = "Usuario '$u_nom' solicitó crear cuenta Staff '$ftp_user_final'";
                $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($user_id, '$u_nom', '$motivo', '👥', 0, NOW())");

                shell_exec("sudo python3 /opt/tfg/scripts/crear_usuarios.py > /dev/null 2>&1");
                header('Location: panel.php?status=provisioning');
            } else {
                $log_dir = "/opt/tfg/scripts/logs";
                $log_file = "$log_dir/acciones.log";
                $date_str = date('Y-m-d H:i:s');
                $log_msg = "[$date_str] [STAFF_CREAR_ERROR] Error SQL al insertar staff '$ftp_user_final' para usuario ID $user_id: " . $db->error . "\n";
                @file_put_contents($log_file, $log_msg, FILE_APPEND);

                error_log("[procesar_staff] Error SQL INSERT: " . $db->error . " | user_id=$user_id");
                header('Location: panel.php?error=db_error');
            }
            break;

        case 'editar_staff':
            $staff_id = (int)($_POST['staff_id'] ?? 0);
            $raw_name       = str_replace(' ', '_', strtolower($_POST['nombre_staff'] ?? ''));
            $nombre_elegido = preg_replace('/[^a-z0-9_]/', '', $raw_name);
            $password       = $_POST['pass_staff'] ?? ''; // Nueva contraseña (puede estar vacía)

            if ($staff_id <= 0 || strlen($nombre_elegido) < 3) {
                header('Location: panel.php?error=datos_invalidos');
                exit;
            }

            // 1. Obtener datos actuales del staff para comparar
            $staff_q = $db->query("SELECT ftp_user, ftp_pass, owner_ftp FROM ftp_cuentas_extra WHERE id = $staff_id AND user_id = $user_id LIMIT 1");
            if (!$staff_q || $staff_q->num_rows === 0) {
                header('Location: panel.php?error=staff_no_encontrado');
                exit;
            }
            $current_staff = $staff_q->fetch_assoc();
            $curr_user = $current_staff['ftp_user'];
            $curr_pass = $current_staff['ftp_pass'];
            $owner_ftp = $current_staff['owner_ftp'];

            // Tarea 6: Sin prefijo automático u[ID]_, solo añadir sufijo _u[ID] si ya existe
            $nombre_elegido_safe = $db->real_escape_string($nombre_elegido);
            $check_st = $db->query("SELECT id FROM ftp_cuentas_extra WHERE ftp_user = '$nombre_elegido_safe' AND id != $staff_id");
            $check_usr = $db->query("SELECT id FROM usuarios WHERE ftp_user = '$nombre_elegido_safe'");
            
            if (($check_st && $check_st->num_rows > 0) || ($check_usr && $check_usr->num_rows > 0)) {
                $ftp_user_final = $nombre_elegido_safe . "_u" . $user_id;
            } else {
                $ftp_user_final = $nombre_elegido_safe;
            }

            // Determinar qué ha cambiado
            $name_changed = ($curr_user !== $ftp_user_final);
            $pass_changed = (!empty($password) && $curr_pass !== $password);

            if (!$name_changed && !$pass_changed) {
                // No hay cambios reales, redirigimos a panel
                header('Location: panel.php?ok=ftp_pendiente');
                exit;
            }

            // Obtener nombre del dueño para alertas
            $owner_q = $db->query("SELECT nombre FROM usuarios WHERE id = $user_id");
            $owner_data = $owner_q->fetch_assoc();
            $u_nom = $db->real_escape_string($owner_data['nombre'] ?? 'Usuario');

            $success = false;

            if ($name_changed) {
                // Si el nombre cambia, validamos que el nuevo no esté duplicado
                $check = $db->query("SELECT id FROM ftp_cuentas_extra WHERE ftp_user = '$ftp_user_final' AND id != $staff_id");
                if ($check && $check->num_rows > 0) {
                    header('Location: panel.php?error=usuario_existente');
                    exit;
                }

                // La contraseña para el nuevo staff (la nueva si se envió, o la actual si no)
                $final_pass = $pass_changed ? $password : $curr_pass;

                // A. Marcar el staff actual como 'Para_Borrar' para que Python lo limpie de Linux
                $db->query("UPDATE ftp_cuentas_extra SET estado = 'Para_Borrar' WHERE id = $staff_id");

                // B. Insertar un nuevo staff como 'Pendiente' con el nuevo nombre y contraseña
                $ftp_user_final_db = $db->real_escape_string($ftp_user_final);
                $final_pass_db = $db->real_escape_string($final_pass);
                
                $success = $db->query("INSERT INTO ftp_cuentas_extra (user_id, ftp_user, ftp_pass, owner_ftp, estado) 
                            VALUES ($user_id, '$ftp_user_final_db', '$final_pass_db', '$owner_ftp', 'Pendiente')");
            } else {
                // Solo ha cambiado la contraseña, no el nombre
                $final_pass_db = $db->real_escape_string($password);
                $success = $db->query("UPDATE ftp_cuentas_extra SET ftp_pass = '$final_pass_db', estado = 'Para_Modificar' WHERE id = $staff_id");
            }

            if ($success) {
                // Registrar logs de acciones
                $log_dir = "/opt/tfg/scripts/logs";
                if (!is_dir($log_dir)) {
                    @mkdir($log_dir, 0755, true);
                }
                $log_file = "$log_dir/acciones.log";
                $date_str = date('Y-m-d H:i:s');
                $log_msg = "[$date_str] [STAFF_EDITAR] Usuario '$u_nom' (ID: $user_id) editó el staff '$curr_user' (nuevo nombre: '$ftp_user_final'). Cambios: Nombre=" . ($name_changed ? 'SI' : 'NO') . ", Pass=" . ($pass_changed ? 'SI' : 'NO') . "\n";
                @file_put_contents($log_file, $log_msg, FILE_APPEND);

                // Alerta admin
                $motivo = "Usuario '$u_nom' editó cuenta Staff '$curr_user' (nuevo: '$ftp_user_final')";
                $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($user_id, '$u_nom', '$motivo', '👥', 0, NOW())");

                // Ejecutar inmediatamente el backend de usuarios usando la ruta absoluta
                shell_exec("sudo python3 /opt/tfg/scripts/crear_usuarios.py > /dev/null 2>&1");
                header('Location: panel.php?status=provisioning');
            } else {
                $log_dir = "/opt/tfg/scripts/logs";
                $log_file = "$log_dir/acciones.log";
                $date_str = date('Y-m-d H:i:s');
                $log_msg = "[$date_str] [STAFF_EDITAR_ERROR] Error SQL al editar staff '$curr_user' para usuario ID $user_id: " . $db->error . "\n";
                @file_put_contents($log_file, $log_msg, FILE_APPEND);

                header('Location: panel.php?error=db_error');
            }
            break;

        case 'borrar_staff':
            $staff_id = (int)($_POST['staff_id'] ?? 0);
            if ($staff_id <= 0) {
                header('Location: panel.php?error=staff_id_invalido');
                exit;
            }

            // Obtener el nombre del staff para el log
            $staff_q = $db->query("SELECT ftp_user FROM ftp_cuentas_extra WHERE id = $staff_id AND user_id = $user_id LIMIT 1");
            $staff_user = ($staff_q && $staff_q->num_rows > 0) ? $staff_q->fetch_assoc()['ftp_user'] : "ID $staff_id";

            // Obtener nombre del dueño
            $owner_q = $db->query("SELECT nombre FROM usuarios WHERE id = $user_id");
            $owner_data = $owner_q->fetch_assoc();
            $u_nom = $db->real_escape_string($owner_data['nombre'] ?? 'Usuario');

            // CORRECCIÓN: Cambiamos DELETE por UPDATE para avisar a Python antes de borrar de la DB
            $sql = "UPDATE ftp_cuentas_extra SET estado = 'Para_Borrar' WHERE id = $staff_id AND user_id = $user_id";
            if ($db->query($sql)) {
                // Registrar logs de acciones
                $log_dir = "/opt/tfg/scripts/logs";
                if (!is_dir($log_dir)) {
                    @mkdir($log_dir, 0755, true);
                }
                $log_file = "$log_dir/acciones.log";
                $date_str = date('Y-m-d H:i:s');
                $log_msg = "[$date_str] [STAFF_BORRAR] Usuario '$u_nom' (ID: $user_id) solicitó borrar el staff '$staff_user'\n";
                @file_put_contents($log_file, $log_msg, FILE_APPEND);

                // Alerta admin
                $motivo = "Usuario '$u_nom' eliminó la cuenta Staff '$staff_user'";
                $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($user_id, '$u_nom', '$motivo', '👥', 0, NOW())");

                shell_exec("sudo python3 /opt/tfg/scripts/crear_usuarios.py > /dev/null 2>&1");
                header('Location: panel.php?status=provisioning');
            } else {
                $log_dir = "/opt/tfg/scripts/logs";
                $log_file = "$log_dir/acciones.log";
                $date_str = date('Y-m-d H:i:s');
                $log_msg = "[$date_str] [STAFF_BORRAR_ERROR] Error SQL al borrar staff '$staff_user' para usuario ID $user_id: " . $db->error . "\n";
                @file_put_contents($log_file, $log_msg, FILE_APPEND);

                header('Location: panel.php?error=db_error');
            }
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