<?php
/* ============================================================
   ARCHIVO: controller_admin.php
   FUNCION: procesar acciones administrativas por POST.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

/* SECCION 1: acceso permitido y utilidades compartidas del administrador. */
require_once __DIR__ . '/sessions.php';
require_once __DIR__ . '/conexiones.php';

$action = $_GET['action'] ?? '';

/* El regreso de una suplantacion debe funcionar aunque la sesion activa sea de cliente. */
if ($action === 'volver_admin') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['admin_original']) || !is_array($_SESSION['admin_original'])) {
        header('Location: panel.php');
        exit;
    }

    $admin_original = $_SESSION['admin_original'];
    unset($_SESSION['admin_original'], $_SESSION['impersonando_id']);
    login_user_from_row($admin_original);
    header('Location: usuarios.php?msg=sesion_restaurada');
    exit;
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    exit('no_auth');
}

function redirigir_admin(string $destino): void {
    header('Location: ' . $destino);
    exit;
}

function registrar_accion_admin(string $message): void {
    $log_dir = '/opt/tfg/scripts/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $date_str = date('Y-m-d H:i:s');
    @file_put_contents("$log_dir/acciones.log", "[$date_str] $message\n", FILE_APPEND);
}

function insertar_alerta_admin(mysqli $db, int $user_id, string $nombre, string $motivo, string $simbolo): void {
    $stmt = $db->prepare(
        'INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha)
         VALUES (?, ?, ?, ?, 0, NOW())'
    );
    $stmt->bind_param('isss', $user_id, $nombre, $motivo, $simbolo);
    $stmt->execute();
    $stmt->close();
}

function calcular_desglose_factura(float $total): array {
    $total = round($total, 2);
    $base = round($total / 1.21, 2);
    return [$base, round($total - $base, 2), $total];
}

/* SECCION 2: lectura, reconocimiento y borrado de alertas. */
function alert_id_from_request(): int {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        http_response_code(400);
        exit('id_invalido');
    }

    return $id;
}

function cambiar_estado_alerta(): void {
    $id = alert_id_from_request();
    $db = getConexion();

    $stmt = $db->prepare('SELECT reconocida FROM alertas_admin WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $db->close();
        exit('not_found');
    }

    $nuevo_estado = ((int)$row['reconocida'] === 1) ? 0 : 1;
    $stmt = $db->prepare('UPDATE alertas_admin SET reconocida = ? WHERE id = ?');
    $stmt->bind_param('ii', $nuevo_estado, $id);
    $ok = $stmt->execute();
    $stmt->close();
    $db->close();

    echo $ok ? 'ok' : 'error';
}

function reconocer_alerta(): void {
    $id = alert_id_from_request();
    $db = getConexion();
    $stmt = $db->prepare('UPDATE alertas_admin SET reconocida = 1 WHERE id = ?');
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        echo "Exito: Alerta #$id reconocida.";
    } else {
        http_response_code(500);
        echo 'Error al actualizar la base de datos.';
    }

    $stmt->close();
    $db->close();
}

function procesar_alertas_masivas(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('metodo_invalido');
    }

    $accion = $_POST['accion'] ?? '';
    $ids_decoded = json_decode($_POST['ids'] ?? '[]', true);
    $ids = is_array($ids_decoded)
        ? array_values(array_filter(array_map('intval', $ids_decoded), static function ($id) {
            return $id > 0;
        }))
        : [];

    $db = getConexion();

    if ($accion === 'eliminar') {
        $stmt = $db->prepare('DELETE FROM alertas_admin WHERE reconocida = 1');
        echo $stmt->execute() ? 'ok' : 'error_db_delete';
        $stmt->close();
        $db->close();
        return;
    }

    if (($accion !== 'reconocer' && $accion !== 'restaurar') || empty($ids)) {
        $db->close();
        exit(empty($ids) ? 'error_no_ids' : 'accion_invalida');
    }

    $nuevo_estado = ($accion === 'reconocer') ? 1 : 0;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("UPDATE alertas_admin SET reconocida = $nuevo_estado WHERE id IN ($placeholders)");
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    echo $stmt->execute() ? 'ok' : 'error_db';
    $stmt->close();
    $db->close();
}

