<?php
/* ============================================================
   ARCHIVO: galeria.php
   FUNCION: mostrar los proyectos publicados.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

require_once 'sessions.php';
require_once 'conexiones.php';
$conexion = getConexion();

/* ============================================================
   ESTILOS DE LA PAGINA: galeria.php
   Solo afectan a esta vista. Los estilos compartidos estan en estilos.css.
   ============================================================ */
$css_pagina = <<<'CSS'
/* Boton dorado propio de la galeria */
body.page-galeria .btn-gold {
  background-color: var(--gold-vibrant) !important;
  background: var(--gold-vibrant) !important;
  color: #000 !important;
  border: none !important;
  font-weight: bold !important;
}
body.page-galeria .btn-gold:hover {
  box-shadow: 0 0 15px var(--gold-vibrant) !important;
  opacity: 0.9;
}

/* ─── ESTILOS GENERALES ─── */
body.page-galeria .preview-slideshow-container {
    padding: 3rem 2rem;
    background: linear-gradient(to bottom, rgba(19, 19, 26, 0.8), transparent);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2rem;
    border-bottom: 1px solid var(--border);
}

body.page-galeria .monitor-frame {
    position: relative;
    width: 100%;
    max-width: 900px;
    background: #000;
    border: 10px solid #c8a96e;
    border-radius: 20px 20px 0 0;
    box-shadow: 0 30px 100px rgba(0,0,0,0.8), 0 0 40px rgba(200, 169, 110, 0.15);
    aspect-ratio: 16 / 9;
    overflow: hidden;
}

body.page-galeria .monitor-base { width: 200px; height: 12px; background: #c8a96e; margin: 0 auto; border-radius: 0 0 8px 8px; }
body.page-galeria .monitor-stand { width: 350px; height: 10px; background: #a68b55; margin: 0 auto; border-radius: 5px; }

body.page-galeria .preview-slide {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    opacity: 0;
    transition: opacity 1.5s ease-in-out;
    pointer-events: none;
}
body.page-galeria .preview-slide.active { opacity: 1; pointer-events: auto; }

body.page-galeria .preview-iframe-scaled {
    width: 1600px; height: 900px;
    border: none;
    transform: scale(0.5);
    transform-origin: top left;
    position: absolute;
    top: 0; left: 0;
}

body.page-galeria .monitor-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-80%);
    background: rgba(0,0,0,0.8);
    color: var(--accent);
    border: 2px solid var(--accent);
    width: 60px; height: 60px;
    border-radius: 50%;
    cursor: pointer;
    z-index: 100;
    font-size: 2rem;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.3s ease;
}
body.page-galeria .monitor-nav-btn:hover { background: var(--accent); color: #000; transform: translateY(-80%) scale(1.1); }
body.page-galeria .prev-btn { left: -90px; }
body.page-galeria .next-btn { right: -90px; }

body.page-galeria .galeria-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 3rem;
    max-width: 1200px;
    margin: 4rem auto;
    padding: 0 1rem;
}

body.page-galeria .proyecto-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
body.page-galeria .btn-gold {
    background: var(--accent);
    color: #000;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.85rem;
    text-decoration:none; 
    text-align:center; 
    margin-top:auto;
    transition: all 0.3s;
}

body.page-galeria .btn-gold:hover {
    transform: translateY(-2px);
    transition: all 0.3s;
    filter: brightness(1.1);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    text-decoration:none;
    font-size: 1rem;
}

body.page-galeria .btn-gold:active {
    transform: translateY(0px);
    transition: all 0.3s;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    font-size: 0.85rem;
}

