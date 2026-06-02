<?php
/* ============================================================
   ARCHIVO: index.php
   FUNCION: presentar la landing publica de bienvenida.
   SECCIONES: estilos exclusivos y contenido principal.
   ============================================================ */

/* ============================================================
   ESTILOS DE LA PAGINA: index.php
   Solo afectan a la landing. Los estilos compartidos estan en estilos.css.
   ============================================================ */
$css_pagina = <<<'CSS'
body.page-index .landing {
  position: relative;
  flex: 1;
  min-height: calc(90vh - 80px);
  display: grid;
  place-items: center;
  padding: 9rem 1.5rem 5rem;
  text-align: center;
  overflow: hidden;
}

body.page-index .landing::before {
  content: "";
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 50% 34%, rgba(200, 169, 110, 0.14), transparent 34rem),
    linear-gradient(rgba(200, 169, 110, 0.035) 1px, transparent 1px),
    linear-gradient(90deg, rgba(200, 169, 110, 0.035) 1px, transparent 1px);
  background-size: auto, 64px 64px, 64px 64px;
  pointer-events: none;
}

body.page-index .landing-content {
  position: relative;
  max-width: 820px;
  animation: fadeUp 0.65s ease both;
}

body.page-index .landing-kicker {
  display: inline-block;
  margin-bottom: 1.6rem;
  padding: 0.45rem 1.15rem;
  border: 1px solid var(--border);
  border-radius: 30px;
  color: var(--accent);
  font-size: 0.7rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
}

body.page-index .landing-title {
  margin-bottom: 1.5rem;
  font-family: "Bebas Neue", sans-serif;
  font-size: clamp(4.2rem, 11vw, 8.8rem);
  font-weight: 400;
  letter-spacing: 0.025em;
  line-height: 0.9;
  color: var(--text);
}

body.page-index .landing-title span {
  color: var(--accent);
}

body.page-index .landing-subtitle {
  max-width: 590px;
  margin: 0 auto;
  color: var(--muted);
  font-size: clamp(0.95rem, 2vw, 1.1rem);
  line-height: 1.85;
}

body.page-index .landing-actions {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 1rem;
  margin-top: 3rem;
}

body.page-index .landing-actions a {
  min-width: 218px;
  justify-content: center;
}
CSS;

$titulo_pagina = 'Bienvenida';
require_once 'includes/header.php';
?>

<!-- SECCION: landing de bienvenida -->
<main class="landing">
  <div class="landing-content">
    <p class="landing-kicker">VinoMadrid Hosting</p>
    <h1 class="landing-title">Hosting web<br><span>gestionado</span></h1>
    <p class="landing-subtitle">
      Una plataforma clara y profesional para alojar proyectos web,
      gestionar servicios y controlar cada recurso desde un único panel.
    </p>

    <div class="landing-actions">
      <a href="inicio.php" class="btn-primary">Conocer la Plataforma</a>
      <a href="auth.php" class="btn-ghost">Iniciar Sesión / Registro</a>
    </div>
  </div>
</main>

<?php require_once 'includes/footer.php'; ?>