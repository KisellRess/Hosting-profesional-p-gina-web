<?php
// Diagnóstico de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'sessions.php';
require_auth();

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

// LÓGICA DE VISOR ADMIN: Si es admin y pide un user_id, cambiamos la consulta al usuario objetivo
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin' && isset($_GET['user_id'])) {
    $target_id = (int)$_GET['user_id'];
    $query_user = "SELECT u.id, u.ftp_user, u.ftp_pass, d.fecha_caducidad, d.renovacion_auto, d.estado_dominio, d.dominio_propio, d.subdominio_alias 
                   FROM usuarios u 
                   LEFT JOIN dominios d ON u.id = d.user_id 
                   WHERE u.id = $target_id LIMIT 1";
} else {
    $query_user = "SELECT u.id, u.ftp_user, u.ftp_pass, d.fecha_caducidad, d.renovacion_auto, d.estado_dominio, d.dominio_propio, d.subdominio_alias 
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

if ($res_user && $res_user->num_rows === 1) {
    $row = $res_user->fetch_assoc();
    $user_id_panel           = (int) $row['id'];
    $fecha_caducidad_dominio = $row['fecha_caducidad'];
    $renovacion_auto         = (int) ($row['renovacion_auto'] ?? 0);
    $subdominio_actual       = $row['subdominio_alias'] ?? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['usuario']));
    $dom_propio              = $row['dominio_propio'];
    $estado_dom              = $row['estado_dominio'] ?? 'No configurado';

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

// Consulta para credenciales MySQL
$db_mysql = getConexion();
$res_sql = $db_mysql->query("SELECT db_name, db_user, db_pass, estado FROM modulo_mysql WHERE user_id = $user_id_panel LIMIT 1");
$mysql_data = ($res_sql && $res_sql->num_rows > 0) ? $res_sql->fetch_assoc() : null;
$db_mysql->close();

$mysql_db_name = $mysql_data['db_name'] ?? 'No configurado';
$mysql_db_user = $mysql_data['db_user'] ?? 'No configurado';
$mysql_db_pass = $mysql_data['db_pass'] ?? 'No configurado';
$estado_mysql = $mysql_data['estado'] ?? 'No configurado';

$ftp_user_folder = $_SESSION['ftp_user'] ?? $ftp_user_db; 
$folder_to_check = ($ftp_user_folder && $ftp_user_folder !== 'En trámite...' && $ftp_user_folder !== 'No asignado') ? "/var/www/hosting_tfg/" . $ftp_user_folder . "/htdocs" : '';
$bytes_usados   = $folder_to_check ? get_folder_size($folder_to_check) : 0;
$mb_usados      = round($bytes_usados / (1024 * 1024), 2);   // en MB
$gb_usados_pct  = $espacio_total_gb > 0 ? min(100, round(($mb_usados / ($espacio_total_gb * 1024)) * 100, 1)) : 0;

$extras = $_SESSION['extras'] ?? [];
$db_activas = in_array('Acceso SQL/PHP', $extras) ? '1 / 1' : '0 / 0';
$multiuser_info = in_array('Multiusuario ilimitado', $extras) ? 'Ilimitado' : (in_array('Pack Multiusuario x' . ($_SESSION['multiuser_qty'] ?? 0), $extras) ? ($_SESSION['multiuser_qty'] ?? 0) : 'Ninguno');

// panel.php - Validación de seguridad para evitar que la página se corte
if (!$user_id_panel && isset($_SESSION['user_id'])) {
    $user_id_panel = $_SESSION['user_id'];
}

// Si a pesar de todo no hay ID, forzamos uno para que no rompa el CSS
if (!$user_id_panel) { $user_id_panel = 0; } 

