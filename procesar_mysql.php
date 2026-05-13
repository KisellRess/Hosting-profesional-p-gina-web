<?php
// ─── Diagnóstico de errores (quitar en producción estable) ───
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'sessions.php';
require_auth();
require_once 'conexiones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel.php');
    exit;
}

try {
    $db = getConexion();
    $user_id = (int)$_SESSION['user_id'];

    // Sanitización: los nombres de DB se prefijan para evitar colisiones entre usuarios
    $prefijo    = "u" . $user_id . "_";
    $raw_name   = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['db_name'] ?? ''));
    $raw_user   = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['db_user'] ?? ''));
    $raw_pass   = trim($_POST['db_pass'] ?? '');

    if (empty($raw_name) || empty($raw_user)) {
        header('Location: panel.php?error=mysql_vacio');
        exit;
    }

    $db_name_s = $db->real_escape_string($prefijo . $raw_name);
    $db_user_s = $db->real_escape_string($prefijo . $raw_user);
    $db_pass_s = $db->real_escape_string($raw_pass);

    // CORRECCIÓN: La tabla modulo_mysql debe tener un UNIQUE KEY en user_id
    // para que ON DUPLICATE KEY funcione. 
    // Si no existe ese índice, el INSERT simplemente crea filas duplicadas.
    // Usamos un INSERT con ON DUPLICATE KEY UPDATE basado en user_id.
    //
    // REQUISITO SQL (ejecutar una vez en el servidor si no existe):
    //   ALTER TABLE modulo_mysql ADD UNIQUE KEY uq_user (user_id);
    //
    if (empty($raw_pass)) {
        // Solo actualizar nombre y usuario de la DB
        $sql = "INSERT INTO modulo_mysql (user_id, db_name, db_user, estado) 
                VALUES ($user_id, '$db_name_s', '$db_user_s', 'Tramitando')
                ON DUPLICATE KEY UPDATE 
                    db_name  = VALUES(db_name),
                    db_user  = VALUES(db_user),
                    estado   = 'Tramitando'";
    } else {
        // Actualizar todo, incluyendo contraseña
        $sql = "INSERT INTO modulo_mysql (user_id, db_name, db_user, db_pass, estado) 
                VALUES ($user_id, '$db_name_s', '$db_user_s', '$db_pass_s', 'Tramitando')
                ON DUPLICATE KEY UPDATE 
                    db_name  = VALUES(db_name),
                    db_user  = VALUES(db_user),
                    db_pass  = VALUES(db_pass),
                    estado   = 'Tramitando'";
    }

    if ($db->query($sql)) {
        header('Location: panel.php?ok=mysql_pendiente');
    } else {
        error_log("[procesar_mysql] Error SQL: " . $db->error . " | user_id=$user_id");
        header('Location: panel.php?error=db_error');
    }

} catch (Exception $e) {
    error_log("[procesar_mysql] Excepción inesperada: " . $e->getMessage());
    header('Location: panel.php?error=db_excepcion');
} finally {
    if (isset($db) && $db instanceof mysqli) {
        $db->close();
    }
}
exit;
?>