<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'conexiones.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: panel.php');
    exit;
}

$db = getConexion();

// 1. MÉTRICAS
$count_users = $db->query("SELECT COUNT(*) as total FROM usuarios")->fetch_assoc()['total'];
$count_doms = $db->query("SELECT COUNT(*) as total FROM dominios WHERE estado_dominio = 'Activo'")->fetch_assoc()['total'];
$count_staff = $db->query("SELECT COUNT(*) as total FROM ftp_cuentas_extra")->fetch_assoc()['total'];

// 2. ALERTAS REALES (Tabla: alertas_admin)
$alertas = [];
$res_alerts = $db->query("SELECT id, user_id, nombre_usuario, motivo, simbolo, reconocida, fecha 
                          FROM alertas_admin 
                          WHERE reconocida = 0 
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
                    colors: { 'bg-dark': '#0b0b0f', 'accent': '#c8a96e', 'card': '#121217' },
                    fontFamily: { 'bebas': ['Bebas Neue'], 'sans': ['DM Sans'] }
                }
            }
        }
    </script>
    <style>
        body { background-color: #0b0b0f !important; color: #e8e4dc; }
        .bebas { font-family: 'Bebas Neue'; letter-spacing: 2px; }
        details summary::-webkit-details-marker { display: none; }
        .row-blink { animation: soft-pulse 2s infinite; }
        @keyframes soft-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        .table-header { text-transform: uppercase; font-size: 10px; letter-spacing: 2px; color: #7a7568; border-bottom: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="font-sans antialiased p-10">

    <div class="max-w-7xl mx-auto space-y-12">
        
        <!-- HEADER -->
        <header class="flex flex-col md:flex-row justify-between items-end gap-8">
            <div>
                <h1 class="bebas text-5xl text-accent mb-2">Central de Mando</h1>
                <p class="text-[10px] uppercase tracking-[4px] text-muted">Monitor de Servicios Administrativos</p>
            </div>
            <nav class="flex gap-4">
                <a href="usuarios.php" class="text-[10px] uppercase tracking-widest text-white border border-accent/20 px-6 py-2 rounded hover:bg-accent hover:text-black transition-all font-bold">Gestión de Usuarios</a>
            </nav>
        </header>

        <div class="py-8">
            <a href="https://vinomadrid.es" class="text-[10px] uppercase tracking-widest text-white border border-accent/20 px-6 py-2 rounded hover:bg-accent hover:text-black transition-all font-bold">ir a la página web</a>
        </div>
        <!-- LEYENDA DESPLEGABLE --     >
        <section class="bg-card border border-white/5 rounded-xl overflow-hidden">
            <details class="group">
                <summary class="p-6 cursor-pointer flex justify-between items-center hover:bg-white/[0.02] transition-all">
                    <span class="bebas text-lg text-accent uppercase tracking-widest">Leyenda de Alertas</span>
                    <span class="text-accent group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="p-8 border-t border-white/5 bg-black/20 grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                    <div class="flex items-center gap-4"><span class="text-red-500 font-bold text-2xl">!</span><span class="text-muted uppercase text-[9px] tracking-widest">Alerta Urgente</span></div>
                    <div class="flex items-center gap-4"><span class="text-orange-400 font-bold text-2xl">⚠️</span><span class="text-muted uppercase text-[9px] tracking-widest">Alerta Moderada</span></div>
                    <div class="flex items-center gap-4"><span class="text-muted font-bold text-2xl">❔</span><span class="text-muted uppercase text-[9px] tracking-widest">Alerta Simple</span></div>
                </div>
            </details>
        </section>

        <!-- TABLA DE ALERTAS (ESTRUCTURA DE IMAGEN) -->
        <section class="bg-card border border-white/5 rounded-xl overflow-x-auto shadow-2xl">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="table-header bg-white/[0.02]">
                        <th class="p-5">ID</th>
                        <th class="p-5">NOMBRE_COMPLETO</th>
                        <th class="p-5">FECHA</th>
                        <th class="p-5">MOTIVO</th>
                        <th class="p-5 text-center">SÍMBOLO</th>
                        <th class="p-5">ACCIÓN</th>
                        <th class="p-5 text-center">¿Hecho?</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php if(empty($alertas)): ?>
                        <tr><td colspan="7" class="p-20 text-center text-muted uppercase tracking-widest text-[10px]">No hay alertas pendientes</td></tr>
                    <?php else: foreach($alertas as $a): ?>
                    <tr class="border-b border-white/5 hover:bg-white/5 transition-colors <?php echo !$a['reconocida'] ? 'row-blink' : ''; ?>" data-id="<?php echo $a['id']; ?>">
                        <td class="p-5 py-4 align-middle text-accent font-bold">#<?php echo $a['id']; ?></td>
                        <td class="p-5 py-4 align-middle font-bold text-white uppercase text-xs"><?php echo htmlspecialchars($a['nombre_usuario']); ?></td>
                        <td class="p-5 py-4 align-middle text-[10px] text-muted"><?php echo date('d/m/Y H:i', strtotime($a['fecha'])); ?></td>
                        <td class="p-5 py-4 align-middle text-[10px] font-bold uppercase tracking-widest text-muted/80 max-w-xs truncate"><?php echo htmlspecialchars($a['motivo']); ?></td>
                        <td class="p-5 py-4 align-middle text-center text-xl w-12">
                            <?php 
                                if($a['simbolo'] == '!') echo '<span class="text-red-500">!</span>';
                                elseif($a['simbolo'] == '⚠️') echo '<span class="text-orange-400">⚠️</span>';
                                elseif($a['simbolo'] == '⭐') echo '<span class="text-yellow-400">⭐</span>';
                                else echo '<span class="text-muted">❓</span>';
                            ?>
                        </td>
                        <td class="p-5 py-4 align-middle">
                            <a href="panel.php?user_id=<?php echo $a['user_id']; ?>" class="text-[9px] font-bold uppercase tracking-widest text-accent hover:underline">ir al panel</a>
                        </td>
                        <td class="p-5 py-4 align-middle text-center min-w-[60px]">
                            <button onclick="marcarAlerta(<?php echo $a['id']; ?>)" data-id="<?php echo $a['id']; ?>" class="bg-green-500/10 hover:bg-green-500/20 text-green-500 p-2 rounded-md text-[10px] font-bold transition-colors border border-green-500/20">
                                <i class="fas fa-check"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </section>

    </div>

    <script>
        async function marcarAlerta(id) {
            try {
                const response = await fetch('marcar_alerta.php?id=' + id);
                const resText = await response.text();
                console.log('Respuesta servidor:', resText);
                
                if (resText.trim() === 'ok') {
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) {
                        row.style.transition = 'all 0.5s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(20px)';
                        setTimeout(() => row.remove(), 500);
                    }
                }
            } catch (e) {
                console.error("Error al marcar alerta:", e);
            }
        }
    </script>
</body>
</html>
