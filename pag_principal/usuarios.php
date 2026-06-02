<?php
/* ============================================================
   ARCHIVO: usuarios.php
   FUNCION: administrar cuentas desde el area privada.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

session_start();
require_once 'conexiones.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: panel.php');
    exit;
}

$db = getConexion();
$msg = "";

/* SECCION: calculo del uso real en la carpeta web de cada cliente. */
function calcular_espacio_usuario(string $ftp_user): int {
    $usuario_seguro = preg_replace('/[^a-zA-Z0-9_-]/', '', $ftp_user);
    if ($usuario_seguro === '' || $usuario_seguro !== $ftp_user) {
        return 0;
    }

    $ruta = "/var/www/hosting_tfg/$usuario_seguro/htdocs";
    if (!is_dir($ruta) || !is_readable($ruta)) {
        return 0;
    }

    $total = 0;
    try {
        $archivos = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($ruta, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($archivos as $archivo) {
            if ($archivo->isFile()) {
                $total += $archivo->getSize();
            }
        }
    } catch (UnexpectedValueException $e) {
        return 0;
    }

    return $total;
}

function formatear_espacio_usuario(int $bytes): string {
    if ($bytes === 0) {
        return '0 MB';
    }
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }
    return number_format($bytes / 1024, 2, ',', '.') . ' KB';
}

