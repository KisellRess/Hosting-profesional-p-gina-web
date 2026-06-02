<?php
/* ============================================================
   ARCHIVO: sessions.php
   FUNCION: centralizar autenticacion y permisos.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

/* SECCION 1: inicio de sesion y control de acceso. */
// ─── Configurar seguridad de cookies de sesión ───
session_set_cookie_params([
    'lifetime' => 0,                    // Cookie de sesión (se borra al cerrar navegador)
    'path'     => '/',                  // Disponible en toda la aplicación
    'domain'   => '',                   // Se auto-detecta el dominio actual
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',  // Solo HTTPS
    'httponly' => true,                 // No accesible desde JavaScript (previene XSS)
    'samesite' => 'Strict'              // Previene ataques CSRF
]);

session_start();

function require_auth() {
    if (!isset($_SESSION['email'])) {
        $plan = isset($_GET['plan']) ? '&redirect_plan=' . urlencode($_GET['plan']) : '';
        header('Location: auth.php?msg=login_required' . $plan);
        exit;
    }
}

/* SECCION 2: cargar en sesion los datos que usa el panel. */
function login_user_from_row(array $usuario) {
    $_SESSION['user_id'] = $usuario['id'] ?? null; // CRÍTICO: Guardar el ID
    $_SESSION['usuario'] = $usuario['nombre'] ?? '';
    $_SESSION['email']   = $usuario['email'] ?? '';
    $_SESSION['rol']     = $usuario['rol'] ?? 'usuario';
    $_SESSION['plan']    = !empty($usuario['plan_contratado']) ? $usuario['plan_contratado'] : 'Ninguno';
    
    // Guardamos cantidades para que aparezcan en el panel
    $_SESSION['storage_qty']   = $usuario['storage_qty'] ?? 0;
    $_SESSION['multiuser_qty'] = $usuario['multiuser_qty'] ?? 0;

    $extras = json_decode($usuario['extras_json'] ?? '[]', true);
    if (!is_array($extras)) { $extras = []; }
    
    $extra_items = [];
    $validModules = [
        'sql_php|5.00' => 'Acceso SQL/PHP', 
        'domain|15.00' => 'Gestión de Dominio', 
        'web_ai|100.00' => 'Diseño Web'
    ];
    
    foreach ($extras as $modulo) {
        if (array_key_exists($modulo, $validModules)) { 
            $extra_items[] = $validModules[$modulo]; 
        }
    }
    $_SESSION['extras'] = $extra_items;
}

/* SECCION 3: cierre de sesion y regreso a la portada. */
function logout_and_redirect() {
    session_unset();
    session_destroy();
    header('Location: index.php?msg=logout_success');
    exit;
}

?>