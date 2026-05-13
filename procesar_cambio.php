<?php
require_once 'sessions.php';
require_auth();
require_once 'conexiones.php';

// Solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel.php');
    exit;
}

$accion = $_POST['accion'] ?? '';
$modulo = $_POST['modulo'] ?? '';

$db      = getConexion();
$email   = trim($_SESSION['email'] ?? '');
$email_s = $db->real_escape_string($email);

// ─── Acciones permitidas ───────────────────────────────────────────────────
switch ($accion) {

    // ── Eliminar un módulo opcional ──────────────────────────────────────
    case 'eliminar_modulo':

        $modulos_validos = ['sql_php', 'domain', 'storage', 'multiuser'];
        if (!in_array($modulo, $modulos_validos, true)) {
            $db->close();
            header('Location: modificar_servicios.php?error=modulo_invalido');
            exit;
        }

        // Obtenemos el extras_json actual del usuario
        $res = $db->query("SELECT extras_json, storage_qty FROM usuarios WHERE email = '$email_s' LIMIT 1");
        if (!$res || $res->num_rows === 0) {
            $db->close();
            header('Location: modificar_servicios.php?error=usuario_no_encontrado');
            exit;
        }
        $row         = $res->fetch_assoc();
        $extras_json = json_decode($row['extras_json'] ?? '[]', true) ?: [];
        $storage_qty = (int) $row['storage_qty'];
        $multiuser_qty = (int) $row['multiuser_qty'];

        if ($modulo === 'storage') {
            // Reducir en 1 el contador de packs de almacenamiento
            if ($storage_qty > 0) {
                $nuevo_storage = $storage_qty - 1;
                $db->query("UPDATE usuarios SET storage_qty = $nuevo_storage WHERE email = '$email_s' LIMIT 1");
            }

        } elseif ($modulo === 'multiuser') {
            // Reducir en 1 el contador de packs multiusuario
            if ($multiuser_qty > 0) {
                $nuevo_multi = $multiuser_qty - 1;
                $db->query("UPDATE usuarios SET multiuser_qty = $nuevo_multi WHERE email = '$email_s' LIMIT 1");
            }

        } elseif ($modulo === 'domain') {
            // Quitar el módulo domain del JSON y limpiar fecha de dominio
            $extras_json = array_values(array_filter($extras_json, fn($m) => $m !== 'domain|15.00'));
            $extras_s    = $db->real_escape_string(json_encode($extras_json));
            // IMPORTANTE: Limpiamos todas las columnas relacionadas
            $query = "UPDATE usuarios SET 
                        extras_json = '$extras_s', 
                        fecha_caducidad_dominio = NULL, 
                        renovacion_auto = 0, 
                        dominio_propio = NULL, 
                        estado_dominio = 'No configurado' 
                      WHERE email = '$email_s' LIMIT 1";
            $db->query($query);

        } elseif ($modulo === 'sql_php') {
            // Quitar el módulo sql/php del JSON
            $extras_json = array_values(array_filter($extras_json, fn($m) => $m !== 'sql_php|5.00'));
            $extras_s    = $db->real_escape_string(json_encode($extras_json));
        }
        
        // --- ALERTA ADMIN: Modificación módulos ---
        $res_user = $db->query("SELECT id, nombre FROM usuarios WHERE email = '$email_s' LIMIT 1");
        if ($res_user && $row_u = $res_user->fetch_assoc()) {
            $u_id = $row_u['id'];
            $u_nom = $db->real_escape_string($row_u['nombre']);
            $check_alert = $db->query("SELECT id FROM alertas_admin WHERE user_id = $u_id AND motivo = 'Modificación módulos' AND reconocida = 0 LIMIT 1");
            if ($check_alert->num_rows === 0) {
                $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($u_id, '$u_nom', 'Modificación módulos', '❓', 0, NOW())");
            }
        }

        // Refrescar la sesión con los datos actualizados
        $res2 = $db->query("SELECT nombre, email, password_hash, rol, plan_contratado, storage_qty, multiuser_qty, extras_json, fecha_caducidad_dominio, renovacion_auto FROM usuarios WHERE email = '$email_s' LIMIT 1");
        if ($res2 && $res2->num_rows === 1) {
            login_user_from_row($res2->fetch_assoc());
        }

        $db->close();
        header('Location: modificar_servicios.php?ok=modulo_eliminado');
        exit;

    // ── Acción desconocida ────────────────────────────────────────────────
    default:
        $db->close();
        header('Location: modificar_servicios.php?error=accion_invalida');
        exit;
}
