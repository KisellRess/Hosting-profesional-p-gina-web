<?php
require_once 'sessions.php';
require_auth();
require_once 'conexiones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel.php');
    exit;
}

$db = getConexion();
$user_id = (int)$_SESSION['user_id'];
$accion = $_POST['accion'] ?? '';
$valor = $db->real_escape_string($_POST['valor'] ?? '');

// ─── Verificar si ya existe una fila para este user_id en la tabla dominios ───
$res = $db->query("SELECT id FROM dominios WHERE user_id = $user_id LIMIT 1");
$fila_existe = ($res && $res->num_rows > 0);

$sql = null;

switch ($accion) {
    case 'cambiar_subdominio':
        if ($fila_existe) {
            $sql = "UPDATE dominios SET subdominio_alias = '$valor', estado_dominio = 'Tramitando' WHERE user_id = $user_id";
        } else {
            $sql = "INSERT INTO dominios (user_id, subdominio_alias, estado_dominio) VALUES ($user_id, '$valor', 'Tramitando')";
        }
        break;

    case 'conectar_dominio':
        if ($fila_existe) {
            $sql = "UPDATE dominios SET dominio_propio = '$valor', estado_dominio = 'Tramitando' WHERE user_id = $user_id";
        } else {
            $sql = "INSERT INTO dominios (user_id, dominio_propio, estado_dominio) VALUES ($user_id, '$valor', 'Tramitando')";
        }
        break;

    case 'comprar_dominio':
        // $valor contiene los dominios separados por comas (principal,alt1,alt2,alt3)
        if ($fila_existe) {
            $sql = "UPDATE dominios SET dominio_propio = '$valor', estado_dominio = 'Tramitando' WHERE user_id = $user_id";
        } else {
            $sql = "INSERT INTO dominios (user_id, dominio_propio, estado_dominio) VALUES ($user_id, '$valor', 'Tramitando')";
        }
        break;

    case 'desvincular_dominio':
        if ($fila_existe) {
            // Limpiamos dominio_propio Y marcamos para borrar en el backend
            $sql = "UPDATE dominios SET dominio_propio = NULL, estado_dominio = 'Para_Borrar' WHERE user_id = $user_id";
        }
        // Si no existe fila, no hay nada que desvincular
        break;

    default:
        header('Location: panel.php?error=accion_invalida');
        $db->close();
        exit;
}

if ($sql) {
    if ($db->query($sql)) {
        // --- ALERTA ADMIN: Gestión Dominio ---
        $motivo_dom = "Gestión Dominio: $accion";
        $check_alert = $db->query("SELECT id FROM alertas_admin WHERE user_id = $user_id AND motivo = '$motivo_dom' AND reconocida = 0 LIMIT 1");
        if ($check_alert->num_rows === 0) {
            $u_nom = $db->real_escape_string($_SESSION['usuario'] ?? 'Usuario');
            $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($user_id, '$u_nom', '$motivo_dom', '❓', 0, NOW())");
        }

        // Ejecutar inmediatamente el backend de Apache para configurar / eliminar el vhost
        shell_exec("sudo python3 virtualhosts.py > /dev/null 2>&1");

        header('Location: panel.php?ok=dominio_actualizado');
    } else {
        error_log("procesar_dominio.php ERROR: " . $db->error . " | SQL: " . $sql);
        header('Location: panel.php?error=db_error');
    }
} else {
    // Caso: desvincular cuando no hay fila — no es un error real
    header('Location: panel.php?ok=dominio_actualizado');
}

$db->close();
exit;