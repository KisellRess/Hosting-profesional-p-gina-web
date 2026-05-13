<?php
require_once 'sessions.php';
require_auth();

$plan_actual  = $_SESSION['plan']   ?? 'Ninguno';
$extras_actual = $_SESSION['extras'] ?? [];

// Jerarquía de planes para saber si es upgrade o downgrade
$jerarquia = ['Ninguno' => 0, 'BÁSICO' => 1, 'PROFESIONAL' => 2, 'ENTERPRISE' => 3];
$nivel_actual = $jerarquia[$plan_actual] ?? 0;

$titulo_pagina = 'Modificar Servicios';
$css_extra = '
<style>
  /* ─── MODIFICAR SERVICIOS ─── */
  #modificar { position: relative; overflow: hidden; }

  .mod-wrap {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
  }

  .mod-section-label {
    font-size: 0.7rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
  }
  .mod-section-label::before {
    content: "";
    display: inline-block;
    width: 24px; height: 1px;
    background: var(--muted);
  }

  /* ─── GRIDS ─── */
  .planes-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 4rem;
  }
  .modulos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 4rem;
  }

  /* ─── TARJETAS ─── */
  .plan-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 2.5rem 2rem;
    position: relative;
    transition: transform 0.3s, border-color 0.3s;
    display: flex;
    flex-direction: column;
  }
  .plan-card:hover { transform: translateY(-4px); border-color: rgba(200,169,110,0.4); }
  .plan-card.featured { background: var(--surface2); border-color: var(--accent); }
  .plan-card.current  { border-color: var(--accent); background: rgba(200,169,110,0.06); }

  .plan-badge {
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
  .badge-current { background: var(--accent); }
  .badge-anual   { background: var(--muted); }

  .plan-name {
    font-family: "Bebas Neue", sans-serif;
    font-size: 1.8rem;
    letter-spacing: 0.08em;
    color: var(--accent);
    margin-bottom: 0.3rem;
  }
  .plan-name.sm { font-size: 1.3rem; }

  .plan-price {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
    margin-bottom: 0.5rem;
  }
  .plan-price .amount { font-family: "Bebas Neue", sans-serif; font-size: 3.5rem; color: var(--text); line-height: 1; }
  .plan-price .amount.sm { font-size: 2.8rem; }
  .plan-price .currency { color: var(--accent); font-size: 1.3rem; }
  .plan-price .period   { color: var(--muted);  font-size: 0.85rem; }

  .plan-desc {
    font-size: 0.82rem;
    color: var(--muted);
    margin-bottom: 1.5rem;
    line-height: 1.6;
  }

  .plan-features {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    margin-bottom: 2rem;
    flex-grow: 1;
  }
  .plan-features li { display: flex; align-items: center; gap: 0.7rem; font-size: 0.87rem; color: var(--text); }
  .plan-features li .check {
    width: 18px; height: 18px;
    background: rgba(200,169,110,0.1);
    border: 1px solid var(--accent);
    border-radius: 2px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    color: var(--accent);
    font-size: 0.65rem;
  }

  /* ─── BOTONES ─── */
  .plan-btn {
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
    transition: background 0.3s, color 0.3s, opacity 0.3s;
    margin-top: auto;
  }
  .plan-btn:hover:not(:disabled) { background: var(--accent); color: var(--bg); }
  .plan-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    border-color: var(--muted);
    color: var(--muted);
  }
  .plan-btn.btn-actual {
    background: rgba(200,169,110,0.1);
    border-color: var(--accent);
    color: var(--accent);
    cursor: default;
  }
  .plan-btn.btn-eliminar {
    border-color: #e74c3c;
    color: #e74c3c;
  }
  .plan-btn.btn-eliminar:hover { background: rgba(231,76,60,0.15); }
  .plan-btn.btn-activo-modulo {
    border-color: #27ae60;
    color: #27ae60;
    cursor: default;
    background: rgba(39,174,96,0.08);
  }

  .plan-card.featured .plan-btn:not(:disabled):not(.btn-actual) {
    background: var(--accent);
    color: var(--bg);
  }

  /* ─── INFO BANNER ─── */
  .mod-info-banner {
    background: rgba(200,169,110,0.06);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1.2rem 1.5rem;
    margin-bottom: 3rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.88rem;
    color: var(--muted);
  }
  .mod-info-banner strong { color: var(--accent); }

  @media (max-width: 1000px) { .planes-grid { grid-template-columns: 1fr; max-width: 420px; } }
  @media (max-width: 700px)  { .modulos-grid { grid-template-columns: 1fr; } }
