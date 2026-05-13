<!-- ─── FOOTER ─── -->
<footer>
  <div class="footer-logo"><a href="index.php" style="color:inherit; text-decoration:none;">VinoMadrid <span>Hosting</span></a></div>
  <p class="footer-copy">© 2026 VinoMadrid Hosting — Proyecto Intermodular</p>
  <ul class="footer-links">
    <li><a href="misiones.php">Proyecto</a></li>
    <li><a href="planes.php">Planes</a></li>
    <li><a href="auth.php">Acceder</a></li>
  </ul>
</footer>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_GET['error'])): ?>
      const errorCodes = {
        'login_incorrecto': 'Email o contraseña incorrectos.',
        'campos_vacios': 'Por favor, completa todos los campos.',
        'credenciales_vacias': 'Por favor, introduce tu email y contraseña.',
        'nombre_invalido': 'El nombre no puede contener números.',
        'email_invalido': 'El formato del email no es válido.',
        'password_corta': 'La contraseña debe tener al menos 8 caracteres.',
        'email_registrado': 'Este email ya está registrado.',
        'registro_fallido': 'Ocurrió un error al registrar el usuario.',
        'pago_error': 'Hubo un error al procesar tu pago. Inténtalo de nuevo.'
      };
      const errorMsg = errorCodes['<?php echo htmlspecialchars($_GET['error']); ?>'] || 'Ha ocurrido un error inesperado.';
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: errorMsg,
        background: 'var(--surface)',
        color: 'var(--text)',
        confirmButtonColor: 'var(--accent)'
      });
    <?php endif; ?>

    <?php if (isset($_GET['msg'])): ?>
      const msgCodes = {
        'login_required': 'Debes iniciar sesión para continuar.',
        'pago_exitoso': 'Pago procesado correctamente. Tu servicio ha sido activado.',
        'logout_success': 'Has cerrado sesión correctamente.',
        'no_plan': 'No tienes ningún plan activo. Por favor, selecciona uno.'
      };
      const msg = msgCodes['<?php echo htmlspecialchars($_GET['msg']); ?>'] || 'Operación completada.';
      const msgIcon = ('<?php echo htmlspecialchars($_GET['msg']); ?>' === 'login_required' || '<?php echo htmlspecialchars($_GET['msg']); ?>' === 'no_plan') ? 'warning' : 'success';
      Swal.fire({
        icon: msgIcon,
        title: msgIcon === 'success' ? 'Éxito' : 'Aviso',
        text: msg,
        background: 'var(--surface)',
        color: 'var(--text)',
        confirmButtonColor: 'var(--accent)'
      });
    <?php endif; ?>
  });
</script>

</body>
</html>
