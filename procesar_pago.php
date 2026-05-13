<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'sessions.php';
require_once 'conexiones.php';
$conexion = getConexion();

// ─── SEGURIDAD: Asegurar que la columna estado_dominio soporte textos largos ───
$conexion->query("ALTER TABLE dominios MODIFY COLUMN estado_dominio VARCHAR(50) DEFAULT 'Pendiente'");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#checkout');
    exit;
}

require_auth();
$tenia_plan = ($_SESSION['plan'] ?? 'Ninguno') !== 'Ninguno';

$plan = trim($_POST['plan_seleccionado'] ?? '');
$servicio_base = $_POST['servicio_base'] ?? '';
$total_calculado = $_POST['total_calculado'] ?? '';
$storage_qty = intval($_POST['storage_qty'] ?? 0);
$multiuser_qty = intval($_POST['multiuser_qty'] ?? 0);
$modulos = $_POST['modulos'] ?? [];
$nombre_titular = trim($_POST['nombre_titular'] ?? '');
$numero_tarjeta = preg_replace('/\s+/', '', $_POST['numero_tarjeta'] ?? '');
$caducidad = $_POST['caducidad'] ?? '';
$cvv = $_POST['cvv'] ?? '';

$planes_validos = ['BÁSICO', 'PROFESIONAL', 'ENTERPRISE'];
if ($plan === '' || !in_array($plan, $planes_validos, true)) {
    header('Location: checkout.php?error=plan&plan=' . urlencode($plan));
    exit;
}

if ($servicio_base === '' || strpos($servicio_base, 'core|') === false) {
    header('Location: checkout.php?error=servicio&plan=' . urlencode($plan));
    exit;
}

if ($nombre_titular === '' || $numero_tarjeta === '' || $caducidad === '' || $cvv === '') {
    header('Location: checkout.php?error=datos_pago&plan=' . urlencode($plan));
    exit;
}

if (!preg_match('/^\d{13,19}$/', $numero_tarjeta)) {
    header('Location: checkout.php?error=tarjeta&plan=' . urlencode($plan));
    exit;
}

if (!preg_match('/^\d{2}\/\d{2}$/', $caducidad)) {
    header('Location: checkout.php?error=caducidad&plan=' . urlencode($plan));
    exit;
}

if (!preg_match('/^\d{3,4}$/', $cvv)) {
    header('Location: checkout.php?error=cvv&plan=' . urlencode($plan));
    exit;
}

if ($total_calculado === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $total_calculado)) {
    header('Location: checkout.php?error=total&plan=' . urlencode($plan));
    exit;
}

$extra_items = [];
// Límite de almacenamiento: máx. 20 packs (~40GB adicionales) para proteger el disco del servidor
if ($storage_qty > 20) {
    header('Location: checkout.php?error=storage_limite&plan=' . urlencode($plan));
    exit;
}
if ($storage_qty > 0) { $extra_items[] = "Pack Almacenamiento x$storage_qty"; }
if ($multiuser_qty > 0) {
    if ($plan === 'ENTERPRISE') { $extra_items[] = 'Multiusuario ilimitado'; }
    else { $extra_items[] = "Pack Multiusuario x$multiuser_qty"; }
}

// Restaurar módulos incluidos (que al estar 'disabled' en el HTML no viajan en el POST)
if ($plan === 'ENTERPRISE') {
    if (!in_array('domain|15.00', $modulos)) $modulos[] = 'domain|15.00';
    if (!in_array('sql_php|5.00', $modulos)) $modulos[] = 'sql_php|5.00';
} elseif ($plan === 'PROFESIONAL') {
    if (!in_array('sql_php|5.00', $modulos)) $modulos[] = 'sql_php|5.00';
}

$validModules = ['sql_php|5.00' => 'Acceso SQL/PHP', 'domain|15.00' => 'Gestión de Dominio', 'web_ai|100.00' => 'Diseño Web IA'];
if (!empty($modulos) && is_array($modulos)) {
    foreach ($modulos as $modulo) {
        if (array_key_exists($modulo, $validModules)) { $extra_items[] = $validModules[$modulo]; }
    }
}

$email_safe = $conexion->real_escape_string($_SESSION['email']);
$plan_safe = $conexion->real_escape_string($plan);
$extras_json = json_encode($modulos);
$extras_json_safe = $conexion->real_escape_string($extras_json);
$tiene_dominio = in_array('domain|15.00', $modulos);
$fecha_caducidad = $tiene_dominio ? "DATE_ADD(NOW(), INTERVAL 1 YEAR)" : "NULL";
$renovacion = $tiene_dominio ? 1 : 0;

$storage_qty = (int)($_POST['storage_qty'] ?? 0);
$multiuser_qty = (int)($_POST['multiuser_qty'] ?? 0);

// Definir la consulta de actualización que faltaba
$query = "UPDATE usuarios SET 
            plan_contratado = '$plan_safe', 
            extras_json = '$extras_json_safe', 
            storage_qty = $storage_qty, 
            multiuser_qty = $multiuser_qty,
            estado_servicio = 'Pendiente',
            creado_en_so = 0
          WHERE email = '$email_safe'";

