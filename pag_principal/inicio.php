<?php
/* ============================================================
   ARCHIVO: inicio.php
   FUNCION: presentar la pagina corporativa oficial del servicio.
   SECCIONES: presentacion, servicio, seguridad y stack tecnologico.
   ============================================================ */

/* ============================================================
   ESTILOS DE LA PAGINA: inicio.php
   Solo afectan a esta vista. Los estilos compartidos estan en estilos.css.
   ============================================================ */
$css_pagina = <<<'CSS'
body.page-inicio .corporate-hero {
  position: relative;
  padding: 10rem 1.5rem 5.5rem;
  text-align: center;
  background: var(--surface);
  overflow: hidden;
}

body.page-inicio .corporate-hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 50% 0%, rgba(200, 169, 110, 0.15), transparent 52%);
  pointer-events: none;
}

body.page-inicio .corporate-hero > * {
  position: relative;
}

body.page-inicio .corporate-tag {
  display: inline-block;
  margin-bottom: 1.4rem;
  color: var(--accent);
  font-size: 0.72rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
}

body.page-inicio .corporate-title {
  margin-bottom: 1.4rem;
  font-family: "Bebas Neue", sans-serif;
  font-size: clamp(3.2rem, 8vw, 6.4rem);
  font-weight: 400;
  line-height: 0.96;
  color: var(--text);
}

body.page-inicio .corporate-title span {
  color: var(--accent);
}

body.page-inicio .corporate-lead {
  max-width: 660px;
  margin: 0 auto;
  color: var(--muted);
  font-size: 1.02rem;
  line-height: 1.85;
}

body.page-inicio .corporate-content {
  max-width: 1120px;
  margin: 0 auto;
  padding: 5.5rem 2rem;
}

body.page-inicio .corporate-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1.4rem;
  margin-bottom: 5rem;
}

body.page-inicio .information-card {
  padding: 2.3rem;
  border: 1px solid var(--border);
  background: var(--surface);
  border-radius: 8px;
}

body.page-inicio .information-card h2 {
  margin-bottom: 1.2rem;
  font-family: "Bebas Neue", sans-serif;
  font-size: 2rem;
  font-weight: 400;
}

body.page-inicio .information-card p {
  color: var(--muted);
  line-height: 1.85;
}

body.page-inicio .information-card p + p {
  margin-top: 1rem;
}

body.page-inicio .stack {
  padding: 0;
  text-align: center;
}

body.page-inicio .stack-intro {
  max-width: 620px;
  margin: 0 auto 2.6rem;
  color: var(--muted);
  line-height: 1.75;
}

body.page-inicio .technology-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
  max-width: 820px;
  margin: 0 auto;
}

body.page-inicio .technology-button {
  padding: 2rem 1.2rem;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  color: var(--text);
  cursor: pointer;
  transition: transform 0.25s, border-color 0.25s, background 0.25s;
}

body.page-inicio .technology-button:hover,
body.page-inicio .technology-button:focus-visible {
  transform: translateY(-4px);
  border-color: var(--accent);
  background: var(--surface2);
  outline: none;
}

body.page-inicio .technology-name {
  display: block;
  margin-bottom: 0.35rem;
  font-family: "Bebas Neue", sans-serif;
  font-size: 2rem;
  letter-spacing: 0.06em;
  color: var(--accent);
}

body.page-inicio .technology-purpose {
  color: var(--muted);
  font-size: 0.86rem;
}

body.page-inicio .technology-modal {
  width: min(540px, calc(100% - 2rem));
  margin: auto;
  padding: 0;
  color: var(--text);
  background: var(--surface);
  border: 1px solid var(--accent);
  border-radius: 10px;
  box-shadow: 0 22px 70px rgba(0, 0, 0, 0.65);
}

body.page-inicio .technology-modal::backdrop {
  background: rgba(5, 5, 8, 0.8);
  backdrop-filter: blur(3px);
}

body.page-inicio .modal-content {
  position: relative;
  padding: 2.5rem;
  text-align: left;
}

