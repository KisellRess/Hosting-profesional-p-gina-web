<?php
require_once 'sessions.php';
require_once 'conexiones.php';

// PROTECCIÓN: Solo accesible para administradores
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conexion = getConexion();
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($user_id > 0) {
        // Devolvemos al usuario a estado Activo y borramos la fecha de cancelación
        $query = "UPDATE usuarios SET 
                  estado_servicio = 'Activo', 
                  fecha_cancelacion = NULL 
                  WHERE id = $user_id LIMIT 1";

        if ($conexion->query($query)) {
            // --- ALERTA ADMIN: Solicitud restablecer cuenta ---
            $check_alert = $conexion->query("SELECT id FROM alertas_admin WHERE user_id = $user_id AND motivo = 'Solicitud restablecer cuenta' AND reconocida = 0 LIMIT 1");
            if ($check_alert->num_rows === 0) {
                // Buscamos el nombre del usuario si no lo tenemos
                $res_n = $conexion->query("SELECT nombre FROM usuarios WHERE id = $user_id LIMIT 1");
                $u_nom = ($res_n && $row_n = $res_n->fetch_assoc()) ? $row_n['nombre'] : 'Usuario';
                $u_nom_s = $conexion->real_escape_string($u_nom);
                $conexion->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($user_id, '$u_nom_s', 'Solicitud restablecer cuenta', '❓', 0, NOW())");
            }
            header("Location: admin_panel.php?msg=usuario_restaurado");
        } else {
            header("Location: admin_panel.php?error=db_error");
        }
    } else {
        header("Location: admin_panel.php?error=id_invalido");
    }
    $conexion->close();
} else {
    header('Location: admin_panel.php');
}
exit;