<?php
/* ============================================================
   ARCHIVO: panel.php
   FUNCION: mostrar y gestionar los servicios del cliente.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

// Diagnóstico de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'sessions.php';
require_auth();

if (empty($_SESSION['csrf_reembolso_web'])) {
    $_SESSION['csrf_reembolso_web'] = bin2hex(random_bytes(32));
}

// --- Sincronizar Módulo IA con DB ---
$user_id_sync = $_SESSION['user_id'] ?? 0;

if ($user_id_sync) {
    require_once 'conexiones.php';
    $db_sync = getConexion();
    
    // Consultamos el extras_json real en la base de datos
    $sql_sync = "SELECT extras_json FROM usuarios WHERE id = $user_id_sync";
    $res_sync = $db_sync->query($sql_sync);
    
    if ($res_sync && $row_sync = $res_sync->fetch_assoc()) {
        $extras_db = $row_sync['extras_json'] ?? '';
        
        // Comprobamos si existe la clave del módulo IA (web_ai)
        if (strpos($extras_db, 'web_ai') !== false || strpos($extras_db, 'Diseño Web IA') !== false) {
            // Solo hacemos el update si aún está en 0 para no saturar la DB
            $db_sync->query("UPDATE usuarios SET modulo_ia = 1 WHERE id = $user_id_sync AND modulo_ia = 0");
        }
    }
    $db_sync->close();
}
// ------------------------------------

/**
 * Calcula el tamaño total de una carpeta en bytes, de forma recursiva.
 * Devuelve 0 si la carpeta no existe o no es accesible.
 */
function get_folder_size(string $path): int {
    if (!is_dir($path) || !is_readable($path)) {
        return 0;
    }
    $total = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $total += $file->getSize();
        }
    }
    return $total;
}
$plan = $_SESSION['plan'] ?? 'Ninguno';
$sin_plan = ($plan === 'Ninguno' || $plan === '');

$storage_qty = $_SESSION['storage_qty'] ?? 0;

// ─── Espacio Total según plan + packs contratados ───
$base_gb_por_plan = ['BÁSICO' => 1, 'PROFESIONAL' => 3, 'ENTERPRISE' => 5];
$espacio_total_gb = ($base_gb_por_plan[$plan] ?? 1) + ($storage_qty * 2);

// --- Obtener datos reales de la DB ---
$ftp_user_db = 'No asignado';
$ftp_pass_db = '••••••••';
$user_id_panel = null;

// 1. Forzamos la conexión fuera de cualquier condición
require_once 'conexiones.php';
$db_panel = getConexion();

// 2. Limpiamos el email de la sesión
$email_session = trim($_SESSION['email'] ?? '');
$user_id_panel = null;

// LÓGICA DE VISOR ADMIN: Si es admin y pide un user_id, cambiamos la consulta al usuario objetivo
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin' && isset($_GET['user_id'])) {
    $target_id = (int)$_GET['user_id'];
    $query_user = "SELECT u.id, u.nombre, u.ftp_user, u.ftp_pass, u.showcase_permission, u.plan_contratado, u.storage_qty, u.multiuser_qty, u.extras_json, u.modulo_ia, u.nombre_fiscal, u.documento_identidad, u.direccion_completa, d.fecha_caducidad, d.renovacion_auto, d.estado_dominio, d.dominio_propio, d.subdominio_alias 
                   FROM usuarios u 
                   LEFT JOIN dominios d ON u.id = d.user_id 
                   WHERE u.id = $target_id LIMIT 1";
} else {
    $query_user = "SELECT u.id, u.nombre, u.ftp_user, u.ftp_pass, u.showcase_permission, u.plan_contratado, u.storage_qty, u.multiuser_qty, u.extras_json, u.modulo_ia, u.nombre_fiscal, u.documento_identidad, u.direccion_completa, d.fecha_caducidad, d.renovacion_auto, d.estado_dominio, d.dominio_propio, d.subdominio_alias 
                   FROM usuarios u 
                   LEFT JOIN dominios d ON u.id = d.user_id 
                   WHERE u.email = '$email_session' LIMIT 1";
}

$res_user = $db_panel->query($query_user);

$fecha_caducidad_dominio = null;
$renovacion_auto = 0;
$subdominio_actual = null;
$dom_propio = null;
$estado_dom = 'No configurado';
$showcase_permission = 0;
$datos_fiscales_completos = false;
$modulo_ia_activo = false;

$plan = $_SESSION['plan'] ?? 'Ninguno';
$storage_qty = $_SESSION['storage_qty'] ?? 0;
$multiuser_qty_val = (int)($_SESSION['multiuser_qty'] ?? 0);
$extras = $_SESSION['extras'] ?? [];
$extras_sesion = is_array($extras) ? $extras : [];
$modulo_ia_activo = count(array_filter(
    $extras_sesion,
    static fn($extra) => str_contains((string)$extra, 'web_ai')
)) > 0;

$ftp_user_db = 'No asignado';
$ftp_pass_db = '••••••••';
$nombre_usuario_actual = $_SESSION['usuario'] ?? 'Cliente';

if ($res_user && $res_user->num_rows === 1) {
    $row = $res_user->fetch_assoc();
    $user_id_panel           = (int) $row['id'];
    $nombre_usuario_actual   = $row['nombre'] ?? ($_SESSION['usuario'] ?? 'Cliente');
    $fecha_caducidad_dominio = $row['fecha_caducidad'];
    $renovacion_auto         = (int) ($row['renovacion_auto'] ?? 0);
    $subdominio_actual       = $row['subdominio_alias'] ?? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $row['nombre']));
    $dom_propio              = $row['dominio_propio'];
    $estado_dom              = $row['estado_dominio'] ?? 'No configurado';
    $showcase_permission     = (int) ($row['showcase_permission'] ?? 0);
    $modulo_ia_activo        = $modulo_ia_activo || ((int)($row['modulo_ia'] ?? 0) === 1);
    $datos_fiscales_completos = trim((string)($row['nombre_fiscal'] ?? '')) !== ''
        && trim((string)($row['documento_identidad'] ?? '')) !== ''
        && trim((string)($row['direccion_completa'] ?? '')) !== '';

    $plan                    = !empty($row['plan_contratado']) ? $row['plan_contratado'] : 'Ninguno';
    $storage_qty             = (int)($row['storage_qty'] ?? 0);
    $multiuser_qty_val       = (int)($row['multiuser_qty'] ?? 0);

    // Decodificar y formatear extras_json de la base de datos
    $extras_db = json_decode($row['extras_json'] ?? '[]', true);
    if (!is_array($extras_db)) { $extras_db = []; }
    $extras = [];
    $validModules = [
        'sql_php|5.00' => 'Acceso SQL/PHP', 
        'domain|15.00' => 'Gestión de Dominio', 
        'web_ai|100.00' => 'Diseño Web IA'
    ];
    foreach ($extras_db as $modulo) {
        if (array_key_exists($modulo, $validModules)) { 
            $extras[] = $validModules[$modulo]; 
        }
    }
    $modulo_ia_activo = $modulo_ia_activo || count(array_filter(
        $extras_db,
        static fn($extra) => str_contains((string)$extra, 'web_ai')
    )) > 0;

    // Si los campos FTP son NULL o vacíos, la cuenta está recién creada
    $ftp_user_db = (!empty(trim((string)$row['ftp_user'])))
        ? $row['ftp_user']
        : 'En trámite...';
    $ftp_pass_db = (!empty(trim((string)$row['ftp_pass'])))
        ? $row['ftp_pass']
        : 'En trámite...';
} else {
    // Si entra aquí, es que no encontró el email o la consulta falló
    $ftp_user_db = 'ERROR: Email no encontrado';
    $ftp_pass_db = 'Revisa la tabla usuarios';
}
$db_panel->close();

$sin_plan = ($plan === 'Ninguno' || $plan === '');

// ─── Espacio Total según plan + packs contratados ───
$base_gb_por_plan = ['BÁSICO' => 1, 'PROFESIONAL' => 3, 'ENTERPRISE' => 5];
$espacio_total_gb = ($base_gb_por_plan[strtoupper($plan)] ?? 1) + ($storage_qty * 2);

// Consulta para credenciales MySQL
$db_mysql = getConexion();
$res_sql = $db_mysql->query("SELECT db_name, db_user, db_pass, estado FROM modulo_mysql WHERE user_id = $user_id_panel LIMIT 1");
$mysql_data = ($res_sql && $res_sql->num_rows > 0) ? $res_sql->fetch_assoc() : null;
$db_mysql->close();

$mysql_db_name = trim((string) ($mysql_data['db_name'] ?? ''));
$mysql_db_user = trim((string) ($mysql_data['db_user'] ?? ''));
$mysql_db_pass = trim((string) ($mysql_data['db_pass'] ?? ''));
$estado_mysql = $mysql_data['estado'] ?? 'No configurado';

$ftp_user_folder = ($ftp_user_db && $ftp_user_db !== 'En trámite...' && $ftp_user_db !== 'No asignado' && $ftp_user_db !== 'ERROR: Email no encontrado') ? $ftp_user_db : '';
$folder_to_check = $ftp_user_folder ? "/var/www/hosting_tfg/" . $ftp_user_folder . "/htdocs" : '';
$bytes_usados   = $folder_to_check ? get_folder_size($folder_to_check) : 0;
$mb_usados      = round($bytes_usados / (1024 * 1024), 2);   // en MB
$gb_usados_pct  = $espacio_total_gb > 0 ? min(100, round(($mb_usados / ($espacio_total_gb * 1024)) * 100, 1)) : 0;

$db_activas = in_array('Acceso SQL/PHP', $extras) ? '1 / 1' : '0 / 0';
$multiuser_info = (strtoupper($plan) === 'ENTERPRISE') ? 'Ilimitado' : ($multiuser_qty_val > 0 ? $multiuser_qty_val . ' usuario(s) extra' : 'Ninguno');

$ia_factura = null;
$ia_reembolso = null;
$ia_proyecto = null;
$ia_dias_restantes = 0;
$ia_estado = $modulo_ia_activo ? 'En negociación' : '';

