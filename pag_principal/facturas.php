<?php
/* ============================================================
   ARCHIVO: facturas.php
   FUNCION: mostrar facturas y renovacion del cliente.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

require_once 'sessions.php';
require_auth();
require_once 'conexiones.php';

$user_id  = (int)($_SESSION['user_id'] ?? 0);
$plan     = $_SESSION['plan'] ?? 'Ninguno';
$sin_plan = ($plan === 'Ninguno' || $plan === '');

$db = getConexion();

// POST: activar / cancelar renovación automática
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_suscripcion'])) {
    $nuevo_estado = ($_POST['cambiar_suscripcion'] === 'activar') ? 1 : 0;
    $stmt_sub = $db->prepare("UPDATE usuarios SET renovacion_automatica = ? WHERE id = ?");
    $stmt_sub->bind_param("ii", $nuevo_estado, $user_id);
    $actualizado = $stmt_sub->execute();
    $stmt_sub->close();

    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($actualizado ? 200 : 422);
        echo json_encode(['ok' => $actualizado, 'activa' => (bool)$nuevo_estado]);
        $db->close();
        exit;
    }

    header("Location: facturas.php");
    exit;
}

// Datos del usuario
$res_u = $db->query("SELECT plan_contratado, extras_json, storage_qty, multiuser_qty, fecha_alta, renovacion_automatica FROM usuarios WHERE id = $user_id LIMIT 1");
$user_data = ($res_u && $res_u->num_rows > 0) ? $res_u->fetch_assoc() : null;
$renovacion_activa = $user_data ? (bool)$user_data['renovacion_automatica'] : true;
$fecha_alta_str    = $user_data['fecha_alta'] ?? null;

// Historial de facturas
$facturas = [];
$res_f = $db->query("SELECT * FROM facturas WHERE user_id = $user_id ORDER BY fecha_emision DESC, id DESC");
if ($res_f && $res_f->num_rows > 0) {
    while ($row = $res_f->fetch_assoc()) {
        $facturas[] = $row;
    }
}
$db->close();

// Próxima facturación
$proxima_facturacion = '—';
if ($user_data && $user_data['plan_contratado'] !== 'Ninguno' && $renovacion_activa) {
    $fecha_base = (!empty($fecha_alta_str) && $fecha_alta_str !== '0000-00-00') ? new DateTime($fecha_alta_str) : new DateTime();
    $hoy        = new DateTime();
    $dia_pago   = $fecha_base->format('d');
    $proxima    = new DateTime($hoy->format('Y-m-') . $dia_pago);
    if ($proxima <= $hoy) {
        $proxima->modify('+1 month');
    }
    $proxima_facturacion = $proxima->format('d/m/Y');
}

// ─── CÁLCULO DE CUOTA ACTUAL CORREGIDO (SUMA DIRECTA DE EXTRAS) ───
$precio_plan = [
    'BÁSICO'      => 7.50,
    'BASICO'      => 7.50,
    'PROFESIONAL' => 15.00,
    'ENTERPRISE'  => 25.00
];

$plan_upper  = strtoupper($plan);
$base        = $precio_plan[$plan_upper] ?? 0.00;
$storage_qty = $user_data ? intval($user_data['storage_qty']) : 0;
$multiuser_qty = $user_data ? intval($user_data['multiuser_qty']) : 0;

// Multiplicación directa sin restar mínimos del plan para que sume correctamente
$extras_total = ($storage_qty * 3.00) + ($multiuser_qty * 2.00);

// Sumar los módulos de extras_json
if ($user_data && !empty($user_data['extras_json'])) {
    $extras_arr = json_decode($user_data['extras_json'], true);
    if (is_array($extras_arr)) {
        foreach ($extras_arr as $extra) {
            $parts = explode('|', $extra);
            if (count($parts) === 2) {
                $m_name  = trim($parts[0]);
                $m_price = floatval($parts[1]);

                // Cortesías de módulos incluidos gratis según el plan
                if ($m_name === 'sql_php' && ($plan_upper === 'PROFESIONAL' || $plan_upper === 'ENTERPRISE')) continue;
                if ($m_name === 'domain'  && $plan_upper === 'ENTERPRISE') continue;
                if ($m_name === 'web_ai') continue;

                $extras_total += $m_price;
            }
        }
    }
}

$precio_actual = number_format($base + $extras_total, 2, ',', '.');

/* ============================================================
   ESTILOS DE LA PAGINA: facturas.php
   Solo afectan a esta vista. Los estilos compartidos estan en estilos.css.
   ============================================================ */
