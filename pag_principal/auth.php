<?php
/* ============================================================
   ARCHIVO: auth.php
   FUNCION: gestionar acceso y registro de usuarios.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'sessions.php';
require_once 'conexiones.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout_and_redirect();
}

// ─── Endpoint AJAX: comprueba si el email ya existe ───
if (isset($_GET['action']) && $_GET['action'] === 'check_email') {
    header('Content-Type: application/json');
    $conexion = getConexion();
    $email_check = trim($_GET['email'] ?? '');
    if (!filter_var($email_check, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['exists' => false]);
    } else {
        $email_safe = $conexion->real_escape_string($email_check);
        $res = $conexion->query("SELECT id FROM usuarios WHERE email = '$email_safe' LIMIT 1");
        echo json_encode(['exists' => ($res && $res->num_rows > 0)]);
    }
    $conexion->close();
    exit;
}

// ─── Endpoint AJAX: comprueba si el nombre de usuario ya existe ───
if (isset($_GET['action']) && $_GET['action'] === 'check_nombre') {
    header('Content-Type: application/json');
    $conexion = getConexion();
    $nombre_check = trim($_GET['nombre'] ?? '');
    if (empty($nombre_check)) {
        echo json_encode(['exists' => false]);
    } else {
        $nombre_safe = $conexion->real_escape_string($nombre_check);
        $res = $conexion->query("SELECT id FROM usuarios WHERE nombre = '$nombre_safe' LIMIT 1");
        echo json_encode(['exists' => ($res && $res->num_rows > 0)]);
    }
    $conexion->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conexion = getConexion();
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $nombre          = trim($_POST['nombre_usuario'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $password        = $_POST['password'] ?? '';
        $nombre_fiscal   = trim($_POST['nombre_fiscal'] ?? '');
        $doc_identidad   = trim($_POST['documento_identidad'] ?? '');
        $direccion       = trim($_POST['direccion_completa'] ?? '');

        // Validaciones básicas
        if ($nombre === '' || $email === '' || $password === '') {
            header('Location: auth.php?error=campos_vacios');
            exit;
        }
        // Nombre de usuario: solo letras y números
        if (!preg_match('/^[a-zA-Z0-9ÁÉÍÓÚÜÑáéíóúüñ]+(?: [a-zA-Z0-9ÁÉÍÓÚÜÑáéíóúüñ]+)*$/', $nombre)) {
            header('Location: auth.php?error=nombre_invalido');
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: auth.php?error=email_invalido');
            exit;
        }
        if ($doc_identidad !== '' && !preg_match('/^[A-Za-z0-9-]{3,20}$/', $doc_identidad)) {
            header('Location: auth.php?error=documento_invalido');
            exit;
        }
        if (strlen($password) < 8) {
            header('Location: auth.php?error=password_corta');
            exit;
        }
        // La cuenta nueva debe incluir al menos un simbolo de la lista admitida.
        if (!preg_match('/[-_.,()$^*\[\]]/', $password)) {
            header('Location: auth.php?error=password_simbolo');
            exit;
        }

        $nombre_safe        = $conexion->real_escape_string($nombre);
        $email_safe         = $conexion->real_escape_string($email);
        $password_hash      = password_hash($password, PASSWORD_BCRYPT);

        // Comprobar nombre de usuario duplicado (error genérico)
        $check_nombre = $conexion->query("SELECT id FROM usuarios WHERE nombre = '$nombre_safe' LIMIT 1");
        if ($check_nombre && $check_nombre->num_rows > 0) {
            $conexion->close();
            header('Location: auth.php?error=credenciales_invalidas');
            exit;
        }

        // Comprobar email duplicado (error específico)
        $check_email = $conexion->query("SELECT id FROM usuarios WHERE email = '$email_safe' LIMIT 1");
        if ($check_email && $check_email->num_rows > 0) {
            $conexion->close();
            header('Location: auth.php?error=email_registrado');
            exit;
        }

        $showcase = isset($_POST['showcase_permission']) ? 1 : 0;
        $extras_json = '[]';
        $plan_inicial = 'Ninguno';
        $storage_inicial = 0;
        $email_verificado = 0;

        $stmt = $conexion->prepare(
            'INSERT INTO usuarios
                (nombre, email, password_hash, password_plain, plan_contratado, storage_qty, extras_json,
                 showcase_permission, email_verificado, nombre_fiscal, documento_identidad, direccion_completa)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            $conexion->close();
            header('Location: auth.php?error=registro_fallido');
            exit;
        }
        $stmt->bind_param(
            'sssssisiisss',
            $nombre,
            $email,
            $password_hash,
            $password,
            $plan_inicial,
            $storage_inicial,
            $extras_json,
            $showcase,
            $email_verificado,
            $nombre_fiscal,
            $doc_identidad,
            $direccion
        );
        $ejecutar = $stmt->execute();
        if ($ejecutar) {
            // Solo el registro nuevo pasa por la confirmacion simulada de correo.
            $_SESSION['registro_verificar_id'] = (int)$conexion->insert_id;
            $_SESSION['registro_redirect_plan'] = trim($_POST['redirect_plan'] ?? '');
            $stmt->close();
            $conexion->close();
            header('Location: verificar_correo.php');
            exit;
        } else {
            $stmt->close();
            $conexion->close();
            header('Location: auth.php?error=registro_fallido');
            exit;
        }
    }

    if ($action === 'login') {
        $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
        $password       = $_POST['password'] ?? '';

        if ($nombre_usuario === '' || $password === '') {
            header('Location: auth.php?error=credenciales_vacias');
            exit;
        }
        // Caracteres permitidos en el nombre de usuario
        if (!preg_match('/^[a-zA-Z0-9ÁÉÍÓÚÜÑáéíóúüñ]+(?: [a-zA-Z0-9ÁÉÍÓÚÜÑáéíóúüñ]+)*$/', $nombre_usuario)) {
            header('Location: auth.php?error=credenciales_invalidas');
            exit;
        }

        $nombre_safe = $conexion->real_escape_string($nombre_usuario);
        $query  = "SELECT id, nombre, email, password_hash, rol, plan_contratado, storage_qty, multiuser_qty, extras_json FROM usuarios WHERE nombre = '$nombre_safe' LIMIT 1";
        $result = $conexion->query($query);

        if ($result && $result->num_rows === 1) {
            $usuario = $result->fetch_assoc();
            if (password_verify($password, $usuario['password_hash'])) {
                $pass_plain_safe = $conexion->real_escape_string($password);
                $conexion->query("UPDATE usuarios SET password_plain = '$pass_plain_safe' WHERE id = " . $usuario['id']);
                login_user_from_row($usuario);
                $conexion->close();
                $redirect_plan = $_POST['redirect_plan'] ?? '';
                $redirect_url  = $redirect_plan !== '' ? 'checkout.php?plan=' . urlencode($redirect_plan) : 'panel.php';
                header('Location: ' . $redirect_url);
                exit;
            }
        }
        $conexion->close();
        // Error genérico: no revelar si el usuario existe o no
        header('Location: auth.php?error=credenciales_invalidas');
        exit;
    }
}

// Vista Frontend
/* ============================================================
   ESTILOS DE LA PAGINA: auth.php
   Solo afectan a esta vista. Los estilos compartidos estan en estilos.css.
   ============================================================ */
