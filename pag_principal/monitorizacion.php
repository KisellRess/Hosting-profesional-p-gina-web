<?php
/* ============================================================
   ARCHIVO: monitorizacion.php
   FUNCION: mostrar estado y registros del servidor.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

session_start();
require_once 'conexiones.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: panel.php');
    exit;
}

$monitor_cache = $_SESSION['monitor_cache'] ?? [];
function monitor_cached(string $clave, callable $cargar, int $ttl = 30) {
    global $monitor_cache;
    $entrada = $monitor_cache[$clave] ?? null;
    if (is_array($entrada) && (time() - (int)$entrada['time']) < $ttl) {
        return $entrada['value'];
    }

    $valor = $cargar();
    $monitor_cache[$clave] = ['time' => time(), 'value' => $valor];
    return $valor;
}

// --- DATOS HARDWARE ---
$load = sys_getloadavg();
$cpu_load = $load[0]; 
$cpu_percent = min(($cpu_load / 4) * 100, 100);

$free = shell_exec('free -b');
$free_lines = explode("\n", trim($free));
$mem_info = preg_split('/ +/', $free_lines[1]);
$total_ram_gb = round($mem_info[1] / (1024**3), 2);
$used_ram_gb = round($mem_info[2] / (1024**3), 2);
$ram_percent = round(($used_ram_gb / $total_ram_gb) * 100, 1);

$disk_total = disk_total_space("/");
$disk_free = disk_free_space("/");
$disk_percent = round((($disk_total - $disk_free) / $disk_total) * 100, 1);
$disk_total_gb = round($disk_total / (1024**3), 1);
$disk_used_gb = round(($disk_total - $disk_free) / (1024**3), 1);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitorización Avanzada | VinoMadrid</title>
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="responsive.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- ESTILOS PROPIOS: monitorizacion.php. No se comparten con otras vistas. -->
    <style>
    body.page-monitorizacion { background-color: var(--bg) !important; color: var(--text); }
            body.page-monitorizacion .monitor-card { background-color: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
            body.page-monitorizacion .progress-bg { background-color: var(--surface2); border-radius: 999px; height: 6px; overflow: hidden; }
            body.page-monitorizacion .log-box { background: #050505; border: 1px solid #1a1a1a; font-family: monospace; font-size: 11px; color: #aaa; }
            body.page-monitorizacion .btn-volver { border: 1px solid var(--accent); color: var(--accent); transition: all 0.3s; }
            body.page-monitorizacion .btn-volver:hover { background: var(--accent); color: var(--bg); }
            body.page-monitorizacion .custom-scrollbar::-webkit-scrollbar { width: 5px; }
            body.page-monitorizacion .custom-scrollbar::-webkit-scrollbar-track { background: var(--surface); }
            body.page-monitorizacion .custom-scrollbar::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 10px; }
            
            /* Efecto al abrir el desplegable */
            body.page-monitorizacion details[open] summary {
                background: rgba(200, 169, 110, 0.1);
                border-bottom: 1px solid var(--border);
            }

    body.page-monitorizacion .inline-monitorizacion-001 { color: var(--accent); }
    body.page-monitorizacion .inline-monitorizacion-002 { color: var(--muted); }
    body.page-monitorizacion .inline-monitorizacion-003 { color:var(--accent); }
    </style>