body.page-galeria .mac-bar {
    background: #1a1a24;
    padding: 0.6rem 1rem;
    display: flex; align-items: center; gap: 0.4rem;
    border-bottom: 1px solid var(--border);
}
body.page-galeria .dot { width: 8px; height: 8px; border-radius: 50%; }
body.page-galeria .dot.red { background: #ff5f56; }
body.page-galeria .dot.yellow { background: #ffbd2e; }
body.page-galeria .dot.green { background: #27c93f; }

@media (max-width: 1200px) {body.page-galeria .prev-btn { left: 10px; }
    body.page-galeria .next-btn { right: 10px; }
    body.page-galeria .monitor-nav-btn { width: 45px; height: 45px; font-size: 1.2rem; }
    body.page-galeria .galeria-grid { grid-template-columns: 1fr; }}
    /* Aislamiento de Scroll para el Monitor */
    body.page-galeria .monitor-screen iframe {
        overscroll-behavior: contain !important;
    }

body.page-galeria .inline-galeria-001 { margin-bottom: 2rem; }
body.page-galeria .inline-galeria-002 { font-family: 'Bebas Neue', sans-serif; font-size: 2.2rem; color: var(--accent); letter-spacing: 2px; text-transform: uppercase; }
body.page-galeria .inline-galeria-003 { font-size: 0.9rem; color: var(--muted); font-family: monospace; }
body.page-galeria .inline-galeria-004 { width: 100%; max-width: 900px; position: relative; }
body.page-galeria .inline-galeria-005 { width:100%; height:100%; position:relative; }
body.page-galeria .inline-galeria-006 { margin: 4rem 0 2rem; }
body.page-galeria .inline-galeria-007 { margin-left:0.5rem; font-size:0.6rem; color:rgba(255,255,255,0.4); font-family: monospace; }
body.page-galeria .inline-galeria-008 { position:relative; width:100%; aspect-ratio:16/10; overflow:hidden; background:#000; }
body.page-galeria .inline-galeria-009 { padding: 1.5rem; flex-grow: 1; display: flex; flex-direction: column; }
body.page-galeria .inline-galeria-010 { color:var(--accent2); font-size:0.7rem; letter-spacing:1px; font-weight:700; }
body.page-galeria .inline-galeria-011 { font-family: 'Bebas Neue', sans-serif; font-size: 1.8rem; color: var(--text); margin: 0.5rem 0; }
body.page-galeria .inline-galeria-012 { font-size:0.85rem; color:var(--muted); line-height:1.5; margin-bottom:1.5rem; }

body.page-galeria .gallery-contained-frame { overscroll-behavior: contain; }
body.page-galeria .gallery-card-frame {
  width: 1400px;
  height: 875px;
  border: none;
  transform-origin: top left;
  position: absolute;
  top: 0;
  left: 0;
}
body.page-galeria .gallery-open-overlay {
  position: absolute;
  inset: 0;
  z-index: 10;
  cursor: pointer;
}
CSS;

$titulo_pagina = 'Galería de Proyectos';

// 1. Definición de Proyectos Manuales (Destacados)
$proyectos_manuales = [
    [
        'titulo' => 'Marsell',
        'tecnologia' => 'WordPress CMS',
        'u_web' => 'marsell',
        'demo_url' => 'https://vinomadrid.es/marsell/',
        'descripcion' => 'Web profesional de repostería artesanal para la gestión de productos, pedidos de pastelería y contacto directo.<br><br>
        <b>¿Qué la hace única?</b><br>
        <b>• E-commerce:</b> Integración completa con WooCommerce para la venta online de productos.<br>
        <b>• Gestión SEO:</b> Optimizada con herramientas avanzadas para posicionar el negocio localmente.<br>
        <b>• Personalización:</b> Sistema de bloques dinámicos que permite actualizar el catálogo fácilmente.<br>
        <b>• Seguridad:</b> Arquitectura WordPress protegida y preparada para transacciones seguras.',
        'plan' => 'Plan Premium'
    ],
    [
        'titulo' => 'Elizabeth Rosell Bonavina',
        'tecnologia' => 'HTML5 • CSS3 • JS',
        'u_web' => 'eli',
        'demo_url' => 'https://elirosellbonavina.es/',
        'descripcion' => 'Portfolio profesional interactivo que presenta el perfil técnico, proyectos y servicios de ingeniería.<br><br>
        <b>¿Qué la hace única?</b><br>
        <b>• Dualidad Visual:</b> Sistema de modo claro/oscuro nativo con persistencia en el navegador.<br>
        <b>• Navegación Limpia:</b> Gestión de scroll suave mediante JavaScript para evitar recargas y limpiar la URL.<br>
        <b>• Arquitectura Moderna:</b> Diseño basado en variables CSS dinámicas para un mantenimiento ultra rápido.<br>
        <b>• Optimización:</b> Código minimalista sin librerías externas para garantizar una carga instantánea.',
        'plan' => 'Plan Básico'
    ],
    [
        'titulo' => 'Skincare.Sl',
        'tecnologia' => 'HTML5 • CSS3 • JS',
        'u_web' => 'skincare',
        'demo_url' => 'https://vinomadrid.es/skincare/',
        'descripcion' => '
        Un diario inteligente para organizar tus rutinas de cara, pelo y cuerpo con seguimiento visual diario.
        <br><br><strong>¿Qué la hace única?</strong>
        <br>• Privacidad: Datos guardados localmente en tu navegador.
        <br>• Inteligencia: Interfaz que cambia de color según la zona.
        <br>• Control: Exportación total de datos en un clic.
        <br>• Rapidez: Funcionamiento instantáneo sin cargas externas.',
        'plan' => 'Plan Básico'
    ],
    [
        'titulo' => 'Típico',
        'tecnologia' => 'HTML5 • CSS3 • JS',
        'u_web' => 'tipico',
        'demo_url' => 'https://vinomadrid.es/tipico/',
        'descripcion' => 'Plataforma de gestión para hostelería con carta digital, reservas y pedidos en vivo.<br><br>
        <b>¿Qué la hace única?</b><br>
        <b>• Ligera:</b> Funciona sin bases de datos externas, todo ocurre en el navegador.<br>
        <b>• Responsive:</b> Menú interactivo que se adapta perfectamente a móviles.<br>
        <b>• Dinámica:</b> Carrito y calendario de reservas programados en JavaScript.<br>
        <b>• Fluida:</b> Navegación suave entre secciones para una mejor experiencia.',
        'plan' => 'Plan Básico'
    ]
];

// 2. Consulta a DB para traer el resto de páginas automáticas
$proyectos_db = [];
if ($conexion) {
    $query = "SELECT nombre, ftp_user, plan_contratado FROM usuarios WHERE plan_contratado != 'Ninguno' AND showcase_permission = 1 ORDER BY id DESC";
    $result = $conexion->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $u_web = strtolower(trim($row['ftp_user']));
            if (empty($u_web)) continue;
            
            $es_manual = false;
            foreach($proyectos_manuales as $pm) {
                if($pm['u_web'] === $u_web) { $es_manual = true; break; }
            }

            if (!$es_manual) {
                $proyectos_db[] = [
                    'titulo' => $row['nombre'],
                    'tecnologia' => 'Hosting Site',
                    'u_web' => $u_web,
                    'demo_url' => "https://vinomadrid.es/{$u_web}/",
                    'descripcion' => 'Sitio desplegado en nuestro servidor .',
                    'plan' => $row['plan_contratado']
                ];
            }
        }
    }
}

// 3. Unificamos todos los sitios
$proyectos = array_merge($proyectos_manuales, $proyectos_db);
$sitios_preview = array_slice($proyectos, 0, 15);

require_once 'includes/header.php';
?>



<section class="galeria">
    <!-- Showcase Superior -->
    <div class="preview-slideshow-container">
        <div class="text-center inline-galeria-001">
            <div class="section-tag">Showcase Realtime</div>
            <div id="slide-title" class="inline-galeria-002">Explorando Nodo...</div>
            <div id="slide-url" class="inline-galeria-003">---</div>
        </div>

        <div class="inline-galeria-004">
            <button class="monitor-nav-btn prev-btn" onclick="manualMoveSlide(-1)">&#10094;</button>
            <button class="monitor-nav-btn next-btn" onclick="manualMoveSlide(1)">&#10095;</button>

            <div class="monitor-frame" id="monitor-box">
                <div id="slideshow-viewport" class="inline-galeria-005">
                    <?php foreach($sitios_preview as $idx => $s): ?>
                        <div class="preview-slide <?php echo $idx === 0 ? 'active' : ''; ?>" 
                             data-title="<?php echo htmlspecialchars($s['titulo']); ?>" 
                             data-url="<?php echo $s['demo_url']; ?>">
                            <iframe src="about:blank" data-src="<?php echo $s['demo_url']; ?>" class="preview-iframe-scaled gallery-contained-frame"
                                    sandbox="allow-scripts allow-forms allow-same-origin"
                                    tabindex="-1"
                                    ></iframe>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="monitor-base"></div>
            <div class="monitor-stand"></div>
        </div>
    </div>

    <!-- Títulos de la Rejilla -->
    <div class="text-center inline-galeria-006">
        <div class="section-tag">Portafolio de Hosting</div>
        <h2 class="section-title">PROYECTOS <em>ACTIVOS</em></h2>
    </div>

    <!-- Rejilla de Cards -->
    <div class="galeria-grid">
        <?php foreach ($proyectos as $p): ?>
            <div class="proyecto-card">
                <div class="mac-bar">
                    <div class="dot red"></div>
                    <div class="dot yellow"></div>
                    <div class="dot green"></div>
                    <span class="inline-galeria-007">
                        <?php echo isset($p['u_web']) ? $p['u_web'] . '.cloud' : 'site.preview'; ?>
                    </span>
                </div>

                <div class="preview-wrapper inline-galeria-008">
                    <iframe src="<?php echo $p['demo_url']; ?>" class="card-iframe gallery-card-frame" loading="lazy"
                            sandbox="allow-scripts allow-same-origin allow-forms"
                            ></iframe>
                    <div class="gallery-open-overlay" onclick="window.open('<?php echo $p['demo_url']; ?>', '_blank')"></div>
                </div>

                <div class="inline-galeria-009">
                    <span class="inline-galeria-010"><?php echo strtoupper($p['tecnologia']); ?></span>
                    <h3 class="inline-galeria-011"><?php echo $p['titulo']; ?></h3>
                    <p class="inline-galeria-012"><?php echo $p['descripcion']; ?></p>
                    <a href="<?php echo $p['demo_url']; ?>" target="_blank" class="btn-gold">Visitar Sitio ↗</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
let currentIdx = 0;
let autoSlideInterval;

function updateScaling() {
    const monitor = document.getElementById('monitor-box');
    if(!monitor) return;
    const monitorWidth = monitor.offsetWidth;
    const monitorScale = monitorWidth / 1600;
    document.querySelectorAll('.preview-iframe-scaled').forEach(iframe => {
        iframe.style.transform = `scale(${monitorScale})`;
    });
    
    document.querySelectorAll('.preview-wrapper').forEach(wrapper => {
        const cardWidth = wrapper.offsetWidth;
        const cardScale = cardWidth / 1400;
        const iframe = wrapper.querySelector('.card-iframe');
        if(iframe) iframe.style.transform = `scale(${cardScale})`;
    });
}

function loadSlide(idx) {
    const slides = document.querySelectorAll('.preview-slide');
    const slide = slides[idx];
    if(!slide) return;
    const iframe = slide.querySelector('iframe');
    if (iframe && (iframe.src === 'about:blank' || iframe.src === '')) {
        iframe.src = iframe.getAttribute('data-src');
    }
}

function showSlide(idx) {
    const slides = document.querySelectorAll('.preview-slide');
    const titleEl = document.getElementById('slide-title');
    const urlEl = document.getElementById('slide-url');

    if(slides.length === 0) return;

    // Guardar posición de scroll actual
    const scrollPos = window.scrollY;

    slides[currentIdx].classList.remove('active');
    currentIdx = (idx + slides.length) % slides.length;
    
    loadSlide(currentIdx);
    slides[currentIdx].classList.add('active');
    
    if(titleEl) titleEl.textContent = slides[currentIdx].getAttribute('data-title');
    if(urlEl) urlEl.textContent = slides[currentIdx].getAttribute('data-url');

    // Restaurar posición de scroll para evitar saltos
    window.scrollTo(window.scrollX, scrollPos);
}

function manualMoveSlide(step) {
    clearInterval(autoSlideInterval);
    showSlide(currentIdx + step);
    startAutoSlide();
}

function startAutoSlide() {
    autoSlideInterval = setInterval(() => {
        showSlide(currentIdx + 1);
    }, 6000);
}

document.addEventListener('DOMContentLoaded', function() {
    window.addEventListener('resize', updateScaling);
    updateScaling();

    const slides = document.querySelectorAll('.preview-slide');
    if (slides.length > 0) {
        loadSlide(0);
        showSlide(0);
        startAutoSlide();
    }

    // Pausa y control de navegación interna en el monitor
    const monitorBox = document.getElementById('monitor-box');
    if (monitorBox) {
        monitorBox.addEventListener('mouseenter', () => {
            clearInterval(autoSlideInterval);
        });
        monitorBox.addEventListener('mouseleave', () => {
            startAutoSlide();
        });

        // Intentar capturar clics internos para evitar saltos de scroll en el padre
        const monitorSlides = document.getElementById('monitor-slides');
        if (monitorSlides) {
            monitorSlides.addEventListener('load', (e) => {
                if (e.target.tagName === 'IFRAME') {
                    try {
                        const iframeWin = e.target.contentWindow;
                        iframeWin.addEventListener('click', (ev) => {
                            const link = ev.target.closest('a');
                            if (link && link.hash && link.hash.startsWith('#')) {
                                ev.preventDefault();
                                const targetEl = iframeWin.document.querySelector(link.hash);
                                if (targetEl) {
                                    targetEl.scrollIntoView({ behavior: 'smooth' });
                                }
                            }
                        }, true);
                    } catch (err) {
                        console.warn("No se pudo acceder al contenido del iframe para bloquear scroll (CORS)");
                    }
                }
            }, true);
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>