$ejecutar = $conexion->query($query);

// Asegurar que tenemos el user_id (por si la sesión es antigua)
$current_user_id = $_SESSION['user_id'] ?? null;
if (!$current_user_id) {
    $res_id = $conexion->query("SELECT id FROM usuarios WHERE email = '$email_safe' LIMIT 1");
    if ($res_id && $res_id->num_rows === 1) {
        $u_data = $res_id->fetch_assoc();
        $current_user_id = $u_data['id'];
        $_SESSION['user_id'] = $current_user_id; // Actualizar sesión para el futuro
    }
}

// --- ALERTA ADMIN: Nuevo Plan o Presupuesto ---
if ($ejecutar && $current_user_id) {
    $motivo_alerta = in_array('web_ai|100.00', $modulos) ? 'Solicitud presupuesto' : 'Nuevo Plan';
    $simbolo_alerta = in_array('web_ai|100.00', $modulos) ? '!' : '⭐';
    
    $check_alert = $conexion->query("SELECT id FROM alertas_admin WHERE user_id = $current_user_id AND motivo = '$motivo_alerta' AND reconocida = 0 LIMIT 1");
    if ($check_alert->num_rows === 0) {
        $nombre_user = $conexion->real_escape_string($_SESSION['usuario'] ?? 'Usuario');
        $conexion->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($current_user_id, '$nombre_user', '$motivo_alerta', '$simbolo_alerta', 0, NOW())");
    }
}
// --------------------------------------------------------------------------

if (!$ejecutar) {
    header('Location: checkout.php?error=db&plan=' . urlencode($plan));
    exit;
}

    // ─── APROVISIONAMIENTO: crear carpeta FTP del usuario ───
    $query_u = "SELECT id, nombre, ftp_user, ftp_pass FROM usuarios WHERE email = '$email_safe' LIMIT 1";
    $result_u = $conexion->query($query_u);
    if ($result_u && $result_u->num_rows === 1) {
        $row_u = $result_u->fetch_assoc();
        $user_id = (int)$row_u['id'];
        $nombre_real = $row_u['nombre'];
        $ftp_user = $row_u['ftp_user'];

        // Si no tiene ftp_user, generamos uno por defecto y delegamos la creación al script de Python
        if (empty($ftp_user)) {
            $u_clean = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombre_real));
            if (empty($u_clean)) $u_clean = "user" . $user_id;
            $ftp_user = $u_clean;
            $temp_pass = "pass_" . substr(md5(uniqid()), 0, 6);
            // Insertamos creado_en_so = 0 y estado_servicio = 'Pendiente' para que Python actúe y aprovisione la cuenta
            $conexion->query("UPDATE usuarios SET ftp_user = '$ftp_user', ftp_pass = '$temp_pass', creado_en_so = 0, estado_servicio = 'Pendiente' WHERE id = $user_id");
        }

        // Sincronizar tabla dominios con estado 'Pendiente' (en lugar de Proc) para que virtualhosts.py actúe
        $conexion->query("INSERT IGNORE INTO dominios (user_id, subdominio_alias, estado_dominio, renovacion_auto, fecha_caducidad) 
                          VALUES ($user_id, '$ftp_user', 'Pendiente', $renovacion, $fecha_caducidad)");
        $conexion->query("UPDATE dominios SET estado_dominio = 'Pendiente', renovacion_auto = $renovacion, fecha_caducidad = $fecha_caducidad WHERE user_id = $user_id");
    }

// Actualizar sesión usando login_user_from_row para asegurar consistencia
$query_user = "SELECT id, nombre, email, password_hash, rol, plan_contratado, storage_qty, multiuser_qty, extras_json FROM usuarios WHERE email = '$email_safe' LIMIT 1";
$query_dominio = "SELECT renovacion_auto, fecha_caducidad FROM dominios WHERE user_id = (SELECT id FROM usuarios WHERE email = '$email_safe' LIMIT 1) LIMIT 1";
$result_user = $conexion->query($query_user);
$result_dominio = $conexion->query($query_dominio);

if ($result_user && $result_user->num_rows === 1) {
    $usuario = $result_user->fetch_assoc();
    if ($result_dominio && $result_dominio->num_rows === 1) {
        $dom_data = $result_dominio->fetch_assoc();
        $usuario['renovacion_auto'] = $dom_data['renovacion_auto'];
        $usuario['fecha_caducidad_dominio'] = $dom_data['fecha_caducidad'];
    } else {
        $usuario['renovacion_auto'] = 0;
        $usuario['fecha_caducidad_dominio'] = null;
    }
    login_user_from_row($usuario);
}

$conexion->close();

// Seguridad: destruir variables sensibles de pago de la memoria
unset($numero_tarjeta, $cvv, $caducidad, $nombre_titular);

// Señal para mostrar la alerta de bienvenida en el panel (solo si es su primer plan)
if (!$tenia_plan) {
    $_SESSION['nuevo_pedido'] = true;
}

header('Location: panel.php');
exit;
