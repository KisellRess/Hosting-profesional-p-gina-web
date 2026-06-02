<?php
require_once 'sessions.php';
require_once 'conexiones.php';
require_auth();

// Solo el admin puede ejecutar borrados
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_panel.php');
    exit;
}

$user_id = intval($_POST['user_id'] ?? 0);
if ($user_id <= 0) {
    header('Location: admin_panel.php?error=id_invalido');
    exit;
}

$conexion = getConexion();
$user_id_safe = (int) $user_id;

// 1. Obtener datos del usuario antes de borrar
$query_check = "SELECT id, email, estado_servicio FROM usuarios WHERE id = $user_id_safe LIMIT 1";
$result_check = $conexion->query($query_check);

if (!$result_check || $result_check->num_rows === 0) {
    $conexion->close();
    header('Location: admin_panel.php?error=usuario_no_encontrado');
    exit;
}

$usuario_row = $result_check->fetch_assoc();

// Solo borrar usuarios que hayan solicitado la cancelación
/* // Comenta este bloque si quieres permitir el borrado de usuarios activos
if ($usuario_row['estado_servicio'] !== 'Cancelado') {
    $conexion->close();
    header('Location: admin_panel.php?error=no_cancelado');
    exit;
}
*/

// 2. Borrar carpeta FTP del usuario del servidor
$user_dir = __DIR__ . '/storage/user_' . $user_id_safe;
if (is_dir($user_dir)) {
    // Borrado recursivo de la carpeta
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($user_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($user_dir);
}

// 3. Eliminar al usuario de la base de datos
$query_delete = "DELETE FROM usuarios WHERE id = $user_id_safe LIMIT 1";
$ejecutar = $conexion->query($query_delete);

$conexion->close();

if ($ejecutar) {
    header('Location: admin_panel.php?success=borrado_completado');
} else {
    header('Location: admin_panel.php?error=db_delete_fallido');
}
exit;
