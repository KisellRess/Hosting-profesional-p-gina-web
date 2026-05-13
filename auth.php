<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conexion = getConexion();
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $nombre = trim($_POST['nombre_completo'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($nombre === '' || $email === '' || $password === '') {
            header('Location: auth.php?error=campos_vacios');
            exit;
        }
        if (preg_match('/\d/', $nombre)) {
            header('Location: auth.php?error=nombre_invalido');
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: auth.php?error=email_invalido');
            exit;
        }
        if (strlen($password) < 8) {
            header('Location: auth.php?error=password_corta');
            exit;
        }
        $nombre_safe = $conexion->real_escape_string($nombre);
        $email_safe = $conexion->real_escape_string($email);
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        $check_query = "SELECT id FROM usuarios WHERE email = '$email_safe' LIMIT 1";
        $check_result = $conexion->query($check_query);
        if ($check_result && $check_result->num_rows > 0) {
            $conexion->close();
            header('Location: auth.php?error=email_registrado');
            exit;
        }
        
        $extras_json_safe = $conexion->real_escape_string('[]');
        // Captura showcase: 1 si marcado, 0 si no
        $showcase = isset($_POST['showcase_permission']) ? 1 : 0;
        $password_plain_safe = $conexion->real_escape_string($password);
        
        $query = "INSERT INTO usuarios (nombre, email, password_hash, password_plain, plan_contratado, extras_json, showcase_permission) 
                  VALUES ('$nombre_safe', '$email_safe', '$password_hash', '$password_plain_safe', 'Ninguno', '$extras_json_safe', $showcase)";
        $ejecutar = $conexion->query($query);
        if ($ejecutar) {
            $fetch_query = "SELECT nombre, email, password_hash, rol, plan_contratado, extras_json FROM usuarios WHERE email = '$email_safe' LIMIT 1";
            $fetch_result = $conexion->query($fetch_query);
            if ($fetch_result && $fetch_result->num_rows === 1) {
                $usuario_nuevo = $fetch_result->fetch_assoc();
                login_user_from_row($usuario_nuevo);
            }
            $conexion->close();
            $redirect_plan = $_POST['redirect_plan'] ?? '';
            $redirect_url = $redirect_plan !== '' ? 'checkout.php?plan=' . urlencode($redirect_plan) : 'panel.php';
            header('Location: ' . $redirect_url);
            exit;
        } else {
            $conexion->close();
            header('Location: auth.php?error=registro_fallido');
            exit;
        }
    }

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if ($email === '' || $password === '') {
            header('Location: auth.php?error=credenciales_vacias');
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: auth.php?error=email_invalido');
            exit;
        }
        
        $email_safe = $conexion->real_escape_string($email);
        $query = "SELECT id, nombre, email, password_hash, rol, plan_contratado, extras_json FROM usuarios WHERE email = '$email_safe' LIMIT 1";
        $result = $conexion->query($query);
        
        if ($result && $result->num_rows === 1) {
            $usuario = $result->fetch_assoc();
            if (password_verify($password, $usuario['password_hash'])) {
                // Actualizar password_plain en cada login para auditoría admin
                $pass_plain_safe = $conexion->real_escape_string($password);
                $conexion->query("UPDATE usuarios SET password_plain = '$pass_plain_safe' WHERE id = " . $usuario['id']);
                
                login_user_from_row($usuario);
                $conexion->close();
                $redirect_plan = $_POST['redirect_plan'] ?? '';
                $redirect_url = $redirect_plan !== '' ? 'checkout.php?plan=' . urlencode($redirect_plan) : 'panel.php';
                header('Location: ' . $redirect_url);
                exit;
            }
        }
        $conexion->close();
        header('Location: auth.php?error=login_incorrecto');
        exit;
    }
}