</head>
<body class="page-monitorizacion p-4 md:p-8">

    <div class="max-w-[1600px] mx-auto">
        
        <div class="flex flex-col md:flex-row justify-between items-center mb-10 gap-6 border-b border-gray-800 pb-8">
            <div>
                <h1 class="text-4xl font-bold uppercase tracking-tighter inline-monitorizacion-001">Torre de Control</h1>
                <p class="inline-monitorizacion-002">Seguridad, Automatización y Negocio</p>
            </div>
            <a href="admin_panel.php" class="btn-volver px-6 py-2 rounded uppercase text-sm font-bold">
                <i class="fas fa-chevron-left mr-2"></i> Volver al Panel
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="monitor-card p-6 border-t-2 border-blue-500/30">
                <p class="text-xs font-bold uppercase opacity-50 mb-2">CPU Load</p>
                <p class="text-4xl font-bold inline-monitorizacion-001"><?php echo $cpu_load; ?></p>
                <div class="progress-bg mt-4"><div class="h-full bg-blue-500" style="width:<?php echo $cpu_percent; ?>%"></div></div>
            </div>
            <div class="monitor-card p-6 border-t-2 border-purple-500/30">
                <p class="text-xs font-bold uppercase opacity-50 mb-2">Memoria RAM</p>
                <p class="text-4xl font-bold inline-monitorizacion-001"><?php echo $used_ram_gb; ?><span class="text-lg opacity-30">/<?php echo $total_ram_gb; ?>GB</span></p>
                <div class="progress-bg mt-4"><div class="h-full bg-purple-500" style="width:<?php echo $ram_percent; ?>%"></div></div>
            </div>
            <div class="monitor-card p-6 border-t-2 border-green-500/30">
                <p class="text-xs font-bold uppercase opacity-50 mb-2">Disco Sistema</p>
                <p class="text-4xl font-bold inline-monitorizacion-001"><?php echo $disk_used_gb; ?><span class="text-lg opacity-30">/<?php echo $disk_total_gb; ?>GB</span></p>
                <div class="progress-bg mt-4"><div class="h-full bg-green-500" style="width:<?php echo $disk_percent; ?>%"></div></div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="monitor-card p-6">
                <h3 class="text-sm font-bold uppercase mb-4 text-red-500"><i class="fas fa-shield-alt mr-2"></i> Radar de Seguridad (Auth)</h3>
                <div class="log-box p-4 rounded h-48 overflow-y-auto">
                    <?php
                    $security = monitor_cached('security', fn() => shell_exec("grep 'Failed' /var/log/auth.log | tail -n 5"));
                    echo $security ? nl2br(htmlspecialchars($security)) : "No se detectan intentos fallidos recientes.";
                    ?>
                </div>
            </div>
            <div class="monitor-card p-6">
                <h3 class="text-sm font-bold uppercase mb-4 inline-monitorizacion-001"><i class="fas fa-robot mr-2"></i> Consola de Logs (Master)</h3>
                <div class="space-y-2 h-80 overflow-y-auto pr-2 custom-scrollbar">
                    <?php
                    $log_path = "/opt/tfg/scripts/logs/";
                    // Buscamos todos los archivos que terminen en _master.log
                    $log_files = monitor_cached('master_files', fn() => glob($log_path . "*_master.log"));

                    if ($log_files) {
                        foreach ($log_files as $file) {
                            $fileName = basename($file);
                            // Limpiamos el nombre para el título (ej: mysql_worker_master.log -> MySQL Worker)
                            $displayTitle = str_replace(['_master.log', '_'], ['', ' '], $fileName);
                            ?>
                            <details class="group border border-gray-800 rounded bg-black/30">
                                <summary class="list-none p-3 cursor-pointer flex justify-between items-center hover:bg-white/5 transition-all">
                                    <span class="text-xs font-bold uppercase tracking-widest opacity-70">
                                        <i class="fas fa-file-alt mr-2 text-blue-400"></i> <?php echo $displayTitle; ?>
                                    </span>
                                    <i class="fas fa-chevron-down text-[10px] group-open:rotate-180 transition-transform"></i>
                                </summary>
                                <div class="p-4 border-t border-gray-800">
                                    <div class="log-box p-3 rounded leading-relaxed text-[10px]">
                                        <?php
                                        $content = monitor_cached('tail_' . basename($file), fn() => shell_exec("tail -n 10 " . escapeshellarg($file)));
                                        if ($content) {
                                            // Ponemos en dorado los ÉXITOS y en rojo los ERRORES
                                            $content = htmlspecialchars($content);
                                            $content = str_replace('✓ ÉXITO', '<span class="inline-monitorizacion-003">✓ ÉXITO</span>', $content);
                                            $content = str_replace('ERROR', '<span class="text-red-500">ERROR</span>', $content);
                                            echo nl2br($content);
                                        } else {
                                            echo "<span class='opacity-30 italic'>Archivo vacío o sin permisos.</span>";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </details>
                            <?php
                        }
                    } else {
                        echo "<p class='text-xs opacity-50'>No se encontraron archivos master.log</p>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="monitor-card p-6">
                <h3 class="text-sm font-bold uppercase mb-4 inline-monitorizacion-001"><i class="fas fa-folder-open mr-2"></i> Top Consumo Hosting</h3>
                <div class="space-y-3">
                    <?php
                    $du = monitor_cached('hosting_du', fn() => shell_exec("sudo du -sh /var/www/hosting_tfg/* | sort -rh | head -n 3"));
                    if($du) {
                        foreach(explode("\n", trim($du)) as $line) {
                            echo "<div class='flex justify-between border-b border-gray-800 pb-1 text-sm'><span>" . basename(explode("\t", $line)[1]) . "</span><span class='font-mono inline-monitorizacion-003'>" . explode("\t", $line)[0] . "</span></div>";
                        }
                    }
                    ?>
                </div>
            </div>
            <div class="monitor-card p-6">
                <h3 class="text-sm font-bold uppercase mb-4 inline-monitorizacion-001"><i class="fas fa-users mr-2"></i> Últimos Clientes</h3>
                <div class="space-y-3">
                    <?php
                    $db = getConexion();
                    // usuarios registra el alta en fecha_alta, no en fecha.
                    $res = $db->query("SELECT nombre, fecha_alta FROM usuarios ORDER BY fecha_alta DESC, id DESC LIMIT 5");
                    while($res && $u = $res->fetch_assoc()) {
                        $nombre_cliente = htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8');
                        $fecha_cliente = !empty($u['fecha_alta']) ? date('d/m/H:i', strtotime($u['fecha_alta'])) : 'Sin fecha';
                        echo "<div class='flex justify-between border-b border-gray-800 pb-1 text-sm'><span>{$nombre_cliente}</span><span class='opacity-40'>{$fecha_cliente}</span></div>";
                    }
                    $db->close();
                    ?>
                </div>
            </div>
        </div>

    </div>

    <?php $_SESSION['monitor_cache'] = $monitor_cache; ?>
    <script>setTimeout(() => { location.reload(); }, 20000);</script>
</body>
</html>