if ($modulo_ia_activo && $user_id_panel > 0) {
    $db_ia = getConexion();
    $concepto_factura_web = 'Desarrollo de Sitio Web Personalizado';
    $stmt_ia = $db_ia->prepare(
        "SELECT id, importe, fecha_emision
         FROM facturas
         WHERE user_id = ?
           AND tipo = 'factura'
           AND concepto = ?
         ORDER BY fecha_emision DESC, id DESC
         LIMIT 1"
    );
    $stmt_ia->bind_param('is', $user_id_panel, $concepto_factura_web);
    $stmt_ia->execute();
    $ia_factura = $stmt_ia->get_result()->fetch_assoc();
    $stmt_ia->close();

    $concepto_reembolso_web = "Reembolso por desistimiento - Garant\u{00ED}a 30 d\u{00ED}as (Desarrollo Web)";
    $stmt_ia = $db_ia->prepare(
        "SELECT id, fecha_emision
         FROM facturas
         WHERE user_id = ?
           AND tipo = 'reembolso'
           AND concepto = ?
         ORDER BY fecha_emision DESC, id DESC
         LIMIT 1"
    );
    $stmt_ia->bind_param('is', $user_id_panel, $concepto_reembolso_web);
    $stmt_ia->execute();
    $ia_reembolso = $stmt_ia->get_result()->fetch_assoc();
    $stmt_ia->close();

    $stmt_ia = $db_ia->prepare(
        "SELECT id, estado, precio_final, fecha_garantia_expira
         FROM proyectos_diseno_web
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt_ia->bind_param('i', $user_id_panel);
    $stmt_ia->execute();
    $ia_proyecto = $stmt_ia->get_result()->fetch_assoc();
    $stmt_ia->close();
    $db_ia->close();

    $estado_proyecto_ia = (string)($ia_proyecto['estado'] ?? '');
    if ($estado_proyecto_ia === 'activo' && $ia_factura) {
        $fecha_inicio_garantia = new DateTime((string)$ia_factura['fecha_emision']);
        $fin_garantia = (clone $fecha_inicio_garantia)->modify('+30 days');
        $hoy_garantia = new DateTime();
        $ia_dias_restantes = max(0, (int)$hoy_garantia->diff($fin_garantia)->format('%r%a'));
        $ia_estado = $ia_dias_restantes > 0 ? 'Diseño en curso - garantía activa' : 'Diseño en curso';
    } elseif ($estado_proyecto_ia === 'tramitando_propuesta' && (float)($ia_proyecto['precio_final'] ?? 0) > 0) {
        $ia_estado = 'Pendiente de pago';
    } elseif ($estado_proyecto_ia === 'reembolsado') {
        $ia_estado = 'Cancelado';
    }
}

if (!isset($_SESSION['email'])) {
    header('Location: auth.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_showcase') {
    require_once 'conexiones.php';
    $db = getConexion();
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $es_admin_gestionando = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin' && isset($_POST['target_user_id']);
    if ($es_admin_gestionando) {
        $uid = (int)$_POST['target_user_id'];
    }
    $new_val = isset($_POST['showcase_permission']) ? 1 : 0;
    $stmt = $db->prepare('UPDATE usuarios SET showcase_permission = ? WHERE id = ?');
    $stmt->bind_param('ii', $new_val, $uid);
    $stmt->execute();
    $stmt->close();
    $db->close();
    header('Location: ' . ($es_admin_gestionando ? 'panel.php?user_id=' . $uid : 'panel.php'));
    exit;
}

// panel.php - Validación de seguridad para evitar que la página se corte
if (!$user_id_panel && isset($_SESSION['user_id'])) {
    $user_id_panel = $_SESSION['user_id'];
}

// Si a pesar de todo no hay ID, forzamos uno para que no rompa el CSS
if (!$user_id_panel) { $user_id_panel = 0; } 

/* ============================================================
   ESTILOS DE LA PAGINA: panel.php
   Solo afectan a esta vista. Los estilos compartidos estan en estilos.css.
   ============================================================ */
$css_pagina = <<<'CSS'
/* Apariencia premium exclusiva de los widgets del panel */
body.page-panel .panel-cred-label,
body.page-panel .panel-widget-label {
  color: var(--gold-vibrant) !important;
  text-transform: uppercase;
  letter-spacing: 1px;
}
body.page-panel .panel-widget,
body.page-panel .panel-cred {
  border: 1px solid var(--gold-vibrant) !important;
  background-color: var(--dark-bg) !important;
}

body.page-panel {
    --surface: #0b0b0f;
    --surface2: #13131a;
    --bg: #0b0b0f;
    --border: #2a2a2a;
    --accent: #c8a96e; /* Dorado Premium restaurado */
    --accent-bright: #e8c989;
    --muted: #7a7568;
    --text: #e8e4dc;
    --glass: rgba(11, 11, 15, 0.8);
  }

  /* ─── CSS PREMIUM (Dark & Gold) ─── */
  
  /* Títulos y Jerarquía */
  body.page-panel h1,
body.page-panel h2,
body.page-panel h3,
body.page-panel .panel-cred-label,
body.page-panel .section-title {
    font-weight: 800 !important;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--accent) !important;
  }

  /* Inputs Uniformes */
  body.page-panel .swal2-input,
body.page-panel input[type="text"],
body.page-panel input[type="password"],
body.page-panel input[type="email"],
body.page-panel select {
    background-color: #1a1a24 !important;
    border: 1px solid var(--border) !important;
    border-radius: 8px !important;
    color: var(--text) !important;
    height: 45px !important;
    padding: 0 15px !important;
    font-size: 0.95rem !important;
    transition: all 0.3s ease !important;
    box-shadow: none !important;
    margin:0; width:100%
  }

  body.page-panel .swal2-input:focus,
body.page-panel input:focus {
    border-color: var(--accent) !important;
    box-shadow: 0 0 8px rgba(212, 175, 55, 0.3) !important;
    outline: none !important;
  }

  /* Botones Premium */
  body.page-panel .btn-gold,
body.page-panel .swal2-confirm,
body.page-panel .plan-btn:not(:disabled) {
    background: linear-gradient(135deg, #c8a96e, #a68b55) !important;
    color: #000 !important;
    font-weight: 700 !important;
    border: none !important;
    border-radius: 8px !important;
    padding: 12px 24px !important;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: all 0.3s ease !important;
    cursor: pointer;
  }

  body.page-panel .btn-gold:hover,
body.page-panel .swal2-confirm:hover,
body.page-panel .plan-btn:hover:not(:disabled) {
    transform: translateY(-2px) !important;
    box-shadow: 0 5px 15px rgba(200, 169, 110, 0.4) !important;
    filter: brightness(1.1);
  }

  body.page-panel .swal2-cancel {
    background: #333 !important;
    color: #ccc !important;
    border-radius: 8px !important;
    font-weight: 600 !important;
  }

  /* Modales Glassmorphism */
  body.page-panel .swal2-popup {
    background: var(--glass) !important;
    backdrop-filter: blur(15px) !important;
    -webkit-backdrop-filter: blur(15px) !important;
    border: 1px solid rgba(212, 175, 55, 0.2) !important;
    border-radius: 20px !important;
    max-width: 450px !important;
    padding: 2rem !important;
  }

  body.page-panel .swal2-title {
    color: var(--accent) !important;
    font-size: 1.5rem !important;
  }

  body.page-panel .panel-widget,
body.page-panel .panel-cred {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 15px;
    transition: border-color 0.3s ease;
  }

  body.page-panel .panel-widget:hover,
body.page-panel .panel-cred:hover {
    border-color: rgba(212, 175, 55, 0.4);
  }

  body.page-panel #panel { background: var(--surface); color: var(--text); position: relative; }

  body.page-panel .panel-wrap {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
  }

  body.page-panel .panel-ui {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    margin-top: 3rem;
    box-shadow: 0 30px 60px rgba(0,0,0,0.8);
  }

  body.page-panel .panel-bar {
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  body.page-panel .panel-bar-dot { width: 10px; height: 10px; border-radius: 50%; }

  body.page-panel .panel-body {
    padding: 2.5rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 350px), 1fr));
    gap: 1.5rem;
  }

  body.page-panel .panel-widget,
body.page-panel .panel-cred {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
  }
  
  body.page-panel .panel-widget:hover,
body.page-panel .panel-cred:hover {
    border-color: var(--accent);
    box-shadow: 0 0 20px rgba(212, 175, 55, 0.15);
    transform: translateY(-5px);
  }

  body.page-panel .panel-widget-label,
body.page-panel .panel-cred-label {
    font-size: 0.7rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 0.5rem;
    font-weight: bold;
  }

  body.page-panel .panel-widget-value {
    font-family: "Bebas Neue", sans-serif;
    font-size: 1.8rem;
    color: #fff;
    letter-spacing: 0.05em;
  }

  body.page-panel .cred-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem 0;
    border-bottom: 1px solid var(--border);
    font-size: 0.9rem;
  }
  body.page-panel .cred-row:last-child { border-bottom: none; }
  body.page-panel .cred-key { color: var(--muted); }
  body.page-panel .cred-val { color: #fff; font-family: "Courier New", monospace; }

  /* ─── BOTONES ─── */
  body.page-panel .plan-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.6rem 1.5rem;
    border-radius: 8px;
    font-weight: bold;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, var(--accent), #e6ac00);
    color: #000;
    border: none;
    cursor: pointer;
    box-shadow: 0 6px 15px rgba(255, 191, 0, 0.2);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.85rem;
  }
  
  body.page-panel .plan-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #e6ac00, var(--accent-bright));
    box-shadow: 0 0 25px rgba(255, 191, 0, 0.5);
    transform: translateY(-3px);
  }
  
  body.page-panel .plan-btn:disabled {
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: rgba(255, 255, 255, 0.2) !important;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
  }

  body.page-panel .cred-pending {
    color: var(--muted) !important;
    font-family: "DM Sans", sans-serif !important;
    font-style: italic;
    animation: credPulse 2s ease-in-out infinite;
  }

  /* SweetAlert Custom Dark */
  body.page-panel .swal2-popup { background: var(--surface) !important; color: var(--text) !important; border: 1px solid var(--border) !important; border-radius: 15px !important; }
  body.page-panel .swal2-input { background: #1a1a1a !important; color: #fff !important; border: 1px solid var(--border) !important; }
  body.page-panel .swal2-input:focus { border-color: var(--accent) !important; box-shadow: 0 0 5px var(--accent) !important; }

  /* Switch Showcase Moderno */
  body.page-panel .showcase-switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
  }
  
  body.page-panel .showcase-switch input {
    opacity: 0;
    width: 0;
    height: 0;
  }
  
  body.page-panel .showcase-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    transition: 0.3s ease;
    border-radius: 34px;
  }
  
  body.page-panel .showcase-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: var(--text);
    transition: 0.3s ease;
    border-radius: 50%;
    box-shadow: 0 2px 5px rgba(0,0,0,0.5);
  }
  
  body.page-panel .showcase-switch input:checked + .showcase-slider {
    background-color: var(--accent);
    border-color: var(--accent);
  }
  
  body.page-panel .showcase-switch input:checked + .showcase-slider:before {
    transform: translateX(24px);
    background-color: #0b0b0f;
  }