/* SECCION 3: gestion rapida de usuarios desde la tabla administrativa. */
function toggle_showcase_usuario(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('metodo_invalido');
    }

    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if (!$user_id || $user_id < 1) {
        http_response_code(400);
        exit('usuario_invalido');
    }

    $db = getConexion();
    $stmt = $db->prepare('SELECT showcase_permission FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $db->close();
        http_response_code(404);
        exit('usuario_no_encontrado');
    }

    $participando = ((int)$row['showcase_permission'] === 1) ? 0 : 1;
    $stmt = $db->prepare('UPDATE usuarios SET showcase_permission = ? WHERE id = ?');
    $stmt->bind_param('ii', $participando, $user_id);
    $ok = $stmt->execute();
    $stmt->close();
    $db->close();

    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($ok ? 200 : 500);
        echo json_encode(['ok' => $ok, 'participando' => (bool)$participando]);
        exit;
    }

    redirigir_admin($ok ? 'usuarios.php?msg=participacion_actualizada' : 'usuarios.php?error=db_error');
}

function impersonar_usuario(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirigir_admin('usuarios.php');
    }

    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $admin_id = (int)($_SESSION['user_id'] ?? 0);
    if (!$user_id || $user_id < 1 || $user_id === $admin_id) {
        redirigir_admin('usuarios.php?error=usuario_invalido');
    }

    $db = getConexion();

    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND rol = 'admin' LIMIT 1");
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    if (!$admin || !$usuario) {
        redirigir_admin('usuarios.php?error=usuario_no_encontrado');
    }

    $_SESSION['admin_original'] = $admin;
    $_SESSION['impersonando_id'] = (int)$usuario['id'];
    registrar_accion_admin("[IMPERSONACION] Admin '{$admin['nombre']}' accede como '{$usuario['nombre']}' (ID: $user_id)");
    login_user_from_row($usuario);
    redirigir_admin('panel.php?msg=impersonando');
}

/* SECCION 4: restauracion y purga completa de cuentas de clientes. */
function restaurar_usuario(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirigir_admin('admin_panel.php');
    }

    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if (!$user_id || $user_id < 1) {
        redirigir_admin('admin_panel.php?error=id_invalido');
    }

    $db = getConexion();
    $stmt = $db->prepare("UPDATE usuarios SET estado_servicio = 'Activo', fecha_cancelacion = NULL WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        $db->close();
        redirigir_admin('admin_panel.php?error=db_error');
    }

    $motivo = 'Solicitud restablecer cuenta';
    $stmt = $db->prepare('SELECT id FROM alertas_admin WHERE user_id = ? AND motivo = ? AND reconocida = 0 LIMIT 1');
    $stmt->bind_param('is', $user_id, $motivo);
    $stmt->execute();
    $alerta_existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$alerta_existe) {
        $stmt = $db->prepare('SELECT nombre FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        insertar_alerta_admin($db, $user_id, $row['nombre'] ?? 'Usuario', $motivo, '❓');
    }

    $db->close();
    redirigir_admin('admin_panel.php?msg=usuario_restaurado');
}

