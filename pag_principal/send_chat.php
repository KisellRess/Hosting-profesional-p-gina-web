<?php
/* ============================================================
   ARCHIVO: send_chat.php
   FUNCION: guardar mensajes de presupuesto y avisos.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

/* SECCION 1: autenticar la llamada y admitir formulario o JSON desde JavaScript. */
session_start();
require_once 'conexiones.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$payload = $_POST;
if (str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    $json = json_decode(file_get_contents('php://input'), true);
    $payload = is_array($json) ? $json : [];
}

$mensaje = trim(strip_tags((string)($payload['mensaje'] ?? '')));
if (!empty($payload['cuestionario']) && is_array($payload['cuestionario'])) {
    $respuestas = [];
    foreach ($payload['cuestionario'] as $pregunta => $respuesta) {
        $clave = trim(strip_tags((string)$pregunta));
        $valor = trim(strip_tags((string)$respuesta));
        if ($valor !== '') {
            $respuestas[] = $clave . ': ' . $valor;
        }
    }
    if ($respuestas !== []) {
        $mensaje .= "\n" . implode("\n", $respuestas);
    }
}

if ($mensaje === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Mensaje vacio']);
    exit;
}

/* SECCION 2: limpiar el mensaje y guardar la conversacion solicitada. */
$db = getConexion();
$user_id = (int)$_SESSION['user_id'];
$emisor = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') ? 'admin' : 'usuario';

if ($emisor === 'admin' && isset($payload['target_user_id'])) {
    $user_id = (int)$payload['target_user_id'];
}

$stmt = $db->prepare('INSERT INTO mensajes_chat (user_id, emisor, mensaje, leido) VALUES (?, ?, ?, 0)');
$stmt->bind_param('iss', $user_id, $emisor, $mensaje);
$guardado = $stmt->execute();
$ultimo_mensaje_id = $guardado ? (int)$db->insert_id : 0;
$mensaje_guardado_id = $ultimo_mensaje_id;
$stmt->close();

if (!$guardado) {
    $db->close();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar']);
    exit;
}

/* SECCION 3: avisar al administrador y preparar bienvenida del modulo Web. */
if ($emisor === 'usuario') {
    $motivo = 'Mensaje nuevo';
    $stmt = $db->prepare(
        'SELECT id FROM alertas_admin WHERE user_id = ? AND motivo = ? AND reconocida = 0 LIMIT 1'
    );
    $stmt->bind_param('is', $user_id, $motivo);
    $stmt->execute();
    $alerta_existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$alerta_existe) {
        $nombre = $_SESSION['usuario'] ?? 'Usuario';
        $simbolo = "\u{26A0}\u{FE0F}";
        $stmt = $db->prepare(
            'INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha)
             VALUES (?, ?, ?, ?, 0, NOW())'
        );
        $stmt->bind_param('isss', $user_id, $nombre, $motivo, $simbolo);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $db->prepare('SELECT modulo_ia FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (($user_data['modulo_ia'] ?? 0) == 1) {
        $emisor_admin = 'admin';
        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM mensajes_chat WHERE user_id = ? AND emisor = ?');
        $stmt->bind_param('is', $user_id, $emisor_admin);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        if ($count === 0) {
            $bienvenida = 'Hola. Gracias por contratar el modulo de Diseno Web Personalizado. Si no llegamos a un acuerdo satisfactorio sobre la estructura final de tu pagina, puedes solicitar el reembolso integro en un plazo de 30 dias.';
            $pregunta = 'En breve un administrador se pondra en contacto contigo a traves de este canal para valorar tu caso. Mientras tanto, cuentanos que webs de nuestro Showcase se acercan mas al resultado estetico que buscas.';
            $stmt = $db->prepare('INSERT INTO mensajes_chat (user_id, emisor, mensaje, leido) VALUES (?, ?, ?, 0)');
            foreach ([$bienvenida, $pregunta] as $respuesta) {
                $stmt->bind_param('iss', $user_id, $emisor_admin, $respuesta);
                $stmt->execute();
                $ultimo_mensaje_id = max($ultimo_mensaje_id, (int)$db->insert_id);
            }
            $stmt->close();
        }
    }
}

$db->close();
echo json_encode([
    'status' => 'success',
    'last_id' => $ultimo_mensaje_id,
    'message_id' => $mensaje_guardado_id
], JSON_UNESCAPED_UNICODE);