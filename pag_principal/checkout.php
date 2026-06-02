<?php
/* ============================================================
   ARCHIVO: checkout.php
   FUNCION: recoger la configuracion elegida antes del pago.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

require_once 'sessions.php';
require_auth();
require_once 'conexiones.php';

$checkout_user_id = (int)($_SESSION['user_id'] ?? 0);
$modo_proyecto_web = isset($_GET['web_project']) && $_GET['web_project'] === '1';
$proyecto_web_checkout = null;

if ($modo_proyecto_web) {
    unset($_SESSION['checkout_web_project_guard']);

    $db_project = getConexion();
    $stmt_project = $db_project->prepare(
        "SELECT id, precio_final
         FROM proyectos_diseno_web
         WHERE user_id = ?
           AND estado = 'tramitando_propuesta'
           AND precio_final > 0
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt_project->bind_param('i', $checkout_user_id);
    $stmt_project->execute();
    $proyecto_web_checkout = $stmt_project->get_result()->fetch_assoc();
    $stmt_project->close();
    $db_project->close();

    if (!$proyecto_web_checkout) {
        header('Location: panel.php?error=presupuesto_web_no_disponible');
        exit;
    }

    $_SESSION['checkout_web_project_guard'] = [
        'user_id' => $checkout_user_id,
        'project_id' => (int)$proyecto_web_checkout['id'],
        'amount' => round((float)$proyecto_web_checkout['precio_final'], 2),
        'created_at' => time(),
    ];
}

/* Datos fiscales del cliente: se muestran y se pueden completar durante la compra. */
$datos_facturacion = ['nombre_fiscal' => '', 'documento_identidad' => '', 'direccion_completa' => ''];
$db_checkout = getConexion();
$stmt_checkout = $db_checkout->prepare(
    'SELECT nombre_fiscal, documento_identidad, direccion_completa FROM usuarios WHERE id = ? LIMIT 1'
);
$stmt_checkout->bind_param('i', $checkout_user_id);
$stmt_checkout->execute();
$fila_facturacion = $stmt_checkout->get_result()->fetch_assoc();
if ($fila_facturacion) {
    foreach ($datos_facturacion as $campo => $valor_por_defecto) {
        $valor_guardado = trim((string)($fila_facturacion[$campo] ?? ''));
        $datos_facturacion[$campo] = $valor_guardado !== '' ? $valor_guardado : $valor_por_defecto;
    }
}
$stmt_checkout->close();
$db_checkout->close();
$facturacion_completa = trim((string)$datos_facturacion['nombre_fiscal']) !== ''
    && trim((string)$datos_facturacion['documento_identidad']) !== ''
    && trim((string)$datos_facturacion['direccion_completa']) !== '';

// Incluye header y footer en todas las vistas principales
/* ============================================================
   ESTILOS DE LA PAGINA: checkout.php
   Solo afectan a esta vista. Los estilos compartidos estan en estilos.css.
   ============================================================ */
