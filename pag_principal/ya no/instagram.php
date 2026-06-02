<?php
require_once 'sessions.php';
$titulo_pagina = 'Social Feed';
function limpiarLinkYouTube($url) {
    // Cambia cualquier link de shorts o normal a formato embed
    return str_replace(['shorts/', 'watch?v='], 'embed/captioned=0', $url);
}

// Configuración de los Reels: Enlaces con su formato de inserción
$reels = [
    // [
    //     'link' => 'https://www.instagram.com/reel/DWbnKNuDTcB/embed/?captioned=0',
    //     'desc' => 'Configuración del entorno de desarrollo local.',
    //     'tag' => '#MEME'
    // ],
    // [
    //     'link' => 'https://www.instagram.com/reel/DU6LxD6E-8T/embed/?captioned=0',
    //     'desc' => 'Implementación de lógica de backend en PHP.',
    //     'tag' => '#MEME'
    // ],
    // [
    //     'link' => 'https://www.instagram.com/reel/DXKef9PAK3k/embed/?captioned=0',
    //     'desc' => 'Depuración de errores y testing de componentes.',
    //     'tag' => '#MEME'
    // ],
    // [
    //     'link' => 'https://www.instagram.com/reel/DU1utR_DeDP/embed/?captioned=0',
    //     'desc' => 'Integración de APIs externas en el sistema.',
    //     'tag' => '#MEME'
    // ],
    // [
    //     'link' => 'https://www.instagram.com/reel/DS462YADNYF/embed/?captioned=0',
    //     'desc' => 'Tips para mejorar la velocidad de carga (Performance).',
    //     'tag' => '#MEME'
    // ],
    [
        'link' => 'https://www.instagram.com/reel/DUYlD3QEVRz/embed/?captioned=0',
        'desc' => 'Manejo de estados en el frontend.',
        'tag' => '#MEME'
    ],
    [
        'link' => 'https://www.instagram.com/reel/DXURSF0jc7D/embed/?captioned=0',
        'desc' => 'Diseño responsivo con Flexbox y Grid.',
        'tag' => '#MEME'
    ],
    [
        'link' => 'https://www.instagram.com/reel/DWW3hQ-Dkf_/embed/?captioned=0',
        'desc' => 'Seguridad y protección contra inyecciones SQL.',
        'tag' => '#MEME'
    ],
    // [
    //     'link' => 'https://www.instagram.com/reel/DVTxDR-Dhou/embed/?captioned=0',
    //     'desc' => 'Organización de archivos y arquitectura MVC.',
    //     'tag' => '#MEME'
    // ],
    [
        'link' => 'https://www.instagram.com/p/DXXUJeTCXF7/embed/?captioned=0',
        'desc' => 'Resumen del progreso semanal del TFG.',
        'tag' => '#MEME'
    ],
    [
        'link' => 'https://www.instagram.com/reel/DXZ93kGj2h4/embed/?captioned=0',
        'desc' => 'Uso de librerías de terceros para gráficas.',
        'tag' => '#MEME'
    ],
    // [
    //     'link' => 'https://www.instagram.com/reel/DXkxZvtjJ-h/embed/?captioned=0',
    //     'desc' => 'Configuración de controladores y rutas.',
    //     'tag' => '#MEME'
    // ],
    [
        'link' => 'https://www.instagram.com/reel/DXW7QpZkav1/embed/?captioned=0',
        'desc' => 'Gestión de autenticación de usuarios.',
        'tag' => '#MEME'
    ],
    [
        'link' => 'https://www.instagram.com/reel/DX2WDMnwgRN/embed/?captioned=0',
        'desc' => 'Validación de formularios en tiempo real.',
        'tag' => '#MEME'
    ],
    [
        'link' => 'https://www.instagram.com/reel/DUtS37xkVG_/embed/?captioned=0',
        'desc' => 'Subida de archivos al servidor mediante PHP.',
        'tag' => '#MEME'
    ],
    [
        'link' => 'https://www.instagram.com/reel/DRWsDp4k0Le/embed/?captioned=0',
        'desc' => 'Mejores prácticas de accesibilidad web (A11y).',
        'tag' => '#MEME'
    ],
    [
        'link' => 'https://www.instagram.com/reel/DU-st4gEy3W/embed/?captioned=0',
        'desc' => 'Documentación técnica del código fuente.',
        'tag' => '#MEME'
    ],
    // [
    //     'link' => 'https://www.instagram.com/reel/DX-GRgWRpJn/embed/?captioned=0',
    //     'desc' => 'Preparación final para el despliegue a producción.',
    //     'tag' => '#MEME'
    // ],

    // reels de verdad, no memes
    [
        'link' => 'https://www.instagram.com/reel/DW6iZIEibQv/embed/?captioned=0',
        'desc' => 'Truco de desarrollo: Cómo utilizar HTML para improvisar un editor de CSS dinámico directamente en el navegador, permitiendo pruebas de estilo en tiempo real.',
        'tag' => '#TypDiv'
    ],
    [
        'link' => 'https://www.instagram.com/reel/DUoXLClCogC/embed/?captioned=0',
        'desc' => 'Guía para implementar un formulario de verificación OTP (One-Time Password) seguro, optimizando la experiencia de validación de usuarios.',
        'tag' => '#TypDiv'
    ],
    [
        'link' => 'https://www.instagram.com/reel/DX0lXRwii_q/embed/?captioned=0',
        'desc' => 'Diseño de un sistema de inicio de sesión interactivo basado en OTP, utilizando exclusivamente HTML y CSS para una interfaz fluida y moderna.',
        'tag' => '#TypDiv'
    ],
    [
        'link' => 'https://www.instagram.com/reel/DXWeQ7Ujt7D/embed/?captioned=0',
        'desc' => 'Desarrollo de una interfaz de usuario (UI) moderna para procesos de Login y Registro, centrada en la estética limpia y la usabilidad.',
        'tag' => '#TypDiv'
    ],
    [
        'link' => 'https://www.instagram.com/reel/DVv3gjaiV84/embed/?captioned=0',
        'desc' => 'Implementación funcional de un checkbox sencillo para alternar la visibilidad de contraseñas, un estándar esencial en la seguridad del frontend.',
        'tag' => '#TypDiv'
    ]
];