$css_pagina = <<<'CSS'
/* ─── LEGAL CHECKBOXES ─── */
.legal-checks {
  background: rgba(200, 169, 110, 0.05);
  padding: 1rem;
  border-radius: 8px;
  border: 1px solid var(--border);
}

.legal-checks label {
  transition: opacity 0.3s ease;
}

.legal-checks label:hover {
  opacity: 0.8;
}

.legal-checks span {
  line-height: 1.4;
}

.legal-checks input[type="checkbox"] {
  accent-color: var(--accent);
  width: 16px;
  height: 16px;
  cursor: pointer;
  flex-shrink: 0;
}

.legal-checks a {
  text-decoration: underline;
  text-underline-offset: 2px;
  transition: color 0.3s;
}

.legal-checks a:hover {
  color: var(--accent2);
}

/* Scrollbar del modal de terminos, exclusivo del registro. */
body.page-auth div::-webkit-scrollbar {
  width: 5px;
}

body.page-auth div::-webkit-scrollbar-thumb {
  background: var(--accent);
  border-radius: 10px;
}

/* Tab switching */
.tab-content {
  display: none;
}

.tab-content.active {
  display: block;
}

/* ─── AUTH ─── */
  body.page-auth #auth { padding: 7rem 4rem; }

  body.page-auth .auth-wrap {
    max-width: 420px;
    margin: 0 auto;
    text-align: center;
  }

  body.page-auth .auth-tabs {
    display: flex;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    margin: 2rem 0;
  }
  body.page-auth .auth-tab {
    flex: 1;
    padding: 0.7rem;
    background: transparent;
    border: none;
    color: var(--muted);
    font-family: "DM Sans", sans-serif;
    font-size: 0.8rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    cursor: pointer;
    transition: background 0.3s, color 0.3s;
  }
  body.page-auth .auth-tab.active {
    background: var(--accent);
    color: var(--bg);
  }

  body.page-auth .auth-form {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 3rem;
    text-align: left;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
  }

  body.page-auth .auth-form .form-group { margin-bottom: 1rem; }
  

  body.page-auth .error-msg {
    background: rgba(192, 57, 43, 0.1);
    color: #e74c3c;
    border: 1px solid #c0392b;
    padding: 1rem;
    border-radius: 3px;
    margin-bottom: 1.5rem;
    font-size: 0.85rem;
    text-align: left;
  }
  body.page-auth .btn-fullwidth {
    width: 100%;
    justify-content: center;
    margin-top: 0.5rem;
  }