$css_pagina = <<<'CSS'
/* ─── CHECKOUT ─── */
  body.page-checkout #checkout { background: var(--bg); }
  body.page-checkout .checkout-card {
    padding: 2rem;
    background: var(--surface);
    border-radius: 12px;
    border: 1px solid var(--border);
  }
  body.page-checkout .modulo-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: border-color 0.3s;
  }
  body.page-checkout .modulo-label:hover { border-color: var(--accent) !important; }
  body.page-checkout .pack-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    margin-bottom: 0.5rem;
  }
  body.page-checkout .pack-row input[type="number"] {
    width: 60px;
    padding: 0.5rem;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--surface);
    color: var(--text);
    text-align: center;
    outline: none;
  }
  body.page-checkout .pack-row input[type="number"]:focus { border-color: var(--accent); }

  @media (max-width: 900px) {body.page-checkout #checkout form { grid-template-columns: 1fr !important; gap: 2rem !important; }}

body.page-checkout .inline-checkout-001 { padding-top: 9rem; min-height: 80vh; }
body.page-checkout .inline-checkout-002 { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1.1fr; gap: 4rem; align-items: start; }
body.page-checkout .inline-checkout-003 { font-family: 'Bebas Neue', sans-serif; font-size: 2rem; color: var(--text); margin-bottom: 1.5rem; }
body.page-checkout .inline-checkout-004 { margin-bottom: 1.5rem; }
body.page-checkout .inline-checkout-005 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); display: block; margin-bottom: 0.5rem; }
body.page-checkout .inline-checkout-006 { width: 100%; padding: 1rem; background: var(--bg); border: 1px solid var(--accent); border-radius: 6px; color: var(--text); outline: none; font-weight: bold; font-size: 1.1rem; cursor: pointer; }
body.page-checkout .inline-checkout-007 { margin-bottom: 2rem; }
body.page-checkout .inline-checkout-008 { font-family: 'Bebas Neue', sans-serif; font-size: 1.2rem; color: var(--text); margin-bottom: 1rem; }
body.page-checkout .inline-checkout-009 { color: var(--text); font-weight: 500; }
body.page-checkout .inline-checkout-010 { color: var(--muted); font-size: 0.8rem; }
body.page-checkout .inline-checkout-011 { display: flex; align-items: center; gap: 1rem; }
body.page-checkout .inline-checkout-012 { color: var(--accent); font-weight: bold; }
body.page-checkout .inline-checkout-013 { color: var(--text); font-size: 0.95rem; }
body.page-checkout .inline-checkout-014 { color: var(--muted); font-size: 0.75rem; }
body.page-checkout .inline-checkout-015 { display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 1.4rem; color: var(--text); border-top: 1px solid var(--border); padding-top: 1.5rem; }
body.page-checkout .inline-checkout-016 { font-family: 'Bebas Neue', sans-serif; font-size: 2.5rem; color: var(--text); margin-bottom: 0.5rem; }
body.page-checkout .inline-checkout-017 { color: var(--muted); font-size: 0.9rem; margin-bottom: 2rem; }
body.page-checkout .inline-checkout-018 { display: flex; flex-direction: column; gap: 1.2rem; }
body.page-checkout .inline-checkout-019 { display: flex; flex-direction: column; gap: 0.5rem; }
body.page-checkout .inline-checkout-020 { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); }
body.page-checkout .inline-checkout-021 { width: 100%; padding: 1rem; background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); outline: none; }
body.page-checkout .inline-checkout-022 { position: relative; }
body.page-checkout .inline-checkout-023 { width: 100%; padding: 1rem 1rem 1rem 3rem; background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); outline: none; }
body.page-checkout .inline-checkout-024 { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem; }
body.page-checkout .inline-checkout-025 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
body.page-checkout .inline-checkout-026 { margin-top: 1rem; width: 100%; padding: 1.2rem; background: var(--accent); color: var(--bg); border: none; border-radius: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; transition: background 0.3s; font-family: 'DM Sans', sans-serif; }
body.page-checkout .inline-checkout-027 { text-align: center; font-size: 0.75rem; color: var(--muted); margin-top: 0.5rem; }
body.page-checkout .inline-checkout-028 { font-size:1.5rem; color:var(--accent); font-weight:bold; }
body.page-checkout .billing-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 1.25rem;
  margin-bottom: 1.4rem;
}
body.page-checkout .billing-title {
  color: var(--accent);
  font-family: 'Bebas Neue', sans-serif;
  font-size: 1.35rem;
  letter-spacing: 0.06em;
  margin: 0 0 0.35rem;
}
body.page-checkout .billing-help {
  color: var(--muted);
  font-size: 0.78rem;
  line-height: 1.5;
  margin: 0 0 1rem;
}
body.page-checkout .billing-grid { display: grid; gap: 0.9rem; }
body.page-checkout .web-project-checkout {
  padding: 2rem;
  background: linear-gradient(135deg, rgba(200,169,110,0.1), rgba(255,255,255,0.02)), var(--surface);
  border: 1px solid rgba(200,169,110,0.35);
  border-radius: 12px;
}
body.page-checkout .web-project-price {
  display: block;
  margin-top: 1rem;
  color: var(--accent);
  font-size: 2.2rem;
  font-weight: 800;
}
body.page-checkout .checkout-error {
  background: rgba(192,57,43,0.12);
  border: 1px solid rgba(192,57,43,0.35);
  color: #e7897e;
  border-radius: 6px;
  padding: 0.85rem 1rem;
  margin-bottom: 1rem;
  font-size: 0.82rem;
}

body.page-checkout .checkout-checkbox {
  accent-color: var(--accent);
  width: 18px;
  height: 18px;
}
CSS;

