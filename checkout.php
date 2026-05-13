<?php
require_once 'sessions.php';
require_auth();

// Incluye header y footer en todas las vistas principales
$titulo_pagina = 'Configurador de Servidor';
$css_extra = '
<style>
  /* ─── CHECKOUT ─── */
  #checkout { background: var(--bg); }
  .checkout-card {
    padding: 2rem;
    background: var(--surface);
    border-radius: 12px;
    border: 1px solid var(--border);
  }
  .modulo-label {
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
  .modulo-label:hover { border-color: var(--accent) !important; }
  .pack-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    margin-bottom: 0.5rem;
  }
  .pack-row input[type="number"] {
    width: 60px;
    padding: 0.5rem;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--surface);
    color: var(--text);
    text-align: center;
    outline: none;
  }
  .pack-row input[type="number"]:focus { border-color: var(--accent); }

  @media (max-width: 900px) {
    #checkout form { grid-template-columns: 1fr !important; gap: 2rem !important; }
  }
</style>
';
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
?>

<!-- ─── CHECKOUT ─── -->
<section id="checkout" style="padding-top: 9rem; min-height: 80vh;">
  <form id="checkoutForm" method="POST" action="procesar_pago.php" style="max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1.1fr; gap: 4rem; align-items: start;">
    
    <input type="hidden" id="total_calculado" name="total_calculado" value="0" />
    <input type="hidden" id="servicio_base_input" name="servicio_base" value="core|7.50" />

    <!-- Configurador del Servidor -->
    <div class="checkout-card">
      <h3 style="font-family: 'Bebas Neue', sans-serif; font-size: 2rem; color: var(--text); margin-bottom: 1.5rem;">Configurador de <em>Servidor</em></h3>
      
      <div style="margin-bottom: 1.5rem;">
        <label style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); display: block; margin-bottom: 0.5rem;">Plan Base</label>
        <select id="plan_select" name="plan_seleccionado" onchange="updateTotal()" style="width: 100%; padding: 1rem; background: var(--bg); border: 1px solid var(--accent); border-radius: 6px; color: var(--text); outline: none; font-weight: bold; font-size: 1.1rem; cursor: pointer;">
          <option value="BÁSICO" data-price="7.50" <?php echo $plan_seleccionado === 'BÁSICO' ? 'selected' : ''; ?>>Plan Básico - 7,50€/mes</option>
          <option value="PROFESIONAL" data-price="15.00" <?php echo $plan_seleccionado === 'PROFESIONAL' ? 'selected' : ''; ?>>Plan Profesional - 15,00€/mes</option>
          <option value="ENTERPRISE" data-price="25.00" <?php echo $plan_seleccionado === 'ENTERPRISE' ? 'selected' : ''; ?>>Plan Enterprise - 25,00€/mes</option>
        </select>
      </div>
      
      <div style="margin-bottom: 2rem;">
        <h4 style="font-family: 'Bebas Neue', sans-serif; font-size: 1.2rem; color: var(--text); margin-bottom: 1rem;">Packs Incrementales</h4>
        
        <div class="pack-row">
          <div>
            <div style="color: var(--text); font-weight: 500;">Almacenamiento Extra</div>
            <div style="color: var(--muted); font-size: 0.8rem;">+2GB por unidad (+3,00€)</div>
          </div>
          <div style="display: flex; align-items: center; gap: 1rem;">
            <span id="storage-cost" style="color: var(--accent); font-weight: bold;">0,00€</span>
            <input type="number" id="storage_qty" name="storage_qty" value="<?php echo $storage_qty_get; ?>" min="0" onchange="updateTotal()" onkeyup="updateTotal()" />
          </div>
        </div>

        <div class="pack-row">
          <div>
            <div style="color: var(--text); font-weight: 500;">Usuarios FTP Extra</div>
            <div style="color: var(--muted); font-size: 0.8rem;">+1 Usuario por unidad (+2,00€)</div>
          </div>
          <div style="display: flex; align-items: center; gap: 1rem;">
            <span id="multiuser-cost" style="color: var(--accent); font-weight: bold;">0,00€</span>
            <input type="number" id="multiuser_qty" name="multiuser_qty" value="<?php echo $multiuser_qty_get; ?>" min="0" onchange="updateTotal()" onkeyup="updateTotal()" />
          </div>
        </div>
      </div>

      <!-- Módulos Opcionales -->
      <div style="margin-bottom: 2rem;">
        <h4 style="font-family: 'Bebas Neue', sans-serif; font-size: 1.2rem; color: var(--text); margin-bottom: 1rem;">Servicios Opcionales</h4>
        
        <label class="modulo-label">
          <div style="display: flex; align-items: center; gap: 1rem;">
            <input type="checkbox" name="modulos[]" value="sql_php|5.00" <?php echo $check_sql; ?> onchange="updateTotal()" style="accent-color: var(--accent); width: 18px; height: 18px;">
            <div>
              <div style="color: var(--text); font-size: 0.95rem;">Acceso SQL/PHP</div>
              <div style="color: var(--muted); font-size: 0.75rem;">Recomendado para webs dinámicas</div>
            </div>
          </div>
          <span style="color: var(--accent); font-weight: bold;">+5,00€</span>
        </label>
        
        <label class="modulo-label">
          <div style="display: flex; align-items: center; gap: 1rem;">
            <input type="checkbox" name="modulos[]" value="domain|15.00" <?php echo $check_dom; ?> onchange="updateTotal()" style="accent-color: var(--accent); width: 18px; height: 18px;">
            <div>
              <div style="color: var(--text); font-size: 0.95rem;">Dominio Personalizado</div>
              <div style="color: var(--muted); font-size: 0.75rem;">Registro anual y configuración DNS</div>
            </div>
          </div>
          <span style="color: var(--accent); font-weight: bold;">+15,00€</span>
        </label>
        
        <label class="modulo-label">
          <div style="display: flex; align-items: center; gap: 1rem;">
            <input type="checkbox" name="modulos[]" value="web_ai|100.00" <?php echo $check_ai; ?> onchange="updateTotal()" style="accent-color: var(--accent); width: 18px; height: 18px;">
            <span style="color: var(--text); font-size: 0.95rem;">Diseño Web IA (Único)</span>
          </div>
          <span style="color: var(--accent); font-weight: bold;">+100,00€</span>
        </label>
      </div>

      <div style="display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 1.4rem; color: var(--text); border-top: 1px solid var(--border); padding-top: 1.5rem;">
        <span>Gran Total:</span>
        <span id="display-total">0,00€</span>
      </div>
    </div>

    <!-- Formulario de Pago -->
    <div>
      <h2 style="font-family: 'Bebas Neue', sans-serif; font-size: 2.5rem; color: var(--text); margin-bottom: 0.5rem;">Detalles de <em>Pago</em></h2>
      <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 2rem;">Completa tu compra de forma segura. (Entorno Sandbox)</p>

      <div style="display: flex; flex-direction: column; gap: 1.2rem;">
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
          <label style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted);">Nombre del Titular</label>
          <input type="text" name="nombre_titular" placeholder="Juan García López" required style="width: 100%; padding: 1rem; background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); outline: none;" onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'"/>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
          <label style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted);">Número de Tarjeta</label>
          <div style="position: relative;">
            <input type="text" name="numero_tarjeta" placeholder="0000 0000 0000 0000" maxlength="19" required style="width: 100%; padding: 1rem 1rem 1rem 3rem; background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); outline: none;" onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'"/>
            <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;">💳</span>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <label style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted);">Caducidad</label>
            <input type="text" name="caducidad" placeholder="MM/AA" maxlength="5" required style="width: 100%; padding: 1rem; background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); outline: none;" onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'"/>
          </div>
          <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <label style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted);">CVV</label>
            <input type="text" name="cvv" placeholder="123" maxlength="4" required style="width: 100%; padding: 1rem; background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); outline: none;" onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'"/>
          </div>
        </div>

        <button type="submit" id="btn-submit" style="margin-top: 1rem; width: 100%; padding: 1.2rem; background: var(--accent); color: var(--bg); border: none; border-radius: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; transition: background 0.3s; font-family: 'DM Sans', sans-serif;">
          Pagar
        </button>
        <div style="text-align: center; font-size: 0.75rem; color: var(--muted); margin-top: 0.5rem;">
          🔒 Pago seguro encriptado
        </div>
      </div>
    </div>

  </form>
</section>

<script>
  function formatMoney(amount) {
    return amount.toFixed(2).replace('.', ',') + '€';
  }

  function updateTotal() {
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

    // SUMA DE MÓDULOS (SQL, Dominio, IA)
    document.querySelectorAll('input[name="modulos[]"]:checked').forEach(cb => {
        const [id, priceStr] = cb.value.split('|');
        let price = parseFloat(priceStr);

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
    const planName = document.getElementById('plan_select').value;
    const total = document.getElementById('total_calculado').value;
    
    Swal.fire({
      title: 'Confirmar Pedido',
      html: `Estás a punto de contratar el <b>Plan ${planName}</b> y sus complementos.<br><br><span style="font-size:1.5rem; color:var(--accent); font-weight:bold;">Total: ${formatMoney(parseFloat(total))}</span>`,
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