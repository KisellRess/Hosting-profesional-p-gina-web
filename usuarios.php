<?php
session_start();
require_once 'conexiones.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: panel.php');
    exit;
}

$db = getConexion();
$msg = "";

// --- LÓGICA DE ACTUALIZACIÓN (POST) ---
if (isset($_POST['actualizar_usuario'])) {
    $uid = (int)$_POST['user_id'];
    $nombre = $db->real_escape_string($_POST['nombre']);
    $email = $db->real_escape_string($_POST['email']);
    $plan = $db->real_escape_string($_POST['plan']);
    $rol = $db->real_escape_string($_POST['rol']);
    $ftp_user = $db->real_escape_string($_POST['ftp_user']);
    $ftp_pass = $db->real_escape_string($_POST['ftp_pass']);

    // Actualización básica y de Rol
    $db->query("UPDATE usuarios SET nombre='$nombre', email='$email', plan_contratado='$plan', rol='$rol', ftp_user='$ftp_user', ftp_pass='$ftp_pass' WHERE id=$uid");

    // Actualización de contraseña de LOGIN (Hash + Plain)
    if (!empty($_POST['nueva_pass'])) {
        $plain = $_POST['nueva_pass'];
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        $plain_safe = $db->real_escape_string($plain);
        $db->query("UPDATE usuarios SET password_hash='$hash', password_plain='$plain_safe' WHERE id=$uid");
    }
    
    // Actualizar MySQL si existe
    if (isset($_POST['db_pass'])) {
        $db_pass = $db->real_escape_string($_POST['db_pass']);
        $db->query("UPDATE modulo_mysql SET db_pass='$db_pass' WHERE user_id=$uid");
    }

    header("Location: usuarios.php?id=$uid&msg=ok");
    exit;
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($user_id) {
    $u = $db->query("SELECT * FROM usuarios WHERE id=$user_id")->fetch_assoc();
    $dom = $db->query("SELECT * FROM dominios WHERE user_id=$user_id")->fetch_assoc();
    $mysql = $db->query("SELECT * FROM modulo_mysql WHERE user_id=$user_id")->fetch_assoc();
    $res_staff = $db->query("SELECT * FROM ftp_cuentas_extra WHERE user_id=$user_id");
    $extras = json_decode($u['extras_json'], true) ?: [];
    
    $base_gb = ['BÁSICO' => 1, 'PROFESIONAL' => 3, 'ENTERPRISE' => 5];
    $total_gb = $base_gb[strtoupper($u['plan_contratado'])] ?? 1;
} else {
    $res_all = $db->query("SELECT * FROM usuarios ORDER BY id DESC");
}
$db->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
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
    <style>
        body { background: #08080a; color: #e8e4dc; font-family: 'DM Sans', sans-serif; }
        .bebas { font-family: 'Bebas Neue'; letter-spacing: 2px; }
        .glass-panel { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 2rem; }
        .btn-premium { background: #c8a96e; color: #000; font-weight: 800; font-size: 10px; text-transform: uppercase; padding: 12px 24px; border-radius: 4px; transition: 0.3s; }
        .btn-premium:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(200,169,110,0.3); }
        .cred-val { font-family: 'Courier New', monospace; color: #fff; }
        .toggle-btn { background: none; border: none; color: #c8a96e; cursor: pointer; font-size: 1.1rem; padding: 0; }
        input, select { background: #15151a; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 10px; color: white; width: 100%; font-size: 13px; }
        .role-badge { font-size: 9px; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; font-weight: 900; }
        .role-admin { background: #ff4d4d; color: white; }
        .role-user { background: #4d79ff; color: white; }
    </style>
</head>
<body class="p-10">

    <div class="max-w-7xl mx-auto">
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'borrado_pendiente'): ?>
            <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 text-red-500 rounded-xl text-xs font-bold uppercase tracking-widest animate-pulse">
                ⚠ Orden de borrado enviada. El sistema purgará los archivos en la próxima ejecución del cron.
            </div>
        <?php endif; ?>

        <?php if ($user_id): ?>
            <!-- CABECERA -->
            <div class="flex justify-between items-center mb-12">
                <div>
                    <h1 class="bebas text-6xl text-white">Consola <span class="text-accent">Maestra</span></h1>
                    <div class="flex items-center gap-3 mt-1">
                        <span class="text-[10px] uppercase tracking-[5px] text-muted opacity-50 italic">Auditoría de Usuario #<?php echo $u['id']; ?></span>
                        <span class="role-badge <?php echo ($u['rol'] === 'admin') ? 'role-admin' : 'role-user'; ?>"><?php echo $u['rol']; ?></span>
                    </div>
                </div>
                <div class="flex gap-4">
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
                                        <span id="log-p" class="cred-val" style="-webkit-text-security: disc;"><?php echo $u['password_plain'] ?? 'No capturada'; ?></span>
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
                                        <span id="ftp-p" class="cred-val" style="-webkit-text-security: disc;"><?php echo $u['ftp_pass']; ?></span>
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
                            <div class="flex justify-between"><span class="text-muted">Nombre DB:</span><span class="cred-val"><?php echo $mysql['db_name'] ?? 'Inactiva'; ?></span></div>
                            <div class="flex justify-between">
                                <span class="text-muted">Password:</span>
                                <div class="flex items-center gap-3">
                                    <span id="db-p" class="cred-val" style="-webkit-text-security: disc;"><?php echo $mysql['db_pass'] ?? '••••'; ?></span>
                                    <button onclick="togglePass('db-p')" class="toggle-btn">👁️</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- LISTA GLOBAL -->
            <div class="flex justify-between items-end mb-12">
                <div><h1 class="bebas text-6xl text-white">Central de <span class="text-accent">Control</span></h1><p class="text-[10px] uppercase tracking-[5px] text-muted opacity-50 mt-1">Gestión de Roles y Auditoría de Contraseñas</p></div>
                <div class="flex flex-col md:flex-row gap-4 items-center">
                    <div class="w-80 relative">
                        <input type="text" id="userSearch" onkeyup="filterUsers()" placeholder="Buscar ID, Cliente..." class="bg-card border border-white/10 rounded-xl px-12 py-4 text-xs w-full">
                        <span class="absolute left-4 top-4 text-accent/30 text-xl">🔍</span>
                    </div>
                    <a href="admin_panel.php" class="bg-white/5 border border-white/10 text-white text-[10px] uppercase tracking-widest px-6 py-4 rounded-xl hover:bg-white/10 transition-all font-bold">Volver al Dashboard</a>
                </div>
            </div>
            <div class="glass-panel overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-white/5 text-[9px] uppercase tracking-widest text-muted">
                        <tr>
                            <th class="p-6">ID</th>
                            <th class="p-6">Cliente / Rol</th>
                            <th class="p-6">Plan</th>
                            <th class="p-6 text-center">Extras</th>
                            <th class="p-6">Contraseña Real</th>
                            <th class="p-6 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php while($u = $res_all->fetch_assoc()): 
                            $ex_count = count(json_decode($u['extras_json'], true) ?: []);
                        ?>
                        <tr class="user-row border-b border-white/5 hover:bg-white/[0.02]">
                            <td class="p-6 text-accent font-bebas text-2xl">#<?php echo $u['id']; ?></td>
                            <td class="p-6 font-bold text-white">
                                <?php echo htmlspecialchars($u['nombre']); ?><br>
                                <span class="role-badge <?php echo ($u['rol'] === 'admin') ? 'role-admin' : 'role-user'; ?>"><?php echo $u['rol']; ?></span>
                            </td>
                            <td class="p-6">
                                <span class="role-badge" style="background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1);">
                                    <?php echo $u['plan_contratado']; ?>
                                </span>
                            </td>
                            <td class="p-6">
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
                            <td class="p-6 text-xs font-mono text-muted"><?php echo htmlspecialchars($u['password_plain'] ?? 'Pendiente login'); ?></td>
                            <td class="p-6 text-center">
                                <div class="flex items-center justify-center gap-3">
                                    <a href="usuarios.php?id=<?php echo $u['id']; ?>" class="btn-premium">Gestionar</a>
                                    <a href="procesar_borrado.php?id=<?php echo $u['id']; ?>" 
                                       onclick="return confirm('¿Estás SEGURO? Esta acción es irreversible: se borrará la base de datos, los archivos web y la cuenta del sistema.')" 
                                       class="bg-red-500/20 hover:bg-red-500 border border-red-500/30 text-red-500 hover:text-white px-3 py-1.5 rounded text-[9px] font-bold uppercase tracking-widest transition-all">
                                       Borrar
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
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
    </script>
</body>
</html>