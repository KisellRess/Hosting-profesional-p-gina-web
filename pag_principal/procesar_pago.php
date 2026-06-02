<?php
/* ============================================================
   ARCHIVO: procesar_pago.php
   FUNCION: crear o ampliar la contratacion pagada.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'sessions.php';
require_once 'conexiones.php';
$conexion = getConexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#checkout');
    exit;
}

require_auth();

function sanitizar_nombre_local($nombre) {
    $nombre_lower = strtolower(str_replace(' ', '', $nombre));
    return preg_replace('/[^a-z0-9]/', '', $nombre_lower);
}

function desglose_iva_factura(float $total): array {
    $total = round($total, 2);
    $base = round($total / 1.21, 2);
    return [$base, round($total - $base, 2), $total];
}

function asegurar_modulo_web_activo(mysqli $conexion, int $user_id): void {
    $stmt = $conexion->prepare('SELECT extras_json FROM usuarios WHERE id = ? LIMIT 1 FOR UPDATE');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $extras = json_decode($row['extras_json'] ?? '[]', true);
    if (!is_array($extras)) {
        $extras = [];
    }
    if (!in_array('web_ai|100.00', $extras, true)) {
        $extras[] = 'web_ai|100.00';
    }

    $extras_json = json_encode(array_values(array_unique($extras)), JSON_UNESCAPED_UNICODE);
    $stmt = $conexion->prepare('UPDATE usuarios SET modulo_ia = 1, extras_json = ? WHERE id = ?');
    $stmt->bind_param('si', $extras_json, $user_id);
    $stmt->execute();
    $stmt->close();
}

$tenia_plan = ($_SESSION['plan'] ?? 'Ninguno') !== 'Ninguno';

$checkout_mode = $_POST['checkout_mode'] ?? 'standard';
$es_pago_proyecto_web = $checkout_mode === 'web_project';

function redirigir_checkout_error(string $error, string $plan, bool $es_pago_proyecto_web): void {
    $destino = $es_pago_proyecto_web
        ? 'checkout.php?web_project=1&error=' . urlencode($error)
        : 'checkout.php?error=' . urlencode($error) . '&plan=' . urlencode($plan);
    header('Location: ' . $destino);
    exit;
}

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
$nombre_fiscal = trim($_POST['nombre_fiscal'] ?? '');
$documento_identidad = trim($_POST['documento_identidad'] ?? '');
$direccion_completa = trim($_POST['direccion_completa'] ?? '');

$email_safe = $conexion->real_escape_string($_SESSION['email'] ?? '');

// Si no hay email en la sesión, entonces sí es un acceso totalmente inválido
if (empty($email_safe)) {
    die("Error: Sesión de usuario no válida (Falta Email).");
}

$user_id = intval($_SESSION['user_id'] ?? 0);

// SI EL USER_ID ESTÁ VACÍO, LO RECOVERY DE LA BASE DE DATOS USANDO EL EMAIL
if ($user_id <= 0) {
    $res_id = $conexion->query("SELECT id FROM usuarios WHERE email = '$email_safe' LIMIT 1");
    if ($res_id && $res_id->num_rows === 1) {
        $row_id = $res_id->fetch_assoc();
        $user_id = intval($row_id['id']);
        $_SESSION['user_id'] = $user_id; // Lo guardamos en la sesión para el futuro
    } else {
        die("Error: No se encontró ningún usuario con el email: " . htmlspecialchars($email_safe));
    }
}

// Validar campos obligatorios
if (empty($nombre_titular) || empty($numero_tarjeta) || empty($caducidad) || empty($cvv)) {
    redirigir_checkout_error('campos_vacios', $plan, $es_pago_proyecto_web);
}

// No se contrata ningun servicio sin informacion fiscal suficiente para su factura.
if ($nombre_fiscal === '' || $documento_identidad === '' || $direccion_completa === '') {
    redirigir_checkout_error('facturacion_incompleta', $plan, $es_pago_proyecto_web);
}
if (mb_strlen($nombre_fiscal) > 150 || mb_strlen($direccion_completa) > 255) {
    redirigir_checkout_error('facturacion_incompleta', $plan, $es_pago_proyecto_web);
}
if (!preg_match('/^[A-Za-z0-9-]{3,20}$/', $documento_identidad)) {
    redirigir_checkout_error('documento_invalido', $plan, $es_pago_proyecto_web);
}

// Validar formato de tarjeta (Luhn o longitud básica)
if (!preg_match('/^\d{13,19}$/', $numero_tarjeta)) {
    redirigir_checkout_error('tarjeta_invalida', $plan, $es_pago_proyecto_web);
}

// Validar CVV
if (!preg_match('/^\d{3,4}$/', $cvv)) {
    redirigir_checkout_error('cvv_invalido', $plan, $es_pago_proyecto_web);
}

// ─── LÓGICA DE PRECIOS REALES (MISMOS VALORES QUE EN PLANES.PHP) ───
$precio_plan_base = 0.00;
$plan_upper = strtoupper($plan);

if ($plan_upper === 'BÁSICO' || $plan_upper === 'BASICO') {
    $precio_plan_base = 7.50;
} elseif ($plan_upper === 'PROFESIONAL') {
    $precio_plan_base = 15.00;
} elseif ($plan_upper === 'ENTERPRISE') {
    $precio_plan_base = 25.00;
}

// Multiplicadores de extras según precios fijados en planes.php
$costo_almacenamiento = $storage_qty * 3.00; // 3.00€ cada pack extra
$costo_multiusuarios  = $multiuser_qty * 2.00; // 2.00€ cada usuario extra

// Sumar módulos adicionales dinámicos (extras_json)
$costo_modulos = 0.00;
$extras_para_json = [];
if (!empty($modulos) && is_array($modulos)) {
    foreach ($modulos as $m_raw) {
        $parts = explode('|', $m_raw);
        if (count($parts) === 2) {
            $m_name = trim($parts[0]);
            $m_price = floatval($parts[1]);
            
            // Reglas de cortesías automáticas según el plan
            if ($m_name === 'sql_php' && ($plan_upper === 'PROFESIONAL' || $plan_upper === 'ENTERPRISE')) {
                continue;
            }
            if ($m_name === 'domain' && $plan_upper === 'ENTERPRISE') {
                continue;
            }
            
            $extras_para_json[] = $m_raw;
            if ($m_name === 'web_ai') {
                continue;
            }

            $costo_modulos += $m_price;
        }
    }
}

// IMPORTE CONSOLIDADO FINAL REAL QUE SE GUARDARÁ EN LA FACTURA
$importe_real_total = $precio_plan_base + $costo_almacenamiento + $costo_multiusuarios + $costo_modulos;
$extras_json_str = json_encode($extras_para_json);
$contrata_web_ai = in_array('web_ai|100.00', $extras_para_json, true);

// Obtener datos actuales del usuario y obtener ftp_user/nombre
$query_user = "SELECT plan_contratado, ftp_user, nombre, storage_qty, multiuser_qty, extras_json FROM usuarios WHERE email = '$email_safe' LIMIT 1";
$res_user = $conexion->query($query_user);

if ($res_user && $res_user->num_rows === 1) {
    $row_user = $res_user->fetch_assoc();
    $plan_actual = $row_user['plan_contratado'];
    $ftp_user_owner = $row_user['ftp_user'];
    $nombre_owner = $row_user['nombre'];

    // Ajustar costo si mantiene el mismo plan base (solo paga los extras añadidos)
    if (strcasecmp($plan_actual, $plan) === 0 && $plan_actual !== 'Ninguno') {
        $precio_plan_base = 0.00;
        $importe_real_total = $precio_plan_base + $costo_almacenamiento + $costo_multiusuarios + $costo_modulos;
    }

    // Validar si ya tiene el mismo plan contratado y NO ha cambiado nada en su configuración de extras
    if (strcasecmp($plan_actual, $plan) === 0 && $plan_actual !== 'Ninguno') {
        $storage_qty_actual = intval($row_user['storage_qty'] ?? 0);
        $multiuser_qty_actual = intval($row_user['multiuser_qty'] ?? 0);
        
        $extras_actual_arr = json_decode($row_user['extras_json'] ?? '[]', true) ?: [];
        sort($extras_actual_arr);
        
        $extras_nuevos_arr = $extras_para_json;
        sort($extras_nuevos_arr);
        
        if ($storage_qty === $storage_qty_actual && 
            $multiuser_qty === $multiuser_qty_actual && 
            $extras_nuevos_arr === $extras_actual_arr) {
            
            header('Location: panel.php?error=ya_tienes_ese_plan');
            exit;
        }
    }
} else {
    die("Error al verificar los datos del usuario.");
}

// Persistir los datos que se utilizaran en las facturas emitidas.
$stmt_fiscal = $conexion->prepare(
    'UPDATE usuarios SET nombre_fiscal = ?, documento_identidad = ?, direccion_completa = ? WHERE id = ?'
);
$stmt_fiscal->bind_param('sssi', $nombre_fiscal, $documento_identidad, $direccion_completa, $user_id);
$facturacion_guardada = $stmt_fiscal->execute();
$stmt_fiscal->close();
if (!$facturacion_guardada) {
    redirigir_checkout_error('facturacion_incompleta', $plan, $es_pago_proyecto_web);
}

if ($es_pago_proyecto_web) {
    $guard = $_SESSION['checkout_web_project_guard'] ?? [];
    $project_id = (int)($_POST['web_project_id'] ?? 0);
    $guard_user_id = (int)($guard['user_id'] ?? 0);
    $guard_project_id = (int)($guard['project_id'] ?? 0);
    $guard_amount = round((float)($guard['amount'] ?? 0), 2);
    $guard_age = time() - (int)($guard['created_at'] ?? 0);

    if (
        $project_id < 1
        || $guard_user_id !== $user_id
        || $guard_project_id !== $project_id
        || $guard_amount <= 0
        || $guard_age > 1800
    ) {
        unset($_SESSION['checkout_web_project_guard']);
        redirigir_checkout_error('guard_invalido', $plan, true);
    }

    $stmt_project = $conexion->prepare(
        "SELECT id, precio_final
         FROM proyectos_diseno_web
         WHERE id = ?
           AND user_id = ?
           AND estado = 'tramitando_propuesta'
           AND precio_final > 0
         LIMIT 1"
    );
    $stmt_project->bind_param('ii', $project_id, $user_id);
    $stmt_project->execute();
    $project = $stmt_project->get_result()->fetch_assoc();
    $stmt_project->close();

    if (!$project || round((float)$project['precio_final'], 2) !== $guard_amount) {
        unset($_SESSION['checkout_web_project_guard']);
        redirigir_checkout_error('presupuesto_no_disponible', $plan, true);
    }

    $conexion->begin_transaction();
    try {
        $concepto_factura = 'Desarrollo de Sitio Web Personalizado';
        [$base_imponible, $iva_importe, $total_factura] = desglose_iva_factura($guard_amount);
        $detalles_json = json_encode([
            ['item' => 'Proyecto Desarrollo Web Llave en Mano', 'precio' => $total_factura],
        ], JSON_UNESCAPED_UNICODE);

        $stmt_f = $conexion->prepare(
            "INSERT INTO facturas (user_id, fecha_emision, concepto, importe, base_imponible, iva_importe, detalles_json, tipo, estado)
             VALUES (?, NOW(), ?, ?, ?, ?, ?, 'factura', 'Pagado')"
        );
        $stmt_f->bind_param('isddds', $user_id, $concepto_factura, $total_factura, $base_imponible, $iva_importe, $detalles_json);
        if (!$stmt_f->execute()) {
            throw new RuntimeException('No se pudo emitir la factura del proyecto web.');
        }
        $stmt_f->close();

        $stmt_project = $conexion->prepare(
            "UPDATE proyectos_diseno_web
             SET estado = 'activo',
                 fecha_pago = NOW(),
                 fecha_garantia_expira = DATE_ADD(NOW(), INTERVAL 30 DAY)
             WHERE id = ? AND user_id = ?"
        );
        $stmt_project->bind_param('ii', $project_id, $user_id);
        if (!$stmt_project->execute()) {
            throw new RuntimeException('No se pudo activar la garantia del proyecto web.');
        }
        $stmt_project->close();

        // Si viene de una recompra, limpiamos la baja previa y devolvemos el acceso al modulo.
        asegurar_modulo_web_activo($conexion, $user_id);

        $mensaje_chat = 'Pago recibido. El proyecto web queda activado y la garantia de 30 dias empieza desde este momento. Puedes consultar la factura desde el panel.';
        $emisor_chat = 'admin';
        $stmt_chat = $conexion->prepare('INSERT INTO mensajes_chat (user_id, emisor, mensaje, leido) VALUES (?, ?, ?, 0)');
        if ($stmt_chat) {
            $stmt_chat->bind_param('iss', $user_id, $emisor_chat, $mensaje_chat);
            $stmt_chat->execute();
            $stmt_chat->close();
        }

        $conexion->commit();
        unset($_SESSION['checkout_web_project_guard']);
        $conexion->close();
        header('Location: panel.php?pago=web_project');
        exit;
    } catch (Throwable $e) {
        $conexion->rollback();
        error_log('[WEB_PROJECT_PAYMENT_FAIL] ID ' . $user_id . ' -> ' . $e->getMessage());
        redirigir_checkout_error('presupuesto_no_disponible', $plan, true);
    }
}

// Forzar el guardado de la fecha de alta real si el usuario no tiene una asignada
// REEMPLAZO DEFINITIVO:
$conexion->query("UPDATE usuarios SET fecha_alta = CURDATE() WHERE id = $user_id AND (fecha_alta IS NULL OR LENGTH(fecha_alta) < 4 OR fecha_alta < '1970-01-01')");

// ─── SOLUCIÓN AL PROBLEMA: GUARDAR OBLIGATORIAMENTE LAS CANTIDADES EN LA BASE DE DATOS ───
$stmt_u = $conexion->prepare("
    UPDATE usuarios 
    SET plan_contratado = ?, 
        storage_qty = ?, 
        multiuser_qty = ?, 
        extras_json = ?, 
        estado_servicio = 'Activo', 
        renovacion_automatica = 1 
    WHERE email = ?
");
$stmt_u->bind_param("siiss", $plan, $storage_qty, $multiuser_qty, $extras_json_str, $email_safe);
$stmt_u->execute();
$stmt_u->close();

if ($contrata_web_ai) {
    // Recompra del modulo: se reactiva el acceso y se abre una nueva propuesta limpia.
    $stmt_web = $conexion->prepare('UPDATE usuarios SET modulo_ia = 1 WHERE id = ?');
    $stmt_web->bind_param('i', $user_id);
    $stmt_web->execute();
    $stmt_web->close();

    $stmt_web = $conexion->prepare(
        "SELECT id, estado
         FROM proyectos_diseno_web
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt_web->bind_param('i', $user_id);
    $stmt_web->execute();
    $ultimo_proyecto_web = $stmt_web->get_result()->fetch_assoc();
    $stmt_web->close();

    if (!$ultimo_proyecto_web || ($ultimo_proyecto_web['estado'] ?? '') === 'reembolsado') {
        $precio_inicial_web = 0.00;
        $stmt_web = $conexion->prepare(
            "INSERT INTO proyectos_diseno_web
                (user_id, estado, precio_final, fecha_pago, fecha_garantia_expira)
             VALUES (?, 'tramitando_propuesta', ?, NULL, NULL)"
        );
        $stmt_web->bind_param('id', $user_id, $precio_inicial_web);
        $stmt_web->execute();
        $stmt_web->close();
    }
}

// ─── INSERTAR LA FACTURA REAL CON EL IMPORTE TOTAL REAL CONSOLIDADO ───
$concepto_factura = "Alta / Suscripción mensual - Plan " . $plan;
if ($storage_qty > 0) $concepto_factura .= " (+ Almacenamiento Extra)";
if ($multiuser_qty > 0) $concepto_factura .= " (+ Multi-usuarios)";

$detalles_factura = [
    ['item' => 'Plan ' . $plan, 'precio' => round($precio_plan_base, 2)],
];
if ($storage_qty > 0) {
    $detalles_factura[] = ['item' => "Almacenamiento Extra x$storage_qty", 'precio' => round($costo_almacenamiento, 2)];
}
if ($multiuser_qty > 0) {
    $detalles_factura[] = ['item' => "Multi-usuarios x$multiuser_qty", 'precio' => round($costo_multiusuarios, 2)];
}
foreach ($extras_para_json as $extra_factura) {
    [$extra_id, $extra_precio] = array_pad(explode('|', $extra_factura, 2), 2, '0');
    $extra_label = match ($extra_id) {
        'sql_php' => 'Acceso SQL/PHP',
        'domain' => 'Gestion de Dominio',
        'web_ai' => 'Modulo Diseño Web - Tramitando propuesta',
        default => ucfirst(str_replace('_', ' ', $extra_id)),
    };
    $precio_detalle = ($extra_id === 'web_ai') ? 0.00 : round((float)$extra_precio, 2);
    if ($extra_id === 'sql_php' && ($plan_upper === 'PROFESIONAL' || $plan_upper === 'ENTERPRISE')) {
        $precio_detalle = 0.00;
    }
    if ($extra_id === 'domain' && $plan_upper === 'ENTERPRISE') {
        $precio_detalle = 0.00;
    }
    $detalles_factura[] = ['item' => $extra_label, 'precio' => $precio_detalle];
}
[$base_imponible, $iva_importe, $total_factura] = desglose_iva_factura($importe_real_total);
$detalles_json = json_encode($detalles_factura, JSON_UNESCAPED_UNICODE);

$stmt_f = $conexion->prepare("INSERT INTO facturas (user_id, fecha_emision, concepto, importe, base_imponible, iva_importe, detalles_json, tipo, estado) VALUES (?, NOW(), ?, ?, ?, ?, ?, 'factura', 'Pagado')");
$stmt_f->bind_param("isddds", $user_id, $concepto_factura, $total_factura, $base_imponible, $iva_importe, $detalles_json);
$stmt_f->execute();
$stmt_f->close();

// MySQL se configura exclusivamente desde el formulario manual del panel.
// El pago habilita el servicio, pero nunca inventa ni inserta credenciales.

// Si se contratan usuarios adicionales en multiuser_qty, habilitar automáticamente cuentas FTP extra
if ($multiuser_qty > 0) {
    $owner_ftp = !empty($ftp_user_owner) ? $ftp_user_owner : sanitizar_nombre_local($nombre_owner);
    if (empty($owner_ftp)) {
        $owner_ftp = sanitizar_nombre_local(explode('@', $_SESSION['email'])[0]);
    }
    for ($i = 1; $i <= $multiuser_qty; $i++) {
        $ftp_subuser = "ftp_" . $user_id . "_" . $i;
        $ftp_subpass = bin2hex(random_bytes(4)); // Contraseña aleatoria

        // La solicitud queda pendiente para que crear_usuarios.py la aplique.
        $stmt_ftp = $conexion->prepare(
            "INSERT INTO ftp_cuentas_extra (user_id, ftp_user, ftp_pass, owner_ftp, estado)
             VALUES (?, ?, ?, ?, 'Pendiente')
             ON DUPLICATE KEY UPDATE estado='Pendiente'"
        );
        $stmt_ftp->bind_param("isss", $user_id, $ftp_subuser, $ftp_subpass, $owner_ftp);
        $stmt_ftp->execute();
        $stmt_ftp->close();
    }
}

// ─── GESTIÓN DE DOMINIOS ASOCIADOS (TU LÓGICA ORIGINAL CORREGIDA) ───
if (strpos(strtolower($servicio_base), 'dominio') !== false || $plan_upper === 'ENTERPRISE') {
    $dominio_solicitado = trim($_POST['dominio_solicitado'] ?? '');
    if (!empty($dominio_solicitado)) {
        // Asegurar que no tumbe la ejecución si ya existe el registro de dominio. Columnas correctas: user_id, dominio_propio, estado_dominio, fecha_caducidad
        $stmt_dominio = $conexion->prepare(
            "INSERT INTO dominios (user_id, dominio_propio, estado_dominio, fecha_caducidad)
             VALUES (?, ?, 'Pendiente', DATE_ADD(NOW(), INTERVAL 1 YEAR))
             ON DUPLICATE KEY UPDATE estado_dominio='Pendiente'"
        );
        $stmt_dominio->bind_param("is", $user_id, $dominio_solicitado);
        $stmt_dominio->execute();
        $stmt_dominio->close();
    }
}

// Ejecución de automatizaciones Linux en bash en caliente
shell_exec("sudo python3 /opt/tfg/scripts/crear_usuarios.py > /dev/null 2>&1");
shell_exec("sudo python3 /opt/tfg/scripts/virtualhosts.py > /dev/null 2>&1");

// ─── RE-SINCRONIZAR VARIABLES DE SESIÓN (TU ESTRUCTURA ORIGINAL AL COMPLETO) ───
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
    
    // Invocamos la función global nativa para mapear la sesión con el estado refrescado
    login_user_from_row($usuario);
}

$conexion->close();
header('Location: panel.php?pago=exito');
exit;