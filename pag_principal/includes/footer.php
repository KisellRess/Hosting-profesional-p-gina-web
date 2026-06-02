<!-- ============================================================
     ARCHIVO: includes\footer.php
     FUNCION: renderizar el pie comun de las vistas publicas.
     SECCIONES: estructura HTML y estilos exclusivos del componente o pagina.
     ============================================================ -->
<!-- ESTILOS DEL COMPONENTE: PIE DE PAGINA -->
<style>
/* ─── FOOTER ─── */
footer {
  border-top: 1px solid var(--border);
  padding: 1.2rem 4rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 2rem;
  margin-top: auto;
  background: var(--surface);
}

.footer-logo {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 1.8rem;
  letter-spacing: 0.08em;
  color: var(--accent);
  text-decoration: none;
}

.footer-logo span {
  color: var(--text);
}

.footer-copy {
  font-size: 0.78rem;
  color: var(--muted);
  line-height: 1.65;
  text-align: center;
}

.footer-credit {
  color: var(--accent);
  font-weight: 500;
}

.footer-links {
  display: flex;
  gap: 2rem;
  list-style: none;
}

.footer-links a {
  text-decoration: none;
  font-size: 0.78rem;
  color: var(--muted);
  transition: color 0.3s;
}

.footer-links a:hover {
  color: var(--accent);
}

/* Aviso de cookies compartido: permanece visible hasta guardar una decision. */
.cookie-banner {
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  background: var(--surface2);
  border-top: 1px solid var(--accent);
  padding: 1rem;
  z-index: 9999;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-sizing: border-box;
  box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.35);
}

.cookie-banner-content {
  width: min(100%, 1100px);
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.25rem;
}

.cookie-text {
  margin: 0;
  color: var(--text);
  font-size: 0.88rem;
  line-height: 1.55;
}

.cookie-actions {
  display: flex;
  flex-shrink: 0;
  gap: 0.75rem;
}

.cookie-btn {
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 0.65rem 1.1rem;
  font: inherit;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: color 0.2s, background 0.2s, border-color 0.2s;
}

.cookie-btn-accept {
  background: var(--accent);
  border-color: var(--accent);
  color: var(--bg);
}

.cookie-btn-reject {
  background: transparent;
  color: var(--text);
}

.cookie-btn-reject:hover {
  border-color: var(--accent);
  color: var(--accent);
}

@media (max-width: 900px) {
  footer {
    padding: 1rem 1.5rem;
    flex-direction: column;
    gap: 1.5rem;
    text-align: center;
  }

  .cookie-banner-content {
    flex-direction: column;
    align-items: stretch;
  }

  .cookie-actions,
  .cookie-btn {
    width: 100%;
  }

  .cookie-actions {
    flex-direction: column;
  }
}

.footer-logo a {
  color: inherit;
  text-decoration: none;
}
</style>

<!-- ─── FOOTER ─── -->
<footer>
  <div class="footer-logo"><a href="index.php">VinoMadrid <span>Hosting</span></a></div>
  <p class="footer-copy">&copy; 2026 VinoMadrid Hosting - Proyecto Intermodular<br><span class="footer-credit">Desarrollado por Adrian Martin, Mariano Franco y Elizabeth Rosell</span></p>
  <ul class="footer-links">
    <li><a href="inicio.php">Proyecto</a></li>
    <li><a href="planes.php">Planes</a></li>
    <li><a href="auth.php">Acceder</a></li>
  </ul>
  <!-- AVISO DE COOKIES: solo aparece hasta registrar aceptar o rechazar. -->
  <div id="cookie-banner" class="cookie-banner" role="dialog" aria-live="polite" aria-label="Preferencias de cookies">
    <div class="cookie-banner-content">
      <p class="cookie-text">
        Utilizamos cookies necesarias para recordar tus preferencias y mantener el funcionamiento de la plataforma.
      </p>
      <div class="cookie-actions">
        <button type="button" id="cookie-accept" class="cookie-btn cookie-btn-accept">Aceptar</button>
        <button type="button" id="cookie-reject" class="cookie-btn cookie-btn-reject">Rechazar</button>
      </div>
    </div>
  </div>
</footer>

<script>
    // Consentimiento: solo se almacena la preferencia elegida por el visitante.
    document.addEventListener('DOMContentLoaded', function() {
    const cookieBanner = document.getElementById('cookie-banner');
    const cookieAccept = document.getElementById('cookie-accept');
    const cookieReject = document.getElementById('cookie-reject');

    // 1. LEER COOKIE DESDE JAVASCRIPT
    // Comprobamos si la cookie 'cookies_decided' ya existe en el navegador
    const cookies = document.cookie.split('; ').find(row => row.startsWith('cookies_decided='));

    if (cookieBanner && cookieAccept && cookieReject) {
      if (!cookies) {
        cookieBanner.style.display = 'flex';
      } else {
        cookieBanner.style.display = 'none';
      }

      // 2. GUARDAR LA COOKIE CON SEGURIDAD DESDE JS (Opción rápida y limpia)
      function setSecureCookie(value) {
        const maxAge = 365 * 24 * 60 * 60; // 1 año de duración
        
        // Detectamos dinámicamente si estamos en HTTPS igual que hiciste en la sesión
        const isSecure = window.location.protocol === 'https:' ? 'Secure;' : '';
        
        // Creamos la cookie. Nota: Desde JS NO se puede poner HttpOnly, pero sí 'Secure' y 'SameSite'
        document.cookie = `cookies_decided=${value}; max-age=${maxAge}; path=/; ${isSecure} SameSite=Lax`;
        
        cookieBanner.style.display = 'none';
      }

      cookieAccept.addEventListener('click', function() {
        setSecureCookie('accepted');
      });

      cookieReject.addEventListener('click', function() {
        setSecureCookie('rejected');
      });
    }
      // ─── CONTROL DE ERRORES (SWEETALERT PHP) ───
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
    
    // ─── CONTROL DE MENSAJES (SWEETALERT PHP) ───
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