body.page-inicio .modal-close {
  position: absolute;
  top: 0.9rem;
  right: 1rem;
  width: 2.2rem;
  height: 2.2rem;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 1.55rem;
  cursor: pointer;
}

body.page-inicio .modal-close:hover {
  color: var(--accent);
}

body.page-inicio .modal-label {
  display: block;
  margin-bottom: 0.55rem;
  color: var(--accent);
  font-size: 0.68rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
}

body.page-inicio .modal-title {
  margin-bottom: 1rem;
  font-family: "Bebas Neue", sans-serif;
  font-size: 2.6rem;
  font-weight: 400;
}

body.page-inicio .modal-text {
  color: var(--muted);
  line-height: 1.85;
}

@media (max-width: 768px) {
  body.page-inicio .corporate-grid,
  body.page-inicio .technology-grid {
    grid-template-columns: 1fr;
  }

  body.page-inicio .corporate-content {
    padding: 4rem 1.5rem;
  }
}
CSS;

$titulo_pagina = 'La Plataforma';
require_once 'includes/header.php';
?>

<!-- SECCION: presentacion corporativa -->
<header class="corporate-hero">
  <p class="corporate-tag">Plataforma de alojamiento web</p>
  <h1 class="corporate-title">Infraestructura clara para<br><span>proyectos digitales</span></h1>
  <p class="corporate-lead">
    VinoMadrid Hosting proporciona un entorno centralizado para contratar,
    administrar y supervisar servicios de alojamiento web con una operativa
    comprensible, segura y orientada al control del cliente.
  </p>
</header>

<main class="corporate-content">
  <!-- SECCION: descripción del servicio y privacidad -->
  <div class="corporate-grid">
    <article class="information-card">
      <h2>Descripción del Servicio</h2>
      <p>
        La plataforma permite gestionar el alojamiento de un proyecto web
        desde una cuenta personal: contratación de planes, acceso FTP,
        bases de datos MySQL, dominios asociados y consulta de facturación.
      </p>
      <p>
        El panel de usuario concentra las operaciones principales y comunica
        cada solicitud al servidor para que los recursos contratados se
        provisionen de forma ordenada y verificable.
      </p>
    </article>

    <article class="information-card">
      <h2>Privacidad y Seguridad</h2>
      <p>
        El acceso a la zona privada requiere autenticación y las operaciones
        sensibles se procesan en el servidor, evitando exponer credenciales
        o datos de configuración en el navegador.
      </p>
      <p>
        La información personal y de facturación se utiliza exclusivamente
        para gestionar la cuenta y prestar el servicio contratado. Los
        cambios administrativos quedan registrados para facilitar su control.
      </p>
    </article>
  </div>

  <!-- SECCION: stack tecnológico interactivo -->
  <section class="stack" aria-labelledby="stack-title">
    <p class="section-tag">Tecnología</p>
    <h2 id="stack-title" class="section-title">Stack <em>Tecnológico</em></h2>
    <p class="stack-intro">
      Selecciona cada tecnología para consultar la función que cumple
      dentro de la plataforma.
    </p>

    <div class="technology-grid">
      <button class="technology-button" type="button" data-open-modal="modal-php">
        <span class="technology-name">PHP</span>
        <span class="technology-purpose">Lógica de servidor</span>
      </button>
      <button class="technology-button" type="button" data-open-modal="modal-mysql">
        <span class="technology-name">MySQL</span>
        <span class="technology-purpose">Persistencia de datos</span>
      </button>
      <button class="technology-button" type="button" data-open-modal="modal-javascript">
        <span class="technology-name">JavaScript</span>
        <span class="technology-purpose">Interacción de interfaz</span>
      </button>
    </div>
  </section>

  <!-- SECCION: impacto ambiental -->
   <section class="stack" aria-labelledby="stack-title" style="margin-top: 5rem;">
        <p class="section-tag">Impacto Ambiental</p>
        <h2 id="stack-title" class="section-title">Medio <em>Ambiente</em></h2>
        <p class="stack-intro">
          Nos comprometemos a operar de manera sostenible, implementando prácticas de eficiencia energética, 
          uso de energías renovables y optimización de recursos para minimizar nuestro impacto ambiental 
          y contribuir a un futuro más verde en el sector del hosting web.
        </p>
      <div class="corporate-grid">
        <article class="information-card">
          <h2>Contribuyendo al medio ambiente</h2>
          <p>
            Cumplimos con los ODS de la ONU mediante prácticas de eficiencia energética, 
            uso de energías renovables y optimización de recursos en nuestros centros de datos, 
            reduciendo la huella de carbono y promoviendo un futuro sostenible para la tecnología.
          </p>
          <p>
            Somos conscientes del impacto ambiental de la tecnología y nos comprometemos a operar de manera responsable,
            implementando medidas que minimicen nuestro impacto y fomenten la sostenibilidad en el sector del hosting web.
          </p>
        </article>

        <article class="information-card">
          <h2>ODS</h2>
          <p>
            El proyecto se alinea con la Agenda 2030 mediante tres pilares fundamentales: 
          </p>
          <p>
            <ul style="color: var(--muted);">
              <li>ODS 7 (Energía asequible), al reutilizar un MacBook Pro de bajo consumo y optimizar recursos con Ubuntu Server;</li>
              <li>ODS 8 (Trabajo decente), facilitando el emprendimiento local con hosting profesional a bajo coste;</li>
              <li>ODS 12 (Producción y consumo responsables), mediante una política de "papel cero" y la gestión digital íntegra de la infraestructura.</li>
            </ul>
          </p>
          <p>
            Nuestra plataforma no solo ofrece servicios de hosting, sino que también promueve prácticas sostenibles y responsables, 
            contribuyendo a un futuro más verde y equitativo en el ámbito tecnológico.
          </p>
        </article>
      </div>
  </section>
