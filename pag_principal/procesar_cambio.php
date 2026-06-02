<?php
/* ============================================================
   ARCHIVO: procesar_cambio.php
   FUNCION: guardar cambios de extras contratados.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

require_once 'sessions.php';
require_auth();
require_once 'conexiones.php';

// Solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel.php');
    exit;
}

function volver_servicios(string $query): void {
    $destino = 'modificar_servicios.php' . $query;
    $es_ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

    if ($es_ajax) {
        parse_str(ltrim($query, '?'), $params);
        $hay_error = isset($params['error']);
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($hay_error ? 422 : 200);
        echo json_encode([
            'ok' => !$hay_error,
            'code' => $params['error'] ?? $params['ok'] ?? 'ok',
            'redirect' => $destino,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ' . $destino);
    exit;
}

$accion = $_POST['accion'] ?? '';
$modulo = $_POST['modulo'] ?? '';

$db      = getConexion();
$email   = trim($_SESSION['email'] ?? '');
$email_s = $db->real_escape_string($email);

// ─── Mapa de módulos incluidos de forma nativa por plan ────────────────────
$modulos_nativos_por_plan = [
    'PROFESIONAL' => ['sql_php'],
    'ENTERPRISE'  => ['sql_php', 'domain'],
];
$plan_upper = strtoupper($_SESSION['plan'] ?? 'Ninguno');
$nativos_del_plan = $modulos_nativos_por_plan[$plan_upper] ?? [];

// ─── Acciones permitidas ───────────────────────────────────────────────────
switch ($accion) {

    // ── Eliminar un módulo opcional ──────────────────────────────────────
    case 'eliminar_modulo':

        $modulos_validos = ['sql_php', 'domain', 'storage', 'multiuser'];
        if (!in_array($modulo, $modulos_validos, true)) {
            $db->close();
            volver_servicios('?error=modulo_invalido');
        }

        // ── GUARDIÁN: Bloquear baja de módulo nativo del plan ───────────
        if (in_array($modulo, $nativos_del_plan, true)) {
            $db->close();
            volver_servicios('?error=modulo_nativo');
        }

        // Obtenemos el extras_json actual del usuario
        $res = $db->query("SELECT extras_json, storage_qty, multiuser_qty FROM usuarios WHERE email = '$email_s' LIMIT 1");
        if (!$res || $res->num_rows === 0) {
            $db->close();
            volver_servicios('?error=usuario_no_encontrado');
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
            // CORRECCIÓN CRÍTICA: dominio_propio / estado_dominio NO están en
            // la tabla usuarios — pertenecen a la tabla dominios.
            $extras_json = array_values(array_filter($extras_json, fn($m) => $m !== 'domain|15.00'));
            $extras_s    = $db->real_escape_string(json_encode($extras_json));
            // 1. Quitar módulo del JSON del usuario
            $db->query("UPDATE usuarios SET extras_json = '$extras_s' WHERE email = '$email_s' LIMIT 1");
            // 2. Marcar en dominios para que virtualhosts.py limpie el VirtualHost de Apache
            $res_uid2 = $db->query("SELECT id FROM usuarios WHERE email = '$email_s' LIMIT 1");
            if ($res_uid2 && $row_uid2 = $res_uid2->fetch_assoc()) {
                $uid_dom = (int)$row_uid2['id'];
                $db->query("UPDATE dominios SET dominio_propio = NULL, estado_dominio = 'Para_Borrar' WHERE user_id = $uid_dom LIMIT 1");
            }

        } elseif ($modulo === 'sql_php') {
            // Quitar el módulo sql/php del JSON
            $extras_json = array_values(array_filter($extras_json, fn($m) => $m !== 'sql_php|5.00'));
            $extras_s    = $db->real_escape_string(json_encode($extras_json));
            $db->query("UPDATE usuarios SET extras_json = '$extras_s' WHERE email = '$email_s' LIMIT 1");
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
        $res2 = $db->query("SELECT id, nombre, email, password_hash, rol, plan_contratado, storage_qty, multiuser_qty, extras_json FROM usuarios WHERE email = '$email_s' LIMIT 1");
        if ($res2 && $res2->num_rows === 1) {
            login_user_from_row($res2->fetch_assoc());
        }

        $db->close();
        volver_servicios('?ok=modulo_eliminado');

    // ── Acción desconocida ────────────────────────────────────────────────
    default:
        $db->close();
        volver_servicios('?error=accion_invalida');
}