function purgar_usuario(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirigir_admin('usuarios.php');
    }

    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if (!$user_id || $user_id < 1) {
        redirigir_admin('usuarios.php');
    }

    $admin_id = (int)($_SESSION['user_id'] ?? 0);
    if ($admin_id === $user_id) {
        redirigir_admin('usuarios.php?error=no_purgar_admin_actual');
    }

    $admin_nom = $_SESSION['usuario'] ?? 'Admin';
    $db = getConexion();
    $stmt = $db->prepare('SELECT nombre FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        $db->close();
        redirigir_admin('usuarios.php?error=usuario_no_encontrado');
    }
    $user_name = $row['nombre'];

    registrar_accion_admin("[PURGA_INICIO] Admin '$admin_nom' inició la purga del usuario '$user_name' (ID: $user_id)");
    insertar_alerta_admin($db, $user_id, $user_name, "Purga de usuario '$user_name' iniciada por Admin '$admin_nom'", '⚙️');

    $stmt = $db->prepare("UPDATE usuarios SET estado_servicio = 'Para_Borrar' WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("UPDATE dominios SET estado_dominio = 'Para_Borrar' WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("UPDATE modulo_mysql SET estado = 'Para_Borrar' WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();

    $outputs = [];
    $errors = 0;
    $salida_vh = [];
    $salida_mysql = [];
    $salida_usuarios = [];

    exec('sudo python3 /opt/tfg/scripts/virtualhosts.py 2>&1', $salida_vh, $retval_vh);
    if ($retval_vh !== 0) {
        $errors++;
    }
    exec('sudo python3 /opt/tfg/scripts/mysql_worker.py 2>&1', $salida_mysql, $retval_mysql);
    if ($retval_mysql !== 0) {
        $errors++;
    }
    exec('sudo python3 /opt/tfg/scripts/crear_usuarios.py 2>&1', $salida_usuarios, $retval_usuarios);
    if ($retval_usuarios !== 0) {
        $errors++;
    }

    $outputs = [
        'virtualhosts' => $salida_vh,
        'mysql' => $salida_mysql,
        'usuarios' => $salida_usuarios,
    ];

    $stmt = $db->prepare('SELECT id FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $usuario_sigue_existiendo = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    $alert_owner_id = $usuario_sigue_existiendo ? $user_id : $admin_id;

    if ($errors === 0) {
        registrar_accion_admin("[PURGA_EXITO] Usuario '$user_name' (ID: $user_id) purgado con éxito de Apache, MySQL y Linux por '$admin_nom'.");
        if ($alert_owner_id > 0) {
            insertar_alerta_admin($db, $alert_owner_id, $user_name, "Purga de usuario '$user_name' completada con éxito", '✅');
        }
    } else {
        $details = json_encode($outputs);
        registrar_accion_admin(
            "[PURGA_ERROR] Fallos en purga de '$user_name' (ID: $user_id). Errores: $errors. "
            . "Códigos: VH=$retval_vh, MY=$retval_mysql, CR=$retval_usuarios. Logs: $details"
        );
        if ($alert_owner_id > 0) {
            insertar_alerta_admin($db, $alert_owner_id, $user_name, "Purga de usuario '$user_name' finalizada con errores", '❌');
        }
    }

    $db->close();
    redirigir_admin('usuarios.php?msg=borrado_pendiente');
}

/* SECCION 5: cierre administrativo del proyecto Web y factura aislada. */
function generar_factura_web(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirigir_admin('usuarios.php');
    }

    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $precio_final = filter_input(INPUT_POST, 'precio_final', FILTER_VALIDATE_FLOAT);
    if (!$user_id || $user_id < 1 || $precio_final === false || $precio_final < 0) {
        redirigir_admin('presupuestos.php?user_id=' . (int)$user_id . '&error=precio_web_invalido');
    }

    $precio_final = round((float)$precio_final, 2);
    $db = getConexion();
    $stmt = $db->prepare('SELECT nombre, extras_json, modulo_ia FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$usuario) {
        $db->close();
        redirigir_admin('usuarios.php?error=usuario_no_encontrado');
    }

    $extras = json_decode($usuario['extras_json'] ?? '[]', true);
    $tiene_modulo_web = ((int)($usuario['modulo_ia'] ?? 0) === 1)
        || (is_array($extras) && count(array_filter(
            $extras,
            static fn($extra) => str_contains((string)$extra, 'web_ai')
        )) > 0);

    if (!$tiene_modulo_web) {
        $db->close();
        redirigir_admin('presupuestos.php?user_id=' . $user_id . '&error=web_no_contratado');
    }

    $stmt = $db->prepare(
        "SELECT estado
         FROM proyectos_diseno_web
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $proyecto_actual = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (($proyecto_actual['estado'] ?? '') === 'activo') {
        $db->close();
        redirigir_admin('presupuestos.php?user_id=' . $user_id . '&error=web_ya_facturado');
    }

    $total = round($precio_final, 2);
    $nombre = $usuario['nombre'] ?? 'Usuario';
    $db->begin_transaction();

    $proyecto_id = null;
    $stmt_proyecto = $db->prepare(
        'SELECT id FROM proyectos_diseno_web WHERE user_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt_proyecto->bind_param('i', $user_id);
    $stmt_proyecto->execute();
    $proyecto = $stmt_proyecto->get_result()->fetch_assoc();
    $stmt_proyecto->close();

    if ($proyecto) {
        $proyecto_id = (int)$proyecto['id'];
        $stmt_proyecto = $db->prepare(
            "UPDATE proyectos_diseno_web
             SET estado = 'tramitando_propuesta',
                 precio_final = ?,
                 fecha_pago = NULL,
                 fecha_garantia_expira = NULL
             WHERE id = ?"
        );
        $stmt_proyecto->bind_param('di', $total, $proyecto_id);
    } else {
        $stmt_proyecto = $db->prepare(
            "INSERT INTO proyectos_diseno_web
                (user_id, estado, precio_final, fecha_pago, fecha_garantia_expira)
             VALUES (?, 'tramitando_propuesta', ?, NULL, NULL)"
        );
        $stmt_proyecto->bind_param('id', $user_id, $total);
    }

    $ok = $stmt_proyecto->execute();
    $stmt_proyecto->close();

    if ($ok) {
        $precio_chat = number_format($total, 2, ',', '.');
        $mensaje_chat = "Presupuesto aprobado. Precio final del proyecto web: {$precio_chat} EUR. Para continuar, entra en checkout.php?web_project=1 y completa de nuevo tus datos de facturacion y pago.";
        $emisor_chat = 'admin';
        $stmt_chat = $db->prepare(
            'INSERT INTO mensajes_chat (user_id, emisor, mensaje, leido) VALUES (?, ?, ?, 0)'
        );
        if ($stmt_chat) {
            $stmt_chat->bind_param('iss', $user_id, $emisor_chat, $mensaje_chat);
            $stmt_chat->execute();
            $stmt_chat->close();
        }

        registrar_accion_admin("[WEB_PRESUPUESTO] Admin fijo presupuesto Web para '$nombre' (ID: $user_id) por {$total} EUR.");
        insertar_alerta_admin($db, $user_id, $nombre, 'Presupuesto de Diseño Web listo para pago en checkout.', 'WEB');
    }

    if ($ok) {
        $db->commit();
    } else {
        $db->rollback();
    }

    $db->close();
    redirigir_admin($ok ? 'presupuestos.php?user_id=' . $user_id . '&ok=presupuesto_web_listo' : 'presupuestos.php?user_id=' . $user_id . '&error=db_error');
}

/* SECCION 6: elegir una unica accion solicitada por la interfaz. */
switch ($action) {
    case 'cambiar_estado':
        cambiar_estado_alerta();
        break;
    case 'reconocer':
        reconocer_alerta();
        break;
    case 'masivas':
        procesar_alertas_masivas();
        break;
    case 'toggle_showcase':
        toggle_showcase_usuario();
        break;
    case 'impersonar_usuario':
        impersonar_usuario();
        break;
    case 'restaurar_usuario':
        restaurar_usuario();
        break;
    case 'purgar_usuario':
        purgar_usuario();
        break;
    case 'generar_factura_web':
        generar_factura_web();
        break;
    default:
        http_response_code(404);
        echo 'accion_invalida';
}