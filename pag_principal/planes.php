<?php
/* ============================================================
   ARCHIVO: planes.php
   FUNCION: mostrar los planes y enlazar su contratacion.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

/* ============================================================
   ESTILOS DE LA PAGINA: planes.php
   Solo afectan a esta vista. Los estilos compartidos estan en estilos.css.
   ============================================================ */
$css_pagina = <<<'CSS'
/* ─── PLANES ─── */
  body.page-planes #planes { position: relative; overflow: hidden; }

  body.page-planes .planes-bg {
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 60% 50% at 50% 100%, rgba(200,169,110,0.05), transparent);
    pointer-events: none;
  }

  body.page-planes .planes-header {
    text-align: center;
    margin-bottom: 4rem;
  }

  body.page-planes .planes-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    max-width: 1100px;
    margin: 0 auto;
  }

  body.page-planes .plan-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 2.5rem 2rem;
    position: relative;
    transition: transform 0.3s, border-color 0.3s;
  }
  body.page-planes .plan-card:hover {
    transform: translateY(-6px);
    border-color: rgba(200,169,110,0.4);
  }

  body.page-planes .plan-card.featured {
    background: var(--surface2);
    border-color: var(--accent);
  }

  body.page-planes .plan-badge {
    position: absolute;
    top: -12px; left: 50%;
    transform: translateX(-50%);
    background: var(--accent);
    color: var(--bg);
    font-size: 0.65rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    font-weight: 700;
    padding: 0.25rem 0.8rem;
    border-radius: 100px;
    white-space: nowrap;
  }

  body.page-planes .plan-name {
    font-family: "Bebas Neue", sans-serif;
    font-size: 1.8rem;
    letter-spacing: 0.08em;
    color: var(--accent);
    margin-bottom: 0.3rem;
  }

  body.page-planes .plan-price {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
    margin-bottom: 0.5rem;
  }
  body.page-planes .plan-price .amount {
    font-family: "Bebas Neue", sans-serif;
    font-size: 3.5rem;
    color: var(--text);
    line-height: 1;
  }
  body.page-planes .plan-price .currency { color: var(--accent); font-size: 1.3rem; }
  body.page-planes .plan-price .period { color: var(--muted); font-size: 0.85rem; }

  body.page-planes .plan-desc {
    font-size: 0.82rem;
    color: var(--muted);
    margin-bottom: 2rem;
    line-height: 1.6;
  }

  body.page-planes .plan-features {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    margin-bottom: 2.5rem;
  }
  body.page-planes .plan-features li {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    font-size: 0.87rem;
    color: var(--text);
    border-radius: 4px;
    border: 0.001px solid var(--border);
    padding: 0.25rem 0.8rem;
    
  }
  body.page-planes .plan-features li .check {
    width: 50px; height: 20px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    color: var(--accent);
    font-size: 0.65rem;
  }

  body.page-planes .plan-btn {
    width: 100%;
    padding: 0.9rem;
    background: transparent;
    border: 1px solid var(--accent);
    color: var(--accent);
    font-family: "DM Sans", sans-serif;
    font-size: 0.85rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    cursor: pointer;
    border-radius: 2px;
    transition: background 0.3s, color 0.3s;
  }
  body.page-planes .plan-btn:hover,
body.page-planes .plan-card.featured .plan-btn {
    background: var(--accent);
    color: var(--bg);
  }

  @media (max-width: 900px) {body.page-planes .planes-grid { grid-template-columns: 1fr; max-width: 420px; }}

