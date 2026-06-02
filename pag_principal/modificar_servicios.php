<?php
/* ============================================================
   ARCHIVO: modificar_servicios.php
   FUNCION: modificar extras de una suscripcion.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

require_once 'sessions.php';
require_auth();

$plan_actual  = $_SESSION['plan']   ?? 'Ninguno';
$extras_actual = $_SESSION['extras'] ?? [];

// Jerarquía de planes para saber si es upgrade o downgrade
$jerarquia = ['Ninguno' => 0, 'BÁSICO' => 1, 'PROFESIONAL' => 2, 'ENTERPRISE' => 3];
$nivel_actual = $jerarquia[$plan_actual] ?? 0;

/* ============================================================
   ESTILOS DE LA PAGINA: modificar_servicios.php
   Solo afectan a esta vista. Los estilos compartidos estan en estilos.css.
   ============================================================ */
$css_pagina = <<<'CSS'
/* ─── MODIFICAR SERVICIOS ─── */
  body.page-modificar-servicios #modificar { position: relative; overflow: hidden; }

  body.page-modificar-servicios .mod-wrap {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
  }

  body.page-modificar-servicios .mod-section-label {
    font-size: 0.7rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
  }
  body.page-modificar-servicios .mod-section-label::before {
    content: "";
    display: inline-block;
    width: 24px; height: 1px;
    background: var(--muted);
  }

  /* ─── GRIDS ─── */
  body.page-modificar-servicios .planes-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 4rem;
  }
  body.page-modificar-servicios .modulos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 4rem;
  }

  /* ─── TARJETAS ─── */
  body.page-modificar-servicios .plan-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 2.5rem 2rem;
    position: relative;
    transition: transform 0.3s, border-color 0.3s;
    display: flex;
    flex-direction: column;
  }
  body.page-modificar-servicios .plan-card:hover { transform: translateY(-4px); border-color: rgba(200,169,110,0.4); }
  body.page-modificar-servicios .plan-card.featured { background: var(--surface2); border-color: var(--accent); }
  body.page-modificar-servicios .plan-card.current { border-color: var(--accent); background: rgba(200,169,110,0.06); }

  body.page-modificar-servicios .plan-badge {
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
  body.page-modificar-servicios .badge-current { background: var(--accent); }
  body.page-modificar-servicios .badge-anual { background: var(--muted); }

  body.page-modificar-servicios .plan-name {
    font-family: "Bebas Neue", sans-serif;
    font-size: 1.8rem;
    letter-spacing: 0.08em;
    color: var(--accent);
    margin-bottom: 0.3rem;
  }
  body.page-modificar-servicios .plan-name.sm { font-size: 1.3rem; }

  body.page-modificar-servicios .plan-price {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
    margin-bottom: 0.5rem;
  }
  body.page-modificar-servicios .plan-price .amount { font-family: "Bebas Neue", sans-serif; font-size: 3.5rem; color: var(--text); line-height: 1; }
  body.page-modificar-servicios .plan-price .amount.sm { font-size: 2.8rem; }
  body.page-modificar-servicios .plan-price .currency { color: var(--accent); font-size: 1.3rem; }
  body.page-modificar-servicios .plan-price .period { color: var(--muted);  font-size: 0.85rem; }

  body.page-modificar-servicios .plan-desc {
    font-size: 0.82rem;
    color: var(--muted);
    margin-bottom: 1.5rem;
    line-height: 1.6;
  }

  body.page-modificar-servicios .plan-features {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    margin-bottom: 2rem;
    flex-grow: 1;
  }
  body.page-modificar-servicios .plan-features li { display: flex; align-items: center; gap: 0.7rem; font-size: 0.87rem; color: var(--text); }
  body.page-modificar-servicios .plan-features li .check {
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
  body.page-modificar-servicios .plan-btn {
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
  body.page-modificar-servicios .plan-btn:hover:not(:disabled) { background: var(--accent); color: var(--bg); }
  body.page-modificar-servicios .plan-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    border-color: var(--muted);
    color: var(--muted);
  }
  body.page-modificar-servicios .plan-btn.btn-actual {
    background: rgba(200,169,110,0.1);
    border-color: var(--accent);
    color: var(--accent);
    cursor: default;
  }
  body.page-modificar-servicios .plan-btn.btn-eliminar {
    border-color: #e74c3c;
    color: #e74c3c;
  }
  body.page-modificar-servicios .plan-btn.btn-eliminar:hover { background: rgba(231,76,60,0.15); }
  body.page-modificar-servicios .plan-btn.btn-activo-modulo {
    border-color: #27ae60;
    color: #27ae60;
    cursor: default;
    background: rgba(39,174,96,0.08);
  }

  body.page-modificar-servicios .plan-card.featured .plan-btn:not(:disabled):not(.btn-actual) {
    background: var(--accent);
    color: var(--bg);
  }

  /* ─── INFO BANNER ─── */
  body.page-modificar-servicios .mod-info-banner {
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
  body.page-modificar-servicios .mod-info-banner strong { color: var(--accent); }

  @media (max-width: 1000px) {body.page-modificar-servicios .planes-grid { grid-template-columns: 1fr; max-width: 420px; }}
  @media (max-width: 700px) {body.page-modificar-servicios .modulos-grid { grid-template-columns: 1fr; }}

body.page-modificar-servicios .inline-modificar-servicios-001 { padding-top: 9rem; flex-grow: 1; }
body.page-modificar-servicios .inline-modificar-servicios-002 { margin-top: 2rem; }
body.page-modificar-servicios .inline-modificar-servicios-003 { font-size: 1.5rem; }
body.page-modificar-servicios .inline-modificar-servicios-004 { margin-top: 1.5rem; }
body.page-modificar-servicios .inline-modificar-servicios-005 { margin-top: 0.5rem; }
body.page-modificar-servicios .inline-modificar-servicios-006 { border-color:#27ae60; color:#27ae60; }
body.page-modificar-servicios .inline-modificar-servicios-007 { color:#27ae60; }
body.page-modificar-servicios .inline-modificar-servicios-008 { font-size:0.85rem; color:var(--muted); }
body.page-modificar-servicios .inline-modificar-servicios-009 { text-align: center; padding-bottom: 3rem; }
body.page-modificar-servicios .inline-modificar-servicios-010 { text-decoration: none; }
CSS;

$titulo_pagina = 'Modificar Servicios';
require_once 'includes/header.php';
?>

<!-- ─── MODIFICAR SERVICIOS ─── -->
<section id="modificar" class="inline-modificar-servicios-001">
  <div class="mod-wrap">

    <div class="section-tag">Mi Cuenta</div>
    <h2 class="section-title">Gestionar <em>Servicios</em></h2>
    <p class="section-desc">Modifica tu plan o añade módulos adicionales a tu suscripción actual.</p>

    <!-- Banner estado actual -->
    <div class="mod-info-banner inline-modificar-servicios-002">
      <span class="inline-modificar-servicios-003">📋</span>
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
        <ul class="plan-features inline-modificar-servicios-004">
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
        <ul class="plan-features inline-modificar-servicios-004">
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
        <ul class="plan-features inline-modificar-servicios-004">
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
        <?php 
        $sql_php_nativo = (strtoupper($plan_actual) === 'PROFESIONAL' || strtoupper($plan_actual) === 'ENTERPRISE');
        if ($sql_php_nativo): ?>
          <button class="plan-btn btn-activo-modulo" disabled>✓ Incluido en tu Plan</button>
        <?php elseif ($tiene): ?>
          <button class="plan-btn btn-activo-modulo" disabled>✓ Módulo Activo</button>
          <form method="POST" action="procesar_cambio.php" class="inline-modificar-servicios-005">
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
        <?php 
        $domain_nativo = (strtoupper($plan_actual) === 'ENTERPRISE');
        if ($domain_nativo): ?>
          <button class="plan-btn btn-activo-modulo" disabled>✓ Incluido en tu Plan</button>
        <?php elseif ($tiene): ?>
          <button class="plan-btn btn-activo-modulo" disabled>✓ Módulo Activo</button>
          <form method="POST" action="procesar_cambio.php" class="inline-modificar-servicios-005">
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
            <li><span class="check inline-modificar-servicios-006">✓</span> <strong class="inline-modificar-servicios-007"><?php echo $storage_qty; ?> pack(s) activo(s)</strong></li>
          <?php endif; ?>
        </ul>
        <button class="plan-btn" onclick="window.location.href='checkout.php?plan=<?php echo urlencode($plan_actual); ?>&storage_qty=1'">
          <?php echo $tiene_storage ? 'Añadir otro pack' : 'Contratar Pack'; ?>
        </button>
        <?php if ($tiene_storage): ?>
          <form method="POST" action="procesar_cambio.php" class="inline-modificar-servicios-005">
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
            <li><span class="check inline-modificar-servicios-006">✓</span> <strong class="inline-modificar-servicios-007">Ilimitados (Incluidos)</strong></li>
          <?php elseif ($multiuser_qty > 0): ?>
            <li><span class="check inline-modificar-servicios-006">✓</span> <strong class="inline-modificar-servicios-007"><?php echo $multiuser_qty; ?> pack(s) activo(s)</strong></li>
          <?php endif; ?>
        </ul>
        
        <?php if ($plan_actual === 'ENTERPRISE'): ?>
          <button class="plan-btn btn-activo-modulo" disabled>✓ Incluido en tu Plan</button>
        <?php else: ?>
          <button class="plan-btn" onclick="window.location.href='checkout.php?plan=<?php echo urlencode($plan_actual); ?>&multiuser_qty=1'">
            <?php echo $multiuser_qty > 0 ? 'Añadir otro usuario' : 'Contratar Pack'; ?>
          </button>
          <?php if ($multiuser_qty > 0): ?>
            <form method="POST" action="procesar_cambio.php" class="inline-modificar-servicios-005">
              <input type="hidden" name="accion"  value="eliminar_modulo">
              <input type="hidden" name="modulo"  value="multiuser">
              <button type="button" class="plan-btn btn-eliminar" onclick="confirmarEliminar(this.form, 'Pack Multiusuario')">Eliminar 1 Usuario</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- Diseño Web -->
      <?php $tiene = in_array('Diseño Web', $extras_actual); ?>
      <div class="plan-card featured <?php echo $tiene ? 'current' : ''; ?>">
        <div class="plan-badge">Premium</div>
        <div class="plan-name sm">Diseño Web</div>
        <div class="plan-price">
          <span class="inline-modificar-servicios-008">Desde</span>
          <span class="amount sm">100,00</span>
          <span class="currency">€</span>
        </div>
        <p class="plan-desc">Sitio personalizado con modelos de lenguaje IA. Precio según complejidad.</p>
        <ul class="plan-features">
          <li><span class="check">✓</span> Diseño con IA a medida</li>
          <li><span class="check">✓</span> Presupuesto personalizado</li>
        </ul>
        <?php if ($tiene): ?>
          <button class="plan-btn" onclick="window.location.href='checkout.php?plan=<?php echo urlencode($plan_actual); ?>&ai=1'">Solicitar otro presupuesto</button>
        <?php else: ?>
          <button class="plan-btn" onclick="window.location.href='checkout.php?plan=<?php echo urlencode($plan_actual); ?>&ai=1'">Solicitar presupuesto</button>
        <?php endif; ?>
      </div>

    </div><!-- /modulos-grid -->

    <!-- Volver al panel -->
    <div class="inline-modificar-servicios-009">
      <a href="panel.php" class="btn-ghost inline-modificar-servicios-010">← Volver al Panel</a>
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

  // --- Módulos Nativos / Errores de Validación ---
  <?php if (isset($_GET['error']) && $_GET['error'] === 'modulo_nativo'): ?>
    Swal.fire({
      title: 'Acción no permitida',
      text: 'Este módulo está incluido de forma nativa en tu plan actual y no puede eliminarse individualmente.',
      icon: 'warning',
      confirmButtonColor: 'var(--accent)',
      background: 'var(--surface)',
      color: 'var(--text)'
    });
  <?php elseif (isset($_GET['error']) && $_GET['error'] === 'modulo_invalido'): ?>
    Swal.fire({
      title: 'Error',
      text: 'El módulo seleccionado no es válido.',
      icon: 'error',
      confirmButtonColor: 'var(--accent)',
      background: 'var(--surface)',
      color: 'var(--text)'
    });
  <?php elseif (isset($_GET['ok']) && $_GET['ok'] === 'modulo_eliminado'): ?>
    Swal.fire({
      title: 'Módulo Eliminado',
      text: 'El servicio opcional ha sido dado de baja correctamente.',
      icon: 'success',
      confirmButtonColor: 'var(--accent)',
      background: 'var(--surface)',
      color: 'var(--text)'
    });
  <?php endif; ?>

  async function eliminarModulo(form, nombre) {
    try {
      const respuesta = await fetch(form.action, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: new FormData(form)
      });
      const resultado = await respuesta.json();
      if (!respuesta.ok || !resultado.ok) {
        const mensajes = {
          modulo_nativo: 'Este modulo forma parte de tu plan y no puede eliminarse por separado.',
          modulo_invalido: 'El modulo seleccionado no es valido.',
          usuario_no_encontrado: 'No se ha encontrado tu cuenta.'
        };
        throw new Error(mensajes[resultado.code] || 'No se ha podido eliminar el modulo.');
      }

      await Swal.fire({
        title: 'Modulo eliminado',
        text: nombre + ' se ha dado de baja correctamente.',
        icon: 'success',
        confirmButtonColor: 'var(--accent)',
        background: 'var(--surface)',
        color: 'var(--text)'
      });
      window.location.href = 'modificar_servicios.php';
    } catch (error) {
      Swal.fire({
        title: 'No se pudo eliminar',
        text: error.message || 'Ha ocurrido un error al enviar la solicitud.',
        icon: 'error',
        confirmButtonColor: 'var(--accent)',
        background: 'var(--surface)',
        color: 'var(--text)'
      });
    }
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
      if (result.isConfirmed) eliminarModulo(form, nombre);
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