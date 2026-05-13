<?php
session_start();
require_once 'conexiones.php';

// 1. Manejo Resiliente de Sesión
$usuario_nombre = $_SESSION['usuario'] ?? 'Invitado';
$user_id = $_SESSION['user_id'] ?? null;
$rol = $_SESSION['rol'] ?? 'usuario';

// Validación de Pago con Bypass para Admin
$pago_confirmado = false;
$error_acceso = "";

if (!$user_id) {
    $error_acceso = "Debe iniciar sesión para acceder a este módulo.";
} else if ($rol === 'admin') {
    $pago_confirmado = true; // Bypass Admin
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

// Si hay error y no es admin, mostramos pantalla de error elegante
if (!$pago_confirmado && $rol !== 'admin') {
    die("
    <body style='background:#0b0b0f; color:#c8a96e; font-family:sans-serif; display:flex; align-items:center; justify-content:center; height:100vh; margin:0;'>
        <div style='text-align:center; border:1px solid #c8a96e; padding:3rem; border-radius:15px; background:#121217;'>
            <h1 style='font-size:1.5rem; margin-bottom:1rem;'>Acceso Restringido</h1>
            <p style='color:#7a7568; font-size:0.9rem;'>$error_acceso</p>
            <a href='panel.php' style='display:inline-block; margin-top:2rem; color:#fff; text-decoration:none; border:1px solid #c8a96e; padding:0.6rem 1.5rem; border-radius:5px;'>Volver al Panel</a>
        </div>
    </body>");
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
    <style>
        body { background-color: #0b0b0f !important; color: #e8e4dc; }
        .chat-area::-webkit-scrollbar { width: 3px; }
        .chat-area::-webkit-scrollbar-thumb { background: #333; }
        .bubble { max-width: 85%; padding: 1.1rem 1.4rem; border-radius: 20px; font-size: 0.9rem; line-height: 1.5; opacity: 0; transform: translateY(10px); animation: slideIn 0.5s forwards; }
        @keyframes slideIn { to { opacity: 1; transform: translateY(0); } }
        .bubble-left { background: #16161c; border-bottom-left-radius: 4px; }
        .bubble-right { background: #c8a96e; color: #000; font-weight: 500; border-bottom-right-radius: 4px; }
        .bubble-sys { background: rgba(200, 169, 110, 0.08); color: #c8a96e; border: 1px solid rgba(200,169,110,0.15); border-radius: 12px; font-size: 0.75rem; text-align: center; width: 100%; max-width: 100%; }
        .choice-btn { border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 0.6rem 1.2rem; font-size: 0.8rem; cursor: pointer; transition: 0.3s; text-align: left; width: 100%; }
        .choice-btn:hover { border-color: #c8a96e; background: rgba(200,169,110,0.05); }
    </style>
</head>
<body class="font-sans antialiased flex flex-col h-screen overflow-hidden">

    <!-- HEADER -->
    <header class="p-8 border-b border-white/5 flex justify-between items-center bg-black/20">
        <div class="flex items-center gap-6">
            <a href="panel.php" class="text-[10px] uppercase tracking-[3px] text-muted hover:text-accent">← Panel</a>
            <h1 class="font-bebas text-2xl tracking-[4px] text-accent">Chat de Presupuesto</h1>
        </div>
        <div class="text-[10px] uppercase tracking-widest text-muted">Usuario: <?php echo htmlspecialchars($usuario_nombre); ?></div>
    </header>

    <!-- CHAT FLOW -->
    <main class="flex-1 flex flex-col items-center overflow-hidden">
        <div id="chat-window" class="chat-area flex-1 w-full max-w-2xl overflow-y-auto p-10 space-y-8"></div>

        <!-- INPUT FOOTER -->
        <div class="w-full max-w-2xl p-8 border-t border-white/5">
            <div id="options-container" class="mb-6 space-y-2 hidden"></div>
            <div class="flex gap-4 items-center bg-white/[0.03] rounded-full px-6 py-4 border border-white/5 focus-within:border-accent/30 transition-all">
                <input id="chat-input" type="text" placeholder="Escribe un mensaje..." class="flex-1 bg-transparent text-sm outline-none">
                <button id="send-btn" class="text-accent hover:scale-110 transition-all">
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

        // Persistencia JSON (Punto 4)
        let chatData = {
            usuario: "<?php echo addslashes($usuario_nombre); ?>",
            respuestas: { showcase: "", colores: "", fuentes: "" },
            mensajes_libres: []
        };

        const sequence = [
            { type: 'sys', text: "gracias por pagar el modulo de creacion de pagina web, le recordamos que, en caso de no llegar a un acuerdo entre el precio o la creación de la página, se le podrá reembolsar el dinero en el periodo de 30 días desde la contratación, en cualquier momento puedes solicitar la cancelación en su panel" },
            { type: 'left', text: "en breves un administrador se pondrá en contacto con usted, si desea puede contestar a las siguientes preguntas para poder comenzar con los preparativos de la creación de la página web" },
            { 
                type: 'left', 
                text: "¿Le ha llamado la atención alguna página web que tenemos en nuestro 'showcase'?, ¿en caso afirmativo, podrías indicarnos cual o cuales?",
                key: 'showcase',
                buttons: [
                    "Sí, pero no se indicarlo ahora",
                    "No me ha gustado ninguna, quiero una página original",
                    "Indica cuáles te han gustado en este recuadro de abajo:"
                ]
            },
            { type: 'left', text: "Perfecto. ¿Cuál es la paleta de colores que visualizas para tu web?", key: 'colores' },
            { type: 'left', text: "¿Tienes alguna preferencia por fuentes de texto especiales o te serviría cualquiera de nuestras fuentes premium?", key: 'fuentes' },
            { type: 'left', text: "Muchas gracias. He guardado tus preferencias. Puedes seguir escribiéndome aquí cualquier detalle adicional." }
        ];

        let stepIndex = 0;
        let questionnaireDone = false;

        function addMessage(text, side = 'left', isSys = false) {
            const wrapper = document.createElement('div');
            wrapper.className = `flex ${isSys ? 'justify-center' : (side === 'left' ? 'justify-start' : 'justify-end')}`;
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
        }

        function showButtons(buttons) {
            optionsContainer.innerHTML = '';
            optionsContainer.classList.remove('hidden');
            buttons.forEach((txt, idx) => {
                const btn = document.createElement('button');
                btn.className = 'choice-btn';
                btn.innerText = txt;
                btn.onclick = () => {
                    if (idx === 2) {
                        chatInput.focus();
                    } else {
                        handleResponse(txt);
                    }
                };
                optionsContainer.appendChild(btn);
            });
        }

        // Función para enviar datos al servidor (Punto 3 del requerimiento)
        async function sendToBackend(payload) {
            try {
                const response = await fetch('procesar_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ...payload,
                        timestamp: new Date().toISOString()
                    })
                });
                const result = await response.json();
                console.log("Sincronización con servidor:", result);
            } catch (error) {
                console.error("Error al sincronizar con el servidor:", error);
            }
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
                sendToBackend({ emisor: "usuario", mensaje: val });
            }

            stepIndex++;
            if (stepIndex < sequence.length) {
                setTimeout(processStep, 800);
            } else if (!questionnaireDone) {
                // Hito: Al finalizar la última pregunta del cuestionario, enviar el objeto completo
                questionnaireDone = true;
                sendToBackend({ 
                    emisor: "usuario", 
                    mensaje: "Cuestionario Inicial Completado", 
                    cuestionario: chatData.respuestas 
                });
                console.log("Cuestionario Finalizado y enviado al backend.");
            }
        }

        function processStep() {
            const current = sequence[stepIndex];
            if (!current) return;

            if (current.type === 'sys') {
                addMessage(current.text, 'left', true);
                stepIndex++;
                setTimeout(processStep, 1500);
            } else {
                addMessage(current.text, 'left');
                if (current.buttons) {
                    setTimeout(() => showButtons(current.buttons), 500);
                }
            }
        }

        sendBtn.onclick = () => handleResponse();
        chatInput.onkeypress = (e) => { if(e.key === 'Enter') handleResponse(); };

        setTimeout(processStep, 1000);
    </script>
</body>
</html>
