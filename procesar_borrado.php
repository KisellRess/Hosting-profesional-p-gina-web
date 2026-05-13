<?php
session_start();
require_once 'conexiones.php';

// Solo permitimos el acceso si es Admin (esto deberías validarlo con tu lógica de roles si la tienes)
if (!isset($_SESSION['user_id'])) { 
    exit("No autorizado"); 
}

$user_id = $_GET['id'] ?? 0;

if ($user_id > 0) {
    $db = getConexion();
    
    // 1. Marcamos el servicio de usuario para borrar (Para Python crear_usuarios.py)
    $sql = "UPDATE usuarios SET estado_servicio = 'Para_Borrar' WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // 2. Marcamos sus dominios para borrar (Para Python virtualhosts.py)
    // Así se borrarán los archivos .conf y se hará a2dissite
    $sql_dom = "UPDATE dominios SET estado_dominio = 'Para_Borrar' WHERE user_id = ?";
    $stmt_dom = $db->prepare($sql_dom);
    $stmt_dom->bind_param("i", $user_id);
    $stmt_dom->execute();

    header("Location: usuarios.php?msg=borrado_pendiente");
    $db->close();
} else {
    header("Location: usuarios.php");
}
?>