$titulo_pagina = 'Configurador de Servidor';
require_once 'includes/header.php';

$plan_seleccionado = $_GET['plan'] ?? 'BÁSICO';
$planes_validos = ['BÁSICO', 'PROFESIONAL', 'ENTERPRISE'];
if (!in_array($plan_seleccionado, $planes_validos)) {
    $plan_seleccionado = 'BÁSICO';
}
// Capturar cantidades desde la URL para pre-rellenar los inputs
$storage_qty_get = max(0, (int) ($_GET['storage_qty'] ?? 0));
$multiuser_qty_get = max(0, (int) ($_GET['multiuser_qty'] ?? 0));
$check_sql = isset($_GET['sql']) ? 'checked' : '';
$check_dom = isset($_GET['dom']) ? 'checked' : '';
$check_ai  = isset($_GET['ai'])  ? 'checked' : '';
$errores_checkout = [
    'campos_vacios' => 'Completa los datos de pago para continuar.',
    'facturacion_incompleta' => 'Los datos de facturación son obligatorios antes de realizar una compra.',
    'documento_invalido' => 'El documento de identidad solo admite letras, números y guiones, con un máximo de 20 caracteres.',
    'tarjeta_invalida' => 'El número de tarjeta introducido no es válido.',
    'cvv_invalido' => 'El código CVV introducido no es válido.',
    'guard_invalido' => 'La sesión segura del presupuesto ha caducado. Vuelve a entrar desde tu panel.',
    'presupuesto_no_disponible' => 'El presupuesto ya no está pendiente de pago.',
];
$error_checkout = $_GET['error'] ?? '';
?>

