<?php
// marcar_alerta.php
require_once 'conexiones.php';
session_start();

// Solo el admin puede gestionar
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die('no_auth');
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $db = getConexion();
    
    // Obtener estado actual para alternar
    $res = $db->query("SELECT reconocida FROM alertas_admin WHERE id = $id");
    if ($row = $res->fetch_assoc()) {
        $nuevo_estado = $row['reconocida'] == 1 ? 0 : 1;
        
        $stmt = $db->prepare("UPDATE alertas_admin SET reconocida = ? WHERE id = ?");
        $stmt->bind_param("ii", $nuevo_estado, $id);
        
        if ($stmt->execute()) {
            echo "ok";
        } else {
            echo "error";
        }
        $stmt->close();
    } else {
        echo "not_found";
    }
    
    $db->close();
}
?>