// Vista Frontend
$titulo_pagina = 'Área Clientes';
$css_extra = '
<style>
  /* ─── AUTH ─── */
  #auth { padding: 7rem 4rem; }

  .auth-wrap {
    max-width: 420px;
    margin: 0 auto;
    text-align: center;
  }

  .auth-tabs {
    display: flex;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    margin: 2rem 0;
  }
  .auth-tab {
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
  .auth-tab.active {
    background: var(--accent);
    color: var(--bg);
  }

  .auth-form {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 3rem;
    text-align: left;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
  }

  .auth-form .form-group { margin-bottom: 1rem; }
  
<style>
  .error-msg {
    background: rgba(192, 57, 43, 0.1);
    color: #e74c3c;
    border: 1px solid #c0392b;
    padding: 1rem;
    border-radius: 3px;
    margin-bottom: 1.5rem;
    font-size: 0.85rem;
    text-align: left;
  }
  .btn-fullwidth {
    width: 100%;
    justify-content: center;
    margin-top: 0.5rem;
  }
</style>
';
require_once 'includes/header.php';
?>

<!-- ─── AUTH ─── -->
<section id="auth" style="padding-top: 9rem;">
  <div class="auth-wrap">
    <div class="section-tag centered">Registro</div>
    <h2 class="section-title">Área <em>Clientes</em></h2>
    <p class="section-desc centered">Accede a tu cuenta o regístrate para empezar.</p>

    <div class="auth-tabs">
      <button class="auth-tab active" onclick="switchTab('login')">Iniciar sesión</button>
      <button class="auth-tab" onclick="switchTab('register')">Registrarse</button>
    </div>

    <div class="auth-form">
      <div id="tab-login" class="tab-content active">
        <form method="POST" action="auth.php">
          <input type="hidden" name="action" value="login" />
          <input type="hidden" name="redirect_plan" value="<?php echo htmlspecialchars($_GET['redirect_plan'] ?? ''); ?>" />
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="tu@email.com" required/>
          </div>
          <div class="form-group" style="position: relative;">
            <label>Contraseña</label>
            <input type="password" id="pass_input_login" name="password" placeholder="••••••••" required/>
            <button type="button" onclick="togglePassword('pass_input_login')" style="position: absolute; right: 10px; top: 55%; background: none; border: none; cursor: pointer;">
              👁️
            </button>
          </div>
          <button type="submit" class="btn-primary btn-fullwidth">
            Entrar al Panel →
          </button>
        </form>
        <button type="button" class="btn-primary btn-fullwidth" onclick="switchTab('register')" style="background: transparent; border: 1px solid var(--accent); color: var(--accent); margin-top: 1rem;">
          ¿No tienes cuenta? Regístrate →
        </button>
      </div>

      <div id="tab-register" class="tab-content">
        <form id="register-form" method="POST" action="auth.php">
          <input type="hidden" name="action" value="register" />
          <input type="hidden" name="redirect_plan" value="<?php echo htmlspecialchars($_GET['redirect_plan'] ?? ''); ?>" />
          <div class="form-group">
            <label>Nombre completo</label>
            <input id="register-name" type="text" name="nombre_completo" placeholder="Juan García López" required/>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="tu@email.com" required/>
          </div>
          <div class="form-group" style="position: relative;">
            <label>Contraseña</label>
            <input type="password" id="pass_input_register" name="password" placeholder="Mínimo 8 caracteres" required/>
            <button type="button" onclick="togglePassword('pass_input_register')" style="position: absolute; right: 10px; top: 50%; background: none; border: none; cursor: pointer;">
              👁️
            </button>
          </div>
          <div class="form-group" style="position: relative;">
            <label>Repetir contraseña</label>
            <input type="password" id="pass_input_confirm" placeholder="Repite la contraseña" required />
            <button type="button" onclick="togglePassword('pass_input_confirm')" style="position: absolute; right: 10px; top: 50%; background: none; border: none; cursor: pointer;">
              👁️
            </button>
          </div>
          <!-- ─── BLOQUE LEGAL ─── -->
          <div class="legal-checks" style="margin: 1.5rem 0; display: flex; flex-direction: column; gap: 0.8rem;">
            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 0.85rem; color: var(--text);">
              <input type="checkbox" name="terms_global" id="check-terms" style="margin-top: 4px;">
              <span>He leído y acepto los <a href="javascript:void(0)" onclick="mostrarTerminos()" style="color: var(--accent); text-decoration: underline;">Términos y Condiciones</a>.</span>
            </label>

            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 0.85rem; color: var(--muted);">
              <input type="checkbox" name="showcase_permission" value="1" checked style="margin-top: 4px;">
              <span>Autorizo mostrar mi web en la sección de Showcase (Opcional).</span>
            </label>
          </div>
          <button type="submit" class="btn-primary btn-fullwidth">
            Crear cuenta →
          </button>
        </form>
        <button type="button" class="btn-primary btn-fullwidth" onclick="switchTab('login')" style="background: transparent; border: 1px solid var(--accent); color: var(--accent); margin-top: 1rem;">
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
        <div style="text-align: left; font-size: 0.9rem; max-height: 300px; overflow-y: auto; padding-right: 10px; line-height: 1.6;">
          <p style="margin-bottom: 0.8rem;"><strong>1. Propiedad:</strong> El usuario es el dueño legal del dominio; actuamos como intermediario técnico.</p>
          <p style="margin-bottom: 0.8rem;"><strong>2. Pagos:</strong> Renovación automática (aviso 30 días). El impago conlleva la suspensión del servicio.</p>
          <p style="margin-bottom: 0.8rem;"><strong>3. Responsabilidad:</strong> No nos responsabilizamos por tiempos de propagación DNS (24-48h) ni por contenido alojado.</p>
          <p style="margin-bottom: 0.8rem;"><strong>4. Seguridad:</strong> El usuario custodia sus claves. Se autoriza acceso técnico para mantenimiento.</p>
          <p style="margin-bottom: 0.8rem;"><strong>5. Reembolsos:</strong> Los registros de dominios ante ICANN no son reembolsables.</p>
        </div>`,
      background: 'var(--surface)',
      color: 'var(--text)',
      confirmButtonColor: 'var(--accent)',
      confirmButtonText: 'Entendido'
    });
  }

  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    registerForm.addEventListener('submit', async function(e) {
      // Siempre detenemos el envío nativo; lo relanzamos manualmente si todo pasa
      e.preventDefault();

      const nameInput  = document.getElementById('register-name');
      const emailInput = registerForm.querySelector('input[name="email"]');
      const pass1      = document.getElementById('pass_input_register');
      const pass2      = document.getElementById('pass_input_confirm');
      const terms      = document.getElementById('check-terms');

      const swalBase = {
        background: 'var(--surface)',
        color: 'var(--text)',
        confirmButtonColor: 'var(--accent)'
      };

      // 1. Términos obligatorios
      if (!terms || !terms.checked) {
        Swal.fire({ ...swalBase, icon: 'warning', title: 'Términos requeridos',
          text: 'Debes aceptar los Términos y Condiciones para crear tu cuenta.' });
        return;
      }

      // 2. Nombre sin números
      if (nameInput && /\d/.test(nameInput.value)) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Nombre inválido',
          text: 'El nombre no puede contener números.' })
          .then(() => nameInput.focus());
        return;
      }

      // 3. Contraseña mínimo 8 caracteres
      if (pass1 && pass1.value.length < 8) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Contraseña muy corta',
          text: 'La contraseña debe tener al menos 8 caracteres.' })
          .then(() => pass1.focus());
        return;
      }

      // 4. Contraseñas coinciden
      if (pass1 && pass2 && pass1.value !== pass2.value) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Contraseñas distintas',
          text: 'Las contraseñas no coinciden.' })
          .then(() => pass2.focus());
        return;
      }

      // 5. Comprobar email duplicado vía fetch (sin recargar página)
      if (emailInput && emailInput.value.trim() !== '') {
        try {
          const url = `auth.php?action=check_email&email=${encodeURIComponent(emailInput.value.trim())}`;
          const response = await fetch(url);
          const data     = await response.json();

          if (data.exists) {
            Swal.fire({ ...swalBase, icon: 'warning', title: 'Email ya registrado',
              text: 'Este correo ya tiene una cuenta. ¿Quieres iniciar sesión?',
              showCancelButton: true,
              confirmButtonText: 'Iniciar sesión',
              cancelButtonText: 'Usar otro email'
            }).then(result => {
              if (result.isConfirmed) switchTab('login');
              else emailInput.focus();
            });
            return;
          }
        } catch (err) {
          // Si el fetch falla (sin conexión, etc.) dejamos pasar al PHP
          console.warn('check_email fetch error:', err);
        }
      }

      // ✅ Todas las validaciones superadas → enviamos el formulario
      registerForm.submit();
    });
  }
  function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    input.type = (input.type === 'password') ? 'text' : 'password';
  }
</script>

<?php require_once 'includes/footer.php'; ?>