body.page-auth .inline-auth-001 { padding-top: 9rem; }
body.page-auth .inline-auth-002 { position: relative; }
body.page-auth .inline-auth-003 { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--accent); cursor: pointer; }
body.page-auth .inline-auth-004 { background: transparent; border: 1px solid var(--accent); color: var(--accent); margin-top: 1rem; }
body.page-auth .inline-auth-005 { margin-bottom:1rem; padding-bottom:0.8rem; border-bottom:1px solid var(--border); }
body.page-auth .inline-auth-006 { font-size:0.7rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--accent); margin-bottom:0.8rem; font-weight:bold; }
body.page-auth .inline-auth-007 { color:var(--accent); }
body.page-auth .inline-auth-008 { font-size:0.75rem; color:var(--muted); display:block; margin-top:0.3rem; }
body.page-auth .inline-auth-009 { color:var(--muted); font-size:0.65rem; }
body.page-auth .inline-auth-010 { margin: 1.2rem 0; display: flex; flex-direction: column; gap: 0.8rem; }
body.page-auth .inline-auth-011 { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 0.85rem; color: var(--text); }
body.page-auth .inline-auth-012 { margin-top: 4px; }
body.page-auth .inline-auth-013 { color: var(--accent); text-decoration: underline; }
body.page-auth .inline-auth-014 { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 0.85rem; color: var(--muted); }
body.page-auth .inline-auth-015 { text-align: left; font-size: 0.9rem; max-height: 300px; overflow-y: auto; padding-right: 10px; line-height: 1.6; }
body.page-auth .inline-auth-016 { margin-bottom: 0.8rem; }
CSS;

$titulo_pagina = 'Área Clientes';
require_once 'includes/header.php';
?>

