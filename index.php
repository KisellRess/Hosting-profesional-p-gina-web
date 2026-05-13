<?php
$titulo_pagina = 'Potencia tu presencia online';
$css_extra = '
<style>
  /* ─── HERO ─── */
  .hero {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 8rem 2rem 6rem;
    position: relative;
    overflow: hidden;
  }

  .hero-bg {
    position: absolute;
    inset: 0;
    background:
      radial-gradient(ellipse 80% 60% at 50% 0%, rgba(200,169,110,0.07) 0%, transparent 70%),
      radial-gradient(ellipse 40% 40% at 80% 80%, rgba(192,57,43,0.06) 0%, transparent 60%);
  }

  .hero-grid {
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(rgba(200,169,110,0.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(200,169,110,0.04) 1px, transparent 1px);
    background-size: 60px 60px;
    mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 80%);
  }

  .hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--accent);
    font-size: 0.75rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    padding: 0.4rem 1rem;
    border-radius: 100px;
    margin-bottom: 2rem;
    animation: fadeUp 0.8s ease both;
  }
  .hero-badge::before {
    content: "";
    width: 6px; height: 6px;
    background: var(--accent);
    border-radius: 50%;
    animation: pulse 2s ease infinite;
  }

  .hero h1 {
    font-family: "Bebas Neue", sans-serif;
    font-size: clamp(4rem, 12vw, 10rem);
    line-height: 0.9;
    letter-spacing: 0.02em;
    color: var(--text);
    animation: fadeUp 0.8s 0.15s ease both;
  }
  .hero h1 em {
    font-style: normal;
    color: var(--accent);
  }

  .hero-sub {
    margin-top: 1.8rem;
    font-size: 1.1rem;
    color: var(--muted);
    max-width: 520px;
    line-height: 1.7;
    animation: fadeUp 0.8s 0.3s ease both;
  }

  .hero-actions {
    margin-top: 2.8rem;
    display: flex;
    gap: 1rem;
    animation: fadeUp 0.8s 0.45s ease both;
  }

  .hero-stats {
    margin-top: 5rem;
    display: flex;
    gap: 4rem;
    animation: fadeUp 0.8s 0.6s ease both;
  }
  .stat { text-align: center; }
  .stat-num {
    font-family: "Bebas Neue", sans-serif;
    font-size: 2.5rem;
    color: var(--accent);
    letter-spacing: 0.05em;
  }
  .stat-label {
    font-size: 0.75rem;
    color: var(--muted);
    letter-spacing: 0.1em;
    text-transform: uppercase;
  }

  @media (max-width: 900px) {
    .hero-stats { gap: 2rem; }
  }
</style>
';
require_once 'includes/header.php';
?>

<!-- ─── HERO ─── -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-grid"></div>

  <div class="hero-badge">Proyecto Web — TFG</div>

  <h1>HOSTING<br/><em>A MEDIDA</em></h1>
  <p class="hero-sub">
    Plataforma de hosting profesional con sistema de registro, planes por suscripción,
    pasarela de pago sandbox y panel de cliente. Construido con LAMP stack.
  </p>
  <div class="hero-actions">
    <a href="planes.php" class="btn-primary">Ver Planes ↓</a>
    <a href="misiones.php" class="btn-ghost">Estructura del Proyecto</a>
  </div>
  <div class="hero-stats">
    <div class="stat"><div class="stat-num">5</div><div class="stat-label">Misiones</div></div>
    <div class="stat"><div class="stat-num">15</div><div class="stat-label">Tareas</div></div>
    <div class="stat"><div class="stat-num">3</div><div class="stat-label">Planes</div></div>
    <div class="stat"><div class="stat-num">100%</div><div class="stat-label">LAMP</div></div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
