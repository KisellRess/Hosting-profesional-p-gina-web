<?php
/* ============================================================
   ARCHIVO: conexiones.php
   FUNCION: abrir la conexion comun con la base de datos.
   IMPORTANTE: la estructura de tablas se mantiene en vinomadrid_db.sql.
   ============================================================ */

function getConexion(): mysqli {
    $host = "localhost";
    $user = "ubuntu";
    $pass = "ubuntu123";
    $db   = "vinomadrid_db";
    $conexion = new mysqli($host, $user, $pass, $db);
    if ($conexion->connect_error) {
        die("Error de conexión: " . $conexion->connect_error);
    }
    $conexion->set_charset("utf8mb4");

    return $conexion;
}
?>