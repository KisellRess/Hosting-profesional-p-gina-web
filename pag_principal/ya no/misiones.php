<?php
$titulo_pagina = 'Las 5 Etapas del Proyecto';
$css_extra = '
<style>
  /* ─── MISIONES ─── */
  #misiones { background: var(--surface); }

  .misiones-header {
    text-align: center;
    margin-bottom: 4rem;
  }

  .misiones-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5px;
    background: var(--border);
    border: 1px solid var(--border);
  }

  .mision-card {
    background: var(--bg);
    padding: 2.5rem;
    position: relative;
    overflow: hidden;
    transition: background 0.3s;
  }
  .mision-card:hover { background: var(--surface2); }

  .mision-card::after {
    content: "";
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 2px;
    background: var(--accent);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.4s ease;
  }
  .mision-card:hover::after { transform: scaleX(1); }

  .mision-num {
    font-family: "Bebas Neue", sans-serif;
    font-size: 5rem;
    color: rgba(200,169,110,0.06);
    position: absolute;
    top: 0.5rem; right: 1rem;
    line-height: 1;
    pointer-events: none;
  }

  .mision-icon {
    font-size: 2rem;
    margin-bottom: 1.2rem;
  }

  .mision-title {
    font-family: "Bebas Neue", sans-serif;
    font-size: 1.5rem;
    letter-spacing: 0.05em;
    color: var(--accent);
    margin-bottom: 0.5rem;
  }

  .mision-subtitle {
    font-size: 0.75rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 1.5rem;
  }

  .mision-tasks {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
  }
  .mision-tasks li {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    font-size: 0.88rem;
    color: var(--muted);
    line-height: 1.5;
  }
  .mision-tasks li::before {
    content: "→";
    color: var(--accent);
    flex-shrink: 0;
    margin-top: 0.1rem;
  }
</style>
';
require_once 'includes/header.php';
?>

<!-- ─── MISIONES ─── -->
<section id="misiones" style="padding-top: 9rem;">
  <div class="misiones-header">
    <div class="section-tag">Arquitectura del Proyecto</div>
    <h2 class="section-title">Las 5 <em>Etapas</em></h2>
    <p class="section-desc" style="margin:0 auto;">Cada estapa representa una capa del sistema, desde el backend hasta el frontend final.</p>
  </div>

  <div class="misiones-grid">
    <div class="mision-card">
      <div class="mision-num">01</div>
      <div class="mision-icon">🚀</div>
      <div class="mision-title">Backend & DB</div>
      <div class="mision-subtitle">Etapa 1 — Preparación del Terreno</div>
      <ul class="mision-tasks">
        <li>Crear la base de datos <strong>vinomadrid_db</strong> en MySQL</li>
        <li>Diseñar la tabla <code>usuarios</code> con campos id, nombre, email, password_hash, plan_contratado, ftp_user y ftp_pass</li>
        <li>Crear el archivo <code>conexion.php</code> para conectar con MySQL</li>
      </ul>
    </div>

    <div class="mision-card">
      <div class="mision-num">02</div>
      <div class="mision-icon">🛡</div>
      <div class="mision-title">Login & Registro</div>
      <div class="mision-subtitle">Etapa 2 — Sistema de Acceso</div>
      <ul class="mision-tasks">
        <li>Formulario de Registro que inserta datos en la tabla de usuarios</li>
        <li>Formulario de Login con validación de credenciales</li>
        <li>Sesiones PHP con <code>session_start()</code> para persistencia</li>
      </ul>
    </div>

    <div class="mision-card">
      <div class="mision-num">03</div>
      <div class="mision-icon">💰</div>
      <div class="mision-title">Landing & Planes</div>
      <div class="mision-subtitle">Etapa 3 — El Escaparate</div>
      <ul class="mision-tasks">
        <li>Tabla de precios con planes Básico, Profesional y Enterprise</li>
        <li>Descripción de beneficios: disco, FTP, soporte, etc.</li>
        <li>Botón "Contratar" que redirige a la pasarela de pago</li>
      </ul>
    </div>

    <div class="mision-card">
      <div class="mision-num">04</div>
      <div class="mision-icon">💳</div>
      <div class="mision-title">Pago & Panel</div>
      <div class="mision-subtitle">Etapa 4 — Pasarela de Pago</div>
      <ul class="mision-tasks">
        <li>Página Checkout Sandbox con simulación de pago por tarjeta</li>
        <li>Actualización del campo <code>plan_contratado</code> tras el pago</li>
        <li>Panel de usuario que muestra plan activo y credenciales FTP</li>
      </ul>
    </div>

    <div class="mision-card" style="grid-column: span 2;">
      <div class="mision-num">05</div>
      <div class="mision-icon">🎨</div>
      <div class="mision-title">Frontend Final</div>
      <div class="mision-subtitle">Etapa 5 — Estética y Chicha</div>
      <ul class="mision-tasks">
        <li>Aplicar estilos CSS o Bootstrap para aspecto profesional</li>
        <li>Galería de fotos: racks de servidores, terminal propio, logos de tecnologías (LAMP, Cloudflare, etc.)</li>
        <li>Revisión final de todos los enlaces y textos de la web</li>
      </ul>
    </div>
  </div>
</section>

<script>
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.style.opacity = '1';
        e.target.style.transform = 'translateY(0)';
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.mision-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(24px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
  });
</script>

<?php require_once 'includes/footer.php'; ?>
