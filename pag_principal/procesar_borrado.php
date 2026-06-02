<?php
session_start();
require_once 'conexiones.php';

// Validar estrictamente el rol de administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') { 
    exit("No autorizado"); 
}

$user_id = $_GET['id'] ?? 0;
$admin_nom = $_SESSION['usuario'] ?? 'Admin';

if ($user_id > 0) {
    $db = getConexion();
    
    // Obtener nombre del usuario afectado para el log/alerta antes de que sea eliminado
    $res_user_purgado = $db->query("SELECT nombre FROM usuarios WHERE id = $user_id LIMIT 1");
    $user_purgado_name = ($res_user_purgado && $res_user_purgado->num_rows > 0) ? $res_user_purgado->fetch_assoc()['nombre'] : "ID $user_id";
    
    $affected_nom = $db->real_escape_string($user_purgado_name);
    $admin_nom_escaped = $db->real_escape_string($admin_nom);
    
    // Registrar el inicio de la purga en el archivo de log
    $log_dir = "/opt/tfg/scripts/logs";
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $log_file = "$log_dir/acciones.log";
    $date_str = date('Y-m-d H:i:s');
    $log_msg = "[$date_str] [PURGA_INICIO] Admin '$admin_nom' inició la purga del usuario '$user_purgado_name' (ID: $user_id)\n";
    @file_put_contents($log_file, $log_msg, FILE_APPEND);

    // Crear una alerta de inicio de purga
    $motivo = "Purga de usuario '$affected_nom' iniciada por Admin '$admin_nom_escaped'";
    $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($user_id, '$affected_nom', '$motivo', '⚙️', 0, NOW())");

    // 1. Marcamos el servicio de usuario para borrar (Para Python crear_usuarios.py)
    $sql = "UPDATE usuarios SET estado_servicio = 'Para_Borrar' WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // 2. Marcamos sus dominios para borrar (Para Python virtualhosts.py)
    $sql_dom = "UPDATE dominios SET estado_dominio = 'Para_Borrar' WHERE user_id = ?";
    $stmt_dom = $db->prepare($sql_dom);
    $stmt_dom->bind_param("i", $user_id);
    $stmt_dom->execute();

    // 3. Marcamos sus bases de datos para borrar (Para Python mysql_worker.py)
    $sql_mysql = "UPDATE modulo_mysql SET estado = 'Para_Borrar' WHERE user_id = ?";
    $stmt_mysql = $db->prepare($sql_mysql);
    $stmt_mysql->bind_param("i", $user_id);
    $stmt_mysql->execute();

    // Ejecución síncrona en cascada secuencial capturando salida y errores
    $outputs = [];
    $errors = 0;

    // 1. Eliminación física de VHosts individuales en Apache
    exec("sudo python3 /opt/tfg/scripts/virtualhosts.py 2>&1", $outputs[], $retval_vh);
    if ($retval_vh !== 0) $errors++;

    // 2. Eliminación física de Bases de Datos MySQL y usuarios asociados
    exec("sudo python3 /opt/tfg/scripts/mysql_worker.py 2>&1", $outputs[], $retval_my);
    if ($retval_my !== 0) $errors++;

    // 3. Eliminación física de usuarios SO Linux, homes y purga final de tablas
    exec("sudo python3 /opt/tfg/scripts/crear_usuarios.py 2>&1", $outputs[], $retval_cr);
    if ($retval_cr !== 0) $errors++;

    $date_str = date('Y-m-d H:i:s');
    if ($errors === 0) {
        $log_msg = "[$date_str] [PURGA_EXITO] Usuario '$user_purgado_name' (ID: $user_id) purgado con éxito de Apache, MySQL y Linux por '$admin_nom'.\n";
        @file_put_contents($log_file, $log_msg, FILE_APPEND);
        
        $motivo_fin = "Purga de usuario '$affected_nom' completada con éxito";
        $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($user_id, '$affected_nom', '$motivo_fin', '✅', 0, NOW())");
    } else {
        $serialized_outputs = json_encode($outputs);
        $log_msg = "[$date_str] [PURGA_ERROR] Fallos en purga de '$user_purgado_name' (ID: $user_id). Errores: $errors. Códigos: VH=$retval_vh, MY=$retval_my, CR=$retval_cr. Logs: $serialized_outputs\n";
        @file_put_contents($log_file, $log_msg, FILE_APPEND);

        $motivo_fin = "Purga de usuario '$affected_nom' finalizada con errores";
        $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($user_id, '$affected_nom', '$motivo_fin', '❌', 0, NOW())");
    }

    header("Location: usuarios.php?msg=borrado_pendiente");
    $db->close();
} else {
    header("Location: usuarios.php");
}
?>