$css_extra = '
<style>
  .insta-grid {
    display: grid;
    /* Cambio a 5 columnas en escritorio */
    grid-template-columns: repeat(5, 1fr); 
    gap: 1.5rem;
    max-width: 1400px; /* Aumentado para dar espacio a las 5 columnas */
    margin: 3rem auto 0;
  }

  .insta-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 4px;
    overflow: hidden;
    transition: transform 0.3s ease, border-color 0.3s ease;
    animation: fadeUp 0.6s ease both;
    width: 100%; /* Que ocupe el ancho de su columna */
  }

  .insta-card:hover {
    transform: translateY(-5px);
    border-color: var(--accent);
  }

  /* Contenedor del video (Proporción Reel 9:16) */
  .reel-container {
    position: relative;
    width: 100%;
    aspect-ratio: 9 / 16;
    background: #000;
  }

  .reel-container iframe {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    border: none;
  }

  .insta-info {
    padding: 0.8rem;
    border-top: 1px solid var(--border);
  }

  .insta-info p {
    font-size: 0.85rem;
    color: var(--muted);
    line-height: 1.5;
  }

  .insta-info .tag {
    color: var(--accent);
    font-weight: 500;
    font-size: 0.7rem;
    text-transform: uppercase;
    display: block;
    margin-bottom: 0.5rem;
  }

/* Ajustes para tablets y móviles */
  @media (max-width: 1200px) {
    .insta-grid { grid-template-columns: repeat(3, 1fr); }
  }

  @media (max-width: 800px) {
    .insta-grid { grid-template-columns: repeat(2, 1fr); }
  }

  @media (max-width: 500px) {
    .insta-grid { grid-template-columns: 1fr; }
  }
</style>
';

require_once 'includes/header.php';
?>

<section>
  <div class="section-tag">Contenido Digital</div>
  <h2 class="section-title">REELS DE <em>PROGRAMACIÓN</em></h2>
  <p class="section-desc">
    Explora fragmentos de código y el humor detrás del desarrollo de este TFG.
  </p>

  <!-- SECCIÓN INSPIRACIÓN (TypDiv) -->
  <h3 style="margin-top: 4rem; color: var(--accent); font-family: 'Bebas Neue', sans-serif; font-size: 2rem;">
    INSPIRACIÓN
  </h3>
  <div class="insta-grid">
      <?php foreach ($reels as $reel): ?>
        <?php if ($reel['tag'] === '#TypDiv'): ?>
          <article class="insta-card">
            <div class="reel-container">
              <iframe 
                src="<?php echo $reel['link']; ?>" 
                frameborder="0" 
                loading="lazy"
                allowfullscreen>
              </iframe>
            </div>
            <div class="insta-info">
              <span class="tag">#Inspiracion</span>
              <p><?php echo $reel['desc']; ?></p>
            </div>
          </article>
        <?php endif; ?>
      <?php endforeach; ?>
  </div>

  <!-- SECCIÓN MEMES -->
  <h3 style="margin-top: 5rem; color: var(--accent); font-family: 'Bebas Neue', sans-serif; font-size: 2rem;">
    MEMES
  </h3>
  <div class="insta-grid">
      <?php foreach ($reels as $reel): ?>
        <?php if ($reel['tag'] === '#MEME'): ?>
          <article class="insta-card">
            <div class="reel-container">
              <iframe 
                src="<?php echo $reel['link']; ?>" 
                frameborder="0" 
                loading="lazy"
                allowfullscreen>
              </iframe>
            </div>
            <div class="insta-info">
              <span class="tag">#HumorDev</span>
              <p><?php echo $reel['desc']; ?></p>
            </div>
          </article>
        <?php endif; ?>
      <?php endforeach; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