/* panel.php */
/* Estilos Glassmorphism Premium */
body.page-panel .chat-trigger {
    position: fixed; bottom: 30px; right: 30px;
    width: 60px; height: 60px;
    background: #d4af37; color: #000;
    border-radius: 50%; display: flex;
    align-items: center; justify-content: center;
    font-size: 24px; cursor: pointer;
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
    z-index: 10001; transition: transform 0.3s ease;
}
body.page-panel .chat-trigger:hover { transform: scale(1.1); }
body.page-panel .chat-trigger.glow { box-shadow: 0 0 20px #d4af37; animation: pulseGlow 2s infinite; }

@keyframes pulseGlow {
    0% { box-shadow: 0 0 0 0 rgba(212, 175, 55, 0.7); }
    70% { box-shadow: 0 0 0 15px rgba(212, 175, 55, 0); }
    100% { box-shadow: 0 0 0 0 rgba(212, 175, 55, 0); }
}

body.page-panel .chat-window {
    position: fixed; bottom: 100px; right: 30px;
    width: min(350px, calc(100vw - 2rem)); height: min(500px, calc(100vh - 8rem));
    background: rgba(15, 15, 15, 0.85);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 20px; display: none;
    flex-direction: column; z-index: 10000;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    overflow: hidden;
}

body.page-panel .chat-header {
    padding: 15px; background: rgba(212, 175, 55, 0.1);
    border-bottom: 1px solid rgba(212, 175, 55, 0.2);
    display: flex; justify-content: space-between; align-items: center;
    color: #d4af37; font-weight: bold;
}

body.page-panel .chat-body {
    flex: 1; overflow-y: auto; padding: 15px;
    display: flex; flex-direction: column; gap: 12px;
}

body.page-panel .msg {
    padding: 10px 14px; border-radius: 15px;
    max-width: 85%; font-size: 14px; line-height: 1.4;
    animation: msgFade 0.3s ease;
}
@keyframes msgFade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

body.page-panel .msg.usuario {
    align-self: flex-end; background: #d4af37; color: #000;
    border-bottom-right-radius: 2px;
}
body.page-panel .msg.admin {
    align-self: flex-start; background: rgba(255,255,255,0.1); color: #fff;
    border-bottom-left-radius: 2px; border: 1px solid rgba(255,255,255,0.1);
}

body.page-panel .chat-footer {
    padding: 15px; display: flex; gap: 10px;
    background: rgba(0,0,0,0.2);
}
body.page-panel #chat-input {
    flex: 1; background: rgba(255,255,255,0.05) !important;
    border: 1px solid rgba(212, 175, 55, 0.2) !important;
    border-radius: 10px; padding: 8px 12px; color: #fff; outline: none;
}
body.page-panel .send-btn {
    background: none; border: none; color: #d4af37;
    font-size: 18px; cursor: pointer; transition: 0.2s;
}
body.page-panel .send-btn:hover { transform: scale(1.1); color: #fff; }

body.page-panel .inline-panel-001 { display:none; }
body.page-panel .inline-panel-002 { color: #d4af37; margin-right: 8px; }
body.page-panel .inline-panel-003 { background:none; border:none; color:#fff; cursor:pointer; }
body.page-panel .inline-panel-004 { padding-top: 9rem; flex-grow: 1; }
body.page-panel .inline-panel-005 { color: var(--accent2); }
body.page-panel .inline-panel-006 { color: var(--accent); }
body.page-panel .inline-panel-007 { margin: 2rem 0; padding: 1.5rem 2rem; background: rgba(212,175,55,0.05); border: 1px solid var(--accent); border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.4); }
body.page-panel .inline-panel-008 { font-family: 'Bebas Neue', sans-serif; font-size: 1.4rem; color: var(--accent); letter-spacing: 0.05em; }
body.page-panel .inline-panel-009 { font-size: 0.9rem; color: var(--muted); margin-top: 0.3rem; }
body.page-panel .inline-panel-010 { text-decoration: none; }
body.page-panel .panel-welcome-actions { display:flex; gap:0.8rem; flex-wrap:wrap; }
body.page-panel .billing-disclaimer { margin: -0.75rem 0 2rem; padding: 1rem 1.25rem; background: rgba(243,156,18,0.1); border: 1px solid rgba(243,156,18,0.45); border-radius: 8px; color: #f5b041; font-size: 0.88rem; }
body.page-panel .billing-disclaimer a { color: var(--accent); font-weight: bold; }
body.page-panel .inline-panel-011 { margin: 1rem 0; padding: 1rem 1.5rem; background: rgba(212,175,55,0.05); border: 1px solid var(--accent); border-radius: 8px; color: var(--accent); font-size: 0.9rem; }
body.page-panel .inline-panel-012 { margin: 1rem 0; padding: 1rem 1.5rem; background: rgba(231,76,60,0.1); border: 1px solid #e74c3c; border-radius: 6px; color: #e74c3c; font-size: 0.88rem; }
body.page-panel .inline-panel-013 { margin: 1rem 0 2rem; padding: 1.5rem; background: rgba(200, 169, 110, 0.05); border: 1px solid var(--accent); border-radius: 12px; backdrop-filter: blur(10px); display: flex; gap: 1.5rem; align-items: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3); animation: fadeUp 0.6s ease both; }
body.page-panel .inline-panel-014 { width: 45px; height: 45px; background: var(--accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #000; font-size: 1.5rem; flex-shrink: 0; }
body.page-panel .inline-panel-015 { color: var(--accent); text-transform: uppercase; letter-spacing: 1px; font-size: 0.9rem; display: block; margin-bottom: 0.3rem; }
body.page-panel .inline-panel-016 { margin: 0; color: var(--text); font-size: 0.9rem; line-height: 1.5; }
body.page-panel .inline-panel-017 { background:#c0392b; }
body.page-panel .inline-panel-018 { background:#f39c12; }
body.page-panel .inline-panel-019 { background:#27ae60; }
body.page-panel .inline-panel-020 { margin-left:1rem; font-size:0.75rem; color:var(--muted); }
body.page-panel .inline-panel-021 { display:inline-block; margin-top: 1rem; font-size: 0.78rem; color: var(--accent); text-decoration: none; letter-spacing: 0.05em; border-bottom: 1px solid rgba(200,169,110,0.3); padding-bottom: 1px; }
body.page-panel .inline-panel-022 { font-size:1rem; }
body.page-panel .inline-panel-023 { display:block; margin-top:0.5rem; }
body.page-panel .inline-panel-024 { display:inline-block; width:100%; height:6px; background:var(--border); border-radius:3px; overflow:hidden; }
body.page-panel .inline-panel-025 { font-size: 1rem; color: var(--text); }
body.page-panel .inline-panel-026 { color:var(--muted); }
body.page-panel .inline-panel-027 { display:flex; flex-direction: column; gap: 2rem; }
body.page-panel .inline-panel-028 { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
body.page-panel .inline-panel-029 { font-size: 0.85rem; color: var(--muted); margin-bottom: 0.3rem; }
body.page-panel .inline-panel-030 { font-size: 1.6rem; color: var(--text); font-family: 'Courier New', monospace; text-transform: lowercase; }
body.page-panel .inline-panel-031 { color:var(--accent); }
body.page-panel .inline-panel-032 { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; }
body.page-panel .inline-panel-033 { font-size: 1.6rem; color: var(--accent); }
body.page-panel .inline-panel-034 { font-size: 1.6rem; color: #f39c12; }
body.page-panel .inline-panel-035 { color: #f39c12; }
body.page-panel .inline-panel-036 { font-size: 1.2rem; color: var(--muted); }
body.page-panel .inline-panel-037 { display: flex; gap: 1rem; }
body.page-panel .inline-panel-038 { padding: 0.5rem 1rem; text-decoration: none; }
body.page-panel .inline-panel-039 { padding: 0.5rem 1rem; }
body.page-panel .inline-panel-040 { padding: 0.5rem 1rem; border-color: #e74c3c; color: #e74c3c; }
body.page-panel .inline-panel-041 { color:var(--muted); font-size:0.8rem; }
body.page-panel .inline-panel-042 { display:flex; align-items:center; gap:0.5rem; }
body.page-panel .inline-panel-043 { -webkit-text-security: disc; }
body.page-panel .inline-panel-044 { background:none; border:none; color:var(--accent); cursor:pointer; font-size:1rem; padding:0; display:flex; }
body.page-panel .inline-panel-045 { margin-top:1rem; width:auto; padding:0.4rem 1rem; text-decoration: none; display: inline-block; }
body.page-panel .inline-panel-046 { margin-top:1rem; width:auto; padding:0.4rem 1rem; }
body.page-panel .inline-panel-047 { display:flex; justify-content:space-between; align-items:center; }
body.page-panel .inline-panel-048 { font-size:0.9rem; color:var(--accent); font-weight:bold; letter-spacing:0.1em; }
body.page-panel .inline-panel-049 { flex-wrap: wrap; gap: 1rem; }
body.page-panel .inline-panel-050 { flex: 1 1 150px; min-width: 0; }
body.page-panel .inline-panel-051 { flex: 1 1 150px; min-width: 0; display: flex; align-items: center; gap: 0.5rem; }
body.page-panel .inline-panel-052 { display: flex; align-items: center; gap: 1rem; }
body.page-panel .inline-panel-053 { font-size:0.8rem; color:var(--muted); }
body.page-panel .inline-panel-054 { font-size:0.8rem; color:#e74c3c; margin-top:1rem; }
body.page-panel .inline-panel-055 { margin-top:0.5rem; width:auto; padding:0.4rem 1rem; background: var(--surface2); color: var(--text); text-decoration: none; }
body.page-panel .inline-panel-056 { grid-column: 1 / -1; border-color: var(--accent); }
body.page-panel .inline-panel-057 { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.5rem; }
body.page-panel .inline-panel-058 { flex: 1 1 280px; min-width: 0; }
body.page-panel .inline-panel-059 { font-size: 1.2rem; display:flex; align-items:center; gap:0.5rem; }
body.page-panel .inline-panel-060 { margin-top:0.4rem; line-height: 1.4; }
body.page-panel .inline-panel-061 { display:flex; align-items:center; gap: 1.5rem; flex-wrap: wrap; }
body.page-panel .inline-panel-062 { display:flex; align-items:center; gap:0.8rem; }
body.page-panel .inline-panel-063 { position: relative; display: inline-block; width: 52px; height: 28px; cursor: pointer; }
body.page-panel .inline-panel-064 { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); transition: 0.3s ease; border-radius: 34px; }
body.page-panel .inline-panel-065 { width:auto; padding:0.5rem 1.2rem; background:rgba(255,255,255,0.05); color:var(--text); border:1px solid rgba(255,255,255,0.15); border-radius:6px; cursor:pointer; font-weight:bold; transition:all 0.2s; }
body.page-panel .inline-panel-066 { grid-column: 1 / -1; border-color: #e74c3c; }
body.page-panel .inline-panel-067 { color: #e74c3c; }
body.page-panel .inline-panel-068 { background: rgba(231, 76, 60, 0.1); padding: 1.5rem; border-radius: 8px; border: 1px solid rgba(231, 76, 60, 0.2); }
body.page-panel .inline-panel-069 { color:#e74c3c; font-size: 1.4rem; }
body.page-panel .inline-panel-070 { font-size: 0.9rem; margin-top: 0.7rem; color: var(--text); }
body.page-panel .inline-panel-071 { font-size: 0.8rem; color: var(--muted); margin-top: 1.2rem; font-style: italic; }
body.page-panel .inline-panel-072 { font-size: 1.6rem; color:#e74c3c; }
body.page-panel .inline-panel-073 { border-color:#e74c3c; color:#e74c3c; background:transparent; }
body.page-panel .inline-panel-074 { text-align:left; }
body.page-panel .inline-panel-075 { position:relative; margin-top:0.2rem; margin-bottom:1rem; }
body.page-panel .inline-panel-076 { margin:0; width:100%; }
body.page-panel .inline-panel-077 { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--accent); cursor:pointer; }
body.page-panel .inline-panel-078 { font-size:0.8rem; color:var(--muted); margin-top:0.5rem; }
body.page-panel .inline-panel-079 { display:flex;align-items:center;justify-content:center;gap:0.5rem; }
body.page-panel .inline-panel-080 { margin:0; width:min(150px, 100%); }
body.page-panel .inline-panel-081 { font-size:0.85rem; color:var(--muted); }
body.page-panel .inline-panel-082 { color:#e74c3c; font-size: 0.9em; }
body.page-panel .inline-panel-083 { margin-bottom: 1rem; font-size: 0.9em; }
body.page-panel .inline-panel-084 { text-align: left; }
body.page-panel .inline-panel-085 { font-size: 0.85rem; color: var(--muted); }
body.page-panel .inline-panel-086 { margin-top: 0.2rem; margin-bottom: 1rem; }
body.page-panel .inline-panel-087 { margin-top: 0.2rem; }
body.page-panel .inline-panel-088 { background: rgba(200, 169, 110, 0.05); border: 1px dashed rgba(200, 169, 110, 0.2); padding: 0.8rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.8rem; color: var(--accent); line-height: 1.4; }
body.page-panel .inline-panel-089 { font-size:0.8rem; color:var(--muted); font-weight:bold; }
body.page-panel .inline-panel-090 { position:relative; margin-top:0.2rem; margin-bottom:1.5rem; }
body.page-panel .inline-panel-091 { margin-top:0.2rem; margin-bottom:1rem; }
body.page-panel .inline-panel-092 { position:relative; margin-top:0.2rem; }
body.page-panel .inline-panel-093 { font-size:0.8rem; color:var(--muted); margin-top:1rem; }
body.page-panel .inline-panel-094 { text-align: left; font-size: 0.95rem; max-height: 400px; overflow-y: auto; padding-right: 15px; line-height: 1.6; }
body.page-panel .inline-panel-095 { margin-bottom: 0.8rem; }

body.page-panel .storage-progress-fill {
  display: block;
  height: 100%;
  border-radius: 3px;
  transition: width 0.6s;
}
body.page-panel .storage-percent {
  font-size: 0.72rem;
  font-weight: bold;
}
body.page-panel .storage-warning {
  margin-top: 0.5rem;
  padding: 0.4rem 0.7rem;
  border: 1px solid;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: bold;
}
body.page-panel .panel-domain-wide { grid-column: 1 / -1; }
body.page-panel .btn-compact { padding: 0.5rem 1rem; width: auto; }
body.page-panel .btn-domain-connect { padding: 0.5rem 1rem; }
body.page-panel .secret-hidden { -webkit-text-security: disc; }
body.page-panel .credential-toggle,
body.page-panel .staff-edit-action,
body.page-panel .staff-delete-action {
  background: none;
  border: none;
  cursor: pointer;
}
body.page-panel .credential-toggle {
  color: var(--accent);
  font-size: 1rem;
  padding: 0;
  display: flex;
}
body.page-panel .staff-edit-action { color: var(--accent); font-size: 0.9rem; }
body.page-panel .staff-delete-action { color: #ff4d4d; font-size: 0.9rem; }
body.page-panel .staff-buy-btn {
  margin-top: 1rem;
  width: auto;
  padding: 0.4rem 1rem;
  background: var(--accent);
  color: white;
  text-decoration: none;
}
body.page-panel .showcase-status {
  font-size: 0.9rem;
  font-weight: bold;
  transition: color 0.3s;
}
body.page-panel .showcase-input { opacity: 0; width: 0; height: 0; }
body.page-panel .modal-input-full {
  margin-top: 0.2rem;
  margin-bottom: 1rem;
  width: 100%;
}
body.page-panel .modal-input-spacing {
  margin-top: 0.2rem;
  margin-bottom: 1rem;
}
body.page-panel .modal-input-disabled {
  background: rgba(255, 255, 255, 0.02) !important;
  color: var(--muted) !important;
  cursor: not-allowed;
}
body.page-panel .web-project-card {
  grid-column: 1 / -1;
  border-color: rgba(200,169,110,0.6) !important;
  background:
    linear-gradient(135deg, rgba(200,169,110,0.08), rgba(231,76,60,0.04)),
    var(--surface) !important;
}
body.page-panel .web-project-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  flex-wrap: wrap;
}
body.page-panel .web-project-title {
  color: var(--text);
  font-size: 1.35rem;
  line-height: 1.2;
}
body.page-panel .web-project-status {
  display: inline-flex;
  align-items: center;
  min-height: 30px;
  padding: 0.35rem 0.75rem;
  border: 1px solid rgba(200,169,110,0.45);
  border-radius: 6px;
  color: var(--accent);
  background: rgba(200,169,110,0.08);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
body.page-panel .web-project-copy {
  margin: 0.8rem 0 0;
  max-width: 760px;
  color: var(--muted);
  line-height: 1.55;
}
body.page-panel .web-project-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-top: 1.2rem;
}
body.page-panel .web-project-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
  width: auto;
  padding: 0.72rem 1.15rem;
  border: 1px solid rgba(200,169,110,0.42);
  border-radius: 4px;
  background: rgba(200,169,110,0.12);
  color: var(--accent);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.2s, border-color 0.2s, color 0.2s, opacity 0.2s;
}
body.page-panel .web-project-button:hover {
  background: rgba(200,169,110,0.2);
  border-color: rgba(200,169,110,0.68);
  color: #f0dca3;
}
body.page-panel .web-project-refund {
  background: rgba(231,76,60,0.12) !important;
  border: 1px solid rgba(231,76,60,0.5) !important;
  color: #ffb0a8 !important;
}
body.page-panel .web-project-refund:hover {
  background: rgba(231,76,60,0.2) !important;
  border-color: rgba(231,76,60,0.75) !important;
  color: #ffd1cc !important;
}
body.page-panel .web-project-button:disabled,
body.page-panel .web-project-button.is-disabled {
  opacity: 0.55;
  cursor: not-allowed;
  pointer-events: none;
}
CSS;

$titulo_pagina = 'Panel de Usuario';
require_once 'includes/header.php';
?>

<!-- ─── SECCIÓN CHAT CORREGIDA ─── -->
<div id="chat-trigger" onclick="toggleChat()" class="chat-trigger">
    <i class="fas fa-comment-dots"></i>
    <span class="chat-badge inline-panel-001" id="chat-badge">!</span>
</div>

<div id="chat-widget" class="chat-window">
    <div class="chat-header">
        <div>
            <i class="fas fa-robot inline-panel-002"></i>
            <span>Asistente de Diseño IA</span>
        </div>
        <button onclick="toggleChat()" class="inline-panel-003">&times;</button>
    </div>
    <div id="chat-messages" class="chat-body">
        <!-- Los mensajes se cargan aquí -->
    </div>
    <div class="chat-footer">
        <input type="text" id="chat-input" placeholder="Escribe tu respuesta..." onkeypress="if(event.key === 'Enter') sendChatMsg(event)">
        <button onclick="sendChatMsg(event)" class="send-btn">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>



<section id="panel" class="inline-panel-004">
  <div class="panel-wrap">
    
    <h2 class="section-title">Panel de <em>Usuario</em> de <span class="inline-panel-005"><?php echo htmlspecialchars($nombre_usuario_actual); ?></span></h2>
    
    <p class="section-desc">Bienvenido, <span class="inline-panel-006"><?php echo htmlspecialchars($nombre_usuario_actual); ?></span>. Gestiona tu hosting y servicios aquí.</p>

    <?php if ($sin_plan): ?>
    <div class="inline-panel-007">
      <div>
        <div class="inline-panel-008">¡Cuenta creada con éxito!</div>
        <div class="inline-panel-009">Aún no tienes un plan contratado. Elige el que mejor se adapte a tu proyecto.</div>
      </div>
      <div class="panel-welcome-actions">
        <a href="planes.php" class="plan-btn inline-panel-010">Ver Planes →</a>
        <a href="facturas.php" class="plan-btn inline-panel-010">Ir a Facturas →</a>
      </div>
    </div>
    <?php if (!$datos_fiscales_completos): ?>
    <div class="billing-disclaimer">
      Antes de realizar una compra debes completar tus datos de facturación.
      <a href="perfil_facturacion.php">Completar datos fiscales</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php
    // Feedback al volver de procesar_cambio.php
    $msg_cambio = $_GET['ok'] ?? '';
    $err_cambio = $_GET['error'] ?? '';
    ?>
    <?php if ($msg_cambio === 'modulo_eliminado'): ?>
    <div class="inline-panel-011">
      ✓ Módulo eliminado correctamente. Los cambios se aplicarán en tu próxima facturación.
    </div>
    <?php elseif ($msg_cambio === 'mysql_pendiente' || $msg_cambio === 'ftp_pendiente' || $msg_cambio === 'dominio_actualizado'): ?>
    <div class="inline-panel-011">
      ✓ Cambios realizados correctamente. Los procesos de fondo actualizarán tu entorno en unos minutos.
    </div>
    <?php elseif ($err_cambio !== ''): ?>
    <div class="inline-panel-012">
      ✗ Error al procesar el cambio (<?php echo htmlspecialchars($err_cambio); ?>). Contacta con soporte si persiste.
    </div>
    <?php endif; ?>

    <!-- AVISO DE PROVISIÓN UX PREMIUM -->
    <?php if (isset($_GET['status']) && $_GET['status'] === 'provisioning'): ?>
    <div class="inline-panel-013">
        <div class="inline-panel-014">
            <i class="fas fa-cogs"></i>
        </div>
        <div>
            <strong class="inline-panel-015">¡Petición recibida con éxito!</strong>
            <p class="inline-panel-016">
                Estamos configurando tu entorno de servidor. Este proceso es automático y suele tardar <strong>menos de 5 minutos</strong>. 
                Mientras tanto, puedes seguir explorando el panel.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <div class="panel-ui">
      <div class="panel-bar">
        <div class="panel-bar-dot inline-panel-017"></div>
        <div class="panel-bar-dot inline-panel-018"></div>
        <div class="panel-bar-dot inline-panel-019"></div>
        <span class="inline-panel-020">panel.vinomadrid-hosting.es — <?php echo htmlspecialchars($nombre_usuario_actual); ?></span>
      </div>

      <div class="panel-body">

        <!-- seccion plan activo -->
        <div class="panel-widget">
          <div class="panel-widget-label">Plan activo</div>
          <div class="panel-widget-value"><?php echo htmlspecialchars($plan); ?></div>

          <?php if ($sin_plan): ?>
          <div class="panel-widget-sub">Falta Contratar un plan</div>
          <a href="planes.php" class="inline-panel-021">Ver Planes</a>
          <?php endif; ?>

          <?php if (!$sin_plan): ?>
            <div class="panel-widget-sub">Renovación automática activa</div>
          <a href="modificar_servicios.php" class="inline-panel-021">⚙ Gestionar servicios →</a>
          <?php endif; ?>
        </div>

        <!-- seccion facturación -->
        <div class="panel-widget">
          <div class="panel-widget-label">Facturación</div>
          <div class="panel-widget-value"><?php echo $sin_plan ? 'Disponible' : 'Activa'; ?></div>

          <?php if ($sin_plan): ?>
          <div class="panel-widget-sub">Completa tus datos o consulta tu historial</div>
          <a href="facturas.php" class="plan-btn inline-panel-010">Ir a facturas →</a>
          <?php endif; ?>

          <?php if (!$sin_plan): ?>
            <div class="panel-widget-sub">Consulta tus facturas y pagos</div>
          <a href="facturas.php" class="inline-panel-021">📄 Consultar facturas →</a>
          <?php endif; ?>
        </div>

        <!-- seccion espacio en disco -->
        <?php
          // Determinar color y estado de la cuota
          if ($gb_usados_pct >= 100) {
              $disco_color  = '#e74c3c'; // rojo crítico
              $disco_estado = 'CUOTA SUPERADA';
              $disco_icon   = '🔴';
          } elseif ($gb_usados_pct >= 80) {
              $disco_color  = '#f39c12'; // naranja advertencia
              $disco_estado = 'Espacio bajo';
              $disco_icon   = '⚠️';
          } else {
              $disco_color  = 'var(--accent)';
              $disco_estado = null;
              $disco_icon   = null;
          }
          $gb_usados_real = round($mb_usados / 1024, 3);
        ?>
        <div class="panel-widget" style="<?php echo $gb_usados_pct >= 100 ? 'border-color:#e74c3c;' : ($gb_usados_pct >= 80 ? 'border-color:#f39c12;' : ''); ?>">
          <div class="panel-widget-label">Espacio en disco</div>
          <div class="panel-widget-value">
            <?php if ($gb_usados_real >= 1): ?>
              <?php echo number_format($gb_usados_real, 2, ',', '.'); ?> <span class="inline-panel-022">GB</span>
            <?php else: ?>
              <?php echo number_format($mb_usados, 2, ',', '.'); ?> <span class="inline-panel-022">MB</span>
            <?php endif; ?>
          </div>
          <div class="panel-widget-sub">
            de <?php echo $espacio_total_gb; ?> GB disponibles
            <span class="inline-panel-023">
              <span class="inline-panel-024">
                <span class="storage-progress-fill" style="width:<?php echo $gb_usados_pct; ?>%; background:<?php echo $disco_color; ?>;"></span>
              </span>
              <span class="storage-percent" style="color:<?php echo $disco_color; ?>;"><?php echo $gb_usados_pct; ?>% usado</span>
            </span>
            <?php if ($disco_estado): ?>
            <div class="storage-warning" style="background:<?php echo $gb_usados_pct >= 100 ? 'rgba(231,76,60,0.12)' : 'rgba(243,156,18,0.12)'; ?>; border-color:<?php echo $disco_color; ?>; color:<?php echo $disco_color; ?>;">
              <?php echo $disco_icon; ?> <?php echo $disco_estado; ?> — Contacta con soporte para ampliar capacidad.
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- seccion bases de datos -->
        <div class="panel-widget">
          <div class="panel-widget-label">Bases de datos</div>
          <div class="panel-widget-value"><?php echo $db_activas; ?></div>
          <div class="panel-widget-sub">MySQL activas</div>
        </div>

        <!-- seccion extras -->
        <div class="panel-widget">
          <div class="panel-widget-label">Módulos Extras Activos</div>
          <div class="panel-widget-value inline-panel-025">
            <?php 
              if (empty($extras)) {
                  echo '<span class="inline-panel-026">Ninguno</span>';
              } else {
                  echo implode('<br>', array_map('htmlspecialchars', $extras));
              }
            ?>
          </div>
          <div class="panel-widget-sub">Usuarios extra: <?php echo htmlspecialchars($multiuser_info); ?></div>
        </div>
        
        <!-- GESTIÓN DE DOMINIOS Y SUBDOMINIOS -->
        <?php if ($modulo_ia_activo): ?>
        <div class="panel-widget web-project-card">
          <div class="web-project-head">
            <div>
              <div class="panel-widget-label">Proyecto de Diseño Web</div>
              <div class="panel-widget-value web-project-title">Seguimiento técnico</div>
            </div>
            <span id="web-project-status" class="web-project-status"><?php echo htmlspecialchars($ia_estado); ?></span>
          </div>

          <p class="web-project-copy">
            <?php if ($ia_factura && (($ia_proyecto['estado'] ?? '') === 'activo')): ?>
              Precio final aprobado: <?php echo number_format((float)$ia_factura['importe'], 2, ',', '.'); ?> EUR. Puedes consultar la factura emitida y, si procede, solicitar el reembolso durante la garantía.
            <?php elseif (($ia_proyecto['estado'] ?? '') === 'tramitando_propuesta' && (float)($ia_proyecto['precio_final'] ?? 0) > 0): ?>
              Presupuesto aprobado: <?php echo number_format((float)$ia_proyecto['precio_final'], 2, ',', '.'); ?> EUR. Para activar factura y garantía debes completar el pago seguro en checkout.
            <?php else: ?>
              Importe base desde 100,00 EUR. El precio final se acordará con la administración según los requisitos de tu espacio.
            <?php endif; ?>
          </p>

          <div class="web-project-actions">
            <a href="presupuestos.php" class="plan-btn">Abrir canal de comunicación</a>
            <?php if (($ia_proyecto['estado'] ?? '') === 'tramitando_propuesta' && (float)($ia_proyecto['precio_final'] ?? 0) > 0): ?>
              <a href="checkout.php?web_project=1" class="web-project-button">Pagar presupuesto</a>
            <?php endif; ?>
            <?php if ($ia_factura && (($ia_proyecto['estado'] ?? '') === 'activo')): ?>
              <a href="facturas.php" class="web-project-button">Ver factura del proyecto</a>
            <?php endif; ?>

            <?php
            // Verificar de fondo el estado en proyectos_diseno_web para mostrar el botón de desistimiento o el mensaje de expirado
            $mostrar_desistimiento = false;
            $garantia_expirada = false;
            $db_time  = getConexion();
            $uid_time = (int)($_SESSION['user_id'] ?? 0);

            $stmt_t = $db_time->prepare(
                "SELECT estado, fecha_garantia_expira FROM proyectos_diseno_web 
                 WHERE user_id = ? 
                 ORDER BY id DESC LIMIT 1"
            );
            if ($stmt_t) {
                $stmt_t->bind_param('i', $uid_time);
                $stmt_t->execute();
                $proj_t = $stmt_t->get_result()->fetch_assoc();
                $stmt_t->close();

                if ($proj_t) {
                    if ($proj_t['estado'] === 'activo') {
                        $fecha_expira = new DateTime($proj_t['fecha_garantia_expira']);
                        $hoy = new DateTime();
                        if ($hoy <= $fecha_expira) {
                            $mostrar_desistimiento = !empty($ia_factura['id']) && !empty($ia_proyecto['id']);
                        } else {
                            $garantia_expirada = true;
                        }
                    }
                }
            }
            $db_time->close();
            ?>

            <?php if ($mostrar_desistimiento): ?>
                <div id="refund-web-wrapper" class="refund-btn-wrapper">
                    <form method="POST" action="solicitar_reembolso_web.php" id="formReembolsoWeb">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_reembolso_web'] ?? ''); ?>">
                        <input type="hidden" name="factura_id" value="<?php echo (int)($ia_factura['id'] ?? 0); ?>">
                        <input type="hidden" name="proyecto_id" value="<?php echo (int)($ia_proyecto['id'] ?? 0); ?>">
                        <button type="button" id="refund-web-button" class="web-project-button web-project-refund" onclick="confirmarDesistimientoWeb(event)">
                            Solicitar desistimiento
                        </button>
                    </form>
                </div>
            <?php elseif ($garantia_expirada): ?>
                <p style="font-size: 0.85rem; color: var(--muted); margin: 0; padding-left: 0.5rem; display: inline-block; vertical-align: middle;">
                    ⚠️ Cobertura de garantía finalizada (Plazo de 30 días expirado).
                </p>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php
          $db_dom = getConexion();
          $u_id_sec = (int)$user_id_panel;
          // Buscamos en la tabla correcta: dominios
          $res_dom = $db_dom->query("SELECT subdominio_alias, dominio_propio, estado_dominio FROM dominios WHERE user_id = $u_id_sec LIMIT 1");
          
          $row_dom = ($res_dom && $res_dom->num_rows > 0) ? $res_dom->fetch_assoc() : null;
          $db_dom->close();

          // Usamos el operador null coalescing (??) para que nunca de error si está vacío
          $sub_alias = $row_dom['subdominio_alias'] ?? null;
          $dom_propio = $row_dom['dominio_propio'] ?? null;
          $estado_dom = $row_dom['estado_dominio'] ?? 'No configurado';
          
          // Lógica de visualización (simplificada)
          $tiene_fecha = false; // Ya no hay fecha_caducidad en dominios, asumir false
          
          $subdominio_actual = $sub_alias ? htmlspecialchars($sub_alias) : strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombre_usuario_actual));
        ?>
        <div class="panel-widget panel-domain-wide" style="border-color: <?php echo ($dom_propio || $tiene_fecha) ? 'var(--accent)' : 'var(--border)'; ?>;">
          <div class="panel-widget-label">Direcciones y Dominios</div>
          
          <div class="inline-panel-027">
            <!-- SUBDOMINIO -->
            <div class="inline-panel-028">
              <div>
                <div class="inline-panel-029">Subdominio Principal (Gratis)</div>
                <div class="panel-widget-value inline-panel-030">
                  vinomadrid.es/<span class="inline-panel-031"><?php echo $subdominio_actual; ?></span>
                </div>
              </div>
              <button class="plan-btn btn-compact" <?php echo $sin_plan ? 'onclick="location.href=\'planes.php\'"' : 'onclick="cambiarSubdominio()"'; ?>>Cambiar alias</button>
            </div>

            <!-- DOMINIO PERSONALIZADO -->
            <div class="inline-panel-032">
              <div>
                <div class="inline-panel-029">Dominio Personalizado (Máx. 1 por cuenta)</div>
                
                <?php if ($dom_propio): ?>
                  <div class="panel-widget-value inline-panel-033">
                    <?php echo htmlspecialchars($dom_propio); ?>
                  </div>
                  <div class="panel-widget-sub inline-panel-006">✓ Servicio Activo</div>

                <?php elseif ($tiene_fecha): ?>
                  <div class="panel-widget-value inline-panel-034">
                    PAGO CONFIRMADO
                  </div>
                  <div class="panel-widget-sub inline-panel-035">✓ Tienes un dominio prepagado listo para asignar.</div>

                <?php else: ?>
                  <div class="panel-widget-value inline-panel-036">No configurado</div>
                  <div class="panel-widget-sub">Elige una opción para tu identidad web.</div>
                <?php endif; ?>
              </div>
              
              <div class="inline-panel-037">
                <?php if ($sin_plan): ?>
                  <a href="planes.php" class="plan-btn inline-panel-038">Comprar Plan</a>
                <?php else: ?>
                  <?php if ($dom_propio): ?>
                    <button class="plan-btn inline-panel-039" onclick="conectarDominioPropio()">Editar</button>
                    <button class="plan-btn inline-panel-040" onclick="desvincularDominio()">Desvincular</button>
                  
                  <?php else: ?>
                    <!-- Botones cuando NO hay dominio activo -->
                    <button class="plan-btn btn-domain-connect" style="border-color: <?php echo $tiene_fecha ? 'var(--accent)' : 'var(--border)'; ?>;" onclick="conectarDominioPropio()">
                      <?php echo $tiene_fecha ? 'Asignar mi dominio pagado' : 'Ya tengo uno'; ?>
                    </button>
                    
                    <?php if (!$tiene_fecha): ?>
                      <button class="plan-btn inline-panel-039" onclick="comprarDominio()">Comprar dominio (15€)</button>
                    <?php else: ?>
                      <!-- Botón para resetear si se equivocó al elegir 'Tengo uno propio' en lugar de comprar[cite: 10] -->
                      <button class="plan-btn inline-panel-040" onclick="desvincularDominio()">Limpiar elección</button>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- CREDENCIALES FTP -->
        <div class="panel-cred">
          <div class="panel-cred-label">Credenciales de acceso FTP</div>
          <div class="cred-row">
            <span class="cred-key">Host</span>
            <?php if ($sin_plan || $ftp_user_db === 'En trámite...'): ?>
              <span class="cred-val inline-panel-041">⏳ Pendiente de asignación</span>
            <?php else: ?>
              <span class="cred-val">kisellress.ddns.net</span>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Usuario FTP</span>
            <?php if ($sin_plan): ?>
              <span class="cred-val inline-panel-041">🔒 Servicio inactivo</span>
            <?php elseif ($ftp_user_db === 'En trámite...'): ?>
              <span class="cred-val cred-pending">⏳ <?php echo htmlspecialchars($ftp_user_db); ?></span>
            <?php else: ?>
              <span class="cred-val"><?php echo htmlspecialchars($ftp_user_db); ?></span>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Puerto</span>
            <?php if ($sin_plan || $ftp_user_db === 'En trámite...'): ?>
              <span class="cred-val inline-panel-041">🔒 Oculto</span>
            <?php else: ?>
              <span class="cred-val">21</span>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Contraseña</span>
            <?php if ($sin_plan): ?>
              <span class="cred-val inline-panel-041">🔒 Desconocido</span>
            <?php elseif ($ftp_pass_db === 'En trámite...'): ?>
              <span class="cred-val cred-pending">⏳ <?php echo htmlspecialchars($ftp_pass_db); ?></span>
            <?php else: ?>
              <div class="inline-panel-042">
                <span id="ftp-pass-val" class="cred-val inline-panel-043"><?php echo htmlspecialchars($ftp_pass_db); ?></span>
                <button type="button" onclick="togglePass('ftp-pass-val')" class="inline-panel-044"><i class="fas fa-eye" id="icon_ftp-pass-val"></i></button>
              </div>
            <?php endif; ?>
          </div>
          <?php if ($sin_plan): ?>
            <a href="planes.php" class="plan-btn inline-panel-045">Ver Planes</a>
          <?php elseif ($ftp_user_db === 'En trámite...'): ?>
            <button class="plan-btn inline-panel-046" disabled>⏳ Configurando entorno...</button>
          <?php else: ?>
            <button class="plan-btn inline-panel-046" onclick="editarFTP()">Editar FTP</button>
          <?php endif; ?>
        </div>

        <!-- CREDENCIALES MYSQL -->
        <div class="panel-cred">
          <div class="panel-cred-label">Credenciales de acceso MySQL</div>
          <div class="cred-row">
            <span class="cred-key">Host</span>
            <?php if ($sin_plan || $estado_mysql === 'Tramitando'): ?>
              <span class="cred-val inline-panel-041">⏳ Pendiente de asignación</span>
            <?php else: ?>
              <span class="cred-val">vinomadrid.es/tfg_phpmyadmin</span>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Nombre DB</span>
            <?php if ($sin_plan): ?>
              <span class="cred-val inline-panel-041">🔒 Servicio inactivo</span>
            <?php elseif ($mysql_db_name === ''): ?>
              <span class="cred-val inline-panel-041">No configurado</span>
            <?php else: ?>
              <span class="cred-val"><?php echo htmlspecialchars($mysql_db_name); ?></span>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Usuario</span>
            <?php if ($sin_plan): ?>
              <span class="cred-val inline-panel-041">🔒 Servicio inactivo</span>
            <?php elseif ($mysql_db_user === ''): ?>
              <span class="cred-val inline-panel-041">No configurado</span>
            <?php else: ?>
              <span class="cred-val"><?php echo htmlspecialchars($mysql_db_user); ?></span>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Contraseña</span>
            <?php if ($sin_plan): ?>
              <span class="cred-val inline-panel-041">🔒 Servicio inactivo</span>
            <?php elseif ($mysql_db_pass === ''): ?>
              <span class="cred-val inline-panel-041">No configurado</span>
            <?php else: ?>
              <div class="inline-panel-042">
                <span id="mysql-pass-val" class="cred-val inline-panel-043"><?php echo htmlspecialchars($mysql_db_pass); ?></span>
                <button type="button" onclick="togglePass('mysql-pass-val')" class="inline-panel-044"><i class="fas fa-eye" id="icon_mysql-pass-val"></i></button>
              </div>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Estado</span>
            <?php if ($sin_plan): ?>
              <span class="cred-val inline-panel-041">🔒 Servicio inactivo</span>
            <?php elseif ($estado_mysql === 'Tramitando'): ?>
              <span class="cred-val cred-pending">⏳ <?php echo htmlspecialchars($estado_mysql); ?></span>
            <?php else: ?>
              <span class="cred-val"><?php echo htmlspecialchars($estado_mysql); ?></span>
            <?php endif; ?>
          </div>
          <?php if ($sin_plan): ?>
            <a href="planes.php#multiusuario" class="plan-btn inline-panel-045">Ver Planes</a>
          <?php elseif ($estado_mysql === 'Tramitando'): ?>
            <button class="plan-btn inline-panel-046" disabled>⏳ Configurando BD...</button>
          <?php elseif ($estado_mysql === 'No configurado'): ?>
            <button class="plan-btn inline-panel-046" onclick="editarMySQL()">Activar MySQL</button>
          <?php else: ?>
            <button class="plan-btn inline-panel-046" onclick="editarMySQL()">Editar MySQL</button>
          <?php endif; ?>
        </div>

        <!-- USUARIOS STAFF (FTP ADICIONALES) -->
        <div class="panel-cred">
          <?php
            $db_staff = getConexion();
            $id_for_query = (int)$user_id_panel;
            $res_staff = $db_staff->query("SELECT id, ftp_user, ftp_pass, estado FROM ftp_cuentas_extra WHERE user_id = $id_for_query");
            $staff_count = ($res_staff && $res_staff->num_rows > 0) ? $res_staff->num_rows : 0;
            
            // Lógica de límites de usuarios Staff
            $plan_actual = $plan;
            $multiuser_qty_comprados = $multiuser_qty_val;
            
            $max_staff = 0;
            $is_unlimited = false;
            
            if (strtoupper($plan_actual) === 'ENTERPRISE') {
                $is_unlimited = true;
            } elseif (strtoupper($plan_actual) === 'PROFESIONAL') {
                $max_staff = 2 + $multiuser_qty_comprados;
            } else {
                $max_staff = $multiuser_qty_comprados;
            }
            
            $can_add_staff = $is_unlimited || ($staff_count < $max_staff);
            $has_staff_access = $is_unlimited || ($max_staff > 0);
            $limite_txt = $is_unlimited ? '&infin;' : $max_staff;
          ?>
          
          <div class="panel-cred-label inline-panel-047">
            <span>Módulos Extras Activos (Staff)</span>
            <span class="inline-panel-048"><?php echo $staff_count; ?> / <?php echo $limite_txt; ?></span>
          </div>

          <?php if ($has_staff_access): ?>
            <?php if ($staff_count > 0): ?>
              <?php while($s = $res_staff->fetch_assoc()): ?>
                <div class="cred-row inline-panel-049">
                    <div class="inline-panel-050">
                      <span class="cred-key">Usuario:</span>
                      <span class="cred-val"><?php echo htmlspecialchars($s['ftp_user']); ?></span>
                    </div>
                    
                    <div class="inline-panel-051">
                      <span class="cred-key">Pass:</span>
                      <span id="staff-pass-<?php echo $s['id']; ?>" class="cred-val secret-hidden"><?php echo htmlspecialchars($s['ftp_pass']); ?></span>
                      <button type="button" class="credential-toggle" onclick="togglePass('staff-pass-<?php echo $s['id']; ?>')"><i class="fas fa-eye" id="icon_staff-pass-<?php echo $s['id']; ?>"></i></button>
                    </div>

                    <div class="inline-panel-052">
                      <span class="cred-val <?php echo ($s['estado'] === 'Tramitando') ? 'cred-pending' : ''; ?>">
                          <?php if ($s['estado'] === 'Tramitando'): ?>⏳ <?php endif; ?><?php echo htmlspecialchars($s['estado']); ?>
                      </span>
                      <?php if ($s['estado'] !== 'Tramitando' && $s['estado'] !== 'Para_Borrar'): ?>
                      <button type="button" class="btn-editar staff-edit-action" onclick="editarStaff(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['ftp_user']); ?>')" title="Editar usuario Staff">
                          <i class="fas fa-edit"></i>
                      </button>
                      <?php endif; ?>
                      <button type="button" class="btn-borrar staff-delete-action" onclick="confirmarBorradoStaff(<?php echo $s['id']; ?>)" title="Borrar usuario Staff">
                          <i class="fas fa-trash"></i>
                      </button>
                    </div>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <p class="inline-panel-053">No hay usuarios staff creados todavía.</p>
            <?php endif; ?>
            
            <?php if ($can_add_staff): ?>
              <button class="plan-btn inline-panel-046" onclick="modalCrearStaff()">+ Añadir Staff</button>
            <?php else: ?>
              <p class="inline-panel-054">Has alcanzado el límite de usuarios Staff (<?php echo $max_staff; ?>).</p>
              <a href="modificar_servicios.php#extras" class="plan-btn inline-panel-055">Ampliar límite</a>
            <?php endif; ?>
            
            <?php $db_staff->close(); ?>
          <?php else: ?>
            <?php $db_staff->close(); ?>
            <p class="inline-panel-053">Servicio no contratado.</p>
            <a href="<?php echo $sin_plan ? 'planes.php' : 'modificar_servicios.php#extras'; ?>" class="plan-btn staff-buy-btn">Comprar Staff</a>
          <?php endif; ?>
        </div>

        <!-- PREFERENCIAS Y LEGAL -->
        <div class="panel-widget inline-panel-056">
          <div class="panel-widget-label inline-panel-006">Preferencias y Legal</div>
          
          <div class="inline-panel-057">
            <div class="inline-panel-058">
              <div class="panel-widget-value inline-panel-059">
                <i class="fas fa-eye inline-panel-006"></i> Privacidad y Showcase
              </div>
              <div class="panel-widget-sub inline-panel-060">
                Controla si tu web aparece en nuestra galería pública. Al activarlo, permites que se muestre una previsualización de tu sitio a otros usuarios con fines promocionales. Puedes cambiar esta preferencia en cualquier momento.
              </div>
            </div>
            
            <form method="POST" action="<?php echo (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin' && isset($_GET['user_id'])) ? 'panel.php?user_id=' . (int)$user_id_panel : 'panel.php'; ?>" class="inline-panel-061">
              <input type="hidden" name="action" value="toggle_showcase">
              <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin' && isset($_GET['user_id'])): ?>
                <input type="hidden" name="target_user_id" value="<?php echo (int)$user_id_panel; ?>">
              <?php endif; ?>
              
              <!-- Interruptor toggle moderno -->
              <div class="inline-panel-062">
                <span class="showcase-status" style="color: <?php echo $showcase_permission ? 'var(--accent)' : 'var(--muted)'; ?>;">
                  <?php echo $showcase_permission ? 'Participando' : 'No participando'; ?>
                </span>
                <label class="showcase-switch inline-panel-063">
                  <input type="checkbox" name="showcase_permission" value="1" <?php if($showcase_permission) echo 'checked'; ?> onchange="this.form.submit()" class="showcase-input">
                  <span class="showcase-slider inline-panel-064"></span>
                </label>
              </div>

              <button type="button" class="plan-btn inline-panel-065" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'" onclick="mostrarTerminos()">
                <i class="fas fa-file-contract"></i> Ver Términos
              </button>
            </form>
          </div>
        </div>

        <!-- ZONA DE PELIGRO / ESTADO DEL SERVICIO -->
        <?php 
        $db_check = getConexion();
        $u_id_del = (int)$user_id_panel;
        // Consultamos por ID, que es más rápido y seguro
        $res_del = $db_check->query("SELECT estado_servicio, fecha_cancelacion FROM usuarios WHERE id = $u_id_del LIMIT 1");
        $user_status = ($res_del && $res_del->num_rows > 0) ? $res_del->fetch_assoc() : ['estado_servicio' => 'Activo', 'fecha_cancelacion' => null];
        $db_check->close();
        ?>

        <div class="panel-widget inline-panel-066">
          <div class="panel-widget-label inline-panel-067">Estado del Servicio</div>
          
          <?php if ($user_status['estado_servicio'] === 'Cancelado'): ?>
            <div class="inline-panel-068">
              <div class="panel-widget-value inline-panel-069">CANCELACIÓN EN CURSO</div>
              <p class="inline-panel-070">
                Solicitado el: <strong><?php echo date('d/m/Y H:i', strtotime($user_status['fecha_cancelacion'])); ?></strong><br>
                Tu cuenta y archivos serán borrados definitivamente por un administrador en menos de 24h.
              </p>
              <p class="inline-panel-071">
                * Para revertir esta acción, contacta con administración antes de que expire el plazo.
              </p>
            </div>
          <?php else: ?>
            <div class="inline-panel-032">
              <div>
                <div class="panel-widget-value inline-panel-072">Eliminar cuenta y datos</div>
                <div class="panel-widget-sub">Atención: Se borrarán tus archivos y bases de datos MySQL en 24h.</div>
              </div>
              <form method="POST" action="cancelar_suscripcion.php" id="form-cancelar">
                <button type="button" class="plan-btn inline-panel-073" onclick="confirmarCancelacion()">Eliminar cuenta</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
  // ─── Utilidades Interfaz ───
  function togglePass(id) {
    const el   = document.getElementById(id);
    const icon = document.getElementById('icon_' + id);
    if (!el) return;

    if (el.tagName === 'INPUT') {
      // --- Modal: input real, togglamos type ---
      if (el.type === 'password') {
        el.type = 'text';
        if (icon) icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        el.type = 'password';
        if (icon) icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    } else {
      // --- Span inline: usamos webkit-text-security ---
      const isHidden = el.style.webkitTextSecurity !== 'none';
      el.style.webkitTextSecurity = isHidden ? 'none' : 'disc';
      if (icon) {
        if (isHidden) {
          icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
          icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
      }
    }
  }

  function toggleInputPass(id) {
    // Alias para compatibilidad o simplificación
    togglePass(id);
  }

  const mensajesErrorAccion = {
    servicio_baja: 'No puedes modificar servicios mientras la cuenta esta en proceso de baja.',
    edicion_capada: 'El nombre no puede cambiarse desde el panel. Contacta con soporte.',
    mysql_vacio: 'Indica el nombre de la base de datos y el usuario.',
    mysql_password_insegura: 'La contraseña MySQL debe tener 8 caracteres o más, con letras, números y un símbolo.',
    ftp_vacio: 'El usuario FTP no puede quedar vacio.',
    datos_invalidos: 'Revisa los datos introducidos.',
    usuario_existente: 'Ese nombre ya esta en uso.',
    staff_no_encontrado: 'No se encontro el acceso Staff seleccionado.',
    staff_id_invalido: 'El acceso Staff no es valido.',
    accion_invalida: 'La accion solicitada no es valida.',
    db_error: 'No se ha podido guardar el cambio.',
    db_excepcion: 'No se ha podido completar el cambio.',
    ftp_error: 'No se ha podido guardar el acceso FTP.',
    ftp_excepcion: 'No se ha podido completar el acceso FTP.'
  };

  function datosAccion(valores = {}) {
    const datos = new FormData();
    Object.entries(valores).forEach(([nombre, valor]) => datos.append(nombre, valor ?? ''));
    return datos;
  }

  async function enviarAccionPanel(url, datos, mensajeExito) {
    try {
      const respuesta = await fetch(url, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: datos
      });
      const resultado = await respuesta.json();
      if (!respuesta.ok || !resultado.ok) {
        throw new Error(mensajesErrorAccion[resultado.code] || 'No se ha podido completar la solicitud.');
      }

      await Swal.fire({
        title: 'Solicitud registrada',
        text: mensajeExito,
        icon: 'success',
        confirmButtonColor: 'var(--accent)',
        background: 'var(--surface)',
        color: 'var(--text)'
      });
      window.location.href = resultado.redirect || 'panel.php';
    } catch (error) {
      Swal.fire({
        title: 'No se pudo completar',
        text: error.message || 'Ha ocurrido un error al enviar la solicitud.',
        icon: 'error',
        confirmButtonColor: 'var(--accent)',
        background: 'var(--surface)',
        color: 'var(--text)'
      });
    }
  }

  function confirmarBorradoStaff(id) {
    Swal.fire({
        title: '¿Eliminar acceso Staff?',
        text: "El usuario se eliminará del acceso, pero los archivos de la carpeta principal no se borrarán por seguridad.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c8a96e',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        background: '#0b0b0f',
        color: '#c8a96e'
    }).then((result) => {
        if (result.isConfirmed) {
            enviarAccionPanel(
              'controller_hosting.php?modulo=staff',
              datosAccion({ accion: 'borrar_staff', staff_id: id }),
              'El acceso Staff se eliminara en el siguiente proceso de provision.'
            );
        }
    });
  }

  function editarStaff(id, fullUser) {
    // Quitar prefijo u[ID]_
    const prefixMatch = fullUser.match(/^u\d+_(.+)$/);
    const displayName = prefixMatch ? prefixMatch[1] : fullUser;

    Swal.fire({
      title: 'Editar Usuario Staff',
      html: `
        <div class="inline-panel-074">
          <label class="inline-panel-053">Nombre (ej: ventas)</label>
          <input type="text" id="swal-edit-user" class="swal2-input modal-input-full" placeholder="Nombre" value="${displayName}">
          
          <label class="inline-panel-053">Nueva Contraseña (Dejar vacío para no cambiar)</label>
          <div class="inline-panel-075">
            <input type="password" id="swal-edit-pass" class="swal2-input inline-panel-076" placeholder="Contraseña">
            <button type="button" onclick="togglePass('swal-edit-pass')" class="inline-panel-077">
              <i class="fas fa-eye" id="icon_swal-edit-pass"></i>
            </button>
          </div>
          <p class="inline-panel-078">El nombre final será el elegido. Solo se agregará un sufijo _u<?php echo $user_id_panel; ?> si ya existe en el sistema.</p>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'Guardar Cambios',
      cancelButtonText: 'Cancelar',
      background: 'var(--surface)',
      color: 'var(--text)',
      preConfirm: () => {
        const nombre = document.getElementById('swal-edit-user').value.trim();
        const pass = document.getElementById('swal-edit-pass').value;
        if (!nombre) {
          Swal.showValidationMessage('El nombre es obligatorio');
          return false;
        }
        if (pass !== "" && pass.length < 6) {
          Swal.showValidationMessage('La contraseña debe tener al menos 6 caracteres');
          return false;
        }
        return { nombre, pass };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        enviarPeticionStaffEdicion('editar_staff', id, result.value.nombre, result.value.pass);
      }
    });
  }

  function enviarPeticionStaffEdicion(accion, id, nombre, pass) {
    enviarAccionPanel(
      'controller_hosting.php?modulo=staff',
      datosAccion({ accion, staff_id: id, nombre_staff: nombre, pass_staff: pass }),
      'Los cambios del acceso Staff quedan pendientes de provision.'
    );
  }

  // ─── Bienvenida tras nueva compra ───
  <?php if (isset($_SESSION['nuevo_pedido'])): ?>
  Swal.fire({
    icon: 'success',
    title: '¡Plan activado con éxito!',
    html: 'Estamos configurando tu entorno.<br>En menos de <b>5 minutos</b> aparecerán tus credenciales FTP en la sección correspondiente.',
    background: 'var(--surface)',
    color: 'var(--text)',
    confirmButtonColor: 'var(--accent)',
    confirmButtonText: 'Entendido',
    timer: 10000,
    timerProgressBar: true
  });
  <?php
    unset($_SESSION['nuevo_pedido']); // Se borra para que no vuelva a aparecer
  ?>
  <?php endif; ?>


  // Esta función DEBE estar fuera de cualquier otra para que el botón la encuentre
  function confirmarCancelacion() {
    Swal.fire({
      title: '¿Solicitar cancelación?',
      html: "<b>Aviso importante:</b> Tu cuenta y archivos serán eliminados definitivamente en <b>24 horas</b>.<br><br>Si deseas revertir esta acción, contacta con vinomadridparla@gmail.com.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#e74c3c',
      cancelButtonColor: 'var(--surface2)',
      confirmButtonText: 'Entiendo, cancelar cuenta',
      cancelButtonText: 'Mantener mi cuenta',
      background: 'var(--surface)',
      color: 'var(--text)'
    }).then((result) => {
      if (result.isConfirmed) {
        enviarAccionPanel(
          'cancelar_suscripcion.php',
          new FormData(document.getElementById('form-cancelar')),
          'La cancelacion ha quedado solicitada.'
        );
      }
    });
  }

  function confirmarDesistimientoWeb(event) {
    if (event) {
      event.preventDefault();
    }

    const form = document.getElementById('formReembolsoWeb');
    const button = document.getElementById('refund-web-button');
    const statusLabel = document.getElementById('web-project-status');

    if (!form || !button || button.disabled) {
      return;
    }

    Swal.fire({
      title: '¿Confirmar Reembolso?',
      text: 'Se dará de baja el Módulo de Diseño Web de inmediato. Si cumple las condiciones de la garantía de 30 días, se emitirá una factura rectificativa y el abono se ingresará en un plazo de 2 días hábiles.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#c0392b',
      cancelButtonColor: '#1c1c28',
      confirmButtonText: 'Sí, solicitar devolución',
      cancelButtonText: 'Conservar servicio',
      background: '#13131a',
      color: '#e8e4dc'
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }

      button.disabled = true;
      button.classList.add('is-disabled');
      button.textContent = 'Procesando...';

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      })
        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
        .then(({ ok, data }) => {
          if (!ok || !data.ok) {
            throw new Error(data.message || 'No se pudo procesar el reembolso.');
          }

          if (statusLabel) {
            statusLabel.textContent = data.estado || 'Cancelado';
          }

          button.textContent = 'Cancelado';
          Swal.fire({
            icon: 'success',
            title: 'Reembolso solicitado',
            text: 'El acceso al modulo se ha revocado y se ha emitido una factura negativa de reembolso sin modificar la factura original.',
            background: '#13131a',
            color: '#e8e4dc',
            confirmButtonColor: '#c8a96e'
          });
        })
        .catch((error) => {
          button.disabled = false;
          button.classList.remove('is-disabled');
          button.textContent = 'Solicitar desistimiento';
          Swal.fire({
            icon: 'error',
            title: 'No se pudo completar',
            text: error.message || 'Inténtalo de nuevo en unos minutos.',
            background: '#13131a',
            color: '#e8e4dc',
            confirmButtonColor: '#c8a96e'
          });
        });
    });
  }

  function cambiarSubdominio() {
    Swal.fire({
      title: 'Cambiar Subdominio',
      html: 'Nuevo alias para tu subdominio gratuito:<br><br><div class="inline-panel-079"><span class="inline-panel-026">vinomadrid.es/</span><input id="swal-input-sub" class="swal2-input inline-panel-080" placeholder="miweb"></div><br><br><span class="inline-panel-081">Nota: El cambio tardará menos de 5 minutos en aplicarse mediante nuestros procesos internos.</span>',
      icon: 'info',
      showCancelButton: true,
      confirmButtonText: 'Actualizar',
      cancelButtonText: 'Cancelar',
      background: 'var(--surface)',
      color: 'var(--text)',
      preConfirm: () => {
        const val = document.getElementById('swal-input-sub').value;
        if (!val || !/^[a-z0-9-]+$/.test(val)) {
          Swal.showValidationMessage('Solo letras minúsculas, números y guiones');
        }
        return val;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        enviarPeticionDominio('cambiar_subdominio', result.value);
      }
    });
  }

  function conectarDominioPropio() {
    Swal.fire({
      title: 'Conectar Dominio Existente',
      html: 'Introduce el dominio que ya posees (ej. <i>miempresa.com</i>).<br><br><strong>¡IMPORTANTE!</strong> Debes apuntar los Nameservers (DNS) de tu proveedor de dominio a los nuestros:<br><br><strong class="inline-panel-031">arya.ns.cloudflare.com</strong><br><strong class="inline-panel-031">elmo.ns.cloudflare.com</strong><br><br><span class="inline-panel-082">Aviso: No nos hacemos cargo de brindar soporte sobre plataformas externas. Tú eres responsable de configurar los DNS en tu proveedor.</span>',
      input: 'text',
      inputPlaceholder: 'midominio.com',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'He cambiado los DNS y quiero conectar',
      cancelButtonText: 'Cancelar',
      background: 'var(--surface)',
      color: 'var(--text)',
      preConfirm: (val) => {
        if (!val || !val.includes('.')) Swal.showValidationMessage('Introduce un dominio válido');
        return val;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        enviarPeticionDominio('conectar_dominio', result.value);
      }
    });
  }

  function comprarDominio() {
    Swal.fire({
      title: 'Comprar Nuevo Dominio',
      html: `
        <p class="inline-panel-083">El proceso de validación y registro oficial puede tardar <b>entre 24 y 48 horas</b>.</p>
        <div class="inline-panel-084">
            <label class="inline-panel-085">Dominio Principal Deseado</label>
            <input id="dom_principal" class="swal2-input inline-panel-086" placeholder="midominio.com">
            
            <label class="inline-panel-085">Alternativa 1 (Opcional)</label>
            <input id="dom_alt1" class="swal2-input inline-panel-086" placeholder="midominio.es">
            
            <label class="inline-panel-085">Alternativa 2 (Opcional)</label>
            <input id="dom_alt2" class="swal2-input inline-panel-086" placeholder="mi-dominio.com">
            
            <label class="inline-panel-085">Alternativa 3 (Opcional)</label>
            <input id="dom_alt3" class="swal2-input inline-panel-087" placeholder="midominio.net">
        </div>
      `,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Comprar (15€)',
      cancelButtonText: 'Cancelar',
      background: 'var(--surface)',
      color: 'var(--text)',
      preConfirm: () => {
        const p = document.getElementById('dom_principal').value;
        const a1 = document.getElementById('dom_alt1').value;
        const a2 = document.getElementById('dom_alt2').value;
        const a3 = document.getElementById('dom_alt3').value;
        
        if (!p || !p.includes('.')) {
          Swal.showValidationMessage('Debes introducir al menos el dominio principal con extensión (.com, .es)');
          return false;
        }
        
        // Empaquetamos las opciones separadas por comas
        const opciones = [p, a1, a2, a3].filter(Boolean).join(',');
        return opciones;
      }
    }).then((result) => {
      if (result.isConfirmed && result.value) {
        // Enviamos los dominios al backend para guardarlos antes de ir al checkout
        enviarPeticionDominio('comprar_dominio', result.value);
      }
    });
  }

  function enviarPeticionDominio(accion, valor) {
    enviarAccionPanel(
      'controller_hosting.php?modulo=dominio',
      datosAccion({ accion, valor }),
      'La configuracion del dominio queda pendiente de aplicacion.'
    );
  }

  function desvincularDominio() {
    Swal.fire({
      title: '¿Desvincular Dominio?',
      text: 'Se eliminará de tu panel y la web dejará de cargar desde allí.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#e74c3c',
      confirmButtonText: 'Sí, desvincular',
      background: 'var(--surface)',
      color: 'var(--text)'
    }).then((result) => {
      if (result.isConfirmed) enviarPeticionDominio('desvincular_dominio', 'none');
    });
  }

  function editarMySQL() {
    const isNew = <?php echo ($estado_mysql === 'No configurado' || empty($mysql_db_name)) ? 'true' : 'false'; ?>;
    Swal.fire({
      title: isNew ? 'Activar Módulo MySQL' : 'Editar Contraseña MySQL',
      html: `
        <div class="inline-panel-074">
          <label class="inline-panel-053">Nombre DB (ej: miweb_db)</label>
          <input type="text" id="swal-db-name" class="swal2-input modal-input-spacing ${isNew ? '' : 'modal-input-disabled'}" placeholder="Nombre DB" value="${isNew ? '' : '<?php echo htmlspecialchars($mysql_db_name); ?>'}" ${isNew ? '' : 'disabled'}>
          
          <label class="inline-panel-053">Usuario DB (ej: miweb_user)</label>
          <input type="text" id="swal-db-user" class="swal2-input modal-input-spacing ${isNew ? '' : 'modal-input-disabled'}" placeholder="Usuario DB" value="${isNew ? '' : '<?php echo htmlspecialchars($mysql_db_user); ?>'}" ${isNew ? '' : 'disabled'}>
          
          ${isNew ? '' : `
            <div class="inline-panel-088">
              <i class="fas fa-info-circle"></i> Para modificar el nombre del usuario o de la base de datos, debes ponerte en contacto con un técnico de soporte.
            </div>
          `}

          <label class="inline-panel-089">${isNew ? 'Contraseña MySQL' : 'Nueva Contraseña (Dejar vacío para no cambiar)'}</label>
          <div class="inline-panel-075">
            <input type="password" id="swal-db-pass" class="swal2-input inline-panel-076" placeholder="Contraseña">
            <button type="button" onclick="toggleInputPass('swal-db-pass')" class="inline-panel-077"><i class="fas fa-eye" id="icon_swal-db-pass"></i></button>
          </div>

          <label class="inline-panel-089">Repetir Contraseña</label>
          <div class="inline-panel-090">
            <input type="password" id="swal-db-pass-rep" class="swal2-input inline-panel-076" placeholder="Repetir Contraseña">
            <button type="button" onclick="toggleInputPass('swal-db-pass-rep')" class="inline-panel-077"><i class="fas fa-eye" id="icon_swal-db-pass-rep"></i></button>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: isNew ? 'Activar' : 'Actualizar',
      background: 'var(--surface)',
      color: 'var(--text)',
      preConfirm: () => {
        const dbName = isNew ? document.getElementById('swal-db-name').value : '<?php echo addslashes($mysql_db_name); ?>';
        const dbUser = isNew ? document.getElementById('swal-db-user').value : '<?php echo addslashes($mysql_db_user); ?>';
        const dbPass = document.getElementById('swal-db-pass').value;
        const dbPassRep = document.getElementById('swal-db-pass-rep').value;

        if (isNew && (!dbName || !dbUser)) {
          Swal.showValidationMessage('El nombre de la BD y el usuario son obligatorios');
          return false;
        }

        if (isNew && !dbPass) {
          Swal.showValidationMessage('La contraseña es obligatoria para activar el módulo');
          return false;
        }

        if (dbPass !== "" || dbPassRep !== "") {
          if (dbPass !== dbPassRep) {
            Swal.showValidationMessage('Las contraseñas no coinciden');
            return false;
          }
          if (dbPass.length < 8 || !/[A-Za-z]/.test(dbPass) || !/[0-9]/.test(dbPass) || !/[^A-Za-z0-9\s]/.test(dbPass) || /\s/.test(dbPass)) {
            Swal.showValidationMessage('Usa al menos 8 caracteres, con letras, números y un símbolo');
            return false;
          }
        }

        return { dbName, dbUser, dbPass };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        enviarPeticionMySQL(result.value.dbName, result.value.dbUser, result.value.dbPass);
      }
    });
  }

  function editarFTP() {
    Swal.fire({
      title: 'Editar Credenciales FTP',
      html: `
        <div class="inline-panel-074">
          <label class="inline-panel-053">Usuario FTP</label>
          <input type="text" id="swal-ftp-user" class="swal2-input modal-input-full" placeholder="Usuario FTP" value="<?php echo htmlspecialchars($ftp_user_db); ?>">
          
          <div class="inline-panel-088">
            <i class="fas fa-exclamation-triangle"></i> <strong>Aviso:</strong> Si cambias el nombre de usuario FTP, se renombrará tu directorio en el servidor. Las rutas absolutas en tus scripts y conexiones activas se verán afectadas temporalmente.
          </div>
          
          <label class="inline-panel-053">Nueva Contraseña (Dejar vacío para no cambiar)</label>
          <div class="inline-panel-075">
            <input type="password" id="swal-ftp-pass" class="swal2-input inline-panel-076" placeholder="Nueva Contraseña">
            <button type="button" onclick="togglePass('swal-ftp-pass')" class="inline-panel-077">
              <i class="fas fa-eye" id="icon_swal-ftp-pass"></i>
            </button>
          </div>

          <label class="inline-panel-053">Repetir Nueva Contraseña</label>
          <div class="inline-panel-075">
          <input type="password" id="swal-ftp-pass-rep" class="swal2-input inline-panel-076" placeholder="Repetir Contraseña">
          <button type="button" onclick="togglePass('swal-ftp-pass-rep')" class="inline-panel-077">
              <i class="fas fa-eye" id="icon_swal-ftp-pass-rep"></i>
            </button>
            </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'Actualizar',
      background: 'var(--surface)',
      color: 'var(--text)',
      preConfirm: () => {
        const ftpUser = document.getElementById('swal-ftp-user').value;
        const ftpPass = document.getElementById('swal-ftp-pass').value;
        const ftpPassRep = document.getElementById('swal-ftp-pass-rep').value;

        if (!ftpUser) {
          Swal.showValidationMessage('El nombre de usuario es obligatorio');
          return false;
        }

        if (ftpPass !== "" || ftpPassRep !== "") {
          if (ftpPass !== ftpPassRep) {
            Swal.showValidationMessage('Las contraseñas no coinciden');
            return false;
          }
          if (ftpPass.length < 6) {
            Swal.showValidationMessage('La contraseña debe tener al menos 6 caracteres');
            return false;
          }
        }

        return { ftpUser, ftpPass };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        enviarPeticionFTP(result.value.ftpUser, result.value.ftpPass);
      }
    });
  }

  function enviarPeticionMySQL(dbName, dbUser, dbPass) {
    enviarAccionPanel(
      'controller_hosting.php?modulo=mysql',
      datosAccion({ db_name: dbName, db_user: dbUser, db_pass: dbPass }),
      'La configuracion MySQL queda pendiente de provision.'
    );
  }

  function enviarPeticionFTP(ftpUser, ftpPass) {
    enviarAccionPanel(
      'controller_hosting.php?modulo=ftp',
      datosAccion({ ftp_user: ftpUser, ftp_pass: ftpPass }),
      'Los cambios FTP quedan pendientes de provision.'
    );
  }

  function modalCrearStaff() {
    Swal.fire({
      title: 'Añadir nuevo usuario Staff',
      html: `
        <div class="inline-panel-074">
          <label class="inline-panel-053">Nombre (ej: ventas)</label>
          <input type="text" id="swal-user" class="swal2-input inline-panel-091" placeholder="Nombre">
          
          <label class="inline-panel-053">Contraseña</label>
          <div class="inline-panel-092">
            <input type="password" id="swal-pass" class="swal2-input inline-panel-076" placeholder="Contraseña">
            <button type="button" onclick="togglePass('swal-pass')" class="inline-panel-077">
              <i class="fas fa-eye" id="icon_swal-pass"></i>
            </button>
          </div>
          <p class="inline-panel-093">El nombre final será el elegido. Solo se agregará un sufijo _u<?php echo $user_id_panel; ?> si ya existe en el sistema.</p>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'Crear Usuario',
      preConfirm: () => {
        const nombre = document.getElementById('swal-user').value;
        const pass = document.getElementById('swal-pass').value;
        if (!nombre || !pass) {
          Swal.showValidationMessage('Todos los campos son obligatorios');
        }
        return { nombre, pass };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        // Enviar por POST al controlador de alojamiento.
        enviarPeticionStaff('crear_staff', result.value.nombre, result.value.pass);
      }
    });
  }

  function enviarPeticionStaff(accion, nombre, pass) {
    enviarAccionPanel(
      'controller_hosting.php?modulo=staff',
      datosAccion({ accion, nombre_staff: nombre, pass_staff: pass }),
      'El acceso Staff queda pendiente de provision.'
    );
  }

  // Animaciones del panel
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.style.opacity = '1';
        e.target.style.transform = 'translateY(0)';
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.panel-widget, .panel-cred').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(24px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
  });

  /* ─── LÓGICA DE CHAT CORREGIDA ─── */
let chatLastId = 0;

function toggleChat() {
    const chat = document.getElementById('chat-widget');
    const trigger = document.getElementById('chat-trigger');
    if (chat.style.display === 'none' || chat.style.display === '') {
        chat.style.display = 'flex';
        trigger.classList.remove('glow');
        const container = document.getElementById('chat-messages');
        container.scrollTop = container.scrollHeight;
    } else {
        chat.style.display = 'none';
    }
}

function initChatSSE() {
    // Detectar si estamos en modo visor admin
    const urlParams = new URLSearchParams(window.location.search);
    const targetId = urlParams.get('user_id');
    const ssePath = targetId ? `get_chat_sse.php?user_id=${targetId}&last_id=${chatLastId}&t=${Date.now()}` : `get_chat_sse.php?last_id=${chatLastId}&t=${Date.now()}`;

    const source = new EventSource(ssePath);
    
    source.onmessage = function(e) {
        const data = JSON.parse(e.data);
        const container = document.getElementById('chat-messages');
        
        // Evitar duplicados por si acaso
        if (document.getElementById(`msg-${data.id}`)) return;

        const msgDiv = document.createElement('div');
        msgDiv.id = `msg-${data.id}`;
        msgDiv.className = `msg ${data.emisor}`;
        msgDiv.textContent = data.mensaje;
        
        container.appendChild(msgDiv);
        container.scrollTop = container.scrollHeight;
        chatLastId = data.id;

        // Notificación si el chat está cerrado
        if (document.getElementById('chat-widget').style.display !== 'flex' && data.emisor === 'admin') {
            document.getElementById('chat-trigger').classList.add('glow');
        }
    };

    source.onerror = function() {
        source.close();
        setTimeout(initChatSSE, 3000); // Reintentar si cae
    };
}

function sendChatMsg(event) {
    if (event) event.preventDefault();
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if (!msg) return;

    const params = new URLSearchParams();
    params.append('mensaje', msg);
    
    // Si estamos en modo visor admin, enviamos el target_user_id
    const urlParams = new URLSearchParams(window.location.search);
    const targetId = urlParams.get('user_id');
    if (targetId) params.append('target_user_id', targetId);

    fetch('send_chat.php', {
        method: 'POST',
        body: params
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            input.value = '';
        }
    });
}

// Iniciar al cargar
document.addEventListener('DOMContentLoaded', initChatSSE);

// Función para mostrar los términos y condiciones
function mostrarTerminos() {
  Swal.fire({
    title: 'Términos y Condiciones',
    html: `
      <div class="inline-panel-094">
        <p class="inline-panel-095"><strong>1. Propiedad:</strong> El usuario es el dueño legal del dominio y de los archivos alojados; VinoMadrid Hosting actúa como mero intermediario técnico.</p>
        <p class="inline-panel-095"><strong>2. Pagos y Renovación:</strong> La renovación de los servicios es automática, con aviso previo de 30 días. El impago conlleva la suspensión inmediata del servicio.</p>
        <p class="inline-panel-095"><strong>3. Responsabilidad Técnica:</strong> No nos responsabilizamos por tiempos de propagación DNS (24-48h) de proveedores externos, ni del contenido que decida alojar en su espacio FTP.</p>
        <p class="inline-panel-095"><strong>4. Seguridad de la Cuenta:</strong> El usuario es el único responsable de custodiar sus claves y credenciales. Se autoriza el acceso técnico de nuestros administradores en caso de necesidad o mantenimiento.</p>
        <p class="inline-panel-095"><strong>5. Showcase y Publicidad:</strong> Al aceptar la casilla opcional de "Showcase", el usuario autoriza a VinoMadrid Hosting a mostrar una previsualización y enlace de su sitio web en la sección pública de Showcase con fines promocionales. Este consentimiento puede ser revocado en cualquier momento desde el Panel de Usuario.</p>
        <p class="inline-panel-095"><strong>6. Reembolsos:</strong> Los registros de dominios procesados no son reembolsables una vez tramitados ante la entidad registradora.</p>
      </div>`,
    background: 'var(--surface)',
    color: 'var(--text)',
    confirmButtonColor: 'var(--accent)',
    confirmButtonText: 'Entendido',
    width: 'min(600px, calc(100vw - 1.5rem))'
  });
}
</script>

<?php require_once 'includes/footer.php'; ?>