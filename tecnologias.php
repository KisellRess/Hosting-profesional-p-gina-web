<?php
$titulo_pagina = 'Stack Tecnológico';
$css_extra = '
<style>
  /* ─── TECH GALLERY ─── */
  #tecnologias { background: var(--surface); text-align: center; }

  .tech-logos {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 2.5rem;
    margin-top: 3rem;
  }

  .tech-logo {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 1.2rem 2rem;
    font-family: "Bebas Neue", sans-serif;
    font-size: 1.1rem;
    letter-spacing: 0.15em;
    color: var(--muted);
    transition: color 0.3s, border-color 0.3s, transform 0.3s;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
  }
  .tech-logo:hover {
    color: var(--accent);
    border-color: var(--accent);
    transform: translateY(-4px);
  }
  .tech-logo .t-icon { font-size: 1.8rem; }
</style>
';
require_once 'includes/header.php';
?>

<!-- ─── TECH GALLERY ─── -->
<section id="tecnologias" style="padding-top: 9rem; flex-grow: 1;">
  <div class="section-tag" style="justify-content:center;">Tecnologías</div>
  <h2 class="section-title">Stack <em>Tecnológico</em></h2>
  <p class="section-desc" style="margin:0.5rem auto 0;">Las tecnologías que hacen funcionar la plataforma.</p>

  <div class="tech-logos">
    <div class="tech-logo"><span class="t-icon">🐧</span>Linux</div>
    <div class="tech-logo"><span class="t-icon">🌐</span>Apache</div>
    <div class="tech-logo"><span class="t-icon">🐬</span>MySQL</div>
    <div class="tech-logo"><span class="t-icon">🐘</span>PHP</div>
    <div class="tech-logo"><span class="t-icon">☁️</span>Cloudflare</div>
    <div class="tech-logo"><span class="t-icon">🔒</span>SSL / TLS</div>
    <div class="tech-logo"><span class="t-icon">📁</span>FTP / SFTP</div>
    <div class="tech-logo"><span class="t-icon">🎨</span>Bootstrap</div>
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

  document.querySelectorAll('.tech-logo').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(24px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
  });
</script>

<?php require_once 'includes/footer.php'; ?>
