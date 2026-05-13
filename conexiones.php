<?php
function getConexion() {
    $host = "localhost";
    $user = "ubuntu"; // El usuario que usaste para entrar a mysql
    $pass = "ubuntu123"; // La que usaste para entrar a mysql
    $db   = "vinomadrid_db";
    $conexion = new mysqli($host, $user, $pass, $db);
    if ($conexion->connect_error) {
        die("Error de conexión: " . $conexion->connect_error);
    }
    $conexion->set_charset("utf8mb4");
    return $conexion;
}
?>