<!-- ─── CHECKOUT ─── -->
<section id="checkout" class="inline-checkout-001">
  <form id="checkoutForm" method="POST" action="procesar_pago.php" class="inline-checkout-002" autocomplete="<?php echo $modo_proyecto_web ? 'off' : 'on'; ?>">
    
    <input type="hidden" id="total_calculado" name="total_calculado" value="0" />
    <input type="hidden" id="servicio_base_input" name="servicio_base" value="core|7.50" />
    <input type="hidden" name="checkout_mode" value="<?php echo $modo_proyecto_web ? 'web_project' : 'standard'; ?>" />
    <?php if ($modo_proyecto_web): ?>
      <input type="hidden" name="web_project_id" value="<?php echo (int)$proyecto_web_checkout['id']; ?>" />
    <?php endif; ?>

    <!-- Configurador del Servidor -->
    <?php if ($modo_proyecto_web): ?>
    <div class="web-project-checkout">
      <h3 class="inline-checkout-003">Pago de <em>Presupuesto Web</em></h3>
      <p class="inline-checkout-017">
        Para continuar debes reintroducir tus datos de facturación y pago. Este paso usa el mismo checkout seguro que la compra de planes y módulos.
      </p>
      <div class="inline-checkout-015">
        <span>Total proyecto:</span>
        <span class="web-project-price"><?php echo number_format((float)$proyecto_web_checkout['precio_final'], 2, ',', '.'); ?>€</span>
      </div>
    </div>
    <?php else: ?>
    <div class="checkout-card">
      <h3 class="inline-checkout-003">Configurador de <em>Servidor</em></h3>
      
      <div class="inline-checkout-004">
        <label class="inline-checkout-005">Plan Base</label>
        <select id="plan_select" name="plan_seleccionado" onchange="updateTotal()" class="inline-checkout-006">
          <option value="BÁSICO" data-price="7.50" <?php echo $plan_seleccionado === 'BÁSICO' ? 'selected' : ''; ?>>Plan Básico - 7,50€/mes</option>
          <option value="PROFESIONAL" data-price="15.00" <?php echo $plan_seleccionado === 'PROFESIONAL' ? 'selected' : ''; ?>>Plan Profesional - 15,00€/mes</option>
          <option value="ENTERPRISE" data-price="25.00" <?php echo $plan_seleccionado === 'ENTERPRISE' ? 'selected' : ''; ?>>Plan Enterprise - 25,00€/mes</option>
        </select>
      </div>
      
      <div class="inline-checkout-007">
        <h4 class="inline-checkout-008">Packs Incrementales</h4>
        
        <div class="pack-row">
          <div>
            <div class="inline-checkout-009">Almacenamiento Extra</div>
            <div class="inline-checkout-010">+2GB por unidad (+3,00€)</div>
          </div>
          <div class="inline-checkout-011">
            <span id="storage-cost" class="inline-checkout-012">0,00€</span>
            <input type="number" id="storage_qty" name="storage_qty" value="<?php echo $storage_qty_get; ?>" min="0" onchange="updateTotal()" onkeyup="updateTotal()" />
          </div>
        </div>

        <div class="pack-row">
          <div>
            <div class="inline-checkout-009">Usuarios FTP Extra</div>
            <div class="inline-checkout-010">+1 Usuario por unidad (+2,00€)</div>
          </div>
          <div class="inline-checkout-011">
            <span id="multiuser-cost" class="inline-checkout-012">0,00€</span>
            <input type="number" id="multiuser_qty" name="multiuser_qty" value="<?php echo $multiuser_qty_get; ?>" min="0" onchange="updateTotal()" onkeyup="updateTotal()" />
          </div>
        </div>
      </div>

      <!-- Módulos Opcionales -->
      <div class="inline-checkout-007">
        <h4 class="inline-checkout-008">Servicios Opcionales</h4>
        
        <label class="modulo-label">
          <div class="inline-checkout-011">
            <input type="checkbox" name="modulos[]" value="sql_php|5.00" <?php echo $check_sql; ?> onchange="updateTotal()" class="checkout-checkbox">
            <div>
              <div class="inline-checkout-013">Acceso SQL/PHP</div>
              <div class="inline-checkout-014">Recomendado para webs dinámicas</div>
            </div>
          </div>
          <span class="inline-checkout-012">+5,00€</span>
        </label>
        
        <label class="modulo-label">
          <div class="inline-checkout-011">
            <input type="checkbox" name="modulos[]" value="domain|15.00" <?php echo $check_dom; ?> onchange="updateTotal()" class="checkout-checkbox">
            <div>
              <div class="inline-checkout-013">Dominio Personalizado</div>
              <div class="inline-checkout-014">Registro anual y configuración DNS</div>
            </div>
          </div>
          <span class="inline-checkout-012">+15,00€</span>
        </label>
        
        <label class="modulo-label">
          <div class="inline-checkout-011">
            <input type="checkbox" name="modulos[]" value="web_ai|100.00" <?php echo $check_ai; ?> onchange="updateTotal()" class="checkout-checkbox">
            <div>
              <div class="inline-checkout-013">Diseño Web</div>
              <div class="inline-checkout-014">Precio desde 100,00€. Se factura tras cerrar presupuesto.</div>
            </div>
          </div>
          <span class="inline-checkout-012">Tramitando</span>
        </label>
      </div>

      <div class="inline-checkout-015">
        <span>Gran Total:</span>
        <span id="display-total">0,00€</span>
      </div>
    </div>
    <?php endif; ?>

    <!-- Formulario de Pago -->
    <div>
      <h2 class="inline-checkout-016">Detalles de <em>Pago</em></h2>
      <p class="inline-checkout-017">Completa tu compra de forma segura. (Entorno Sandbox)</p>

      <?php if (isset($errores_checkout[$error_checkout])): ?>
        <div class="checkout-error"><?php echo htmlspecialchars($errores_checkout[$error_checkout]); ?></div>
      <?php endif; ?>

      <!-- Facturacion obligatoria: queda vinculada a la cuenta antes de emitir la factura. -->
      <div class="billing-card">
        <h3 class="billing-title">Datos de facturación</h3>
        <p class="billing-help">
          <?php echo $facturacion_completa
            ? 'Revisa tus datos guardados. Puedes actualizarlos antes de confirmar el pago.'
            : 'Completa estos datos obligatorios para poder emitir tu factura y procesar la compra.'; ?>
          <?php if ($modo_proyecto_web): ?>
            Por seguridad, en el pago de presupuestos estos datos se precargan solo si existen en tu perfil y debes revisarlos antes de continuar.
          <?php endif; ?>
        </p>
        <div class="billing-grid">
          <div class="inline-checkout-019">
            <label class="inline-checkout-020">Nombre Fiscal o Razón Social</label>
            <input type="text" name="nombre_fiscal" maxlength="150" value="<?php echo htmlspecialchars($datos_facturacion['nombre_fiscal']); ?>" placeholder="Ej: Empresa S.L. o Juan Pérez" required class="inline-checkout-021"/>
          </div>
          <div class="inline-checkout-019">
            <label class="inline-checkout-020">Documento de Identidad (NIF/CIF/DNI)</label>
            <input type="text" name="documento_identidad" maxlength="20" pattern="[A-Za-z0-9-]{3,20}" title="Usa letras, números y guiones. Máximo 20 caracteres." value="<?php echo htmlspecialchars($datos_facturacion['documento_identidad']); ?>" placeholder="Ej: B-12345678" required class="inline-checkout-021"/>
          </div>
          <div class="inline-checkout-019">
            <label class="inline-checkout-020">Dirección Completa</label>
            <input type="text" name="direccion_completa" maxlength="255" value="<?php echo htmlspecialchars($datos_facturacion['direccion_completa']); ?>" placeholder="Calle, Número, Ciudad, C.P., País" required class="inline-checkout-021"/>
          </div>
        </div>
      </div>

      <div class="inline-checkout-018">
        <div class="inline-checkout-019">
          <label class="inline-checkout-020">Nombre del Titular</label>
          <input type="text" name="nombre_titular" placeholder="Juan García López" required onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'" class="inline-checkout-021"/>
        </div>

        <div class="inline-checkout-019">
          <label class="inline-checkout-020">Número de Tarjeta</label>
          <div class="inline-checkout-022">
            <input type="text" name="numero_tarjeta" placeholder="0000 0000 0000 0000" maxlength="19" required onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'" class="inline-checkout-023"/>
            <span class="inline-checkout-024">💳</span>
          </div>
        </div>

        <div class="inline-checkout-025">
          <div class="inline-checkout-019">
            <label class="inline-checkout-020">Caducidad</label>
            <input type="text" name="caducidad" placeholder="MM/AA" maxlength="5" required onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'" class="inline-checkout-021"/>
          </div>
          <div class="inline-checkout-019">
            <label class="inline-checkout-020">CVV</label>
            <input type="text" name="cvv" placeholder="123" maxlength="4" required onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'" class="inline-checkout-021"/>
          </div>
        </div>

        <button type="submit" id="btn-submit" class="inline-checkout-026">
          Pagar
        </button>
        <div class="inline-checkout-027">
          🔒 Pago seguro encriptado
        </div>
      </div>
    </div>

  </form>
