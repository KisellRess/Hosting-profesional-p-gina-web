<?php
/* ============================================================
   ARCHIVO: get_chat_sse.php
   FUNCION: entregar mensajes nuevos del chat.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Para servidores con Nginx

session_start();
require_once 'conexiones.php';

if (!isset($_SESSION['user_id'])) {
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// LÓGICA DE VISOR ADMIN: Si es admin, puede pedir el chat de otro usuario vía GET
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin' && isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
}

session_write_close(); // Liberar sesión para evitar bloqueo

$db = getConexion();
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// --- CARGA DE HISTORIAL INICIAL ---
$sql_history = "SELECT * FROM mensajes_chat WHERE user_id = $user_id AND id > $last_id ORDER BY id ASC";
$res_history = $db->query($sql_history);
if ($res_history) {
    while ($row = $res_history->fetch_assoc()) {
        echo "data: " . json_encode($row) . "\n\n";
        $last_id = $row['id']; // Importante para que el bucle siga desde aquí
    }
    ob_flush();
    flush();
}
// ----------------------------------

// Bucle infinito para SSE
while (true) {
    $sql = "SELECT id, user_id, emisor, mensaje, fecha FROM mensajes_chat WHERE user_id = $user_id AND id > $last_id ORDER BY id ASC";
    $res = $db->query($sql);

    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            echo "data: " . json_encode($row) . "\n\n";
            $last_id = (int)$row['id'];
        }
        ob_flush();
        flush();
    }

    // 2. Comprobar si el Admin está escribiendo (opcional: usando una columna en usuarios o tabla meta)
    // Por ahora simularemos la detección mediante un flag en la sesión o DB
    // echo "event: typing\n";
    // echo "data: {\"typing\": true}\n\n";

    // Dormir un poco para no saturar la CPU
    usleep(500000); // 0.5 segundos
    
    // Si la conexión se cierra, salimos
    if (connection_aborted()) break;
}

$db->close();
?>