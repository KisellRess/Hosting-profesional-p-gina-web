<?php
session_start();
require_once 'sessions.php';

// Seguridad: Solo usuarios autenticados
if (!isset($_SESSION['usuario'])) {
    http_response_code(403);
    die(json_encode(["error" => "No autorizado"]));
}

// Recibir datos del chat (JS)
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if ($data) {
    $username = $_SESSION['usuario'];
    
    // Escapar el JSON para la terminal
    $payload = escapeshellarg($json_data);
    $user_arg = escapeshellarg($username);
    
    // Ejecutar script de Python
    // Nota: Asegúrate de que el usuario de Apache tiene permisos en /opt/tfg/conv/
    $comando = "python3 save_chat.py $user_arg $payload 2>&1";
    $output = shell_exec($comando);
    
    echo json_encode([
        "status" => "success",
        "python_output" => $output
    ]);
} else {
    echo json_encode(["error" => "Datos inválidos"]);
}
?>