</main>

<!-- MODALES NATIVOS: explicacion tecnica del stack -->
<dialog id="modal-php" class="technology-modal" aria-labelledby="title-php">
  <div class="modal-content">
    <button class="modal-close" type="button" data-close-modal aria-label="Cerrar ventana">&times;</button>
    <span class="modal-label">Servidor</span>
    <h2 id="title-php" class="modal-title">PHP</h2>
    <p class="modal-text">
      PHP procesa las sesiones, valida las solicitudes del usuario y comunica
      la interfaz con la base de datos. En esta plataforma actúa como capa de
      servidor, manteniendo las operaciones sensibles fuera del navegador.
    </p>
  </div>
</dialog>

<dialog id="modal-mysql" class="technology-modal" aria-labelledby="title-mysql">
  <div class="modal-content">
    <button class="modal-close" type="button" data-close-modal aria-label="Cerrar ventana">&times;</button>
    <span class="modal-label">Datos</span>
    <h2 id="title-mysql" class="modal-title">MySQL</h2>
    <p class="modal-text">
      MySQL almacena usuarios, servicios contratados, dominios, facturas y
      alertas administrativas. Las consultas se ejecutan desde el servidor
      para conservar la integridad y la confidencialidad de la información.
    </p>
  </div>
</dialog>

<dialog id="modal-javascript" class="technology-modal" aria-labelledby="title-javascript">
  <div class="modal-content">
    <button class="modal-close" type="button" data-close-modal aria-label="Cerrar ventana">&times;</button>
    <span class="modal-label">Interfaz</span>
    <h2 id="title-javascript" class="modal-title">JavaScript</h2>
    <p class="modal-text">
      JavaScript mejora la experiencia del usuario mediante interacciones
      inmediatas, ventanas informativas y envíos controlados desde la página,
      sin asumir responsabilidades propias del servidor o la base de datos.
    </p>
  </div>
</dialog>

<script>
  /* SECCION: apertura y cierre accesible de los modales tecnológicos. */
  document.querySelectorAll('[data-open-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = document.getElementById(button.dataset.openModal);
      if (modal) {
        modal.showModal();
      }
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog').close());
  });

  document.querySelectorAll('.technology-modal').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        modal.close();
      }
    });
  });
</script>

<?php require_once 'includes/footer.php'; ?>