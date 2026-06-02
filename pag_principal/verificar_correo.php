<?php
/* ============================================================
   ARCHIVO: verificar_correo.php
   FUNCION: simular la confirmacion de correo despues del registro.
   SECCIONES: control del registro pendiente, validacion y vista.
   ============================================================ */

require_once 'sessions.php';
require_once 'conexiones.php';

$registro_id = (int)($_SESSION['registro_verificar_id'] ?? 0);
if ($registro_id < 1) {
    header('Location: auth.php');
    exit;
}

$error_codigo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verificar') {
    $codigo = trim($_POST['codigo'] ?? '');

    if ($codigo !== '123-123') {
        $error_codigo = 'El código no es correcto. Comprueba los seis dígitos.';
    } else {
        $db = getConexion();
        $stmt = $db->prepare('UPDATE usuarios SET email_verificado = 1 WHERE id = ?');
        $stmt->bind_param('i', $registro_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare(
            'SELECT id, nombre, email, password_hash, rol, plan_contratado, storage_qty, multiuser_qty, extras_json
             FROM usuarios WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $registro_id);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $db->close();

        if (!$usuario) {
            unset($_SESSION['registro_verificar_id'], $_SESSION['registro_redirect_plan']);
            header('Location: auth.php?error=registro_fallido');
            exit;
        }

        $redirect_plan = $_SESSION['registro_redirect_plan'] ?? '';
        unset($_SESSION['registro_verificar_id'], $_SESSION['registro_redirect_plan']);
        login_user_from_row($usuario);
        $redirect_url = $redirect_plan !== '' ? 'checkout.php?plan=' . urlencode($redirect_plan) : 'panel.php';
        header('Location: ' . $redirect_url);
        exit;
    }
}

$css_pagina = <<<'CSS'
body.page-verificar-correo .verification-page {
  min-height: calc(100vh - 180px);
  display: grid;
  place-items: center;
  padding: 7rem 1.5rem 4rem;
}
body.page-verificar-correo .verification-card {
  width: min(470px, 100%);
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 2.5rem;
  text-align: center;
}
body.page-verificar-correo .verification-card h1 { margin: 0.7rem 0 1rem; }
body.page-verificar-correo .verification-card p { color: var(--muted); margin-bottom: 1.3rem; }
body.page-verificar-correo .verification-demo {
  display: inline-block;
  color: var(--accent);
  background: rgba(200,169,110,0.08);
  border: 1px solid rgba(200,169,110,0.22);
  border-radius: 6px;
  padding: 0.7rem 1rem;
  margin-bottom: 1.4rem;
  font-family: monospace;
  font-size: 1.05rem;
}
body.page-verificar-correo .verification-form { display: grid; gap: 1rem; justify-items: center; }
body.page-verificar-correo .verification-form input {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 6px;
  color: var(--text);
  font-size: 1.35rem;
  letter-spacing: 0.22em;
  padding: 0.85rem 1rem;
  text-align: center;
}
body.page-verificar-correo .verification-form button { width: auto; margin-top: 10px; }
body.page-verificar-correo .verification-error {
  background: rgba(192,57,43,0.12);
  border: 1px solid rgba(192,57,43,0.3);
  color: #e7897e;
  border-radius: 6px;
  padding: 0.75rem;
  margin-bottom: 1rem;
  font-size: 0.85rem;
}
CSS;

require_once 'includes/header.php';
?>

<main class="verification-page">
  <section class="verification-card">
    <div class="section-tag centered">Verificación</div>
    <h1 class="section-title">Confirma tu <em>correo</em></h1>
    <p>Tu cuenta ha sido creada. Introduce el código de seis dígitos para continuar al panel.</p>
    <span class="verification-demo">Código de demostración: 123-123</span>

    <?php if ($error_codigo !== ''): ?>
      <div class="verification-error"><?php echo htmlspecialchars($error_codigo); ?></div>
    <?php endif; ?>

    <form method="POST" action="verificar_correo.php" class="verification-form">
      <input type="hidden" name="action" value="verificar">
      <input type="text" name="codigo" inputmode="numeric" maxlength="7" pattern="[0-9]{3}-[0-9]{3}" placeholder="000-000" aria-label="Código de verificación" required autofocus>
      <button type="submit" class="btn-primary btn-fullwidth">Verificar y acceder</button>
    </form>
  </section>
</main>

<?php require_once 'includes/footer.php'; ?>