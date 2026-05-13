<?php
session_start();
require_once 'conexiones.php';

// Seguridad: Solo admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    die("No autorizado");
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db = getConexion();
    
    // Actualizar el estado en la base de datos
    $stmt = $db->prepare("UPDATE alertas_admin SET reconocida = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo "Éxito: Alerta #$id reconocida.";
    } else {
        http_response_code(500);
        echo "Error al actualizar la base de datos.";
    }
    
    $stmt->close();
    $db->close();
} else {
    http_response_code(400);
    echo "ID de alerta no proporcionado.";
}
?>