// --- LÓGICA DE ACTUALIZACIÓN (POST) ---
if (isset($_POST['actualizar_usuario'])) {
    $uid = (int)$_POST['user_id'];
    $nombre = $db->real_escape_string($_POST['nombre']);
    $email = $db->real_escape_string($_POST['email']);
    $plan = $db->real_escape_string($_POST['plan']);
    $rol = $db->real_escape_string($_POST['rol']);
    $ftp_user = $db->real_escape_string($_POST['ftp_user']);
    $ftp_pass = $db->real_escape_string($_POST['ftp_pass']);

    $admin_nom = $_SESSION['usuario'] ?? 'Admin';
    $admin_id = $_SESSION['user_id'] ?? 0;
    $date_str = date('Y-m-d H:i:s');
    $log_dir = "/opt/tfg/scripts/logs";
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $log_file = "$log_dir/acciones.log";

    // 1. Obtener valores actuales del usuario
    $user_curr_q = $db->query("SELECT nombre, email, plan_contratado, rol, ftp_user, ftp_pass, estado_servicio FROM usuarios WHERE id = $uid LIMIT 1");
    if ($user_curr_q && $user_curr_row = $user_curr_q->fetch_assoc()) {
        $curr_nombre = $user_curr_row['nombre'];
        $curr_email = $user_curr_row['email'];
        $curr_plan = $user_curr_row['plan_contratado'];
        $curr_rol = $user_curr_row['rol'];
        $curr_ftp_user = $user_curr_row['ftp_user'];
        $curr_ftp_pass = $user_curr_row['ftp_pass'];
    } else {
        $log_msg = "[$date_str] [ADMIN_EDITAR_ERROR] Admin '$admin_nom' intentó editar usuario inexistente (ID: $uid). Fallo.\n";
        @file_put_contents($log_file, $log_msg, FILE_APPEND);
        header("Location: usuarios.php?error=no_existe");
        exit;
    }

    $cambio_ftp = ($curr_ftp_user !== $ftp_user) || ($curr_ftp_pass !== $ftp_pass);
    $cambio_plan = ($curr_plan !== $plan);

    // Actualización de campos principales
    if ($cambio_ftp || $cambio_plan) {
        $db->query("UPDATE usuarios SET nombre='$nombre', email='$email', plan_contratado='$plan', rol='$rol', ftp_user='$ftp_user', ftp_pass='$ftp_pass', estado_servicio='Para_Modificar', creado_en_so=0 WHERE id=$uid");
    } else {
        $db->query("UPDATE usuarios SET nombre='$nombre', email='$email', plan_contratado='$plan', rol='$rol', ftp_user='$ftp_user', ftp_pass='$ftp_pass' WHERE id=$uid");
    }

    // Actualización de contraseña de LOGIN (Hash + Plain)
    if (!empty($_POST['nueva_pass'])) {
        $plain = $_POST['nueva_pass'];
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        $plain_safe = $db->real_escape_string($plain);
        $db->query("UPDATE usuarios SET password_hash='$hash', password_plain='$plain_safe' WHERE id=$uid");
    }
    
    // Actualizar MySQL si existe y si cambió su contraseña
    $cambio_mysql = false;
    if (isset($_POST['db_pass'])) {
        $db_pass = $db->real_escape_string($_POST['db_pass']);
        $mysql_curr_q = $db->query("SELECT db_pass FROM modulo_mysql WHERE user_id=$uid LIMIT 1");
        if ($mysql_curr_q && $mysql_curr_q->num_rows > 0) {
            $mysql_curr_row = $mysql_curr_q->fetch_assoc();
            $curr_db_pass = $mysql_curr_row['db_pass'];
            if ($curr_db_pass !== $db_pass) {
                // Pendiente reprocesa la base existente y esta permitido por el esquema SQL.
                $db->query("UPDATE modulo_mysql SET db_pass='$db_pass', estado='Pendiente' WHERE user_id=$uid");
                $cambio_mysql = true;
            }
        }
    }

    // Ejecutar trabajadores del sistema y registrar logs / alertas si hay cambios
    if ($cambio_ftp || $cambio_plan) {
        shell_exec("sudo python3 /opt/tfg/scripts/crear_usuarios.py > /dev/null 2>&1");
        
        $log_msg = "[$date_str] [ADMIN_EDITAR_USUARIO_FTP_PLAN] Admin '$admin_nom' (ID: $admin_id) modificó FTP/Plan de usuario '$nombre' (ID: $uid). Plan: $plan, FTP: $ftp_user. Éxito.\n";
        @file_put_contents($log_file, $log_msg, FILE_APPEND);
        
        $motivo = "Admin '$admin_nom' editó credenciales FTP o Plan del usuario '$nombre'";
        $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($uid, '$nombre', '$motivo', '⚙️', 0, NOW())");
    }

    if ($cambio_mysql) {
        shell_exec("sudo python3 /opt/tfg/scripts/mysql_worker.py > /dev/null 2>&1");
        
        $log_msg = "[$date_str] [ADMIN_EDITAR_USUARIO_MYSQL] Admin '$admin_nom' (ID: $admin_id) modificó base de datos MySQL de usuario '$nombre' (ID: $uid). Éxito.\n";
        @file_put_contents($log_file, $log_msg, FILE_APPEND);
        
        $motivo = "Admin '$admin_nom' modificó la contraseña MySQL del usuario '$nombre'";
        $db->query("INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) VALUES ($uid, '$nombre', '$motivo', '🗄️', 0, NOW())");
    }

    // Log general de la acción
    $log_msg = "[$date_str] [ADMIN_EDITAR_USUARIO] Admin '$admin_nom' (ID: $admin_id) actualizó la ficha del usuario '$nombre' (ID: $uid). Éxito.\n";
    @file_put_contents($log_file, $log_msg, FILE_APPEND);

    header("Location: usuarios.php?id=$uid&msg=ok");
    exit;
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($user_id) {
    $u = $db->query("SELECT * FROM usuarios WHERE id=$user_id")->fetch_assoc();
    $dom = $db->query("SELECT * FROM dominios WHERE user_id=$user_id")->fetch_assoc();
    $mysql = $db->query("SELECT * FROM modulo_mysql WHERE user_id=$user_id")->fetch_assoc();
    // Los datos ausentes de MySQL se muestran de forma explicita en administracion.
    $mysql_db_name_admin = trim((string) ($mysql['db_name'] ?? ''));
    $mysql_db_user_admin = trim((string) ($mysql['db_user'] ?? ''));
    $mysql_db_pass_admin = trim((string) ($mysql['db_pass'] ?? ''));
    $res_staff = $db->query("SELECT * FROM ftp_cuentas_extra WHERE user_id=$user_id");
    $extras = json_decode($u['extras_json'], true) ?: [];
    
    $base_gb = ['BÁSICO' => 1, 'PROFESIONAL' => 3, 'ENTERPRISE' => 5];
    $total_gb = $base_gb[strtoupper($u['plan_contratado'])] ?? 1;

    // ── CONSULTA DE FACTURACIÓN ───────────────────────────────────────
    $stmt_f = $db->prepare("SELECT id, fecha_emision, concepto, importe, base_imponible, iva_importe, estado FROM facturas WHERE user_id = ? ORDER BY fecha_emision DESC");
    $stmt_f->bind_param("i", $user_id);
    $stmt_f->execute();
    $res_facturas = $stmt_f->get_result();
    $facturas_list = [];
    while ($frow = $res_facturas->fetch_assoc()) {
        $base = isset($frow['base_imponible']) && $frow['base_imponible'] !== null
            ? round((float)$frow['base_imponible'], 2)
            : round($frow['importe'] / 1.21, 2);
        $iva = isset($frow['iva_importe']) && $frow['iva_importe'] !== null
            ? round((float)$frow['iva_importe'], 2)
            : round($frow['importe'] - $base, 2);
        $facturas_list[] = array_merge($frow, ['iva' => $iva, 'base' => $base]);
    }
    $stmt_f->close();
} else {
    $res_all = $db->query("SELECT * FROM usuarios ORDER BY id DESC");
}
$db->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Admin Console | VinoMadrid</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 'bg-dark': '#08080a', 'accent': '#c8a96e', 'card': '#111116', 'admin': '#ff4d4d' },
                    fontFamily: { 'bebas': ['Bebas Neue'], 'sans': ['DM Sans'] }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="responsive.css">
    <!-- ESTILOS PROPIOS: usuarios.php. No se comparten con otras vistas. -->
    <style>
    body.page-usuarios { background: #08080a; color: #e8e4dc; font-family: 'DM Sans', sans-serif; }
            body.page-usuarios .bebas { font-family: 'Bebas Neue'; letter-spacing: 2px; }
            body.page-usuarios .glass-panel { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 2rem; }
            body.page-usuarios .btn-premium { background: #c8a96e; color: #000; font-weight: 800; font-size: 10px; text-transform: uppercase; padding: 12px 24px; border-radius: 4px; transition: 0.3s; }
            body.page-usuarios .btn-premium:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(200,169,110,0.3); }
            body.page-usuarios .cred-val { font-family: 'Courier New', monospace; color: #fff; }
            body.page-usuarios .mysql-unconfigured { color: #7a7568; font-style: italic; }
            body.page-usuarios .toggle-btn { background: none; border: none; color: #c8a96e; cursor: pointer; font-size: 1.1rem; padding: 0; }
            body.page-usuarios input,
    body.page-usuarios select { background: #15151a; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 10px; color: white; width: 100%; font-size: 13px; }
            body.page-usuarios .role-badge { font-size: 9px; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; font-weight: 900; }
            body.page-usuarios .role-admin { background: #ff4d4d; color: white; }
            body.page-usuarios .role-user { background: #4d79ff; color: white; }

    body.page-usuarios .inline-usuarios-001 { -webkit-text-security: disc; }
    body.page-usuarios .inline-usuarios-002 { border:1px solid rgba(200,169,110,0.18); border-radius:16px; }
    body.page-usuarios .inline-usuarios-003 { letter-spacing:3px; }
    body.page-usuarios .inline-usuarios-004 { background:rgba(200,169,110,0.08); border:1px solid rgba(200,169,110,0.2); color:#c8a96e; font-size:9px; font-weight:900; text-transform:uppercase; padding:4px 14px; border-radius:20px; letter-spacing:2px; }
    body.page-usuarios .inline-usuarios-005 { text-align:center; padding:3rem 0; color:#7a7568; font-size:10px; text-transform:uppercase; letter-spacing:3px; opacity:0.6; }
    body.page-usuarios .inline-usuarios-006 { overflow-x:auto; border-radius:8px; border:1px solid rgba(255,255,255,0.04); }
    body.page-usuarios .inline-usuarios-007 { width:100%; border-collapse:collapse; font-size:12px; }
    body.page-usuarios .inline-usuarios-008 { background:rgba(255,255,255,0.03); border-bottom:1px solid rgba(255,255,255,0.06); }
    body.page-usuarios .inline-usuarios-009 { padding:14px 16px; text-align:left; font-size:8px; text-transform:uppercase; letter-spacing:2px; color:#7a7568; font-weight:700; }
    body.page-usuarios .inline-usuarios-010 { padding:14px 16px; text-align:right; font-size:8px; text-transform:uppercase; letter-spacing:2px; color:#7a7568; font-weight:700; }
    body.page-usuarios .inline-usuarios-011 { padding:14px 16px; text-align:center; font-size:8px; text-transform:uppercase; letter-spacing:2px; color:#7a7568; font-weight:700; }
    body.page-usuarios .inline-usuarios-012 { border-bottom:1px solid rgba(255,255,255,0.04); transition:background 0.2s; }
    body.page-usuarios .inline-usuarios-013 { padding:14px 16px; color:#c8a96e; font-family:'Bebas Neue'; font-size:1.1rem; letter-spacing:1px; font-weight:700; }
    body.page-usuarios .inline-usuarios-014 { padding:14px 16px; color:#7a7568; font-family:'Courier New'; font-size:11px; }
    body.page-usuarios .inline-usuarios-015 { padding:14px 16px; color:#e8e4dc; font-size:11px; max-width:260px; }
    body.page-usuarios .inline-usuarios-016 { padding:14px 16px; text-align:right; color:#e8e4dc; font-family:'Courier New'; font-size:11px; }
    body.page-usuarios .inline-usuarios-017 { padding:14px 16px; text-align:right; color:#7a7568; font-family:'Courier New'; font-size:11px; }
    body.page-usuarios .inline-usuarios-018 { padding:14px 16px; text-align:right; color:#c8a96e; font-family:'Courier New'; font-size:13px; font-weight:700; }
    body.page-usuarios .inline-usuarios-019 { padding:14px 16px; text-align:center; }
    body.page-usuarios .inline-usuarios-020 { background:rgba(200,169,110,0.05); border-top:1px solid rgba(200,169,110,0.2); }
    body.page-usuarios .inline-usuarios-021 { padding:14px 16px; text-align:right; color:#c8a96e; font-family:'Courier New'; font-size:15px; font-weight:900; }
    body.page-usuarios .inline-usuarios-022 { background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); }
    body.page-usuarios .inline-usuarios-023 { display:inline; }
    body.page-usuarios .storage-current { color:#c8a96e; font-size:11px; font-weight:700; white-space:nowrap; }
    body.page-usuarios .participation-button { border:1px solid transparent; border-radius:4px; padding:6px 10px; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:1px; white-space:nowrap; transition:background .2s; }
    body.page-usuarios .participation-on { background:rgba(62,168,94,.16); border-color:rgba(62,168,94,.35); color:#5ed882; }
    body.page-usuarios .participation-off { background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.1); color:#aaa; }
    body.page-usuarios .impersonate-button { background:rgba(77,121,255,.16); border:1px solid rgba(77,121,255,.35); color:#91aaff; padding:6px 10px; border-radius:4px; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:1px; white-space:nowrap; }
    body.page-usuarios .impersonate-button:hover { background:#4d79ff; color:#fff; }

    body.page-usuarios .invoice-status {
      font-size: 8px;
      padding: 3px 10px;
      border-radius: 4px;
      text-transform: uppercase;
      font-weight: 900;
      letter-spacing: 1px;
      white-space: nowrap;
    }
    body.page-usuarios .invoice-download {
      background: #c8a96e;
      color: #000;
      font-size: 8px;
      font-weight: 900;
      text-transform: uppercase;
      padding: 6px 14px;
      border-radius: 4px;
      text-decoration: none;
      letter-spacing: 1px;
      display: inline-block;
      transition: all 0.2s;
    }
    </style>
</head>
<body class="page-usuarios p-10">

    <div class="users-shell max-w-7xl mx-auto">
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'borrado_pendiente'): ?>
            <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 text-red-500 rounded-xl text-xs font-bold uppercase tracking-widest animate-pulse">
                ⚠ Orden de borrado enviada. El sistema purgará los archivos en la próxima ejecución del cron.
            </div>
        <?php endif; ?>

        <?php if ($user_id): ?>
            <!-- CABECERA -->
            <div class="users-detail-header flex justify-between items-center mb-12">
                <div>
                    <h1 class="bebas text-6xl text-white">Consola <span class="text-accent">Maestra</span></h1>
                    <div class="flex items-center gap-3 mt-1">
                        <span class="text-[10px] uppercase tracking-[5px] text-muted opacity-50 italic">Auditoría de Usuario #<?php echo $u['id']; ?></span>
                        <span class="role-badge <?php echo ($u['rol'] === 'admin') ? 'role-admin' : 'role-user'; ?>"><?php echo $u['rol']; ?></span>
                    </div>
                </div>
                <div class="users-header-actions flex gap-4">
                    <button onclick="toggleEdit()" class="btn-premium">Editar Todo</button>
                    <a href="usuarios.php" class="text-[10px] uppercase tracking-widest text-white/40 border border-white/10 px-6 py-2.5 rounded hover:text-white transition-all">Regresar</a>
                </div>
            </div>

            <!-- MODO EDICIÓN TOTAL -->
            <div id="editPanel" class="hidden mb-12 glass-panel border-accent/20 bg-accent/[0.01]">
                <h2 class="bebas text-xl mb-8 text-accent">Panel de Edición y Cambio de Roles</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                    
                    <div class="space-y-4">
                        <h3 class="text-[10px] uppercase font-bold text-muted border-b border-white/5 pb-2">Identidad y Rol</h3>
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($u['nombre']); ?>" placeholder="Nombre">
                        <input type="email" name="email" value="<?php echo htmlspecialchars($u['email']); ?>" placeholder="Email">
                        <select name="rol">
                            <option value="usuario" <?php if($u['rol'] == 'usuario') echo 'selected'; ?>>Rol: Usuario</option>
                            <option value="admin" <?php if($u['rol'] == 'admin') echo 'selected'; ?>>Rol: Administrador</option>
                        </select>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-[10px] uppercase font-bold text-muted border-b border-white/5 pb-2">Contraseña Login</h3>
                        <input type="password" name="nueva_pass" placeholder="Nueva Clave de Login">
                        <select name="plan">
                            <option value="Básico" <?php if($u['plan_contratado'] == 'Básico') echo 'selected'; ?>>Plan Básico</option>
                            <option value="Pro" <?php if($u['plan_contratado'] == 'Pro') echo 'selected'; ?>>Plan Pro</option>
                            <option value="Enterprise" <?php if($u['plan_contratado'] == 'Enterprise') echo 'selected'; ?>>Plan Enterprise</option>
                        </select>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-[10px] uppercase font-bold text-muted border-b border-white/5 pb-2">Accesos Técnicos</h3>
                        <input type="text" name="ftp_user" value="<?php echo htmlspecialchars($u['ftp_user']); ?>" placeholder="User FTP">
                        <input type="text" name="ftp_pass" value="<?php echo htmlspecialchars($u['ftp_pass']); ?>" placeholder="Pass FTP">
                        <input type="text" name="db_pass" value="<?php echo htmlspecialchars($mysql['db_pass'] ?? ''); ?>" placeholder="Pass MySQL">
                    </div>

                    <div class="flex items-end"><button type="submit" name="actualizar_usuario" class="btn-premium w-full">Guardar Cambios</button></div>
                </form>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- COLUMNA PRINCIPAL (8 COL) -->
                <div class="lg:col-span-8 space-y-8">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- ACCESO LOGIN (AUDITORÍA) -->
                        <div class="glass-panel">
                            <h3 class="bebas text-2xl text-accent mb-6">Acceso a la Web (Login)</h3>
                            <div class="space-y-4 text-sm">
                                <div class="flex justify-between border-b border-white/5 pb-2"><span class="text-muted uppercase text-[9px]">Usuario:</span><span class="cred-val"><?php echo $u['email']; ?></span></div>
                                <div class="flex justify-between border-b border-white/5 pb-2">
                                    <span class="text-muted uppercase text-[9px]">Contraseña Real:</span>
                                    <div class="flex items-center gap-3">
                                        <span id="log-p" class="cred-val inline-usuarios-001"><?php echo $u['password_plain'] ?? 'No capturada'; ?></span>
                                        <button onclick="togglePass('log-p')" class="toggle-btn">👁️</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ACCESO FTP -->
                        <div class="glass-panel">
                            <h3 class="bebas text-2xl text-white mb-6">Ficheros (FTP)</h3>
                            <div class="space-y-4 text-sm">
                                <div class="flex justify-between border-b border-white/5 pb-2"><span class="text-muted uppercase text-[9px]">User:</span><span class="cred-val"><?php echo $u['ftp_user']; ?></span></div>
                                <div class="flex justify-between border-b border-white/5 pb-2">
                                    <span class="text-muted uppercase text-[9px]">Pass:</span>
                                    <div class="flex items-center gap-3">
                                        <span id="ftp-p" class="cred-val inline-usuarios-001"><?php echo $u['ftp_pass']; ?></span>
                                        <button onclick="togglePass('ftp-p')" class="toggle-btn">👁️</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="glass-panel">
                        <h3 class="bebas text-2xl text-white mb-6">Módulos Extra</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <?php foreach($extras as $item): $p = explode('|', $item); ?>
                                <div class="bg-white/5 p-3 rounded border border-white/5 text-center">
                                    <p class="text-[8px] uppercase text-accent font-bold"><?php echo str_replace('_', ' ', $p[0]); ?></p>
                                    <p class="text-xs font-bold"><?php echo $p[1] ?? '0'; ?>€</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- COLUMNA LATERAL (4 COL) -->
                <div class="lg:col-span-4 space-y-8">
                    <div class="glass-panel">
                        <h3 class="bebas text-xl text-white mb-4 uppercase">Estado y Rol</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between text-xs"><span class="text-muted uppercase">Rol de Cuenta:</span><span class="role-badge <?php echo ($u['rol'] === 'admin') ? 'role-admin' : 'role-user'; ?>"><?php echo $u['rol']; ?></span></div>
                            <div class="flex justify-between text-xs"><span class="text-muted uppercase">Plan Actual:</span><span class="font-bold text-accent"><?php echo $u['plan_contratado']; ?></span></div>
                        </div>
                    </div>

                    <div class="glass-panel">
                        <h3 class="bebas text-xl text-white mb-4 uppercase">Servidor MySQL</h3>
                        <div class="space-y-4 text-xs">
                            <div class="flex justify-between">
                                <span class="text-muted">Nombre DB:</span>
                                <span class="cred-val <?php echo $mysql_db_name_admin === '' ? 'mysql-unconfigured' : ''; ?>">
                                    <?php echo $mysql_db_name_admin !== '' ? htmlspecialchars($mysql_db_name_admin) : 'No configurado'; ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted">Usuario:</span>
                                <span class="cred-val <?php echo $mysql_db_user_admin === '' ? 'mysql-unconfigured' : ''; ?>">
                                    <?php echo $mysql_db_user_admin !== '' ? htmlspecialchars($mysql_db_user_admin) : 'No configurado'; ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted">Password:</span>
                                <?php if ($mysql_db_pass_admin !== ''): ?>
                                    <div class="flex items-center gap-3">
                                        <span id="db-p" class="cred-val inline-usuarios-001"><?php echo htmlspecialchars($mysql_db_pass_admin); ?></span>
                                        <button onclick="togglePass('db-p')" class="toggle-btn">👁️</button>
                                    </div>
                                <?php else: ?>
                                    <span class="cred-val mysql-unconfigured">No configurado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div><!-- /lateral -->
            </div><!-- /grid -->

            <!-- ═══════════════════════════════════════════════════════════════════ -->
            <!-- HISTORIAL DE FACTURACIÓN E INGRESOS (Auditoría Financiera Admin)  -->
            <!-- ═══════════════════════════════════════════════════════════════════ -->
            <div class="glass-panel mt-10 inline-usuarios-002">
                <div class="users-finance-header flex justify-between items-center mb-6">
                    <div>
                        <h3 class="bebas text-3xl text-accent inline-usuarios-003">Historial de Facturación e Ingresos</h3>
                        <p class="text-[9px] uppercase tracking-widest text-muted mt-1">Auditoría financiera en tiempo real · IVA 21% desglosado</p>
                    </div>
                    <span class="inline-usuarios-004">
                        <?php echo count($facturas_list); ?> factura(s)
                    </span>
                </div>

                <?php if (empty($facturas_list)): ?>
                    <div class="inline-usuarios-005">
                        Sin facturas registradas para este usuario.
                    </div>
                <?php else: ?>
                <div class="inline-usuarios-006 responsive-table-scroll users-invoices-scroll">
                <table class="inline-usuarios-007">
                    <thead>
                        <tr class="inline-usuarios-008">
                            <th class="inline-usuarios-009">Nº Factura</th>
                            <th class="inline-usuarios-009">Fecha Emisión</th>
                            <th class="inline-usuarios-009">Concepto / Plan</th>
                            <th class="inline-usuarios-010">Base Imponible</th>
                            <th class="inline-usuarios-010">IVA (21%)</th>
                            <th class="inline-usuarios-010">Total Pagado</th>
                            <th class="inline-usuarios-011">Estado</th>
                            <th class="inline-usuarios-011">Auditar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($facturas_list as $f): ?>
                        <tr
                            onmouseover="this.style.background='rgba(200,169,110,0.04)'"
                            onmouseout="this.style.background='transparent'" class="inline-usuarios-012">
                            <td data-label="Factura" class="inline-usuarios-013">
                                #<?php echo str_pad($f['id'], 5, '0', STR_PAD_LEFT); ?>
                            </td>
                            <td data-label="Fecha" class="inline-usuarios-014">
                                <?php echo date('d/m/Y', strtotime($f['fecha_emision'])); ?>
                            </td>
                            <td data-label="Concepto" class="inline-usuarios-015">
                                <?php echo htmlspecialchars($f['concepto']); ?>
                            </td>
                            <td data-label="Base imponible" class="inline-usuarios-016">
                                <?php echo number_format($f['base'], 2, ',', '.'); ?> €
                            </td>
                            <td data-label="IVA (21%)" class="inline-usuarios-017">
                                <?php echo number_format($f['iva'], 2, ',', '.'); ?> €
                            </td>
                            <td data-label="Total pagado" class="inline-usuarios-018">
                                <?php echo number_format($f['importe'], 2, ',', '.'); ?> €
                            </td>
                            <td data-label="Estado" class="inline-usuarios-019">
                                <?php
                                $sc = match($f['estado']) {
                                    'Pagado'    => 'background:rgba(39,174,96,0.15);color:#27ae60;border:1px solid rgba(39,174,96,0.3);',
                                    'Pendiente' => 'background:rgba(231,76,60,0.15);color:#e74c3c;border:1px solid rgba(231,76,60,0.3);',
                                    default     => 'background:rgba(255,255,255,0.05);color:#7a7568;border:1px solid rgba(255,255,255,0.1);',
                                };
                                ?>
                                <span class="invoice-status" style="<?php echo $sc; ?>">
                                    <?php echo htmlspecialchars($f['estado']); ?>
                                </span>
                            </td>
                            <td data-label="Auditar" class="inline-usuarios-019">
                                <a href="descargar_factura.php?id=<?php echo $f['id']; ?>"
                                   target="_blank"
                                   class="invoice-download"
                                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(200,169,110,0.4)';"
                                   onmouseout="this.style.transform=''; this.style.boxShadow='';">
                                    ↓ PDF
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="inline-usuarios-020 mobile-table-summary">
                            <td data-label="" colspan="5" class="inline-usuarios-010">
                                Total Facturado al Cliente:
                            </td>
                            <td data-label="Total" class="inline-usuarios-021">
                                <?php echo number_format(array_sum(array_column($facturas_list, 'importe')), 2, ',', '.'); ?> €
                            </td>
                            <td data-label="" colspan="2" class="mobile-table-blank"></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- LISTA GLOBAL -->
            <div class="users-list-header flex justify-between items-end mb-12">
                <div><h1 class="bebas text-6xl text-white">Central de <span class="text-accent">Control</span></h1><p class="text-[10px] uppercase tracking-[5px] text-muted opacity-50 mt-1">Gestión de Roles y Auditoría de Contraseñas</p></div>
                <div class="users-list-controls flex flex-col md:flex-row gap-4 items-center">
                    <div class="users-search w-full md:w-80 relative">
                        <input type="text" id="userSearch" onkeyup="filterUsers()" placeholder="Buscar ID, Cliente..." class="bg-card border border-white/10 rounded-xl px-12 py-4 text-xs w-full">
                        <span class="absolute left-4 top-4 text-accent/30 text-xl">🔍</span>
                    </div>
                    <a href="admin_panel.php" class="bg-white/5 border border-white/10 text-white text-[10px] uppercase tracking-widest px-6 py-4 rounded-xl hover:bg-white/10 transition-all font-bold">Volver al Dashboard</a>
                </div>
            </div>
            <div class="glass-panel users-table-panel">
              <div class="responsive-table-scroll users-list-scroll">
                <table class="w-full text-left">
                    <thead class="bg-white/5 text-[9px] uppercase tracking-widest text-muted">
                        <tr>
                            <th class="p-6">ID</th>
                            <th class="p-6">Cliente / Rol</th>
                            <th class="p-6">Plan</th>
                            <th class="p-6 text-center">Extras</th>
                            <th class="p-6">Espacio almacenado</th>
                            <th class="p-6 text-center">Showcase</th>
                            <th class="p-6">Contraseña Real</th>
                            <th class="p-6 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php while($u = $res_all->fetch_assoc()): 
                            $ex_count = count(json_decode($u['extras_json'], true) ?: []);
                            $espacio_usado = formatear_espacio_usuario(calcular_espacio_usuario((string)($u['ftp_user'] ?? '')));
                            $participando = (int)($u['showcase_permission'] ?? 0) === 1;
                        ?>
                        <tr class="user-row border-b border-white/5 hover:bg-white/[0.02]">
                            <td data-label="ID" class="p-6 text-accent font-bebas text-2xl">#<?php echo $u['id']; ?></td>
                            <td data-label="Cliente / Rol" class="p-6 font-bold text-white">
                                <?php echo htmlspecialchars($u['nombre']); ?><br>
                                <span class="role-badge <?php echo ($u['rol'] === 'admin') ? 'role-admin' : 'role-user'; ?>"><?php echo $u['rol']; ?></span>
                            </td>
                            <td data-label="Plan" class="p-6">
                                <span class="role-badge inline-usuarios-022">
                                    <?php echo $u['plan_contratado']; ?>
                                </span>
                            </td>
                            <td data-label="Extras" class="p-6">
                                <div class="flex flex-wrap gap-2 justify-center">
                                    <?php 
                                    $extras_list = json_decode($u['extras_json'], true) ?: [];
                                    if(empty($extras_list)): ?>
                                        <span class="text-[9px] text-muted italic">Ninguno</span>
                                    <?php else: foreach($extras_list as $ex): 
                                        $ex_name = explode('|', $ex)[0];
                                        $ex_name = str_replace('_', ' ', $ex_name);
                                    ?>
                                        <span class="bg-accent/10 border border-accent/20 text-accent text-[8px] px-2 py-0.5 rounded uppercase font-bold">
                                            <?php echo $ex_name; ?>
                                        </span>
                                    <?php endforeach; endif; ?>
                                </div>
                            </td>
                            <td data-label="Espacio almacenado" class="p-6 storage-current"><?php echo htmlspecialchars($espacio_usado); ?></td>
                            <td data-label="Showcase" class="p-6 text-center">
                                <button type="button"
                                    onclick="toggleParticipacion(<?php echo (int)$u['id']; ?>, this)"
                                    class="participation-button <?php echo $participando ? 'participation-on' : 'participation-off'; ?>">
                                    <?php echo $participando ? 'Participando' : 'No participa'; ?>
                                </button>
                            </td>
                            <td data-label="Contrasena" class="p-6 text-xs font-mono text-muted"><?php echo htmlspecialchars($u['password_plain'] ?? 'Pendiente login'); ?></td>
                            <td data-label="Accion" class="p-6 text-center">
                                <div class="flex flex-wrap items-center justify-center gap-3">
                                    <a href="usuarios.php?id=<?php echo $u['id']; ?>" class="btn-premium">Gestionar</a>
                                    <?php if ((int)($u['modulo_ia'] ?? 0) === 1): ?>
                                        <a href="presupuestos.php?user_id=<?php echo (int)$u['id']; ?>" class="btn-premium">&#128172; Ver Proyecto Web</a>
                                    <?php endif; ?>
                                    <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                        <form method="POST" action="controller_admin.php?action=impersonar_usuario" class="inline-usuarios-023">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <button type="submit" class="impersonate-button">Loguearse como</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="controller_admin.php?action=purgar_usuario" class="inline-usuarios-023">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit"
                                           onclick="return confirm('¿Estás SEGURO? Esta acción es irreversible: se borrará la base de datos, los archivos web y la cuenta del sistema.')"
                                           class="bg-red-500/20 hover:bg-red-500 border border-red-500/30 text-red-500 hover:text-white px-3 py-1.5 rounded text-[9px] font-bold uppercase tracking-widest transition-all">
                                           Borrar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
              </div>
            </div>
        <?php endif; ?>

    </div>

    <script>
        function togglePass(id) {
            const el = document.getElementById(id);
            el.style.webkitTextSecurity = (el.style.webkitTextSecurity === 'disc') ? 'none' : 'disc';
        }
        function toggleEdit() { document.getElementById('editPanel').classList.toggle('hidden'); }
        function filterUsers() {
            const input = document.getElementById('userSearch').value.toLowerCase();
            const rows = document.querySelectorAll('.user-row');
            rows.forEach(row => {
                const id = row.cells[0].innerText.toLowerCase();
                const nombre = row.cells[1].innerText.toLowerCase();
                if (id.includes(input) || nombre.includes(input)) { row.style.display = ""; } else { row.style.display = "none"; }
            });
        }
        // Cambia la participacion en Showcase sin abandonar el listado.
        async function toggleParticipacion(userId, button) {
            const formData = new FormData();
            formData.append('user_id', userId);
            button.disabled = true;

            try {
                const response = await fetch('controller_admin.php?action=toggle_showcase', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const result = await response.json();
                if (!response.ok || !result.ok) {
                    throw new Error('Cambio rechazado');
                }

                button.textContent = result.participando ? 'Participando' : 'No participa';
                button.classList.toggle('participation-on', result.participando);
                button.classList.toggle('participation-off', !result.participando);
            } catch (error) {
                alert('No se pudo cambiar la participacion. Vuelve a intentarlo.');
            } finally {
                button.disabled = false;
            }
        }
    </script>
</body>
</html>