$titulo_pagina = 'Panel de Usuario';
$css_extra = '
<style>
  :root {
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
  h1, h2, h3, .panel-cred-label, .section-title {
    font-weight: 800 !important;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--accent) !important;
  }

  /* Inputs Uniformes */
  .swal2-input, input[type="text"], input[type="password"], input[type="email"], select {
    background-color: #1a1a24 !important;
    border: 1px solid var(--border) !important;
    border-radius: 8px !important;
    color: var(--text) !important;
    height: 45px !important;
    padding: 0 15px !important;
    font-size: 0.95rem !important;
    transition: all 0.3s ease !important;
    box-shadow: none !important;
  }

  .swal2-input:focus, input:focus {
    border-color: var(--accent) !important;
    box-shadow: 0 0 8px rgba(212, 175, 55, 0.3) !important;
    outline: none !important;
  }

  /* Botones Premium */
  .btn-gold, .swal2-confirm, .plan-btn:not(:disabled) {
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

  .btn-gold:hover, .swal2-confirm:hover, .plan-btn:hover:not(:disabled) {
    transform: translateY(-2px) !important;
    box-shadow: 0 5px 15px rgba(200, 169, 110, 0.4) !important;
    filter: brightness(1.1);
  }

  .swal2-cancel {
    background: #333 !important;
    color: #ccc !important;
    border-radius: 8px !important;
    font-weight: 600 !important;
  }

  /* Modales Glassmorphism */
  .swal2-popup {
    background: var(--glass) !important;
    backdrop-filter: blur(15px) !important;
    -webkit-backdrop-filter: blur(15px) !important;
    border: 1px solid rgba(212, 175, 55, 0.2) !important;
    border-radius: 20px !important;
    max-width: 450px !important;
    padding: 2rem !important;
  }

  .swal2-title {
    color: var(--accent) !important;
    font-size: 1.5rem !important;
  }

  .panel-widget, .panel-cred {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 15px;
    transition: border-color 0.3s ease;
  }

  .panel-widget:hover, .panel-cred:hover {
    border-color: rgba(212, 175, 55, 0.4);
  }

  #panel { background: var(--surface); color: var(--text); position: relative; }

  .panel-wrap {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
  }

  .panel-ui {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    margin-top: 3rem;
    box-shadow: 0 30px 60px rgba(0,0,0,0.8);
  }

  .panel-bar {
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .panel-bar-dot { width: 10px; height: 10px; border-radius: 50%; }

  .panel-body {
    padding: 2.5rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
  }

  .panel-widget, .panel-cred {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
  }
  
  .panel-widget:hover, .panel-cred:hover {
    border-color: var(--accent);
    box-shadow: 0 0 20px rgba(212, 175, 55, 0.15);
    transform: translateY(-5px);
  }

  .panel-widget-label, .panel-cred-label {
    font-size: 0.7rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 0.5rem;
    font-weight: bold;
  }

  .panel-widget-value {
    font-family: "Bebas Neue", sans-serif;
    font-size: 1.8rem;
    color: #fff;
    letter-spacing: 0.05em;
  }

  .cred-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem 0;
    border-bottom: 1px solid var(--border);
    font-size: 0.9rem;
  }
  .cred-row:last-child { border-bottom: none; }
  .cred-key { color: var(--muted); }
  .cred-val { color: #fff; font-family: "Courier New", monospace; }

  /* ─── BOTONES ─── */
  .plan-btn {
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
  
  .plan-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #e6ac00, var(--accent-bright));
    box-shadow: 0 0 25px rgba(255, 191, 0, 0.5);
    transform: translateY(-3px);
  }
  
  .plan-btn:disabled {
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: rgba(255, 255, 255, 0.2) !important;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
  }

  .cred-pending {
    color: var(--muted) !important;
    font-family: "DM Sans", sans-serif !important;
    font-style: italic;
    animation: credPulse 2s ease-in-out infinite;
  }

  /* SweetAlert Custom Dark */
  .swal2-popup { background: var(--surface) !important; color: var(--text) !important; border: 1px solid var(--border) !important; border-radius: 15px !important; }
  .swal2-input { background: #1a1a1a !important; color: #fff !important; border: 1px solid var(--border) !important; }
  .swal2-input:focus { border-color: var(--accent) !important; box-shadow: 0 0 5px var(--accent) !important; }

  </style>
';
require_once 'includes/header.php';
?>

<!-- ─── SECCIÓN CHAT CORREGIDA ─── -->
<div id="chat-trigger" onclick="toggleChat()" class="chat-trigger">
    <i class="fas fa-comment-dots"></i>
    <span class="chat-badge" id="chat-badge" style="display:none;">!</span>
</div>

<div id="chat-widget" class="chat-window">
    <div class="chat-header">
        <div>
            <i class="fas fa-robot" style="color: #d4af37; margin-right: 8px;"></i>
            <span>Asistente de Diseño IA</span>
        </div>
        <button onclick="toggleChat()" style="background:none; border:none; color:#fff; cursor:pointer;">&times;</button>
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

<style>
/* Estilos Glassmorphism Premium */
.chat-trigger {
    position: fixed; bottom: 30px; right: 30px;
    width: 60px; height: 60px;
    background: #d4af37; color: #000;
    border-radius: 50%; display: flex;
    align-items: center; justify-content: center;
    font-size: 24px; cursor: pointer;
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
    z-index: 10001; transition: transform 0.3s ease;
}
.chat-trigger:hover { transform: scale(1.1); }
.chat-trigger.glow { box-shadow: 0 0 20px #d4af37; animation: pulseGlow 2s infinite; }

@keyframes pulseGlow {
    0% { box-shadow: 0 0 0 0 rgba(212, 175, 55, 0.7); }
    70% { box-shadow: 0 0 0 15px rgba(212, 175, 55, 0); }
    100% { box-shadow: 0 0 0 0 rgba(212, 175, 55, 0); }
}

.chat-window {
    position: fixed; bottom: 100px; right: 30px;
    width: 350px; height: 500px;
    background: rgba(15, 15, 15, 0.85);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 20px; display: none;
    flex-direction: column; z-index: 10000;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    overflow: hidden;
}

.chat-header {
    padding: 15px; background: rgba(212, 175, 55, 0.1);
    border-bottom: 1px solid rgba(212, 175, 55, 0.2);
    display: flex; justify-content: space-between; align-items: center;
    color: #d4af37; font-weight: bold;
}

.chat-body {
    flex: 1; overflow-y: auto; padding: 15px;
    display: flex; flex-direction: column; gap: 12px;
}

.msg {
    padding: 10px 14px; border-radius: 15px;
    max-width: 85%; font-size: 14px; line-height: 1.4;
    animation: msgFade 0.3s ease;
}
@keyframes msgFade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.msg.usuario {
    align-self: flex-end; background: #d4af37; color: #000;
    border-bottom-right-radius: 2px;
}
.msg.admin {
    align-self: flex-start; background: rgba(255,255,255,0.1); color: #fff;
    border-bottom-left-radius: 2px; border: 1px solid rgba(255,255,255,0.1);
}

.chat-footer {
    padding: 15px; display: flex; gap: 10px;
    background: rgba(0,0,0,0.2);
}
#chat-input {
    flex: 1; background: rgba(255,255,255,0.05) !important;
    border: 1px solid rgba(212, 175, 55, 0.2) !important;
    border-radius: 10px; padding: 8px 12px; color: #fff; outline: none;
}
.send-btn {
    background: none; border: none; color: #d4af37;
    font-size: 18px; cursor: pointer; transition: 0.2s;
}
.send-btn:hover { transform: scale(1.1); color: #fff; }
</style>

<section id="panel" style="padding-top: 9rem; flex-grow: 1;">
  <div class="panel-wrap">
    <div class="section-tag">Misión 4</div>
    <h2 class="section-title">Panel de <em>Usuario</em></h2>
    <p class="section-desc">Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Cliente'); ?>. Gestiona tu hosting y servicios aquí.</p>

    <?php if ($sin_plan): ?>
    <div style="margin: 2rem 0; padding: 1.5rem 2rem; background: rgba(212,175,55,0.05); border: 1px solid var(--accent); border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.4);">
      <div>
        <div style="font-family: 'Bebas Neue', sans-serif; font-size: 1.4rem; color: var(--accent); letter-spacing: 0.05em;">¡Cuenta creada con éxito!</div>
        <div style="font-size: 0.9rem; color: var(--muted); margin-top: 0.3rem;">Aún no tienes un plan contratado. Elige el que mejor se adapte a tu proyecto.</div>
      </div>
      <a href="planes.php" class="plan-btn" style="text-decoration: none;">Ver Planes →</a>
    </div>
    <?php endif; ?>

    <?php
    // Feedback al volver de procesar_cambio.php
    $msg_cambio = $_GET['ok'] ?? '';
    $err_cambio = $_GET['error'] ?? '';
    ?>
    <?php if ($msg_cambio === 'modulo_eliminado'): ?>
    <div style="margin: 1rem 0; padding: 1rem 1.5rem; background: rgba(212,175,55,0.05); border: 1px solid var(--accent); border-radius: 8px; color: var(--accent); font-size: 0.9rem;">
      ✓ Módulo eliminado correctamente. Los cambios se aplicarán en tu próxima facturación.
    </div>
    <?php elseif ($msg_cambio === 'mysql_pendiente' || $msg_cambio === 'ftp_pendiente' || $msg_cambio === 'dominio_actualizado'): ?>
    <div style="margin: 1rem 0; padding: 1rem 1.5rem; background: rgba(212,175,55,0.05); border: 1px solid var(--accent); border-radius: 8px; color: var(--accent); font-size: 0.9rem;">
      ✓ Cambios realizados correctamente. Los procesos de fondo actualizarán tu entorno en unos minutos.
    </div>
    <?php elseif ($err_cambio !== ''): ?>
    <div style="margin: 1rem 0; padding: 1rem 1.5rem; background: rgba(231,76,60,0.1); border: 1px solid #e74c3c; border-radius: 6px; color: #e74c3c; font-size: 0.88rem;">
      ✗ Error al procesar el cambio (<?php echo htmlspecialchars($err_cambio); ?>). Contacta con soporte si persiste.
    </div>
    <?php endif; ?>

    <div class="panel-ui">
      <div class="panel-bar">
        <div class="panel-bar-dot" style="background:#c0392b;"></div>
        <div class="panel-bar-dot" style="background:#f39c12;"></div>
        <div class="panel-bar-dot" style="background:#27ae60;"></div>
        <span style="margin-left:1rem; font-size:0.75rem; color:var(--muted);">panel.vinomadrid-hosting.es — Mi Cuenta</span>
      </div>

      <div class="panel-body">
        <div class="panel-widget">
          <div class="panel-widget-label">Plan activo</div>
          <div class="panel-widget-value"><?php echo htmlspecialchars($plan); ?></div>
          <div class="panel-widget-sub">Renovación automática activa</div>
          <?php if (!$sin_plan): ?>
          <a href="modificar_servicios.php" style="display:inline-block; margin-top: 1rem; font-size: 0.78rem; color: var(--accent); text-decoration: none; letter-spacing: 0.05em; border-bottom: 1px solid rgba(200,169,110,0.3); padding-bottom: 1px;">⚙ Gestionar servicios →</a>
          <?php endif; ?>
        </div>
        <div class="panel-widget">
          <div class="panel-widget-label">Espacio en disco</div>
          <div class="panel-widget-value"><?php echo number_format($mb_usados, 2, ',', '.'); ?> <span style="font-size:1rem;">MB</span></div>
          <div class="panel-widget-sub">
            de <?php echo $espacio_total_gb; ?> GB disponibles
            <span style="display:block; margin-top:0.4rem;">
              <span style="display:inline-block; width:100%; height:4px; background:var(--border); border-radius:2px; overflow:hidden;">
                <span style="display:block; height:100%; width:<?php echo $gb_usados_pct; ?>%; background:var(--accent); border-radius:2px; transition:width 0.6s;"></span>
              </span>
              <span style="font-size:0.72rem; color:var(--muted);"><?php echo $gb_usados_pct; ?>% usado</span>
            </span>
          </div>
        </div>
        <div class="panel-widget">
          <div class="panel-widget-label">Bases de datos</div>
          <div class="panel-widget-value"><?php echo $db_activas; ?></div>
          <div class="panel-widget-sub">MySQL activas</div>
        </div>
        <div class="panel-widget">
          <div class="panel-widget-label">Módulos Extras Activos</div>
          <div class="panel-widget-value" style="font-size: 1rem; color: var(--text);">
            <?php 
              if (empty($extras)) {
                  echo '<span style="color:var(--muted)">Ninguno</span>';
              } else {
                  echo implode('<br>', array_map('htmlspecialchars', $extras));
              }
            ?>
          </div>
          <div class="panel-widget-sub">Usuarios extra: <?php echo htmlspecialchars($multiuser_info); ?></div>
        </div>
        
        <!-- GESTIÓN DE DOMINIOS Y SUBDOMINIOS -->
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
          
          $subdominio_actual = $sub_alias ? htmlspecialchars($sub_alias) : strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['usuario']));
        ?>
        <div class="panel-widget" style="grid-column: 1 / -1; border-color: <?php echo ($dom_propio || $tiene_fecha) ? 'var(--accent)' : 'var(--border)'; ?>;">
          <div class="panel-widget-label">Direcciones y Dominios</div>
          
          <div style="display:flex; flex-direction: column; gap: 2rem;">
            <!-- SUBDOMINIO -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border);">
              <div>
                <div style="font-size: 0.85rem; color: var(--muted); margin-bottom: 0.3rem;">Subdominio Principal (Gratis)</div>
                <div class="panel-widget-value" style="font-size: 1.6rem; color: var(--text); font-family: 'Courier New', monospace; text-transform: lowercase;">
                  vinomadrid.es/<span style="color:var(--accent);"><?php echo $subdominio_actual; ?></span>
                </div>
              </div>
              <button class="plan-btn" style="padding: 0.5rem 1rem; width: auto;" <?php echo $sin_plan ? 'onclick="location.href=\'planes.php\'"' : 'onclick="cambiarSubdominio()"'; ?>>Cambiar alias</button>
            </div>

            <!-- DOMINIO PERSONALIZADO -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
              <div>
                <div style="font-size: 0.85rem; color: var(--muted); margin-bottom: 0.3rem;">Dominio Personalizado (Máx. 1 por cuenta)</div>
                
                <?php if ($dom_propio): ?>
                  <div class="panel-widget-value" style="font-size: 1.6rem; color: var(--accent);">
                    <?php echo htmlspecialchars($dom_propio); ?>
                  </div>
                  <div class="panel-widget-sub" style="color: var(--accent);">✓ Servicio Activo</div>

                <?php elseif ($tiene_fecha): ?>
                  <div class="panel-widget-value" style="font-size: 1.6rem; color: #f39c12;">
                    PAGO CONFIRMADO
                  </div>
                  <div class="panel-widget-sub" style="color: #f39c12;">✓ Tienes un dominio prepagado listo para asignar.</div>

                <?php else: ?>
                  <div class="panel-widget-value" style="font-size: 1.2rem; color: var(--muted);">No configurado</div>
                  <div class="panel-widget-sub">Elige una opción para tu identidad web.</div>
                <?php endif; ?>
              </div>
              
              <div style="display: flex; gap: 1rem;">
                <?php if ($sin_plan): ?>
                  <a href="planes.php" class="plan-btn" style="padding: 0.5rem 1rem; text-decoration: none;">Comprar Plan</a>
                <?php else: ?>
                  <?php if ($dom_propio): ?>
                    <button class="plan-btn" style="padding: 0.5rem 1rem;" onclick="conectarDominioPropio()">Editar</button>
                    <button class="plan-btn" style="padding: 0.5rem 1rem; border-color: #e74c3c; color: #e74c3c;" onclick="desvincularDominio()">Desvincular</button>
                  
                  <?php else: ?>
                    <!-- Botones cuando NO hay dominio activo -->
                    <button class="plan-btn" style="padding: 0.5rem 1rem; border-color: <?php echo $tiene_fecha ? 'var(--accent)' : 'var(--border)'; ?>;" onclick="conectarDominioPropio()">
                      <?php echo $tiene_fecha ? 'Asignar mi dominio pagado' : 'Ya tengo uno'; ?>
                    </button>
                    
                    <?php if (!$tiene_fecha): ?>
                      <button class="plan-btn" style="padding: 0.5rem 1rem;" onclick="comprarDominio()">Comprar dominio (15€)</button>
                    <?php else: ?>
                      <!-- Botón para resetear si se equivocó al elegir 'Tengo uno propio' en lugar de comprar[cite: 10] -->
                      <button class="plan-btn" style="padding: 0.5rem 1rem; border-color: #e74c3c; color: #e74c3c;" onclick="desvincularDominio()">Limpiar elección</button>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        
        <div class="panel-cred">
          <div class="panel-cred-label">Credenciales de acceso FTP</div>
          <div class="cred-row">
            <span class="cred-key">Host</span>
            <?php if ($sin_plan || $ftp_user_db === 'En trámite...'): ?>
              <span class="cred-val" style="color:var(--muted); font-size:0.8rem;">⏳ Pendiente de asignación</span>
            <?php else: ?>
              <span class="cred-val">kiellress.ddns.net</span>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Usuario FTP</span>
            <?php if ($sin_plan): ?>
              <span class="cred-val" style="color:var(--muted); font-size:0.8rem;">🔒 Servicio inactivo</span>
            <?php elseif ($ftp_user_db === 'En trámite...'): ?>
              <span class="cred-val cred-pending">⏳ <?php echo htmlspecialchars($ftp_user_db); ?></span>
            <?php else: ?>
              <span class="cred-val"><?php echo htmlspecialchars($ftp_user_db); ?></span>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Puerto</span>
            <?php if ($sin_plan || $ftp_user_db === 'En trámite...'): ?>
              <span class="cred-val" style="color:var(--muted); font-size:0.8rem;">🔒 Oculto</span>
            <?php else: ?>
              <span class="cred-val">21</span>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Contraseña</span>
            <?php if ($sin_plan): ?>
              <span class="cred-val" style="color:var(--muted); font-size:0.8rem;">🔒 Compra un plan para ver credenciales</span>
            <?php elseif ($ftp_pass_db === 'En trámite...'): ?>
              <span class="cred-val cred-pending">⏳ <?php echo htmlspecialchars($ftp_pass_db); ?></span>
            <?php else: ?>
              <div style="display:flex; align-items:center; gap:0.5rem;">
                <span id="ftp-pass-val" class="cred-val" style="-webkit-text-security: disc;"><?php echo htmlspecialchars($ftp_pass_db); ?></span>
                <button type="button" onclick="togglePass('ftp-pass-val')" style="background:none; border:none; color:var(--accent); cursor:pointer; font-size:1rem; padding:0; display:flex;">👁️</button>
              </div>
            <?php endif; ?>
          </div>
          <?php if ($sin_plan): ?>
            <a href="planes.php" class="plan-btn" style="margin-top:1rem; width:auto; padding:0.4rem 1rem; text-decoration: none; display: inline-block;">Ver Planes</a>
          <?php elseif ($ftp_user_db === 'En trámite...'): ?>
            <button class="plan-btn" disabled style="margin-top:1rem; width:auto; padding:0.4rem 1rem;">⏳ Configurando entorno...</button>
          <?php else: ?>
            <button class="plan-btn" onclick="editarFTP()" style="margin-top:1rem; width:auto; padding:0.4rem 1rem;">Editar FTP</button>
          <?php endif; ?>
        </div>

        <!-- CREDENCIALES MYSQL -->
        <div class="panel-cred">
          <div class="panel-cred-label">Credenciales de acceso MySQL</div>
          <div class="cred-row">
            <span class="cred-key">Host</span>
            <?php if ($sin_plan || $estado_mysql === 'Tramitando'): ?>
              <span class="cred-val" style="color:var(--muted); font-size:0.8rem;">⏳ Pendiente de asignación</span>
            <?php else: ?>
              <span class="cred-val">vinomadrid.es/tfg_phpmyadmin</span>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Nombre DB</span>
            <?php if ($sin_plan): ?>
              <span class="cred-val" style="color:var(--muted); font-size:0.8rem;">🔒 Servicio inactivo</span>
            <?php else: ?>
              <span class="cred-val"><?php echo htmlspecialchars($mysql_db_name); ?></span>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Usuario</span>
            <?php if ($sin_plan): ?>
              <span class="cred-val" style="color:var(--muted); font-size:0.8rem;">🔒 Servicio inactivo</span>
            <?php else: ?>
              <span class="cred-val"><?php echo htmlspecialchars($mysql_db_user); ?></span>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Contraseña</span>
            <?php if ($sin_plan): ?>
              <span class="cred-val" style="color:var(--muted); font-size:0.8rem;">🔒 Servicio inactivo</span>
            <?php else: ?>
              <div style="display:flex; align-items:center; gap:0.5rem;">
                <span id="mysql-pass-val" class="cred-val" style="-webkit-text-security: disc;"><?php echo htmlspecialchars($mysql_db_pass); ?></span>
                <button type="button" onclick="togglePass('mysql-pass-val')" style="background:none; border:none; color:var(--accent); cursor:pointer; font-size:1rem; padding:0; display:flex;">👁️</button>
              </div>
            <?php endif; ?>
          </div>
          <div class="cred-row">
            <span class="cred-key">Estado</span>
            <?php if ($estado_mysql === 'Tramitando'): ?>
              <span class="cred-val cred-pending">⏳ <?php echo htmlspecialchars($estado_mysql); ?></span>
            <?php else: ?>
              <span class="cred-val"><?php echo htmlspecialchars($estado_mysql); ?></span>
            <?php endif; ?>
          </div>
          <?php if ($sin_plan): ?>
            <a href="planes.php" class="plan-btn" style="margin-top:1rem; width:auto; padding:0.4rem 1rem; text-decoration: none; display: inline-block;">Ver Planes</a>
          <?php elseif ($estado_mysql === 'Tramitando'): ?>
            <button class="plan-btn" disabled style="margin-top:1rem; width:auto; padding:0.4rem 1rem;">⏳ Configurando BD...</button>
          <?php elseif ($estado_mysql === 'No configurado'): ?>
            <button class="plan-btn" onclick="editarMySQL()" style="margin-top:1rem; width:auto; padding:0.4rem 1rem;">Activar MySQL</button>
          <?php else: ?>
            <button class="plan-btn" onclick="editarMySQL()" style="margin-top:1rem; width:auto; padding:0.4rem 1rem;">Editar MySQL</button>
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
            $plan_actual = $_SESSION['plan'] ?? 'Ninguno';
            $multiuser_qty_comprados = (int)($_SESSION['multiuser_qty'] ?? 0);
            
            $max_staff = 0;
            $is_unlimited = false;
            
            if ($plan_actual === 'ENTERPRISE') {
                $is_unlimited = true;
            } elseif ($plan_actual === 'PROFESIONAL') {
                $max_staff = 2 + $multiuser_qty_comprados;
            } else {
                $max_staff = $multiuser_qty_comprados;
            }
            
            $can_add_staff = $is_unlimited || ($staff_count < $max_staff);
            $has_staff_access = $is_unlimited || ($max_staff > 0);
            $limite_txt = $is_unlimited ? '&infin;' : $max_staff;
          ?>
          
          <div class="panel-cred-label" style="display:flex; justify-content:space-between; align-items:center;">
            <span>Módulos Extras Activos (Staff)</span>
            <span style="font-size:0.9rem; color:var(--accent); font-weight:bold; letter-spacing:0.1em;"><?php echo $staff_count; ?> / <?php echo $limite_txt; ?></span>
          </div>

          <?php if ($has_staff_access): ?>
            <?php if ($staff_count > 0): ?>
              <?php while($s = $res_staff->fetch_assoc()): ?>
                <div class="cred-row" style="flex-wrap: wrap; gap: 1rem;">
                    <div style="flex: 1; min-width: 150px;">
                      <span class="cred-key">Usuario:</span>
                      <span class="cred-val"><?php echo htmlspecialchars($s['ftp_user']); ?></span>
                    </div>
                    
                    <div style="flex: 1; min-width: 150px; display: flex; align-items: center; gap: 0.5rem;">
                      <span class="cred-key">Pass:</span>
                      <span id="staff-pass-<?php echo $s['id']; ?>" class="cred-val" style="-webkit-text-security: disc;"><?php echo htmlspecialchars($s['ftp_pass']); ?></span>
                      <button type="button" onclick="togglePass('staff-pass-<?php echo $s['id']; ?>')" style="background:none; border:none; color:var(--accent); cursor:pointer; font-size:1rem; padding:0; display:flex;">👁️</button>
                    </div>

                    <div style="display: flex; align-items: center; gap: 1rem;">
                      <span class="cred-val <?php echo ($s['estado'] === 'Tramitando') ? 'cred-pending' : ''; ?>">
                          <?php if ($s['estado'] === 'Tramitando'): ?>⏳ <?php endif; ?><?php echo htmlspecialchars($s['estado']); ?>
                      </span>
                      <button type="button" class="btn-borrar" onclick="confirmarBorradoStaff(<?php echo $s['id']; ?>)" style="background:none; border:none; color:#ff4d4d; cursor:pointer; font-size:0.9rem;">
                          <i class="fas fa-trash"></i>
                      </button>
                    </div>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <p style="font-size:0.8rem; color:var(--muted);">No hay usuarios staff creados todavía.</p>
            <?php endif; ?>
            
            <?php if ($can_add_staff): ?>
              <button class="plan-btn" onclick="modalCrearStaff()" style="margin-top:1rem; width:auto; padding:0.4rem 1rem;">+ Añadir Staff</button>
            <?php else: ?>
              <p style="font-size:0.8rem; color:#e74c3c; margin-top:1rem;">Has alcanzado el límite de usuarios Staff (<?php echo $max_staff; ?>).</p>
              <a href="modificar_servicios.php#extras" class="plan-btn" style="margin-top:0.5rem; width:auto; padding:0.4rem 1rem; background: var(--surface2); color: var(--text); text-decoration: none;">Ampliar límite</a>
            <?php endif; ?>
            
            <?php $db_staff->close(); ?>
          <?php else: ?>
            <?php $db_staff->close(); ?>
            <p style="font-size:0.8rem; color:var(--muted);">Servicio no contratado.</p>
            <a href="<?php echo $sin_plan ? 'planes.php' : 'modificar_servicios.php#extras'; ?>" class="plan-btn" style="margin-top:1rem; width:auto; padding:0.4rem 1rem; background: var(--accent); color: white; text-decoration: none;">Comprar Staff</a>
          <?php endif; ?>
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

        <div class="panel-widget" style="grid-column: 1 / -1; border-color: #e74c3c;">
          <div class="panel-widget-label" style="color: #e74c3c;">Estado del Servicio</div>
          
          <?php if ($user_status['estado_servicio'] === 'Cancelado'): ?>
            <div style="background: rgba(231, 76, 60, 0.1); padding: 1.5rem; border-radius: 8px; border: 1px solid rgba(231, 76, 60, 0.2);">
              <div class="panel-widget-value" style="color:#e74c3c; font-size: 1.4rem;">CANCELACIÓN EN CURSO</div>
              <p style="font-size: 0.9rem; margin-top: 0.7rem; color: var(--text);">
                Solicitado el: <strong><?php echo date('d/m/Y H:i', strtotime($user_status['fecha_cancelacion'])); ?></strong><br>
                Tu cuenta y archivos serán borrados definitivamente por un administrador en menos de 24h.
              </p>
              <p style="font-size: 0.8rem; color: var(--muted); margin-top: 1.2rem; font-style: italic;">
                * Para revertir esta acción, contacta con administración antes de que expire el plazo.
              </p>
            </div>
          <?php else: ?>
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
              <div>
                <div class="panel-widget-value" style="font-size: 1.6rem; color:#e74c3c;">Eliminar cuenta y datos</div>
                <div class="panel-widget-sub">Atención: Se borrarán tus archivos y bases de datos MySQL en 24h.</div>
              </div>
              <form method="POST" action="cancelar_suscripcion.php" id="form-cancelar">
                <button type="button" class="plan-btn" style="border-color:#e74c3c; color:#e74c3c; background:transparent;" onclick="confirmarCancelacion()">Eliminar cuenta</button>
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
    const input = document.getElementById(id);
    const icon = document.getElementById('icon_' + id);
    
    // Si no hay icono (ej: en las tablas), usamos el método antiguo de webkitTextSecurity
    if (!icon) {
      if (input.style.webkitTextSecurity === 'disc') {
        input.style.webkitTextSecurity = 'none';
      } else {
        input.style.webkitTextSecurity = 'disc';
      }
      return;
    }

    // Lógica para inputs con icono (modal)
    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = "password";
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
  }

  function toggleInputPass(id) {
    // Alias para compatibilidad o simplificación
    togglePass(id);
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
            // Enviamos vía POST para mantener compatibilidad con procesar_staff.php
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'procesar_staff.php';
            
            const acc = document.createElement('input');
            acc.type = 'hidden'; acc.name = 'accion'; acc.value = 'borrar_staff';
            form.appendChild(acc);
            
            const sid = document.createElement('input');
            sid.type = 'hidden'; sid.name = 'staff_id'; sid.value = id;
            form.appendChild(sid);
            
            document.body.appendChild(form);
            form.submit();
        }
    });
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
        document.getElementById('form-cancelar').submit();
      }
    });
  }

  function cambiarSubdominio() {
    Swal.fire({
      title: 'Cambiar Subdominio',
      html: 'Nuevo alias para tu subdominio gratuito:<br><br><div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;"><span style="color:var(--muted);">vinomadrid.es/</span><input id="swal-input-sub" class="swal2-input" style="margin:0; width:150px;" placeholder="miweb"></div><br><br><span style="font-size:0.85rem; color:var(--muted);">Nota: El cambio tardará menos de 5 minutos en aplicarse mediante nuestros procesos internos.</span>',
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
      html: 'Introduce el dominio que ya posees (ej. <i>miempresa.com</i>).<br><br><strong>¡IMPORTANTE!</strong> Debes apuntar los Nameservers (DNS) de tu proveedor de dominio a los nuestros:<br><br><strong style="color:var(--accent);">arya.ns.cloudflare.com</strong><br><strong style="color:var(--accent);">elmo.ns.cloudflare.com</strong><br><br><span style="color:#e74c3c; font-size: 0.9em;">Aviso: No nos hacemos cargo de brindar soporte sobre plataformas externas. Tú eres responsable de configurar los DNS en tu proveedor.</span>',
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
        <p style="margin-bottom: 1rem; font-size: 0.9em;">El proceso de validación y registro oficial puede tardar <b>entre 24 y 48 horas</b>.</p>
        <div style="text-align: left;">
            <label style="font-size: 0.85rem; color: var(--muted);">Dominio Principal Deseado</label>
            <input id="dom_principal" class="swal2-input" placeholder="midominio.com" style="margin-top: 0.2rem; margin-bottom: 1rem;">
            
            <label style="font-size: 0.85rem; color: var(--muted);">Alternativa 1 (Opcional)</label>
            <input id="dom_alt1" class="swal2-input" placeholder="midominio.es" style="margin-top: 0.2rem; margin-bottom: 1rem;">
            
            <label style="font-size: 0.85rem; color: var(--muted);">Alternativa 2 (Opcional)</label>
            <input id="dom_alt2" class="swal2-input" placeholder="mi-dominio.com" style="margin-top: 0.2rem; margin-bottom: 1rem;">
            
            <label style="font-size: 0.85rem; color: var(--muted);">Alternativa 3 (Opcional)</label>
            <input id="dom_alt3" class="swal2-input" placeholder="midominio.net" style="margin-top: 0.2rem;">
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
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'procesar_dominio.php';
    form.style.display = 'none';

    const act = document.createElement('input');
    act.type = 'hidden';
    act.name = 'accion';
    act.value = accion;
    form.appendChild(act);

    const val = document.createElement('input');
    val.type = 'hidden';
    val.name = 'valor';
    val.value = valor;
    form.appendChild(val);

    document.body.appendChild(form);
    form.submit();
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
    Swal.fire({
      title: 'Editar Credenciales MySQL',
      html: `
        <div style="text-align:left;">
          <label style="font-size:0.8rem; color:var(--muted);">Nombre DB (ej: miweb_db)</label>
          <input type="text" id="swal-db-name" class="swal2-input" placeholder="Nombre DB" value="<?php echo htmlspecialchars($mysql_db_name); ?>" style="margin-top:0.2rem; margin-bottom:1rem;">
          
          <label style="font-size:0.8rem; color:var(--muted);">Usuario DB (ej: miweb_user)</label>
          <input type="text" id="swal-db-user" class="swal2-input" placeholder="Usuario DB" value="<?php echo htmlspecialchars($mysql_db_user); ?>" style="margin-top:0.2rem; margin-bottom:1rem;">
          
          <label style="font-size:0.8rem; color:var(--muted);">Nueva Contraseña (Dejar vacío para no cambiar)</label>
          <div style="position:relative; margin-top:0.2rem; margin-bottom:1rem;">
            <input type="password" id="swal-db-pass" class="swal2-input" placeholder="Nueva Contraseña" style="margin:0; width:100%;">
            <button type="button" onclick="toggleInputPass('swal-db-pass')" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--accent); cursor:pointer;">👁️</button>
          </div>

          <label style="font-size:0.8rem; color:var(--muted);">Repetir Nueva Contraseña</label>
          <input type="password" id="swal-db-pass-rep" class="swal2-input" placeholder="Repetir Contraseña" style="margin-top:0.2rem;">
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'Actualizar',
      background: 'var(--surface)',
      color: 'var(--text)',
      preConfirm: () => {
        const dbName = document.getElementById('swal-db-name').value;
        const dbUser = document.getElementById('swal-db-user').value;
        const dbPass = document.getElementById('swal-db-pass').value;
        const dbPassRep = document.getElementById('swal-db-pass-rep').value;

        if (!dbName || !dbUser) {
          Swal.showValidationMessage('Nombre y usuario son obligatorios');
          return false;
        }

        if (dbPass !== "" || dbPassRep !== "") {
          if (dbPass !== dbPassRep) {
            Swal.showValidationMessage('Las contraseñas no coinciden');
            return false;
          }
          if (dbPass.length < 6) {
            Swal.showValidationMessage('La contraseña debe tener al menos 6 caracteres');
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
        <div style="text-align:left;">
          <label style="font-size:0.8rem; color:var(--muted);">Usuario FTP</label>
          <input type="text" id="swal-ftp-user" class="swal2-input" placeholder="Usuario FTP" value="<?php echo htmlspecialchars($ftp_user_db); ?>" style="margin-top:0.2rem; margin-bottom:1rem;">
          
          <label style="font-size:0.8rem; color:var(--muted);">Nueva Contraseña (Dejar vacío para no cambiar)</label>
          <div style="position:relative; margin-top:0.2rem; margin-bottom:1rem;">
            <input type="password" id="swal-ftp-pass" class="swal2-input" placeholder="Nueva Contraseña" style="margin:0; width:100%;">
            <button type="button" onclick="togglePass('swal-ftp-pass')" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--accent); cursor:pointer;">
              <i class="fas fa-eye" id="icon_swal-ftp-pass"></i>
            </button>
          </div>

          <label style="font-size:0.8rem; color:var(--muted);">Repetir Nueva Contraseña</label>
          <input type="password" id="swal-ftp-pass-rep" class="swal2-input" placeholder="Repetir Contraseña" style="margin-top:0.2rem;">
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
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'procesar_mysql.php';
    form.style.display = 'none';

    const name = document.createElement('input');
    name.type = 'hidden';
    name.name = 'db_name';
    name.value = dbName;
    form.appendChild(name);

    const user = document.createElement('input');
    user.type = 'hidden';
    user.name = 'db_user';
    user.value = dbUser;
    form.appendChild(user);

    const pass = document.createElement('input');
    pass.type = 'hidden';
    pass.name = 'db_pass';
    pass.value = dbPass;
    form.appendChild(pass);

    document.body.appendChild(form);
    form.submit();
  }

  function enviarPeticionFTP(ftpUser, ftpPass) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'procesar_ftp.php';
    form.style.display = 'none';

    const user = document.createElement('input');
    user.type = 'hidden';
    user.name = 'ftp_user';
    user.value = ftpUser;
    form.appendChild(user);

    const pass = document.createElement('input');
    pass.type = 'hidden';
    pass.name = 'ftp_pass';
    pass.value = ftpPass;
    form.appendChild(pass);

    document.body.appendChild(form);
    form.submit();
  }

  function modalCrearStaff() {
    Swal.fire({
      title: 'Añadir nuevo usuario Staff',
      html: `
        <div style="text-align:left;">
          <label style="font-size:0.8rem; color:var(--muted);">Nombre (ej: ventas)</label>
          <input type="text" id="swal-user" class="swal2-input" placeholder="Nombre" style="margin-top:0.2rem; margin-bottom:1rem;">
          
          <label style="font-size:0.8rem; color:var(--muted);">Contraseña</label>
          <div style="position:relative; margin-top:0.2rem;">
            <input type="password" id="swal-pass" class="swal2-input" placeholder="Contraseña" style="margin:0; width:100%;">
            <button type="button" onclick="togglePass('swal-pass')" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--accent); cursor:pointer;">
              <i class="fas fa-eye" id="icon_swal-pass"></i>
            </button>
          </div>
          <p style="font-size:0.8rem; color:var(--muted); margin-top:1rem;">El usuario final será: u<?php echo $_SESSION['user_id']; ?>_nombre</p>
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
        // Enviar por POST a procesar_staff.php
        enviarPeticionStaff('crear_staff', result.value.nombre, result.value.pass);
      }
    });
  }

  function enviarPeticionStaff(accion, nombre, pass) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'procesar_staff.php';
    form.style.display = 'none';

    const actInput = document.createElement('input');
    actInput.type = 'hidden';
    actInput.name = 'accion';
    actInput.value = accion;
    form.appendChild(actInput);

    const nameInput = document.createElement('input');
    nameInput.type = 'hidden';
    nameInput.name = 'nombre_staff';
    nameInput.value = nombre;
    form.appendChild(nameInput);

    const passInput = document.createElement('input');
    passInput.type = 'hidden';
    passInput.name = 'pass_staff';
    passInput.value = pass;
    form.appendChild(passInput);

    document.body.appendChild(form);
    form.submit();
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
</script>



<?php require_once 'includes/footer.php'; ?>
