<?php
/* ============================================================
   ARCHIVO: cancelar_suscripcion.php
   FUNCION: registrar la solicitud de cancelacion.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

/* SECCION 1: sesion obligatoria y respuesta compatible con formulario o JS. */
require_once 'sessions.php';
require_once 'conexiones.php';
require_auth();

function responder_cancelacion(bool $ok, string $destino): void {
    $es_ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

    if ($es_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($ok ? 200 : 422);
        echo json_encode(['ok' => $ok, 'redirect' => $destino], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ' . $destino);
    exit;
}

/* SECCION 2: solo se admiten solicitudes realizadas mediante formulario. */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel.php');
    exit;
}

$conexion = getConexion();

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0 && !empty($_SESSION['email'])) {
    $stmt_usuario = $conexion->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt_usuario->bind_param('s', $_SESSION['email']);
    $stmt_usuario->execute();
    $usuario = $stmt_usuario->get_result()->fetch_assoc();
    $stmt_usuario->close();
    $user_id = (int)($usuario['id'] ?? 0);
    $_SESSION['user_id'] = $user_id;
}

if ($user_id <= 0) {
    $conexion->close();
    responder_cancelacion(false, 'panel.php?error=pago_error');
}

/* SECCION 3: marcar la cuenta y generar una unica alerta para administracion. */
$stmt = $conexion->prepare("UPDATE usuarios SET estado_servicio = 'Cancelado', fecha_cancelacion = NOW() WHERE id = ?");
$stmt->bind_param('i', $user_id);
$actualizado = $stmt->execute();
$stmt->close();

if (!$actualizado) {
    $conexion->close();
    responder_cancelacion(false, 'panel.php?error=pago_error');
}

$motivo = 'Solicitud borrado cuenta';
$stmt = $conexion->prepare(
    'SELECT id FROM alertas_admin WHERE user_id = ? AND motivo = ? AND reconocida = 0 LIMIT 1'
);
$stmt->bind_param('is', $user_id, $motivo);
$stmt->execute();
$alerta_existe = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$alerta_existe) {
    $nombre = $_SESSION['usuario'] ?? 'Usuario';
    $simbolo = "\u{26A0}\u{FE0F}";
    $stmt = $conexion->prepare(
        'INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha)
         VALUES (?, ?, ?, ?, 0, NOW())'
    );
    $stmt->bind_param('isss', $user_id, $nombre, $motivo, $simbolo);
    $stmt->execute();
    $stmt->close();
}

$_SESSION['estado_servicio'] = 'Cancelado';
$conexion->close();
responder_cancelacion(true, 'panel.php?msg=cancelacion_pendiente');