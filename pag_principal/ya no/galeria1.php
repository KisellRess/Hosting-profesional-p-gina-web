<?php
$titulo_pagina = 'Galería de Proyectos';

// Configuración con tus dominios reales
$proyectos = [
    [
        'titulo' => 'Vino Madrid (Ana)',
        'tecnologia' => 'WordPress CMS',
        'imagenes' => ['img/vino1.jpg', 'img/vino2.jpg'], // Sustituir por tus capturas
        'demo_url' => 'https://vinomadrid.es/anas/',
        'descripcion' => 'Sitio gestionado con WordPress, optimizado para el catálogo de vinos y gestión de contenidos.'
    ],
    [
        'titulo' => 'Eli Rosell Bonavina',
        'tecnologia' => 'HTML5 • CSS3 • JS • PHP',
        'imagenes' => ['img/eli1.jpg', 'img/eli2.jpg'], // Sustituir por tus capturas
        'demo_url' => 'https://elirosellbonavina.es/',
        'descripcion' => 'Despliegue de código personalizado con dominio propio, enfocado en marca personal y rendimiento.'
    ]
];

$css_extra = '
<style>
  .galeria-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 3rem;
    max-width: 1100px;
    margin: 4rem auto;
  }

  .proyecto-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 4px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    animation: fadeUp 0.8s ease both;
  }

  .proyecto-card:hover { border-color: var(--accent); }

  /* Contenedor Carrusel */
  .carousel-container {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: #000;
  }

  .carousel-track {
    display: flex;
    transition: transform 0.5s ease-in-out;
    height: 100%;
  }

  .carousel-slide {
    min-width: 100%;
    height: 100%;
  }

  .carousel-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  /* Botones del carrusel */
  .carousel-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,0.6);
    color: var(--accent);
    border: none;
    padding: 10px;
    cursor: pointer;
    z-index: 5;
    font-size: 1.2rem;
    transition: background 0.3s;
  }
  .carousel-btn:hover { background: var(--accent); color: var(--bg); }
  .prev { left: 0; border-radius: 0 4px 4px 0; }
  .next { right: 0; border-radius: 4px 0 0 4px; }

  .proyecto-content { padding: 1.5rem; flex-grow: 1; display: flex; flex-direction: column; }
  .proyecto-titulo { font-family: "Bebas Neue", sans-serif; font-size: 1.8rem; color: var(--text); margin: 0.5rem 0; }
  .proyecto-desc { font-size: 0.85rem; color: var(--muted); line-height: 1.5; margin-bottom: 1.5rem; }

  /* Enlace con target _blank */
  .link-demo {
    margin-top: auto;
    color: var(--accent);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: color 0.3s;
  }
  .link-demo:hover { color: var(--text); }

  @media (max-width: 850px) { .galeria-grid { grid-template-columns: 1fr; } }
</style>
';

require_once 'includes/header.php';
?>

<section>
  <div class="section-tag">Portafolio de Hosting</div>
  <h2 class="section-title">PROYECTOS <em>ACTIVOS</em></h2>

  <div class="galeria-grid">
    <?php foreach ($proyectos as $idx => $p): ?>
      <div class="proyecto-card">
        
        <div class="carousel-container">
          <button class="carousel-btn prev" onclick="moveSlide(<?php echo $idx; ?>, -1)">&#10094;</button>
          
          <div class="carousel-track" id="track-<?php echo $idx; ?>">
            <?php foreach ($p['imagenes'] as $img): ?>
              <div class="carousel-slide">
                <img src="<?php echo $img; ?>" alt="Screenshot">
              </div>
            <?php endforeach; ?>
          </div>

          <button class="carousel-btn next" onclick="moveSlide(<?php echo $idx; ?>, 1)">&#10095;</button>
        </div>

        <div class="proyecto-content">
          <span style="color:var(--accent2); font-size:0.7rem; letter-spacing:1px;"><?php echo $p['tecnologia']; ?></span>
          <h3 class="proyecto-titulo"><?php echo $p['titulo']; ?></h3>
          <p class="proyecto-desc"><?php echo $p['descripcion']; ?></p>
          
          <a href="<?php echo $p['demo_url']; ?>" target="_blank" rel="noopener noreferrer" class="link-demo">
            Explorar sitio alojado ↗
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<script>
const slideIndexes = {};

function moveSlide(id, step) {
    const track = document.getElementById('track-' + id);
    const slides = track.querySelectorAll('.carousel-slide');
    
    if (!(id in slideIndexes)) slideIndexes[id] = 0;
    
    slideIndexes[id] += step;

    if (slideIndexes[id] >= slides.length) slideIndexes[id] = 0;
    if (slideIndexes[id] < 0) slideIndexes[id] = slides.length - 1;

    track.style.transform = `translateX(-${slideIndexes[id] * 100}%)`;
}

// Movimiento automático cada 6 segundos
setInterval(() => {
    <?php foreach ($proyectos as $idx => $p): ?>
        moveSlide(<?php echo $idx; ?>, 1);
    <?php endforeach; ?>
}, 6000);
</script>

<?php require_once 'includes/footer.php'; ?>
