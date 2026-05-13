<?php
session_start();
require_once 'conexiones.php';

// 1. Seguridad: Validar que el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$db = getConexion();
$user_id = (int)$_SESSION['user_id'];
// Limpieza profunda del mensaje para evitar SQLi y XSS básico
$mensaje = $db->real_escape_string(strip_tags($_POST['mensaje'] ?? ''));
$emisor = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') ? 'admin' : 'usuario';

// Si es admin enviando una respuesta, el ID del usuario destino viene por POST
if ($emisor === 'admin' && isset($_POST['target_user_id'])) {
    $user_id = (int)$_POST['target_user_id'];
}

if (empty($mensaje)) {
    echo json_encode(['status' => 'error', 'message' => 'Mensaje vacío']);
    exit;
}

// 2. Guardar el mensaje del usuario (o admin)
$sql = "INSERT INTO mensajes_chat (user_id, emisor, mensaje, leido) VALUES ($user_id, '$emisor', '$mensaje', 0)";
$res = $db->query($sql);

if ($res) {
    // --- ALERTA ADMIN: Mensaje nuevo ---
    if ($emisor === 'usuario') {
        $check_alert = $db->query("SELECT id FROM alertas_admin WHERE user_id = $user_id AND motivo = 'Mensaje nuevo' AND reconocida = 0 LIMIT 1");
        if ($check_alert->num_rows === 0) {
            $nombre_user = $db->real_escape_string($_SESSION['usuario'] ?? 'Usuario');
            $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($user_id, '$nombre_user', 'Mensaje nuevo', '⚠️', 0, NOW())");
        }
    }

    // 3. LÓGICA DE AUTO-RESPUESTA (Cuestionario)
    // 1. Consultar si el usuario tiene el módulo activo en DB
    $user_data = $db->query("SELECT modulo_ia FROM usuarios WHERE id = $user_id")->fetch_assoc();

    // 2. Si el usuario envía mensaje y tiene el módulo activo...
    if ($emisor === 'usuario' && ($user_data['modulo_ia'] == 1)) {
        
        // Comprobar si ya se le dio la bienvenida
        $count_res = $db->query("SELECT COUNT(*) as total FROM mensajes_chat WHERE user_id = $user_id AND emisor = 'admin'");
        $count = $count_res->fetch_assoc()['total'];

        if ($count == 0) {
            // Mensaje 1: Bienvenida Legal y Compromiso
            $msg1 = "¡Hola! Gracias por contratar el módulo de Diseño Web IA. Te recordamos que si no llegamos a un acuerdo sobre el diseño final, puedes solicitar el reembolso íntegro en un plazo de 30 días.";
            $db->query("INSERT INTO mensajes_chat (user_id, emisor, mensaje) VALUES ($user_id, 'admin', '$msg1')");

            // Mensaje 2: Inicio Cuestionario
            $msg2 = "En breves un administrador se pondrá en contacto contigo. Mientras tanto, ¿podrías decirnos si te ha gustado alguna web de nuestro 'Showcase'? (1. Sí, pero no sé cuál / 2. No, quiero algo original / 3. Me gustan éstas: ...)";
            $db->query("INSERT INTO mensajes_chat (user_id, emisor, mensaje) VALUES ($user_id, 'admin', '$msg2')");
        }
    }
    
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar']);
}

$db->close();
?>
