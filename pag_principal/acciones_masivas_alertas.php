<?php
// acciones_masivas_alertas.php
require_once 'conexiones.php';
session_start();

// Solo el admin puede gestionar
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die('no_auth');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $db = getConexion();
    $accion = $_POST['accion'];
    $ids = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : [];

    if ($accion === 'reconocer') {
        if (empty($ids)) die('error_no_ids');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("UPDATE alertas_admin SET reconocida = 1 WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        if ($stmt->execute()) echo "ok";
        else echo "error_db";
        $stmt->close();

    } elseif ($accion === 'restaurar') {
        if (empty($ids)) die('error_no_ids');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("UPDATE alertas_admin SET reconocida = 0 WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        if ($stmt->execute()) echo "ok";
        else echo "error_db";
        $stmt->close();

    } elseif ($accion === 'eliminar') {
        // Eliminar físicamente todas las reconocidas (VACIAR HISTORIAL)
        $stmt = $db->prepare("DELETE FROM alertas_admin WHERE reconocida = 1");
        if ($stmt->execute()) echo "ok";
        else echo "error_db_delete";
        $stmt->close();
    }

    $db->close();
}
?>