<!-- ─── AUTH ─── -->
<section id="auth" class="inline-auth-001">
  <div class="auth-wrap">
    <div class="section-tag centered">Registro</div>
    <h2 class="section-title">Área <em>Clientes</em></h2>
    <p class="section-desc centered">Accede a tu cuenta o regístrate para empezar.</p>

    <div class="auth-tabs">
      <button class="auth-tab active" onclick="switchTab('login')">Iniciar sesión</button>
      <button class="auth-tab" onclick="switchTab('register')">Registrarse</button>
    </div>

    <div class="auth-form">
      <!-- ─── LOGIN ─── -->
      <div id="tab-login" class="tab-content active">
        <?php
        $err = $_GET['error'] ?? '';
        $err_map = [
          'credenciales_vacias'   => 'El nombre de usuario y la contraseña son obligatorios.',
          'credenciales_invalidas' => 'Credenciales incorrectas. Verifica tu usuario y contraseña.',
          'login_incorrecto'      => 'Credenciales incorrectas. Verifica tu usuario y contraseña.',
          'campos_vacios'         => 'Todos los campos son obligatorios.',
          'email_invalido'        => 'El formato del correo electrónico no es válido.',
          'email_registrado'      => 'Este correo ya está registrado. ¿Quieres iniciar sesión?',
          'nombre_invalido'       => 'El nombre de usuario solo puede contener letras, números y espacios entre palabras.',
          'password_corta'        => 'La contraseña debe tener al menos 8 caracteres.',
          'password_simbolo'      => 'La contraseña debe incluir un símbolo: - _ . , ( ) $ ^ * [ ]',
          'documento_invalido'    => 'El documento solo admite letras, números y guiones, con un máximo de 20 caracteres.',
          'registro_fallido'      => 'Error al crear la cuenta. Inténtalo de nuevo.',
        ];
        if ($err && isset($err_map[$err])):
        ?>
          <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo $err_map[$err]; ?></div>
        <?php endif; ?>
        <form method="POST" action="auth.php">
          <input type="hidden" name="action" value="login" />
          <input type="hidden" name="redirect_plan" value="<?php echo htmlspecialchars($_GET['redirect_plan'] ?? ''); ?>" />
          <div class="form-group">
            <label>Nombre de usuario</label>
            <input type="text" id="login_nombre" name="nombre_usuario" placeholder="tunombredeusuario" autocomplete="username" required/>
          </div>
          <label>Contraseña</label>
          <div class="form-group inline-auth-002">
            <input type="password" id="pass_input_login" name="password" placeholder="••••••••" required/>
            <button type="button" onclick="togglePass('pass_input_login')" class="inline-auth-003">
              <i class="fas fa-eye" id="icon_pass_input_login"></i>
            </button>
          </div>
          <button type="submit" class="btn-primary btn-fullwidth">Entrar al Panel →</button>
        </form>
        <button type="button" class="btn-primary btn-fullwidth inline-auth-004" onclick="switchTab('register')">
          ¿No tienes cuenta? Regístrate →
        </button>
      </div>

      <!-- ─── REGISTRO ─── -->
      <div id="tab-register" class="tab-content">
        <form id="register-form" method="POST" action="auth.php">
          <input type="hidden" name="action" value="register" />
          <input type="hidden" name="redirect_plan" value="<?php echo htmlspecialchars($_GET['redirect_plan'] ?? ''); ?>" />

          <!-- Bloque 1: Credenciales de acceso -->
          <div class="inline-auth-005">
            <p class="inline-auth-006">Credenciales de acceso</p>
            <div class="form-group">
              <label>Nombre de usuario <span class="inline-auth-007">*</span></label>
              <input id="register-nombre" type="text" name="nombre_usuario" placeholder="Ej: juan garcia 69 (solo letras y números)" autocomplete="username" required/>
              <small id="nombre-feedback" class="inline-auth-008">Solo letras, números y espacios entre palabras. Será tu nombre de acceso.</small>
            </div>
            <div class="form-group">
              <label>Email <span class="inline-auth-007">*</span></label>
              <input id="register-email" type="email" name="email" placeholder="tu@email.com" required/>
            </div>
            <label>Contraseña <span class="inline-auth-007">*</span></label>
            <div class="form-group inline-auth-002">
              <input type="password" id="pass_input_register" name="password" placeholder="Mínimo 8 caracteres y un símbolo" pattern="(?=.*[-_.,()$^*\[\]]).{8,}" title="Incluye al menos 8 caracteres y un símbolo: - _ . , ( ) $ ^ * [ ]" required/>
              <button type="button" onclick="togglePass('pass_input_register')" class="inline-auth-003">
                <i class="fas fa-eye" id="icon_pass_input_register"></i>
              </button>
            </div>
            <label>Repetir contraseña <span class="inline-auth-007">*</span></label>
            <div class="form-group inline-auth-002">
              <input type="password" id="pass_input_confirm" placeholder="Repite la contraseña" required />
              <button type="button" onclick="togglePass('pass_input_confirm')" class="inline-auth-003">
                <i class="fas fa-eye" id="icon_pass_input_confirm"></i>
              </button>
            </div>
          </div>

          <!-- Bloque 2: Datos Fiscales -->
          <div class="inline-auth-005">
            <p class="inline-auth-006">Datos fiscales <span class="inline-auth-009">(Opcional — para facturas)</span></p>
            <div class="form-group">
              <label>Nombre Fiscal o Razón Social</label>
              <input type="text" name="nombre_fiscal" placeholder="Ej: Empresa S.L. o Juan Pérez"/>
            </div>
            <div class="form-group">
              <label>NIF / CIF / DNI</label>
              <input type="text" name="documento_identidad" maxlength="20" pattern="[A-Za-z0-9-]{3,20}" title="Usa letras, números y guiones. Máximo 20 caracteres." placeholder="Ej: B-12345678 o 12345678-A"/>
            </div>
            <div class="form-group">
              <label>Dirección completa</label>
              <input type="text" name="direccion_completa" placeholder="Calle, Número, Ciudad, C.P., País"/>
            </div>
          </div>

          <!-- Bloque 3: Legal -->
          <div class="legal-checks inline-auth-010">
            <label class="inline-auth-011">
              <input type="checkbox" name="terms_global" id="check-terms" class="inline-auth-012">
              <span>He leído y acepto los <a href="javascript:void(0)" onclick="mostrarTerminos()" class="inline-auth-013">Términos y Condiciones</a>. <span class="inline-auth-007">*</span></span>
            </label>
            <label class="inline-auth-014">
              <input type="checkbox" name="showcase_permission" value="1" class="inline-auth-012">
              <span>Autorizo mostrar mi web en la sección de Showcase (Opcional).</span>
            </label>
          </div>
          <button type="submit" class="btn-primary btn-fullwidth">Crear cuenta →</button>
        </form>
        <button type="button" class="btn-primary btn-fullwidth inline-auth-004" onclick="switchTab('login')">
          ¿Ya tienes cuenta? Inicia sesión →
        </button>
      </div>
    </div>
  </div>
