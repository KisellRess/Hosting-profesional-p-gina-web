<?php
session_start();
require_once 'conexiones.php';

// Validar que la sesión es de admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    exit('no_autorizado');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $db = getConexion();
    $id_safe = $db->real_escape_string($id);
    
    // Marcar como reconocida
    $sql = "UPDATE alertas_admin SET reconocida = 1 WHERE id = $id_safe";
    if ($db->query($sql)) {
        echo 'ok';
        exit;
    } else {
        echo 'error';
        exit;
    }
    
    $db->close();
} else {
    echo 'id_invalido';
}
?>
