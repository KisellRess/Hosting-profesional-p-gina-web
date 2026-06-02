<?php
/* ============================================================
   ARCHIVO: presupuestos.php
   FUNCION: mostrar y atender consultas de presupuesto.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

session_start();
require_once 'conexiones.php';

// 1. Manejo Resiliente de Sesión
$usuario_nombre = $_SESSION['usuario'] ?? 'Invitado';
$user_id = $_SESSION['user_id'] ?? null;
$rol = $_SESSION['rol'] ?? 'usuario';
$es_admin_proyecto = false;
$target_user_id = null;
$target_user_nombre = '';

// Validación de Pago con Bypass para Admin
$pago_confirmado = false;
$error_acceso = "";

if (!$user_id) {
    $error_acceso = "Debe iniciar sesión para acceder a este módulo.";
} else if ($rol === 'admin') {
    $pago_confirmado = true; // Bypass Admin
    $target_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    if ($target_user_id && $target_user_id > 0) {
        $db_admin = getConexion();
        $stmt_admin = $db_admin->prepare('SELECT nombre FROM usuarios WHERE id = ? LIMIT 1');
        $stmt_admin->bind_param('i', $target_user_id);
        $stmt_admin->execute();
        $target_user = $stmt_admin->get_result()->fetch_assoc();
        $stmt_admin->close();
        $db_admin->close();

        if ($target_user) {
            $es_admin_proyecto = true;
            $target_user_nombre = $target_user['nombre'] ?? ('Usuario #' . $target_user_id);
        } else {
            $error_acceso = "No se ha encontrado el usuario seleccionado.";
            $pago_confirmado = false;
        }
    }
} else {
    // Verificación Real para Usuarios
    try {
        $db = getConexion();
        $res = $db->query("SELECT extras_json FROM usuarios WHERE id = $user_id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $modulos = json_decode($row['extras_json'] ?? '[]', true);
            if (is_array($modulos) && in_array('web_ai|100.00', $modulos)) {
                $pago_confirmado = true;
            } else {
                $error_acceso = "No se ha detectado el pago del Módulo de Página Web.";
            }
        }
        $db->close();
    } catch (Exception $e) {
        $error_acceso = "Error técnico al validar el servicio. Por favor, contacte con soporte.";
    }
}

$total_mensajes = 0;
$chat_user_id = $es_admin_proyecto ? (int)$target_user_id : (int)$user_id;
if ($pago_confirmado && $chat_user_id > 0) {
    try {
        $db_historial = getConexion();
        $stmt_historial = $db_historial->prepare('SELECT COUNT(*) AS total FROM mensajes_chat WHERE user_id = ?');
        $stmt_historial->bind_param('i', $chat_user_id);
        $stmt_historial->execute();
        $total_mensajes = (int)($stmt_historial->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt_historial->close();
        $db_historial->close();
    } catch (Throwable $e) {
        error_log('Error contando historial de presupuesto: ' . $e->getMessage());
        $total_mensajes = 0;
    }
}

// Si hay error y no es admin, mostramos pantalla de error elegante
if (!$pago_confirmado && ($rol !== 'admin' || $error_acceso !== '')) {
    $error_seguro = htmlspecialchars($error_acceso, ENT_QUOTES, 'UTF-8');
    die("<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Acceso Restringido | VinoMadrid</title>
        <link rel='stylesheet' href='estilos.css'>
        <link rel='stylesheet' href='responsive.css'>
        <!-- ESTILOS PROPIOS: presupuestos.php. No se comparten con otras vistas. -->
    <style>
    body.page-presupuestos { background-color: #0b0b0f !important; color: #e8e4dc; }
            body.page-presupuestos .chat-area::-webkit-scrollbar { width: 3px; }
            body.page-presupuestos .chat-area::-webkit-scrollbar-thumb { background: #333; }
            body.page-presupuestos .bubble { max-width: 85%; padding: 1.1rem 1.4rem; border-radius: 20px; font-size: 0.9rem; line-height: 1.5; opacity: 0; transform: translateY(10px); animation: slideIn 0.5s forwards; }
            @keyframes slideIn { to { opacity: 1; transform: translateY(0); } }
            body.page-presupuestos .bubble-left { background: #16161c; border-bottom-left-radius: 4px; }
            body.page-presupuestos .bubble-right { background: #c8a96e; color: #000; font-weight: 500; border-bottom-right-radius: 4px; }
            body.page-presupuestos .bubble-sys { background: rgba(200, 169, 110, 0.08); color: #c8a96e; border: 1px solid rgba(200,169,110,0.15); border-radius: 12px; font-size: 0.75rem; text-align: center; width: 100%; max-width: 100%; }
            body.page-presupuestos .choice-btn { border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 0.6rem 1.2rem; font-size: 0.8rem; cursor: pointer; transition: 0.3s; text-align: left; width: 100%; }
            body.page-presupuestos .choice-btn:hover { border-color: #c8a96e; background: rgba(200,169,110,0.05); }
    </style>
</head>
    <body class='page-presupuestos-error'>
        <div class='access-denied'>
            <h1>Acceso Restringido</h1>
            <p>$error_seguro</p>
            <a href='panel.php' class='access-back'>Volver al Panel</a>
        </div>
    </body>
    </html>");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurador de Proyecto | VinoMadrid</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 'bg-main': '#0b0b0f', 'accent': '#c8a96e', 'bubble-left': '#16161c' },
                    fontFamily: { 'bebas': ['Bebas Neue'], 'sans': ['DM Sans'] }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="responsive.css">
    <style>
        body.page-presupuestos .admin-project-panel {
            width: min(48rem, calc(100% - 2rem));
            margin: 1.5rem auto 0;
            padding: 1.25rem;
            background: rgba(19, 19, 26, 0.92);
            border: 1px solid rgba(200, 169, 110, 0.28);
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.28);
        }
        body.page-presupuestos .admin-project-title {
            color: #c8a96e;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.35rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        body.page-presupuestos .admin-project-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        body.page-presupuestos .admin-project-input {
            min-width: 0;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            background: rgba(255,255,255,0.04);
            color: #e8e4dc;
            padding: 0.85rem 1rem;
            outline: none;
        }
        body.page-presupuestos .admin-project-input:focus {
            border-color: rgba(200,169,110,0.55);
        }
        body.page-presupuestos .btn-submit {
            display: inline-block;
            background-color: #c8a96e;
            border: 1px solid rgba(200,169,110,0.65);
            border-radius: 4px;
            padding: 0.85rem 1rem;
            color: #0b0b0f;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        body.page-presupuestos .btn-submit:hover {
            background-color: #e8c989;
            opacity: 0.92;
        }
        body.page-presupuestos .btn-submit:active {
            transform: scale(0.98);
        }
        body.page-presupuestos .chat-area {
            scrollbar-width: thin;
            scrollbar-color: rgba(200,169,110,0.22) transparent;
        }
        body.page-presupuestos .chat-area::-webkit-scrollbar {
            width: 4px;
        }
        body.page-presupuestos .chat-area::-webkit-scrollbar-track {
            background: transparent;
        }
        body.page-presupuestos .chat-area::-webkit-scrollbar-thumb {
            background: rgba(200,169,110,0.22);
            border-radius: 999px;
        }
        body.page-presupuestos .choice-btn {
            display: inline-block;
            background-color: rgba(255,255,255,0.03);
            border: 1px solid rgba(200,169,110,0.25);
            border-radius: 4px;
            padding: 0.75rem 1rem;
            color: #e8e4dc;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        body.page-presupuestos .choice-btn:hover {
            background-color: rgba(200,169,110,0.1);
            opacity: 0.95;
        }
        body.page-presupuestos .choice-btn:active {
            transform: scale(0.98);
        }
        body.page-presupuestos .choice-btn-custom {
            border-style: dashed;
            color: #c8a96e;
        }
        body.page-presupuestos .chat-input-shell.is-disabled {
            opacity: 0.55;
        }
        body.page-presupuestos .chat-input-shell.is-disabled input,
        body.page-presupuestos .chat-input-shell.is-disabled button {
            cursor: not-allowed;
        }
        @media (max-width: 640px) {
            body.page-presupuestos .admin-project-form {
                grid-template-columns: 1fr;
            }
        }
        @media (min-width: 1024px) {
            body.page-presupuestos .chat-area,
            body.page-presupuestos .admin-project-panel,
            body.page-presupuestos .chat-footer {
                max-width: 1200px !important;
                width: 90% !important;
            }
        }
    </style>
</head>
<body class="page-presupuestos font-sans antialiased flex flex-col h-screen overflow-hidden">

    <!-- HEADER -->
    <header class="p-8 border-b border-white/5 flex justify-between items-center bg-black/20">
        <div class="flex items-center gap-6">
            <a href="panel.php" class="text-[10px] uppercase tracking-[3px] text-muted hover:text-accent">← Panel</a>
            <h1 class="font-bebas text-2xl tracking-[4px] text-accent">Chat de Presupuesto</h1>
        </div>
        <div class="flex items-center gap-4">
            <?php if (!empty($_SESSION['admin_original'])): ?>
                <form method="POST" action="controller_admin.php?action=volver_admin" class="nav-inline-form" style="margin: 0;">
                    <button type="submit" class="border border-[#c8a96e] bg-transparent text-[#c8a96e] hover:bg-[#c8a96e] hover:text-[#0b0b0f] px-3 py-1 rounded text-[10px] uppercase tracking-widest transition-all">Volver Admin</button>
                </form>
            <?php endif; ?>
            <div class="text-[10px] uppercase tracking-widest text-muted">Usuario: <?php echo htmlspecialchars($es_admin_proyecto ? $target_user_nombre : $usuario_nombre); ?></div>
        </div>
    </header>

    <?php if ($es_admin_proyecto): ?>
    <section class="admin-project-panel">
        <div class="admin-project-title">Panel de Gestión de Proyecto para: <?php echo htmlspecialchars($target_user_nombre, ENT_QUOTES, 'UTF-8'); ?></div>
        <form method="POST" action="controller_admin.php?action=generar_factura_web" class="admin-project-form">
            <input type="hidden" name="user_id" value="<?php echo (int)$target_user_id; ?>">
            <input type="hidden" name="concepto" value="Desarrollo de Sitio Web Personalizado">
            <input type="number" name="precio_final" min="0" step="0.01" placeholder="Introduce el PVP acordado (EUR)" class="admin-project-input" required>
            <button type="submit" class="btn-submit">Aprobar y enviar a checkout</button>
        </form>
        <?php if (($_GET['ok'] ?? '') === 'presupuesto_web_listo'): ?>
            <p class="mt-4 text-[11px] uppercase tracking-widest text-accent">Presupuesto aprobado. El cliente debe pagarlo desde checkout para activar factura y garantía.</p>
        <?php elseif (($_GET['ok'] ?? '') === 'factura_web_generada'): ?>
            <p class="mt-4 text-[11px] uppercase tracking-widest text-accent">Factura emitida correctamente. La garantía de 30 días queda activa.</p>
        <?php elseif (!empty($_GET['error'])): ?>
            <p class="mt-4 text-[11px] uppercase tracking-widest text-red-400">No se pudo completar la operación: <?php echo htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- CHAT FLOW -->
    <main class="flex-1 flex flex-col items-center overflow-hidden">
        <div id="chat-window" class="chat-area flex-1 w-full max-w-2xl overflow-y-auto p-10 space-y-8"></div>

        <!-- INPUT FOOTER -->
        <div class="chat-footer w-full max-w-2xl p-8 border-t border-white/5">
            <div id="options-container" class="mb-6 space-y-2 hidden"></div>
            <div id="chat-input-shell" class="chat-input-shell is-disabled flex gap-4 items-center bg-white/[0.03] rounded-full px-6 py-4 border border-white/5 focus-within:border-accent/30 transition-all">
                <input id="chat-input" type="text" placeholder="Espera a que aparezca la primera pregunta..." class="flex-1 bg-transparent text-sm outline-none" disabled>
                <button id="send-btn" class="text-accent hover:scale-110 transition-all" disabled>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </button>
            </div>
        </div>
    </main>

    <script>
        const chatWindow = document.getElementById('chat-window');
        const chatInput = document.getElementById('chat-input');
        const sendBtn = document.getElementById('send-btn');
        const optionsContainer = document.getElementById('options-container');
        const chatInputShell = document.getElementById('chat-input-shell');
        const isAdminProject = <?php echo $es_admin_proyecto ? 'true' : 'false'; ?>;
        const targetUserId = <?php echo $es_admin_proyecto ? (int)$target_user_id : 'null'; ?>;
        const tieneHistorial = <?php echo $total_mensajes > 0 ? 'true' : 'false'; ?>;
        let lastMessageId = 0;
        let sseStarted = false;
        let questionnaireSent = false;
        let manualModeBound = false;

        // Persistencia JSON (Punto 4)
        let chatData = {
            usuario: "<?php echo addslashes($usuario_nombre); ?>",
            respuestas: { showcase: "", colores: "", fuentes: "" },
            mensajes_libres: []
        };

        const sequence = [
            { type: 'sys', text: "gracias por solicitar el modulo de creacion de pagina web. El precio base es desde 100 EUR y se fijara con el administrador. Cuando el presupuesto este aprobado, deberas pagarlo desde checkout para emitir la factura y activar los 30 dias de garantia." },
            { type: 'left', text: "en breves un administrador se pondrá en contacto con usted, si desea puede contestar a las siguientes preguntas para poder comenzar con los preparativos de la creación de la página web" },
            { 
                type: 'left', 
                text: "¿Le ha llamado la atención alguna página web que tenemos en nuestro 'showcase'?, ¿en caso afirmativo, podrías indicarnos cual o cuales?",
                key: 'showcase',
                buttons: [
                    { text: "Sí, pero no sé indicarlo ahora" },
                    { text: "No me ha gustado ninguna, quiero una página original" },
                    { text: "Escribir proyectos concretos del Showcase", custom: true, placeholder: "Escribe aquí las páginas del Showcase que te han gustado..." }
                ]
            },
            {
                type: 'left',
                text: "Perfecto. ¿Cuál es la paleta de colores que visualizas para tu web?",
                key: 'colores',
                buttons: [
                    { text: "Tonos vino, dorado y fondo oscuro" },
                    { text: "Estilo claro, limpio y elegante" },
                    { text: "Minimalista en blanco, negro y acento dorado" },
                    { text: "Escribir otra paleta de colores", custom: true, placeholder: "Describe aquí los colores que quieres para tu web..." }
                ]
            },
            {
                type: 'left',
                text: "¿Tienes alguna preferencia por fuentes de texto especiales o te serviría cualquiera de nuestras fuentes premium?",
                key: 'fuentes',
                buttons: [
                    { text: "Cualquiera de vuestras fuentes premium me sirve" },
                    { text: "Prefiero una fuente elegante y seria" },
                    { text: "Prefiero una fuente moderna y llamativa" },
                    { text: "Escribir preferencia de tipografía", custom: true, placeholder: "Indica aquí la fuente o estilo de texto que prefieres..." }
                ]
            },
            { type: 'left', text: "Muchas gracias. He guardado tus preferencias. Puedes seguir escribiéndome aquí cualquier detalle adicional.", finish: true }
        ];

        let stepIndex = 0;
        let questionnaireDone = false;

        function setInputEnabled(enabled, placeholder = 'Escribe un mensaje...') {
            chatInput.disabled = !enabled;
            sendBtn.disabled = !enabled;
            chatInput.placeholder = placeholder;
            chatInputShell.classList.toggle('is-disabled', !enabled);
        }

        function claveMensajePendiente(emisor, mensaje) {
            return `${emisor}|${String(mensaje || '').trim()}`;
        }

        function vincularMensajePendiente(id, emisor, mensaje) {
            if (!id) return false;
            const pendingKey = claveMensajePendiente(emisor, mensaje);
            const pendientes = chatWindow.querySelectorAll('[data-pending-key]');
            for (const item of pendientes) {
                if (item.dataset.pendingKey === pendingKey) {
                    item.id = `msg-${id}`;
                    delete item.dataset.pendingKey;
                    item.classList.remove('message-pending');
                    return true;
                }
            }
            return false;
        }

        function addMessage(text, side = 'left', isSys = false, serverId = 0, pendingKey = '') {
            const id = Number(serverId || 0);
            if (id && document.getElementById(`msg-${id}`)) {
                return document.getElementById(`msg-${id}`);
            }

            const wrapper = document.createElement('div');
            if (id) {
                wrapper.id = `msg-${id}`;
            } else if (pendingKey) {
                wrapper.id = `msg-pending-${Date.now()}-${Math.random().toString(36).slice(2)}`;
                wrapper.dataset.pendingKey = pendingKey;
            }
            wrapper.className = `flex ${isSys ? 'justify-center' : (side === 'left' ? 'justify-start' : 'justify-end')}`;
            if (pendingKey) {
                wrapper.classList.add('message-pending');
            }
            const bubble = document.createElement('div');
            bubble.className = isSys ? 'bubble bubble-sys' : `bubble bubble-${side}`;
            bubble.innerText = text;
            wrapper.appendChild(bubble);
            chatWindow.appendChild(wrapper);
            chatWindow.scrollTop = chatWindow.scrollHeight;

            if (questionnaireDone && side === 'right') {
                chatData.mensajes_libres.push(text);
                console.log("Historial actualizado:", chatData);
            }

            return wrapper;
        }

        function showButtons(buttons) {
            optionsContainer.innerHTML = '';
            optionsContainer.classList.remove('hidden');
            buttons.forEach((option) => {
                const data = typeof option === 'string' ? { text: option } : option;
                const btn = document.createElement('button');
                btn.className = `choice-btn${data.custom ? ' choice-btn-custom' : ''}`;
                btn.type = 'button';
                btn.innerText = data.text;
                btn.onclick = () => {
                    if (data.custom) {
                        setInputEnabled(true, data.placeholder || 'Escribe tu respuesta personalizada...');
                        chatInput.value = '';
                        chatInput.focus();
                    } else {
                        handleResponse(data.text);
                    }
                };
                optionsContainer.appendChild(btn);
            });
        }

        // Función para enviar datos al servidor (Punto 3 del requerimiento)
        async function sendToBackend(payload, optimisticElement = null) {
            try {
                const response = await fetch('send_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ...payload,
                        ...(targetUserId ? { target_user_id: targetUserId } : {}),
                        timestamp: new Date().toISOString()
                    })
                });
                const result = await response.json();
                if (result.last_id) {
                    lastMessageId = Math.max(lastMessageId, Number(result.last_id));
                }
                if (optimisticElement && result.message_id) {
                    const messageId = Number(result.message_id);
                    const existing = document.getElementById(`msg-${messageId}`);
                    if (existing && existing !== optimisticElement) {
                        optimisticElement.remove();
                    } else {
                        optimisticElement.id = `msg-${messageId}`;
                        delete optimisticElement.dataset.pendingKey;
                        optimisticElement.classList.remove('message-pending');
                    }
                }
                console.log("Sincronización con servidor:", result);
            } catch (error) {
                console.error("Error al sincronizar con el servidor:", error);
            }
        }

        function addStoredMessage(message) {
            const id = Number(message.id || 0);
            if (id && document.getElementById(`msg-${id}`)) return;
            if (id && vincularMensajePendiente(id, message.emisor, message.mensaje)) {
                if (id > lastMessageId) lastMessageId = id;
                return;
            }
            if (id > lastMessageId) lastMessageId = id;

            const side = isAdminProject
                ? (message.emisor === 'admin' ? 'right' : 'left')
                : (message.emisor === 'usuario' ? 'right' : 'left');
            const wrapper = document.createElement('div');
            if (id) wrapper.id = `msg-${id}`;
            wrapper.className = `flex ${side === 'left' ? 'justify-start' : 'justify-end'}`;

            const bubble = document.createElement('div');
            bubble.className = `bubble bubble-${side}`;
            bubble.innerText = message.mensaje || '';

            wrapper.appendChild(bubble);
            chatWindow.appendChild(wrapper);
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }

        function iniciarChatSSE() {
            if (sseStarted) return;
            sseStarted = true;
            optionsContainer.classList.add('hidden');

            const params = new URLSearchParams({
                last_id: String(lastMessageId),
                t: String(Date.now())
            });
            if (targetUserId) {
                params.set('user_id', String(targetUserId));
            }

            const sseUrl = `get_chat_sse.php?${params.toString()}`;
            const source = new EventSource(sseUrl);
            source.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    
                    // LÓGICA DE ALINEACIÓN DUAL DE BURBUJAS
                    let side = 'left';
                    if (isAdminProject) {
                        side = (data.emisor === 'admin') ? 'right' : 'left';
                    } else {
                        side = (data.emisor === 'usuario') ? 'right' : 'left';
                    }

                    // Para evitar duplicados en el pooling de SSE, usamos el identificador único de mensaje
                    const id = Number(data.id || 0);
                    if (id && document.getElementById(`msg-${id}`)) return;
                    if (id && vincularMensajePendiente(id, data.emisor, data.mensaje)) {
                        if (id > lastMessageId) lastMessageId = id;
                        return;
                    }
                    if (id > lastMessageId) lastMessageId = id;

                    // Pintar la burbuja en el contenedor de forma limpia respetando el orden cronológico
                    const wrapper = document.createElement('div');
                    if (id) wrapper.id = `msg-${id}`;
                    wrapper.className = `flex ${(data.emisor === 'sys') ? 'justify-center' : (side === 'left' ? 'justify-start' : 'justify-end')}`;

                    const bubble = document.createElement('div');
                    bubble.className = (data.emisor === 'sys') ? 'bubble bubble-sys' : `bubble bubble-${side}`;
                    bubble.innerText = data.mensaje || '';

                    wrapper.appendChild(bubble);
                    chatWindow.appendChild(wrapper);
                    chatWindow.scrollTop = chatWindow.scrollHeight;
                    
                } catch (err) {
                    console.error("Error parseando el mensaje del historial SSE:", err);
                }
            };
            source.onerror = () => {
                console.warn('La conexion del chat se esta reintentando.');
            };
        }

        function enviarMensajeManual() {
            const val = chatInput.value.trim();
            if (!val) return;

            chatInput.value = '';
            const emisor = isAdminProject ? 'admin' : 'usuario';
            const pendingKey = claveMensajePendiente(emisor, val);
            const optimisticElement = addMessage(val, 'right', false, 0, pendingKey);
            sendToBackend({
                emisor: emisor,
                mensaje: val
            }, optimisticElement);
        }

        function activarModoManual() {
            questionnaireDone = true;
            setInputEnabled(true, 'Escribe cualquier detalle adicional para el administrador...');
            if (!manualModeBound) {
                manualModeBound = true;
                sendBtn.onclick = enviarMensajeManual;
                chatInput.onkeypress = null;
                chatInput.onkeydown = (e) => { if(e.key === 'Enter') enviarMensajeManual(); };
            }
        }

        function finalizarCuestionario() {
            if (questionnaireSent) return;
            questionnaireSent = true;
            activarModoManual();
            sendToBackend({
                emisor: isAdminProject ? "admin" : "usuario",
                mensaje: "Cuestionario Inicial Completado",
                cuestionario: chatData.respuestas
            });
            setTimeout(iniciarChatSSE, 300);
            console.log("Cuestionario Finalizado y enviado al backend.");
        }

        function handleResponse(val) {
            if (!val) val = chatInput.value.trim();
            if (!val) return;

            addMessage(val, 'right');
            chatInput.value = '';
            optionsContainer.classList.add('hidden');

            const current = sequence[stepIndex];
            if (current && current.key) {
                chatData.respuestas[current.key] = val;
            }

            // Si el cuestionario ha terminado, enviar mensajes individuales
            if (questionnaireDone) {
                sendToBackend({ emisor: isAdminProject ? "admin" : "usuario", mensaje: val });
            }

            stepIndex++;
            if (stepIndex < sequence.length) {
                setTimeout(processStep, 800);
            }
        }

        function processStep() {
            const current = sequence[stepIndex];
            if (!current) return;

            if (current.type === 'sys') {
                setInputEnabled(false, 'Espera a que aparezca la primera pregunta...');
                addMessage(current.text, 'left', true);
                stepIndex++;
                setTimeout(processStep, 1500);
            } else {
                setInputEnabled(Boolean(current.key), current.key ? 'Elige una opción o escribe tu respuesta...' : 'Espera al siguiente paso...');
                addMessage(current.text, 'left');
                if (current.buttons) {
                    setTimeout(() => showButtons(current.buttons), 500);
                }
                if (!current.key && !current.finish) {
                    stepIndex++;
                    setTimeout(processStep, 1200);
                }
                if (current.finish) {
                    setTimeout(finalizarCuestionario, 500);
                }
            }
        }

        if (isAdminProject || tieneHistorial) {
            activarModoManual();
            setTimeout(iniciarChatSSE, 300);
        } else {
            setInputEnabled(false, 'Espera a que aparezca la primera pregunta...');
            sendBtn.onclick = () => handleResponse();
            chatInput.onkeypress = (e) => { if(e.key === 'Enter') handleResponse(); };
            setTimeout(processStep, 1000);
        }
    </script>
</body>
</html>