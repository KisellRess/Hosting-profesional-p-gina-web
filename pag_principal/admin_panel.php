<?php
/* ============================================================
   ARCHIVO: admin_panel.php
   FUNCION: mostrar alertas y acciones del administrador.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'conexiones.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: panel.php');
    exit;
}

$db = getConexion();

// 0. DETERMINAR VISTA
$view = $_GET['view'] ?? 'pending';
$status_filter = ($view === 'history') ? 1 : 0;

// 1. MÉTRICAS
$count_users = $db->query("SELECT COUNT(*) as total FROM usuarios")->fetch_assoc()['total'];
$count_doms = $db->query("SELECT COUNT(*) as total FROM dominios WHERE estado_dominio = 'Activo'")->fetch_assoc()['total'];
$count_staff = $db->query("SELECT COUNT(*) as total FROM ftp_cuentas_extra")->fetch_assoc()['total'];

// 2. ALERTAS REALES (Tabla: alertas_admin)
$alertas = [];
$res_alerts = $db->query("SELECT id, user_id, nombre_usuario, motivo, simbolo, reconocida, fecha 
                          FROM alertas_admin 
                          WHERE reconocida = $status_filter 
                          ORDER BY fecha DESC");

if ($res_alerts) {
    while($row = $res_alerts->fetch_assoc()) {
        $alertas[] = $row;
    }
}
$db->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | VinoMadrid</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 'bg-dark': '#0b0b0f', 'accent': '#c8a96e', 'card': '#121217', 'surface2': '#1a1a20' },
                    fontFamily: { 'bebas': ['Bebas Neue'], 'sans': ['DM Sans'] }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="responsive.css">
    <!-- ESTILOS PROPIOS: admin_panel.php. No se comparten con otras vistas. -->
    <style>
    body.page-admin-panel {
                --accent: #c8a96e;
                --bg-dark: #0b0b0f;
                --surface2: #1a1a20;
            }
            body.page-admin-panel { background-color: #0b0b0f !important; color: #e8e4dc; }
            body.page-admin-panel .bebas { font-family: 'Bebas Neue'; letter-spacing: 2px; }
            body.page-admin-panel .table-container { border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); }
            body.page-admin-panel .alerta-parpadeo { 
                animation: parpadeo-borde 1.5s infinite !important; 
                border-left: 4px solid #c0392b !important;
            }
            @keyframes parpadeo-borde {
                0%, 100% { border-left-color: #c0392b; }
                50% { border-left-color: rgba(192, 57, 43, 0.2); }
            }
            body.page-admin-panel .table-header { text-transform: uppercase; font-size: 10px; letter-spacing: 2px; color: #7a7568; border-bottom: 1px solid rgba(255,255,255,0.05); }
            body.page-admin-panel .alerts-legend {
                padding: 0;
                background: #121217;
                border: 1px solid rgba(255,255,255,0.05);
                border-radius: 12px;
                overflow: hidden;
            }
            body.page-admin-panel .alerts-legend-summary {
                padding: 0.85rem 1.15rem;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            body.page-admin-panel .alerts-legend-title {
                color: #c8a96e;
                font-size: 1rem;
                text-transform: uppercase;
                letter-spacing: 0.15em;
            }
            body.page-admin-panel .alerts-legend-grid {
                border-top: 1px solid rgba(255,255,255,0.05);
                background: rgba(0,0,0,0.2);
                padding: 0.85rem 1.15rem;
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 0.75rem 1.25rem;
            }
            body.page-admin-panel .alerts-legend-item { display:flex; align-items:center; gap:0.65rem; }
            body.page-admin-panel .alerts-legend-symbol { width:1.1rem; text-align:center; font-size:1rem; line-height:1; }
    </style>
</head>
<body class="page-admin-panel font-sans antialiased p-10">

    <div class="admin-shell max-w-7xl mx-auto space-y-6">
        
        <!-- NAVEGACIÓN -->
        <a href="panel.php" class="inline-flex items-center text-accent hover:text-white transition-colors mb-2 text-[10px] uppercase tracking-[3px] font-bold">
            <i class="fas fa-arrow-left mr-2"></i> Volver al Panel
        </a>
        
        <!-- HEADER -->
        <header class="admin-header flex flex-col md:flex-row justify-between items-end gap-8">
            <div>
                <h1 class="bebas text-5xl text-accent mb-2">Central de Mando</h1>
                <p class="text-[10px] uppercase tracking-[4px] text-muted">Monitor de Servicios Administrativos</p>
            </div>
            <div class="admin-header-actions flex flex-col items-end gap-4">
                <nav class="admin-action-nav flex gap-4">
                    <a href="usuarios.php" class="text-[10px] uppercase tracking-widest text-white border border-accent/20 px-6 py-2 rounded hover:bg-accent hover:text-black transition-all font-bold">Gestión de Usuarios</a>
                </nav>
                <nav class="admin-action-nav flex gap-4">
                    <a href="monitorizacion.php" class="text-[10px] uppercase tracking-widest text-white border border-accent/20 px-6 py-2 rounded hover:bg-accent hover:text-black transition-all font-bold">Monitorización</a>
                </nav>
                <!-- PESTAÑAS DE VISTA -->
                <div class="admin-view-tabs flex bg-white/5 p-1 rounded-lg border border-white/10">
                    <a href="admin_panel.php?view=pending" class="px-6 py-1.5 text-[9px] uppercase tracking-widest font-bold rounded-md transition-all <?php echo ($view === 'pending') ? 'bg-accent text-black shadow-lg' : 'text-zinc-500 hover:text-white'; ?>">Pendientes</a>
                    <a href="admin_panel.php?view=history" class="px-6 py-1.5 text-[9px] uppercase tracking-widest font-bold rounded-md transition-all <?php echo ($view === 'history') ? 'bg-accent text-black shadow-lg' : 'text-zinc-500 hover:text-white'; ?>">Historial</a>
                </div>
            </div>
        </header>

        <!-- BARRA DE ACCIONES MASIVAS -->
        <div class="massive-actions-bar bg-surface2/50 border border-white/5 p-4 rounded-xl shadow-lg flex items-center gap-4">
            <div class="flex items-center gap-3 border-r border-white/10 pr-6">
                <input type="checkbox" id="check-all" class="check-alerta" onclick="toggleAll(this)">
                <span class="text-[9px] uppercase tracking-widest text-muted font-bold">Monitorización Global</span>
            </div>
            
            <?php if ($view === 'pending'): ?>
                <button onclick="procesarMasivo('reconocer')" class="btn-masivo-gold">
                    <i class="fas fa-check-double mr-2"></i> Reconocer Seleccionadas
                </button>
            <?php else: ?>
                <button onclick="procesarMasivo('restaurar')" class="btn-masivo-gold">
                    <i class="fas fa-undo mr-2"></i> Restaurar Seleccionadas
                </button>
                <button onclick="procesarMasivo('eliminar')" class="btn-masivo-outline">
                    <i class="fas fa-trash-alt mr-2"></i> Vaciar Historial
                </button>
            <?php endif; ?>

            <div id="status-masivo" class="ml-auto text-[9px] uppercase tracking-widest text-accent font-bold opacity-0 transition-opacity">
                Sincronizando...
            </div>
        </div>

        <!-- LEYENDA DESPLEGABLE -->
        <section class="alerts-legend">
            <details class="group">
                <summary class="alerts-legend-summary hover:bg-white/[0.02] transition-all">
                    <span class="bebas alerts-legend-title">Leyenda de Alertas</span>
                    <span class="text-accent group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="alerts-legend-grid">
                    <div class="alerts-legend-item"><span class="alerts-legend-symbol text-red-500 font-bold">!</span><span class="text-muted uppercase text-[9px] tracking-widest">Alerta Urgente</span></div>
                    <div class="alerts-legend-item"><span class="alerts-legend-symbol text-orange-400 font-bold">⚠️</span><span class="text-muted uppercase text-[9px] tracking-widest">Alerta Moderada</span></div>
                    <div class="alerts-legend-item"><span class="alerts-legend-symbol text-muted font-bold">❔</span><span class="text-muted uppercase text-[9px] tracking-widest">Alerta Simple</span></div>
                </div>
            </details>
        </section>

        <!-- TABLA DE ALERTAS -->
        <div class="table-container shadow-2xl bg-card">
          <div class="responsive-table-scroll admin-alerts-scroll">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="table-header bg-white/[0.03]">
                        <th class="p-5 w-12"></th>
                        <th class="p-5">ID</th>
                        <th class="p-5">USUARIO</th>
                        <th class="p-5">FECHA</th>
                        <th class="p-5">MOTIVO</th>
                        <th class="p-5 text-center">SÍMBOLO</th>
                        <th class="p-5">ACCIÓN</th>
                        <th class="p-5 text-center">GESTIÓN</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php if(empty($alertas)): ?>
                        <tr><td data-label="" colspan="8" class="mobile-table-empty p-20 text-center text-muted uppercase tracking-widest text-[10px]">
                            <?php echo ($view === 'history') ? 'El historial está vacío' : 'No hay alertas pendientes'; ?>
                        </td></tr>
                    <?php else: foreach($alertas as $a): 
                        // Lógica de Deep Linking
                        $target_section = "";
                        $motivo_lower = strtolower($a['motivo']);
                        if(strpos($motivo_lower, 'ftp') !== false) $target_section = "#ftp-section";
                        elseif(strpos($motivo_lower, 'dominio') !== false) $target_section = "#dominios-section";
                        elseif(strpos($motivo_lower, 'chat') !== false) $target_section = "#chat-section";
                        
                        $clase_parpadeo = (!$a['reconocida'] && $view === 'pending') ? 'alerta-parpadeo' : '';
                        $clase_btn = !$a['reconocida'] ? 'btn-alerta-pendiente' : 'btn-alerta-hecho';
                        $icono_btn = !$a['reconocida'] ? 'fas fa-check' : 'fas fa-undo';
                        $tooltip = !$a['reconocida'] ? 'Marcar como hecho' : 'Restaurar a pendientes';
                    ?>
                    <tr class="border-b border-white/5 hover:bg-white/5 transition-colors <?php echo $clase_parpadeo; ?>" data-id="<?php echo $a['id']; ?>">
                        <td data-label="Seleccionar" class="p-5 py-4 align-middle">
                            <input type="checkbox" class="check-alerta alerta-item" value="<?php echo $a['id']; ?>">
                        </td>
                        <td data-label="ID" class="p-5 py-4 align-middle text-accent font-bold">#<?php echo $a['id']; ?></td>
                        <td data-label="Usuario" class="p-5 py-4 align-middle font-bold text-white uppercase text-xs"><?php echo htmlspecialchars($a['nombre_usuario']); ?></td>
                        <td data-label="Fecha" class="p-5 py-4 align-middle text-[10px] text-muted"><?php echo date('d/m/Y H:i', strtotime($a['fecha'])); ?></td>
                        <td data-label="Motivo" class="p-5 py-4 align-middle text-[10px] font-bold uppercase tracking-widest text-muted/80 max-w-xs truncate"><?php echo htmlspecialchars($a['motivo']); ?></td>
                        <td data-label="Simbolo" class="p-5 py-4 align-middle text-center text-xl w-12">
                            <?php 
                                if($a['simbolo'] == '!') echo '<span class="text-red-500">!</span>';
                                elseif($a['simbolo'] == '⚠️') echo '<span class="text-orange-400">⚠️</span>';
                                elseif($a['simbolo'] == '⭐') echo '<span class="text-yellow-400">⭐</span>';
                                else echo '<span class="text-muted">❓</span>';
                            ?>
                        </td>
                        <td data-label="Accion" class="p-5 py-4 align-middle">
                            <a href="panel.php?user_id=<?php echo $a['user_id'] . $target_section; ?>" class="text-[9px] font-bold uppercase tracking-widest text-accent hover:underline">Ir al Problema →</a>
                        </td>
                        <td data-label="Gestion" class="p-5 py-4 align-middle text-center min-w-[60px]">
                            <button onclick="marcarAlerta(<?php echo $a['id']; ?>, this)" class="<?php echo $clase_btn; ?>">
                                <i class="<?php echo $icono_btn; ?>"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
          </div>
        </div>

    </div>
                        
    <script>
        function toggleAll(master) {
            document.querySelectorAll('.alerta-item').forEach(el => el.checked = master.checked);
        }

        async function procesarMasivo(accion) {
            const seleccionados = Array.from(document.querySelectorAll('.alerta-item:checked')).map(el => el.value);
            
            if (accion === 'reconocer' && seleccionados.length === 0) {
                alert('Selecciona al menos una alerta para reconocer.');
                return;
            }

            const status = document.getElementById('status-masivo');
            status.style.opacity = '1';

            try {
                const formData = new FormData();
                formData.append('accion', accion);
                formData.append('ids', JSON.stringify(seleccionados));

                const response = await fetch('controller_admin.php?action=masivas', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.text();

                if (res.trim() === 'ok') {
                    location.reload();
                } else {
                    alert('Error al procesar: ' + res);
                }
            } catch (e) {
                console.error("Error masivo:", e);
            } finally {
                status.style.opacity = '0';
            }
        }

        async function marcarAlerta(id, btn) {
            btn.classList.remove('btn-alerta-pendiente');
            btn.classList.add('btn-alerta-hecho');
            btn.innerHTML = '<i class="fas fa-check-double"></i>';
            
            try {
                const response = await fetch('controller_admin.php?action=cambiar_estado&id=' + id);
                const resText = await response.text();
                
                if (resText.trim() === 'ok') {
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) {
                        row.classList.remove('alerta-parpadeo');
                        setTimeout(() => {
                            row.style.transition = 'all 0.4s ease';
                            row.style.opacity = '0.5';
                            // No borramos la fila para que el admin vea que se ha reconocido
                        }, 300);
                    }
                }
            } catch (e) {
                console.error("Error al marcar alerta:", e);
            }
        }
    </script>
</body>
</html>