</section>

<script>
  const checkoutMode = <?php echo json_encode($modo_proyecto_web ? 'web_project' : 'standard'); ?>;
  const webProjectAmount = <?php echo $modo_proyecto_web ? json_encode(round((float)$proyecto_web_checkout['precio_final'], 2)) : '0'; ?>;

  function formatMoney(amount) {
    return amount.toFixed(2).replace('.', ',') + '€';
  }

  function updateTotal() {
    if (checkoutMode === 'web_project') {
      document.getElementById('display-total')?.remove();
      document.getElementById('total_calculado').value = webProjectAmount.toFixed(2);
      document.getElementById('btn-submit').innerText = 'Pagar ' + formatMoney(webProjectAmount);
      return;
    }

    const planSelect = document.getElementById('plan_select');
    const selectedOption = planSelect.options[planSelect.selectedIndex];
    const planPrice = parseFloat(selectedOption.dataset.price);
    const planName = selectedOption.value;
    
    // Update hidden base service
    document.getElementById('servicio_base_input').value = `core|${planPrice.toFixed(2)}`;

    let total = planPrice;

    // Comprobar si el plan seleccionado es igual al plan actual del usuario
    const userPlan = <?php echo json_encode($_SESSION['plan'] ?? 'Ninguno'); ?>;
    
    // Si elige su plan actual, el coste del plan base para esta compra es 0 (solo paga los extras)
    if (userPlan !== 'Ninguno' && planName === userPlan) {
        total = 0;
    }

    // LÓGICA DE USUARIOS FTP
    const multiuserInput = document.getElementById('multiuser_qty');
    const multiuserLabel = document.getElementById('multiuser-cost');
    
    if (planName === 'ENTERPRISE') {
        multiuserInput.disabled = true;
        multiuserInput.value = 0;
        multiuserLabel.innerText = 'Incluidos (∞)'; // Gratis en Enterprise
    } else {
        multiuserInput.disabled = false;
        const mQty = parseInt(multiuserInput.value) || 0;
        const mPrice = mQty * 2.00;
        multiuserLabel.innerText = formatMoney(mPrice);
        total += mPrice;
    }

    // LÓGICA DE ALMACENAMIENTO (3€ por pack de +2GB)
    const sQty = parseInt(document.getElementById('storage_qty').value) || 0;
    const sPrice = sQty * 3.00;
    document.getElementById('storage-cost').innerText = formatMoney(sPrice);
    total += sPrice;

    // REFLEJO VISUAL DE MÓDULOS INCLUIDOS
    document.querySelectorAll('input[name="modulos[]"]').forEach(cb => {
        const [id, priceStr] = cb.value.split('|');
        const labelSpan = cb.closest('.modulo-label').lastElementChild;

        if (id === 'sql_php') {
            if (planName === 'PROFESIONAL' || planName === 'ENTERPRISE') {
                cb.checked = true;
                cb.disabled = true;
                labelSpan.innerText = 'Incluido';
                labelSpan.style.color = 'var(--muted)';
            } else {
                cb.disabled = false;
                labelSpan.innerText = '+5,00€';
                labelSpan.style.color = 'var(--accent)';
            }
        }
        
        if (id === 'domain') {
            if (planName === 'ENTERPRISE') {
                cb.checked = true;
                cb.disabled = true;
                labelSpan.innerText = 'Gratis (1er año)';
                labelSpan.style.color = 'var(--muted)';
            } else {
                cb.disabled = false;
                labelSpan.innerText = '+15,00€';
                labelSpan.style.color = 'var(--accent)';
            }
        }
    });

    // SUMA DE MODULOS (SQL, Dominio). Diseño Web se factura tras presupuesto final.
    document.querySelectorAll('input[name="modulos[]"]:checked').forEach(cb => {
        const [id, priceStr] = cb.value.split('|');
        let price = parseFloat(priceStr);

        if (id === 'web_ai') {
            price = 0;
        }

        // FIX: Si es SQL y el plan es Profesional o Enterprise, el coste es 0
        if (id === 'sql_php' && (planName === 'PROFESIONAL' || planName === 'ENTERPRISE')) {
            price = 0;
        }
        
        // FIX: Dominio incluido en Enterprise (primer año)
        if (id === 'domain' && planName === 'ENTERPRISE') {
            price = 0;
        }

        total += price;
    });

    // ACTUALIZACIÓN FINAL
    document.getElementById('display-total').innerText = formatMoney(total);
    document.getElementById('btn-submit').innerText = 'Pagar ' + formatMoney(total);
    
    // Actualizar input oculto para procesar_pago.php
    document.getElementById('total_calculado').value = total.toFixed(2);
    
    // Efectos visuales de selección
    document.querySelectorAll('.modulo-label').forEach(label => {
      const checkbox = label.querySelector('input');
      if (checkbox.checked) {
          label.style.borderColor = 'var(--accent)';
          label.style.background = 'rgba(200,169,110,0.05)';
      } else {
          label.style.borderColor = 'var(--border)';
          label.style.background = 'var(--bg)';
      }
    });
  }

  // Inicializar cálculo al cargar
  window.addEventListener('DOMContentLoaded', () => {
    updateTotal();
  });

  // SweetAlert interceptor
  document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const planName = checkoutMode === 'web_project'
      ? 'Presupuesto Web'
      : document.getElementById('plan_select').value;
    const total = document.getElementById('total_calculado').value;
    
    Swal.fire({
      title: checkoutMode === 'web_project' ? 'Confirmar Pago del Presupuesto' : 'Confirmar Pedido',
      html: checkoutMode === 'web_project'
        ? `Vas a pagar el <b>${planName}</b> acordado con administración.<br><br><span class="inline-checkout-028">Total: ${formatMoney(parseFloat(total))}</span>`
        : `Estás a punto de contratar el <b>Plan ${planName}</b> y sus complementos.<br><br><span class="inline-checkout-028">Total: ${formatMoney(parseFloat(total))}</span>`,
      icon: 'info',
      background: 'var(--surface)',
      color: 'var(--text)',
      showCancelButton: true,
      confirmButtonColor: 'var(--accent)',
      cancelButtonColor: 'var(--surface2)',
      confirmButtonText: 'Sí, procesar pago',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.submit();
      }
    });
  });
</script>

<?php require_once 'includes/footer.php'; ?>