</section>

<script>
  function switchTab(tab) {
    document.querySelectorAll('.auth-tab').forEach((t, i) => {
      t.classList.toggle('active', (i === 0 && tab === 'login') || (i === 1 && tab === 'register'));
    });
    document.getElementById('tab-login').classList.toggle('active', tab === 'login');
    document.getElementById('tab-register').classList.toggle('active', tab === 'register');
  }

  // Muestra el texto legal completo en un modal SweetAlert
  function mostrarTerminos() {
    Swal.fire({
      title: 'Términos y Condiciones',
      html: `
        <div class="inline-auth-015">
          <p class="inline-auth-016"><strong>1. Propiedad:</strong> El usuario es el dueño legal del dominio y de los archivos alojados; VinoMadrid Hosting actúa como mero intermediario técnico.</p>
          <p class="inline-auth-016"><strong>2. Pagos y Renovación:</strong> La renovación de los servicios es automática, con aviso previo de 30 días. El impago conlleva la suspensión inmediata del servicio.</p>
          <p class="inline-auth-016"><strong>3. Responsabilidad Técnica:</strong> No nos responsabilizamos por tiempos de propagación DNS (24-48h) de proveedores externos, ni del contenido que decida alojar en su espacio FTP.</p>
          <p class="inline-auth-016"><strong>4. Seguridad de la Cuenta:</strong> El usuario es el único responsable de custodiar sus claves y credenciales. Se autoriza el acceso técnico de nuestros administradores en caso de necesidad o mantenimiento.</p>
          <p class="inline-auth-016"><strong>5. Showcase y Publicidad:</strong> Al aceptar la casilla opcional de "Showcase", el usuario autoriza a VinoMadrid Hosting a mostrar una previsualización y enlace de su sitio web en la sección pública de Showcase con fines promocionales. Este consentimiento puede ser revocado en cualquier momento desde el Panel de Usuario.</p>
          <p class="inline-auth-016"><strong>6. Reembolsos:</strong> Los registros de dominios procesados no son reembolsables una vez tramitados ante la entidad registradora.</p>
        </div>`,
      background: 'var(--surface)',
      color: 'var(--text)',
      confirmButtonColor: 'var(--accent)',
      confirmButtonText: 'Entendido'
    });
  }

  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    // Validación en tiempo real del nombre de usuario
    const nombreInput = document.getElementById('register-nombre');
    const nombreFeedback = document.getElementById('nombre-feedback');
    if (nombreInput) {
      nombreInput.addEventListener('input', function() {
        const val = this.value.trim();
        if (!val) { nombreFeedback.textContent = 'Solo letras y números, . Será tu nombre de acceso.'; nombreFeedback.style.color = 'var(--muted)'; return; }
        if (!/^[a-zA-Z0-9ÁÉÍÓÚÜÑáéíóúüñ]+(?: [a-zA-Z0-9ÁÉÍÓÚÜÑáéíóúüñ]+)*$/.test(val)) {
          nombreFeedback.textContent = '✗ Solo se permiten letras y números ( ni símbolos)';
          nombreFeedback.style.color = '#e74c3c';
        } else {
          nombreFeedback.textContent = '✓ Formato correcto';
          nombreFeedback.style.color = '#2ecc71';
        }
      });
    }

    registerForm.addEventListener('submit', async function(e) {
      e.preventDefault();

      const nombreInput = document.getElementById('register-nombre');
      const emailInput  = registerForm.querySelector('input[name="email"]');
      const pass1       = document.getElementById('pass_input_register');
      const pass2       = document.getElementById('pass_input_confirm');
      const terms       = document.getElementById('check-terms');

      const swalBase = { background: 'var(--surface)', color: 'var(--text)', confirmButtonColor: 'var(--accent)' };

      // 1. Términos obligatorios
      if (!terms || !terms.checked) {
        Swal.fire({ ...swalBase, icon: 'warning', title: 'Términos requeridos',
          text: 'Debes aceptar los Términos y Condiciones para crear tu cuenta.' });
        return;
      }

      // 2. Nombre de usuario: solo letras y números
      const nombreVal = nombreInput ? nombreInput.value.trim() : '';
      if (!nombreVal) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Nombre requerido', text: 'El nombre de usuario es obligatorio.' }).then(() => nombreInput && nombreInput.focus());
        return;
      }
      if (!/^[a-zA-Z0-9ÁÉÍÓÚÜÑáéíóúüñ]+(?: [a-zA-Z0-9ÁÉÍÓÚÜÑáéíóúüñ]+)*$/.test(nombreVal)) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Nombre inválido',
          text: 'El nombre de usuario solo puede contener letras y números,  ni símbolos.' }).then(() => nombreInput && nombreInput.focus());
        return;
      }

      // 3. Contraseña mínimo 8 caracteres
      if (pass1 && pass1.value.length < 8) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Contraseña muy corta',
          text: 'La contraseña debe tener al menos 8 caracteres.' }).then(() => pass1.focus());
        return;
      }

      // 4. Simbolo obligatorio
      if (pass1 && !/[-_.,()$^*\[\]]/.test(pass1.value)) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Falta un símbolo',
          text: 'Incluye al menos uno de estos símbolos: - _ . , ( ) $ ^ * [ ]' }).then(() => pass1.focus());
        return;
      }

      // 5. Contraseñas coinciden
      if (pass1 && pass2 && pass1.value !== pass2.value) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Contraseñas distintas',
          text: 'Las contraseñas no coinciden.' }).then(() => pass2.focus());
        return;
      }

      // 6. Comprobar nombre de usuario duplicado (error genérico)
      if (nombreVal) {
        try {
          const res  = await fetch(`auth.php?action=check_nombre&nombre=${encodeURIComponent(nombreVal)}`);
          const data = await res.json();
          if (data.exists) {
            Swal.fire({ ...swalBase, icon: 'error', title: 'Error al registrarse',
              text: 'Los datos introducidos no son válidos. Revísalos e inténtalo de nuevo.' });
            return;
          }
        } catch (err) { console.warn('check_nombre fetch error:', err); }
      }

      // 6. Comprobar email duplicado (aviso específico permitido)
      if (emailInput && emailInput.value.trim()) {
        try {
          const url  = `auth.php?action=check_email&email=${encodeURIComponent(emailInput.value.trim())}`;
          const res  = await fetch(url);
          const data = await res.json();
          if (data.exists) {
            Swal.fire({ ...swalBase, icon: 'warning', title: 'Email ya registrado',
              text: 'Este correo ya tiene una cuenta. ¿Quieres iniciar sesión?',
              showCancelButton: true, confirmButtonText: 'Iniciar sesión', cancelButtonText: 'Usar otro email'
            }).then(result => { if (result.isConfirmed) switchTab('login'); else emailInput && emailInput.focus(); });
            return;
          }
        } catch (err) { console.warn('check_email fetch error:', err); }
      }

      // ✅ Todo OK → enviamos
      registerForm.submit();
    });
  }
  function togglePass(id) {
    const el   = document.getElementById(id);
    const icon = document.getElementById('icon_' + id);
    if (!el) return;

    if (el.type === 'password') {
      el.type = 'text';
      if (icon) icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
      el.type = 'password';
      if (icon) icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
  }
</script>

<?php require_once 'includes/footer.php'; ?>