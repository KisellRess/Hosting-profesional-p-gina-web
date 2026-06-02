<?php
/* ============================================================
   ARCHIVO: solicitar_reembolso_web.php
   FUNCION: emitir una factura rectificativa del modulo Diseno Web.
   ============================================================ */

require_once 'sessions.php';
require_auth();
require_once 'conexiones.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: frame-ancestors 'self'");
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function es_ajax_reembolso(): bool {
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

function responder_reembolso(bool $ok, string $destino, int $status = 303, array $extra = []): void {
    if (es_ajax_reembolso()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($ok ? 200 : ($status >= 400 ? $status : 422));
        echo json_encode(array_merge(['ok' => $ok, 'redirect' => $destino], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code($status);
    header('Location: ' . $destino);
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id < 1) {
    responder_reembolso(false, 'panel.php?error=no_auth', 403);
}

$token_post = (string)($_POST['csrf_token'] ?? '');
$token_session = (string)($_SESSION['csrf_reembolso_web'] ?? '');
$bypass_csrf = !empty($_SESSION['admin_original']);

if (!$bypass_csrf && ($token_post === '' || $token_session === '' || !hash_equals($token_session, $token_post))) {
    responder_reembolso(false, 'panel.php?error=csrf_invalid', 403);
}

$factura_id = filter_input(INPUT_POST, 'factura_id', FILTER_VALIDATE_INT);
$proyecto_id = filter_input(INPUT_POST, 'proyecto_id', FILTER_VALIDATE_INT);
if (!$factura_id || $factura_id < 1 || !$proyecto_id || $proyecto_id < 1) {
    responder_reembolso(false, 'panel.php?error=pago_id_invalido', 400, [
        'message' => 'No se recibio un ID de pago o proyecto valido.',
    ]);
}

$db = getConexion();
$db->begin_transaction();

try {
    // Buscar el proyecto activo y la factura pagada indicada por el formulario.
    $stmt = $db->prepare(
        "SELECT p.id, p.precio_final, p.fecha_garantia_expira,
                f.id AS factura_id, f.importe, f.estado AS factura_estado
         FROM proyectos_diseno_web p
         JOIN facturas f
           ON f.user_id = p.user_id
          AND f.id = ?
          AND f.tipo = 'factura'
          AND f.concepto = 'Desarrollo de Sitio Web Personalizado'
          AND f.estado = 'Pagado'
         WHERE p.id = ?
           AND p.user_id = ?
           AND p.estado = 'activo'
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bind_param('iii', $factura_id, $proyecto_id, $user_id);
    $stmt->execute();
    $proyecto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$proyecto) {
        throw new RuntimeException('No se ha encontrado un pago activo y reembolsable para este usuario.');
    }

    // Verificar ventana de garantía
    $fecha_garantia = new DateTime($proyecto['fecha_garantia_expira']);
    $hoy            = new DateTime();
    if ($hoy > $fecha_garantia) {
        throw new RuntimeException('El periodo de cobertura de la garantía de 30 días ha expirado.');
    }

    // Calcular desglose inverso en negativo para el abono
    $total_negativo = round(abs((float)$proyecto['precio_final']), 2) * -1;
    $base_negativa  = round($total_negativo / 1.21, 2);
    $iva_negativa   = round($total_negativo - $base_negativa, 2);

    $concepto = 'Reembolso por desistimiento - Garantía 30 días (Desarrollo Web)';
    $tipo = 'reembolso';
    // El reembolso queda como factura negativa pagada; la factura original se conserva intacta.
    $estado = 'Pagado';

    $detalles_json = json_encode([
        [
            'item' => 'Abono e inversion de propuesta de Diseno Web',
            'precio' => $total_negativo,
            'factura_original_id' => (int)$factura_id,
            'proyecto_id' => (int)$proyecto['id']
        ]
    ], JSON_UNESCAPED_UNICODE);

    // CONSULTA REPARADA MILIMÉTRICAMENTE: El orden de los campos mapea con el bind_param posterior
    $stmt = $db->prepare(
        'INSERT INTO facturas (user_id, fecha_emision, concepto, importe, base_imponible, iva_importe, detalles_json, tipo, estado)
         VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        throw new RuntimeException('Error interno al preparar el asiento contable.');
    }

    // bind_param: user_id [i], concepto [s], importe [d], base_imponible [d], iva_importe [d], detalles_json [s], tipo [s], estado [s]
    $stmt->bind_param(
        'isdddsss', 
        $user_id, 
        $concepto, 
        $total_negativo, 
        $base_negativa, 
        $iva_negativa, 
        $detalles_json, 
        $tipo, 
        $estado
    );

    if (!$stmt->execute()) {
        $error_msg = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Error de base de datos en inserción: ' . $error_msg);
    }
    $stmt->close();

    // En este esquema no existe estado "inactivo" para proyectos: el acceso real se revoca en usuarios.
    $stmt = $db->prepare("UPDATE proyectos_diseno_web SET estado = 'reembolsado' WHERE id = ?");
    if (!$stmt) {
        throw new RuntimeException('Error interno al preparar la actualización del proyecto.');
    }
    $stmt->bind_param('i', $proyecto['id']);
    if (!$stmt->execute()) {
        $error_msg = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Error de base de datos al actualizar el estado del proyecto: ' . $error_msg);
    }
    $stmt->close();

    // Compatibilidad opcional: si existe una tabla pedidos con columna estado, se sincroniza tambien.
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'pedidos'
           AND COLUMN_NAME = 'estado'"
    );
    if ($stmt) {
        $stmt->execute();
        $tiene_pedidos = ((int)($stmt->get_result()->fetch_assoc()['total'] ?? 0)) > 0;
        $stmt->close();

        if ($tiene_pedidos) {
            $stmt = $db->prepare("UPDATE pedidos SET estado = 'reembolsado' WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $factura_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // ─── ELIMINACIÓN DEL MÓDULO WEB EN EXTRAS_JSON DEL USUARIO ───
    $stmt = $db->prepare('SELECT extras_json FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $extras_actuales = json_decode($row_user['extras_json'] ?? '[]', true);
    if (!is_array($extras_actuales)) { $extras_actuales = []; }

    // Filtrar limpiando cualquier rastro de la clave técnica 'web_ai'
    $extras_limpios  = array_values(array_filter($extras_actuales, static fn($e) => !str_contains((string)$e, 'web_ai')));
    $extras_json_limpio = json_encode($extras_limpios, JSON_UNESCAPED_UNICODE);

    // Actualizar fila del cliente: apagamos modulo_ia a 0 y guardamos el JSON limpio
    $stmt = $db->prepare('UPDATE usuarios SET modulo_ia = 0, extras_json = ? WHERE id = ?');
    $stmt->bind_param('si', $extras_json_limpio, $user_id);
    $stmt->execute();
    $stmt->close();

    // Mensaje automatico de auditoria visible en el chat del modulo.
    $mensaje_desistimiento = 'El desistimiento se ha procesado correctamente. Se ha emitido la factura de reembolso y su acceso al módulo ha sido revocado. Podrá volver a acceder si adquiere el módulo nuevamente';
    $emisor_desistimiento = 'admin';
    $stmt = $db->prepare('INSERT INTO mensajes_chat (user_id, emisor, mensaje, leido) VALUES (?, ?, ?, 0)');
    if ($stmt) {
        $stmt->bind_param('iss', $user_id, $emisor_desistimiento, $mensaje_desistimiento);
        $stmt->execute();
        $stmt->close();
    }

    // ─── RE-SINCRONIZAR LA SESIÓN ACTIVA EN PANTALLA DE FORMA INMEDIATA ───
    $extras_visibles = [];
    $nombres_mapeo = [
        'sql_php|5.00'  => 'Acceso SQL/PHP',
        'domain|15.00'  => 'Gestión de Dominio'
    ];
    foreach ($extras_limpios as $ex) {
        if (isset($nombres_mapeo[$ex])) {
            $extras_visibles[] = $nombres_mapeo[$ex];
        }
    }
    $_SESSION['extras'] = $extras_visibles;
    unset($_SESSION['csrf_reembolso_web']); // Consumir el token de seguridad

    $db->commit();
    $db->close();

    responder_reembolso(true, 'panel.php?msg=reembolso_procesado', 303, [
        'estado' => 'Cancelado',
        'proyecto_id' => (int)$proyecto['id'],
        'factura_id' => (int)$factura_id,
        'factura_estado' => 'Pagado',
        'reembolso_estado' => 'Pagado',
    ]);

} catch (Throwable $e) {
    if ($db instanceof mysqli) {
        $db->rollback();
        $db->close();
    }
    // Registrar el error exacto en el log interno para auditorías rápidas
    error_log('[REEMBOLSO_FAIL] ID ' . $user_id . ' -> ' . $e->getMessage());
    responder_reembolso(false, 'panel.php?error=db_excepcion', 422, [
        'message' => 'No se pudo procesar el reembolso. Revisa que el proyecto siga activo y dentro de garantia.',
    ]);
}