$css_pagina = <<<'CSS'
body.page-facturas { --surface: #0b0b0f; --surface2: #13131a; --border: #2a2a2a; --accent: #c8a96e; --muted: #7a7568; --text: #e8e4dc; --danger: #e74c3c; --success: #2ecc71; }
  body.page-facturas h1,
body.page-facturas h2,
body.page-facturas h3,
body.page-facturas .section-title { font-weight: 800 !important; text-transform: uppercase; letter-spacing: 1px; color: var(--accent) !important; }
  body.page-facturas #facturas-page { background: var(--surface); color: var(--text); min-height: 100vh; padding-top: 9rem; padding-bottom: 4rem; }
  body.page-facturas .fact-wrap { max-width: 1100px; margin: 0 auto; padding: 0 2rem; }
  body.page-facturas .fact-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin: 2.5rem 0; }
  body.page-facturas .fact-card { background: var(--surface2); border: 1px solid var(--border); border-radius: 4px; padding: 1.5rem; }
  body.page-facturas .fact-card-label { font-size: 0.7rem; letter-spacing: 0.2em; text-transform: uppercase; color: var(--accent); margin-bottom: 0.6rem; font-weight: bold; }
  body.page-facturas .fact-card-value { font-family: "Bebas Neue", sans-serif; font-size: 2rem; color: #fff; line-height: 1; }
  body.page-facturas .fact-table-wrap { background: var(--surface2); border: 1px solid var(--border); border-radius: 4px; overflow: hidden; margin-top: 2rem; }
  body.page-facturas .fact-table-header { background: rgba(200,169,110,0.06); border-bottom: 1px solid var(--border); padding: 1.2rem 1.5rem; display: flex; align-items: center; gap: 0.7rem; }
  body.page-facturas table { width: 100%; border-collapse: collapse; }
  body.page-facturas thead th { background: rgba(200,169,110,0.04); color: var(--accent); font-size: 0.72rem; text-transform: uppercase; padding: 1rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border); }
  body.page-facturas tbody tr { border-bottom: 1px solid var(--border); }
  body.page-facturas tbody td { padding: 1rem 1.5rem; font-size: 0.88rem; }
  body.page-facturas .badge { padding: 0.25rem 0.7rem; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }
  body.page-facturas .badge-ok { background: rgba(39,174,96,0.15); color: #2ecc71; border: 1px solid rgba(39,174,96,0.3); }
  body.page-facturas .btn-suscripcion { padding: 0.6rem 1.2rem; border-radius: 4px; font-weight: bold; border: none; cursor: pointer; transition: 0.3s; text-transform: uppercase; font-size: 0.75rem; }
  body.page-facturas .btn-cancel { background: rgba(231,76,60,0.2); color: #e74c3c; border: 1px solid #e74c3c; }
  body.page-facturas .btn-cancel:hover { background: #e74c3c; color: #fff; }
  body.page-facturas .btn-active { background: rgba(39,174,96,0.2); color: #2ecc71; border: 1px solid #2ecc71; }
  body.page-facturas .btn-active:hover { background: #2ecc71; color: #fff; }

body.page-facturas .fact-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}
body.page-facturas .fact-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.4rem;
  border-radius: 4px;
  text-decoration: none;
  font-size: 0.82rem;
  font-weight: 700;
  text-transform: uppercase;
}
body.page-facturas .fact-link-primary {
  background: linear-gradient(135deg, #c8a96e, #a68b55);
  color: #000;
}
body.page-facturas .fact-link-secondary {
  border: 1px solid var(--border);
  color: var(--accent);
  background: var(--surface2);
}
body.page-facturas .fact-no-plan {
  margin: 2rem 0;
  padding: 1.5rem 2rem;
  background: rgba(200, 169, 110, 0.05);
  border: 1px solid var(--accent);
  border-radius: 4px;
}
body.page-facturas .fact-no-plan-title {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 1.3rem;
  color: var(--accent);
}
body.page-facturas .fact-card-date { font-size: 1.5rem; }
body.page-facturas .fact-renewal {
  background: var(--surface2);
  border: 1px solid var(--border);
  padding: 1.5rem;
  border-radius: 4px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  gap: 1rem;
  flex-wrap: wrap;
}
body.page-facturas .fact-renewal-title { margin: 0; color: #fff; font-size: 1.05rem; }
body.page-facturas .fact-renewal-text { margin: 0.3rem 0 0; font-size: 0.85rem; color: var(--muted); }
body.page-facturas .text-danger { color: var(--danger); }
body.page-facturas .text-accent { color: var(--accent); }
body.page-facturas .fact-table-title { margin: 0; font-size: 0.85rem; letter-spacing: 0.15em; }
body.page-facturas .fact-empty { text-align: center; padding: 3rem; color: var(--muted); }
body.page-facturas .fact-code { color: var(--muted); font-family: monospace; }
body.page-facturas .fact-amount { color: var(--accent); font-weight: 700; font-family: monospace; }
body.page-facturas .fact-download { color: var(--accent); text-decoration: none; font-weight: bold; }
CSS;

$titulo_pagina = 'Historial de Facturación';
require_once 'includes/header.php';
?>

<section id="facturas-page">
  <div class="fact-wrap">
    <div class="fact-actions">
        <a href="panel.php" class="fact-link fact-link-primary"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
        <a href="perfil_facturacion.php" class="fact-link fact-link-secondary"><i class="fas fa-id-card"></i> Datos Fiscales</a>
    </div>

    <h2 class="section-title">Historial de <em>Pagos</em></h2>
    <p class="section-desc">Consulta tus recibos y gestiona la renovación automática.</p>

    <?php if ($sin_plan): ?>
    <div class="fact-no-plan">
      <div class="fact-no-plan-title">Sin plan activo contratado</div>
    </div>
    <?php else: ?>

    <div class="fact-cards">
      <div class="fact-card">
        <div class="fact-card-label">Plan Activo</div>
        <div class="fact-card-value"><?php echo htmlspecialchars($plan); ?></div>
      </div>
      <div class="fact-card">
        <div class="fact-card-label">Cuota Actual</div>
        <div class="fact-card-value"><?php echo $precio_actual; ?> €</div>
      </div>
      <div class="fact-card">
        <div class="fact-card-label">Próximo Cobro</div>
        <div class="fact-card-value fact-card-date"><?php echo $renovacion_activa ? $proxima_facturacion : 'Cancelado'; ?></div>
      </div>
    </div>

    <div class="fact-renewal">
        <div>
            <h4 class="fact-renewal-title">Suscripción y Renovación Automática</h4>
            <p class="fact-renewal-text">
                <?php echo $renovacion_activa ? 'Tu plan se renovará automáticamente cada mes.' : '<strong class="text-danger">Atención:</strong> Has desactivado la renovación automática.'; ?>
            </p>
        </div>
        <form method="POST" action="" id="form-renovacion">
            <?php if ($renovacion_activa): ?>
                <input type="hidden" name="cambiar_suscripcion" value="cancelar">
                <button type="submit" class="btn-suscripcion btn-cancel" onclick="return confirm('¿Seguro que deseas desactivar la renovación?');"><i class="fas fa-toggle-on"></i> Desactivar Renovación</button>
            <?php else: ?>
                <input type="hidden" name="cambiar_suscripcion" value="activar">
                <button type="submit" class="btn-suscripcion btn-active"><i class="fas fa-toggle-off"></i> Activar Renovación</button>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <div class="fact-table-wrap">
      <div class="fact-table-header">
        <i class="fas fa-file-invoice-dollar text-accent"></i>
        <h3 class="fact-table-title">Facturas Emitidas</h3>
      </div>

      <?php if (empty($facturas)): ?>
        <div class="fact-empty">No se registran facturas emitidas todavía.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr><th># Factura</th><th>Fecha Emisión</th><th>Concepto</th><th>Importe</th><th>Estado</th><th>Descargar</th></tr>
          </thead>
          <tbody>
            <?php foreach ($facturas as $f):
              $estado_val      = $f['estado'] ?? 'Pagado';
              $importe_factura = number_format((float)($f['importe'] ?? 0), 2, ',', '.');
            ?>
            <tr>
              <td class="fact-code">#<?php echo str_pad($f['id'], 5, '0', STR_PAD_LEFT); ?></td>
              <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($f['fecha_emision']))); ?></td>
              <td><?php echo htmlspecialchars($f['concepto']); ?></td>
              <td class="fact-amount"><?php echo $importe_factura; ?> €</td>
              <td><span class="badge badge-ok"><?php echo htmlspecialchars($estado_val); ?></span></td>
              <td><a class="fact-download" href="descargar_factura.php?id=<?php echo (int)$f['id']; ?>"><i class="fas fa-file-pdf"></i> PDF</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
  document.getElementById('form-renovacion')?.addEventListener('submit', async function (event) {
    event.preventDefault();
    try {
      const respuesta = await fetch('facturas.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: new FormData(this)
      });
      const resultado = await respuesta.json();
      if (!respuesta.ok || !resultado.ok) throw new Error();
      await Swal.fire({
        title: 'Renovacion actualizada',
        icon: 'success',
        confirmButtonColor: 'var(--accent)',
        background: 'var(--surface)',
        color: 'var(--text)'
      });
      window.location.reload();
    } catch (error) {
      Swal.fire({
        title: 'No se pudo actualizar',
        text: 'Intentalo de nuevo en unos instantes.',
        icon: 'error',
        confirmButtonColor: 'var(--accent)',
        background: 'var(--surface)',
        color: 'var(--text)'
      });
    }
  });
</script>

<?php require_once 'includes/footer.php'; ?>