</style>
';
require_once 'includes/header.php';
?>

<!-- ─── MODIFICAR SERVICIOS ─── -->
<section id="modificar" style="padding-top: 9rem; flex-grow: 1;">
  <div class="mod-wrap">

    <div class="section-tag">Mi Cuenta</div>
    <h2 class="section-title">Gestionar <em>Servicios</em></h2>
    <p class="section-desc">Modifica tu plan o añade módulos adicionales a tu suscripción actual.</p>

    <!-- Banner estado actual -->
    <div class="mod-info-banner" style="margin-top: 2rem;">
      <span style="font-size: 1.5rem;">📋</span>
      <span>
        Tu plan actual es <strong><?php echo htmlspecialchars($plan_actual); ?></strong>.
        <?php if (!empty($extras_actual)): ?>
          Módulos activos: <strong><?php echo implode(', ', array_map('htmlspecialchars', $extras_actual)); ?></strong>.
        <?php else: ?>
          No tienes módulos adicionales contratados.
        <?php endif; ?>
      </span>
    </div>

    <!-- ══════════════════════════════════════ -->
    <!-- 1. PLANES BASE                         -->
    <!-- ══════════════════════════════════════ -->
    <div class="mod-section-label">1. Plan Base — Selecciona tu nivel</div>

    <div class="planes-grid">

      <!-- BÁSICO -->
      <?php $es_actual = ($plan_actual === 'BÁSICO'); $nivel = $jerarquia['BÁSICO']; ?>
      <div class="plan-card <?php echo $es_actual ? 'current' : ''; ?>">
        <?php if ($es_actual): ?><div class="plan-badge badge-current">Tu plan actual</div><?php endif; ?>
        <div class="plan-name">Plan Básico</div>
        <div class="plan-price">
          <span class="amount">7,50</span>
          <span class="currency">€</span>
          <span class="period">/mes</span>
        </div>
        <ul class="plan-features" style="margin-top: 1.5rem;">
          <li><span class="check">✓</span> 1 GB Almacenamiento SSD</li>
          <li><span class="check">✓</span> 1 Usuario FTP incluido</li>
          <li><span class="check">✓</span> 0 Bases de Datos</li>
          <li><span class="check">✓</span> Soporte Email</li>
          <li><span class="check">✓</span> Seguridad Cloudflare Básico</li>
        </ul>
        <?php if ($es_actual): ?>
          <button class="plan-btn btn-actual" disabled>Tu Plan Actual</button>
        <?php elseif ($nivel > $nivel_actual): ?>
          <button class="plan-btn" onclick="window.location.href='checkout.php?plan=BÁSICO&upgrade=1'">Mejorar a Básico →</button>
        <?php else: ?>
          <button class="plan-btn" onclick="solicitarDowngrade('Básico')">Cambiar a Básico</button>
        <?php endif; ?>
      </div>

      <!-- PROFESIONAL -->
      <?php $es_actual = ($plan_actual === 'PROFESIONAL'); $nivel = $jerarquia['PROFESIONAL']; ?>
      <div class="plan-card featured <?php echo $es_actual ? 'current' : ''; ?>">
        <?php if ($es_actual): ?>
          <div class="plan-badge badge-current">Tu plan actual</div>
        <?php else: ?>
          <div class="plan-badge">Recomendado</div>
        <?php endif; ?>
        <div class="plan-name">Plan Profesional</div>
        <div class="plan-price">
          <span class="amount">15,00</span>
          <span class="currency">€</span>
          <span class="period">/mes</span>
        </div>
        <ul class="plan-features" style="margin-top: 1.5rem;">
          <li><span class="check">✓</span> 3 GB Almacenamiento SSD</li>
          <li><span class="check">✓</span> 2 Usuarios FTP incluidos</li>
          <li><span class="check">✓</span> 1 Base de Datos MySQL</li>
          <li><span class="check">✓</span> Soporte Prioritario</li>
          <li><span class="check">✓</span> Seguridad Cloudflare + SSL</li>
        </ul>
        <?php if ($es_actual): ?>
          <button class="plan-btn btn-actual" disabled>Tu Plan Actual</button>
        <?php elseif ($nivel > $nivel_actual): ?>
          <button class="plan-btn" onclick="window.location.href='checkout.php?plan=PROFESIONAL&upgrade=1'">Mejorar a Profesional →</button>
        <?php else: ?>
          <button class="plan-btn" onclick="solicitarDowngrade('Profesional')">Cambiar a Profesional</button>
        <?php endif; ?>
      </div>

      <!-- ENTERPRISE -->
      <?php $es_actual = ($plan_actual === 'ENTERPRISE'); $nivel = $jerarquia['ENTERPRISE']; ?>
      <div class="plan-card <?php echo $es_actual ? 'current' : ''; ?>">
        <?php if ($es_actual): ?><div class="plan-badge badge-current">Tu plan actual</div><?php endif; ?>
        <div class="plan-name">Plan Enterprise</div>
        <div class="plan-price">
          <span class="amount">25,00</span>
          <span class="currency">€</span>
          <span class="period">/mes</span>
        </div>
        <ul class="plan-features" style="margin-top: 1.5rem;">
          <li><span class="check">✓</span> 5 GB Almacenamiento SSD</li>
          <li><span class="check">✓</span> Usuarios FTP Ilimitados</li>
          <li><span class="check">✓</span> Bases de Datos Ilimitadas</li>
          <li><span class="check">✓</span> Dominio Gratis (1 año)</li>
          <li><span class="check">✓</span> Soporte 24/7 + Teléfono</li>
          <li><span class="check">✓</span> Seguridad Avanzada</li>
        </ul>
        <?php if ($es_actual): ?>
          <button class="plan-btn btn-actual" disabled>Tu Plan Actual</button>
        <?php elseif ($nivel > $nivel_actual): ?>
          <button class="plan-btn" onclick="window.location.href='checkout.php?plan=ENTERPRISE&upgrade=1'">Mejorar a Enterprise →</button>
        <?php else: ?>
          <button class="plan-btn" onclick="solicitarDowngrade('Enterprise')">Cambiar a Enterprise</button>
        <?php endif; ?>
      </div>

    </div><!-- /planes-grid -->

    <!-- ══════════════════════════════════════ -->
    <!-- 2. MÓDULOS OPCIONALES                  -->
    <!-- ══════════════════════════════════════ -->
    <div class="mod-section-label">2. Módulos y Extras — Opcionales</div>

    <div class="modulos-grid">

      <!-- Acceso SQL/PHP -->
      <?php $tiene = in_array('Acceso SQL/PHP', $extras_actual); ?>
      <div class="plan-card <?php echo $tiene ? 'current' : ''; ?>">
        <div class="plan-name sm">Acceso SQL/PHP</div>
        <div class="plan-price">
          <span class="amount sm">5,00</span>
          <span class="currency">€</span>
          <span class="period">/mes</span>
        </div>
        <p class="plan-desc">Usuario de base de datos MySQL y entorno para scripts PHP dinámicos.</p>
        <ul class="plan-features">
          <li><span class="check">✓</span> Usuario MySQL dedicado</li>
          <li><span class="check">✓</span> Entorno PHP activo</li>
        </ul>
        <?php if ($tiene): ?>
          <button class="plan-btn btn-activo-modulo" disabled>✓ Módulo Activo</button>
          <form method="POST" action="procesar_cambio.php" style="margin-top: 0.5rem;">
            <input type="hidden" name="accion"  value="eliminar_modulo">
            <input type="hidden" name="modulo"  value="sql_php">
            <button type="button" class="plan-btn btn-eliminar" onclick="confirmarEliminar(this.form, 'Acceso SQL/PHP')">Eliminar Módulo</button>
          </form>
        <?php else: ?>
          <button class="plan-btn" onclick="window.location.href='checkout.php?plan=<?php echo urlencode($plan_actual); ?>&sql=1'">Contratar Módulo</button>
        <?php endif; ?>
      </div>

      <!-- Dominio Personalizado -->
      <?php $tiene = in_array('Gestión de Dominio', $extras_actual); ?>
      <div class="plan-card <?php echo $tiene ? 'current' : ''; ?>">
        <div class="plan-badge badge-anual">Anual</div>
        <div class="plan-name sm">Dominio Personalizado</div>
        <div class="plan-price">
          <span class="amount sm">15,00</span>
          <span class="currency">€</span>
          <span class="period">/año</span>
        </div>
        <p class="plan-desc">Registro anual del dominio (.com/.es) y configuración completa de DNS.</p>
        <ul class="plan-features">
          <li><span class="check">✓</span> Dominio propio .es / .com</li>
          <li><span class="check">✓</span> Renovación anual</li>
        </ul>
        <?php if ($tiene): ?>
          <button class="plan-btn btn-activo-modulo" disabled>✓ Módulo Activo</button>
          <form method="POST" action="procesar_cambio.php" style="margin-top: 0.5rem;">
            <input type="hidden" name="accion"  value="eliminar_modulo">
            <input type="hidden" name="modulo"  value="domain">
            <button type="button" class="plan-btn btn-eliminar" onclick="confirmarEliminar(this.form, 'Dominio Personalizado')">Eliminar Módulo</button>
          </form>
        <?php else: ?>
          <button class="plan-btn" onclick="window.location.href='checkout.php?plan=<?php echo urlencode($plan_actual); ?>&dom=1'">Contratar Módulo</button>
        <?php endif; ?>
      </div>

      <!-- Pack Almacenamiento -->
      <?php
        $storage_qty = $_SESSION['storage_qty'] ?? 0;
        $tiene_storage = $storage_qty > 0;
      ?>
      <div class="plan-card <?php echo $tiene_storage ? 'current' : ''; ?>">
        <div class="plan-name sm">Pack Almacenamiento</div>
        <div class="plan-price">
          <span class="amount sm">3,00</span>
          <span class="currency">€</span>
          <span class="period">/mes</span>
        </div>
        <p class="plan-desc">Ampliación de +2 GB de cuota SSD por pack contratado.</p>
        <ul class="plan-features">
          <li><span class="check">✓</span> +2 GB SSD adicionales</li>
          <li><span class="check">✓</span> Ampliable por módulos</li>
          <?php if ($tiene_storage): ?>
            <li><span class="check" style="border-color:#27ae60; color:#27ae60;">✓</span> <strong style="color:#27ae60;"><?php echo $storage_qty; ?> pack(s) activo(s)</strong></li>
          <?php endif; ?>
        </ul>
        <button class="plan-btn" onclick="window.location.href='checkout.php?plan=<?php echo urlencode($plan_actual); ?>&storage_qty=1'">
          <?php echo $tiene_storage ? 'Añadir otro pack' : 'Contratar Pack'; ?>
        </button>
        <?php if ($tiene_storage): ?>
          <form method="POST" action="procesar_cambio.php" style="margin-top: 0.5rem;">
            <input type="hidden" name="accion"  value="eliminar_modulo">
            <input type="hidden" name="modulo"  value="storage">
            <button type="button" class="plan-btn btn-eliminar" onclick="confirmarEliminar(this.form, 'Pack Almacenamiento')">Eliminar 1 Pack</button>
          </form>
        <?php endif; ?>
      </div>

      <!-- Pack Multiusuario -->
      <?php
        $multiuser_qty = $_SESSION['multiuser_qty'] ?? 0;
        $tiene_multiuser = $multiuser_qty > 0 || $plan_actual === 'ENTERPRISE';
      ?>
      <div class="plan-card <?php echo $tiene_multiuser ? 'current' : ''; ?>">
        <div class="plan-name sm">Pack Multiusuario</div>
        <div class="plan-price">
          <span class="amount sm">2,00</span>
          <span class="currency">€</span>
          <span class="period">/usuario</span>
        </div>
        <p class="plan-desc">Creación de accesos FTP independientes y aislados para tu equipo.</p>
        <ul class="plan-features">
          <li><span class="check">✓</span> Usuario FTP independiente</li>
          <li><span class="check">✓</span> Acceso aislado vía VSFTPD</li>
          <?php if ($plan_actual === 'ENTERPRISE'): ?>
            <li><span class="check" style="border-color:#27ae60; color:#27ae60;">✓</span> <strong style="color:#27ae60;">Ilimitados (Incluidos)</strong></li>
          <?php elseif ($multiuser_qty > 0): ?>
            <li><span class="check" style="border-color:#27ae60; color:#27ae60;">✓</span> <strong style="color:#27ae60;"><?php echo $multiuser_qty; ?> pack(s) activo(s)</strong></li>
          <?php endif; ?>
        </ul>
        
        <?php if ($plan_actual === 'ENTERPRISE'): ?>
          <button class="plan-btn btn-activo-modulo" disabled>✓ Incluido en tu Plan</button>
        <?php else: ?>
          <button class="plan-btn" onclick="window.location.href='checkout.php?plan=<?php echo urlencode($plan_actual); ?>&multiuser_qty=1'">
            <?php echo $multiuser_qty > 0 ? 'Añadir otro usuario' : 'Contratar Pack'; ?>
          </button>
          <?php if ($multiuser_qty > 0): ?>
            <form method="POST" action="procesar_cambio.php" style="margin-top: 0.5rem;">
              <input type="hidden" name="accion"  value="eliminar_modulo">
              <input type="hidden" name="modulo"  value="multiuser">
              <button type="button" class="plan-btn btn-eliminar" onclick="confirmarEliminar(this.form, 'Pack Multiusuario')">Eliminar 1 Usuario</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- Diseño Web IA -->
      <?php $tiene = in_array('Diseño Web IA', $extras_actual); ?>
      <div class="plan-card featured <?php echo $tiene ? 'current' : ''; ?>">
        <div class="plan-badge">Premium</div>
        <div class="plan-name sm">Diseño Web IA</div>
        <div class="plan-price">
          <span style="font-size:0.85rem; color:var(--muted);">Desde</span>
          <span class="amount sm">100,00</span>
          <span class="currency">€</span>
        </div>
        <p class="plan-desc">Sitio personalizado con modelos de lenguaje IA. Precio según complejidad.</p>
        <ul class="plan-features">
          <li><span class="check">✓</span> Diseño con IA a medida</li>
          <li><span class="check">✓</span> Presupuesto personalizado</li>
        </ul>
        <?php if ($tiene): ?>
          <button class="plan-btn btn-activo-modulo" disabled>✓ Módulo Activo</button>
        <?php else: ?>
          <button class="plan-btn" onclick="window.location.href='checkout.php?plan=<?php echo urlencode($plan_actual); ?>&ai=1'">Solicitar presupuesto</button>
        <?php endif; ?>
      </div>

    </div><!-- /modulos-grid -->

    <!-- Volver al panel -->
    <div style="text-align: center; padding-bottom: 3rem;">
      <a href="panel.php" class="btn-ghost" style="text-decoration: none;">← Volver al Panel</a>
    </div>

  </div><!-- /mod-wrap -->
</section>

<script>
  function solicitarDowngrade(plan) {
    Swal.fire({
      title: 'Cambio de Plan Solicitado',
      text: 'Tu solicitud de cambio al Plan ' + plan + ' ha sido registrada y se aplicará automáticamente al finalizar tu periodo de facturación actual.',
      icon: 'info',
      confirmButtonColor: 'var(--accent)',
      background: 'var(--surface)',
      color: 'var(--text)'
    });
  }

  function confirmarEliminar(form, nombre) {
    Swal.fire({
      title: '¿Eliminar módulo?',
      html: 'Vas a dar de baja <strong>' + nombre + '</strong>.<br>El cambio se aplicará en tu próxima facturación.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#e74c3c',
      cancelButtonColor: 'var(--surface2)',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      background: 'var(--surface)',
      color: 'var(--text)'
    }).then(result => {
      if (result.isConfirmed) form.submit();
    });
  }

  // Animación de entrada
  const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.style.opacity = '1';
        e.target.style.transform = 'translateY(0)';
      }
    });
  }, { threshold: 0.08 });

  document.querySelectorAll('.plan-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(24px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
  });
</script>

<?php require_once 'includes/footer.php'; ?>