body.page-planes .inline-planes-001 { padding-top: 9rem; }
body.page-planes .inline-planes-002 { margin:0 auto; }
body.page-planes .inline-planes-003 { max-width:1100px; margin:0 auto 3rem; }
body.page-planes .inline-planes-004 { font-size:0.7rem; letter-spacing:0.2em; text-transform:uppercase; color:var(--muted); margin-bottom:1rem; display:flex; align-items:center; gap:0.6rem; }
body.page-planes .inline-planes-005 { display:inline-block; width:24px; height:1px; background:var(--muted); }
body.page-planes .inline-planes-006 { margin-top: 1.5rem; }
body.page-planes .inline-planes-007 { margin-top: 1rem; }
body.page-planes .inline-planes-008 { max-width:1100px; margin:0 auto; }
body.page-planes .inline-planes-009 { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
body.page-planes .inline-planes-010 { font-size:1.3rem; }
body.page-planes .inline-planes-011 { font-size:2.8rem; }
body.page-planes .inline-planes-012 { background:var(--muted); }
body.page-planes .inline-planes-013 { grid-column: span 1; }
body.page-planes .inline-planes-014 { align-items:baseline; gap:0.4rem; }
body.page-planes .inline-planes-015 { font-size:0.85rem; color:var(--muted); }
CSS;

$titulo_pagina = 'Tarifas y Servicios';
require_once 'includes/header.php';
?>

<!-- ─── PLANES ─── -->
<section id="planes" class="inline-planes-001">
  <div class="planes-bg"></div>
  <div class="planes-header">
    <div class="section-tag">Planes</div>
    <h2 class="section-title">Tarifas y <em>Servicios</em></h2>
    <p class="section-desc inline-planes-002">Un servicio base obligatorio y módulos opcionales para personalizar tu hosting a medida.</p>
  </div>

  <!-- SERVICIO BASE -->
  <div id="servicio-base" class="inline-planes-003">
    <div class="inline-planes-004">
      <span class="inline-planes-005"></span>
      1. Servicio Base (Elige tu Plan)
    </div>
    <div class="planes-grid">
      <!-- Plan Básico -->
      <div class="plan-card">
        <div class="plan-name">Plan Básico</div>
        <div class="plan-price">
          <span class="amount">7,50</span>
          <span class="currency">€</span>
          <span class="period">/mes</span>
        </div>
        <ul class="plan-features inline-planes-006">
          <li><span class="check">✓</span> 1 GB Almacenamiento SSD</li>
          <li><span class="check">✓</span> 1 Usuario FTP incluido</li>
          <li><span class="check">✓</span> 0 Bases de Datos (Opcional)</li>
          <li><span class="check">✓</span> Soporte Email</li>
          <li><span class="check">✓</span> Seguridad Cloudflare Básico</li>
        </ul>
        <button class="plan-btn inline-planes-007" onclick="window.location.href='checkout.php?plan=BÁSICO'">Contratar Básico</button>
      </div>

      <!-- Plan Profesional -->
      <div class="plan-card featured">
        <div class="plan-badge">Recomendado</div>
        <div class="plan-name">Plan Profesional</div>
        <div class="plan-price">
          <span class="amount">15,00</span>
          <span class="currency">€</span>
          <span class="period">/mes</span>
        </div>
        <ul class="plan-features inline-planes-006">
          <li><span class="check">✓</span> 3 GB Almacenamiento SSD</li>
          <li><span class="check">✓</span> 2 Usuarios FTP incluidos</li>
          <li><span class="check">✓</span> 1 Base de Datos MySQL</li>
          <li><span class="check">✓</span> Soporte Prioritario</li>
          <li><span class="check">✓</span> Seguridad Cloudflare + SSL</li>
        </ul>
        <button class="plan-btn inline-planes-007" onclick="window.location.href='checkout.php?plan=PROFESIONAL'">Contratar Profesional</button>
      </div>

      <!-- Plan Enterprise -->
      <div class="plan-card">
        <div class="plan-name">Plan Enterprise</div>
        <div class="plan-price">
          <span class="amount">25,00</span>
          <span class="currency">€</span>
          <span class="period">/mes</span>
        </div>
        <ul class="plan-features inline-planes-006">
          <li><span class="check">✓</span> 5 GB Almacenamiento SSD</li>
          <li><span class="check">✓</span> Usuarios FTP Ilimitados</li>
          <li><span class="check">✓</span> Bases de Datos Ilimitadas</li>
          <li><span class="check">✓</span> Dominio Gratis (1 año)</li>
          <li><span class="check">✓</span> Soporte 24/7 + Teléfono</li>
          <li><span class="check">✓</span> Seguridad Avanzada</li>
        </ul>
        <button class="plan-btn inline-planes-007" onclick="window.location.href='checkout.php?plan=ENTERPRISE'">Contratar Enterprise</button>
      </div>
    </div>
  </div>

  <!-- MÓDULOS OPCIONALES -->
  <div class="inline-planes-008">
    <div class="inline-planes-004">
      <span class="inline-planes-005"></span>
      2. Módulos y Extras (Opcionales)
    </div>
    <div class="planes-grid inline-planes-009">

      <!-- Pack Almacenamiento -->
      <div id="almacenamiento"  class="plan-card">
        <div class="plan-name inline-planes-010">Pack Almacenamiento</div>
        <div class="plan-price">
          <span class="amount inline-planes-011">3,00</span>
          <span class="currency">€</span>
          <span class="period">/mes</span>
        </div>
        <p class="plan-desc">Ampliación de +2 GB de cuota SSD (límite según disponibilidad).</p>
        <ul class="plan-features">
          <li><span class="check">✓</span> +2 GB SSD adicionales</li>
          <li><span class="check">✓</span> Ampliable por módulos</li>
        </ul>
        <button class="plan-btn" onclick="window.location.href='checkout.php?plan=BÁSICO&storage_qty=1'">Añadir módulo</button>
      </div>

      <!-- Acceso SQL/PHP -->
      <div id="sql-php" class="plan-card">
        <div class="plan-name inline-planes-010">Acceso SQL/PHP</div>
        <div class="plan-price">
          <span class="amount inline-planes-011">5,00</span>
          <span class="currency">€</span>
          <span class="period">/mes</span>
        </div>
        <p class="plan-desc">Creación de usuario de base de datos y entorno para scripts dinámicos.</p>
        <ul class="plan-features">
          <li><span class="check">✓</span> Usuario MySQL dedicado</li>
          <li><span class="check">✓</span> Entorno PHP activo</li>
        </ul>
        <button class="plan-btn" onclick="window.location.href='checkout.php?plan=BÁSICO&sql=1'">Añadir módulo</button>
      </div>

      <!-- Gestión de Dominio -->
      <div id="dominio" class="plan-card">
        <div class="plan-badge inline-planes-012">Anual</div>
        <div class="plan-name inline-planes-010">Dominio Personalizado</div>
        <div class="plan-price">
          <span class="amount inline-planes-011">15,00</span>
          <span class="currency">€</span>
          <span class="period">/año</span>
        </div>
        <p class="plan-desc">Registro anual del dominio (.com/.es) y configuración completa de DNS.</p>
        <ul class="plan-features">
          <li><span class="check">✓</span> Dominio propio .es / .com</li>
          <li><span class="check">✓</span> Renovación anual</li>
        </ul>
        <button class="plan-btn" onclick="window.location.href='checkout.php?plan=BÁSICO&dom=1'">Añadir módulo</button>
      </div>

      <!-- Pack Multiusuario -->
      <div id="multiusuario" class="plan-card">
        <div class="plan-name inline-planes-010">Pack Multiusuario</div>
        <div class="plan-price">
          <span class="amount inline-planes-011">2,00</span>
          <span class="currency">€</span>
          <span class="period">/usuario</span>
        </div>
        <p class="plan-desc">Creación de accesos independientes vía VSFTPD (por usuario).</p>
        <ul class="plan-features">
          <li><span class="check">✓</span> Usuario FTP independiente</li>
          <li><span class="check">✓</span> Acceso aislado vía VSFTPD</li>
        </ul>
        <button class="plan-btn" onclick="window.location.href='checkout.php?plan=BÁSICO&multiuser_qty=1'">Añadir módulo</button>
      </div>

      <!-- Diseño Web -->
      <div id="diseño-web-ia" class="plan-card featured inline-planes-013">
        <div class="plan-badge">Premium</div>
        <div class="plan-name inline-planes-010">Diseño Web</div>
        <div class="plan-price inline-planes-014">
          <span class="inline-planes-015">Desde</span>
          <span class="amount inline-planes-011">100,00</span>
          <span class="currency">€</span>
        </div>
        <p class="plan-desc">Desarrollo de sitio personalizado usando modelos de lenguaje. Precio según complejidad del proyecto. Pago único.</p>
        <ul class="plan-features">
          <li><span class="check">✓</span> Diseño a medida</li>
          <li><span class="check">✓</span> Presupuesto personalizado</li>
        </ul>
        <button class="plan-btn" onclick="window.location.href='checkout.php?plan=BÁSICO&ai=1'">Solicitar presupuesto</button>
      </div>
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

  document.querySelectorAll('.plan-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(24px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
  });
</script>

<?php require_once 'includes/footer.php'; ?>