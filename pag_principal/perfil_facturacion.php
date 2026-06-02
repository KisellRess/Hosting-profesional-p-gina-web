<?php
/* ============================================================
   ARCHIVO: perfil_facturacion.php
   FUNCION: actualizar datos fiscales del cliente.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

require_once 'sessions.php';
require_auth();
require_once 'conexiones.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$db = getConexion();
$mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_fiscal = trim($_POST['nombre_fiscal'] ?? '');
    $doc_identidad = trim($_POST['documento_identidad'] ?? '');
    $direccion = trim($_POST['direccion_completa'] ?? '');

    $datos_validos = $nombre_fiscal !== '' && $direccion !== ''
        && mb_strlen($nombre_fiscal) <= 150 && mb_strlen($direccion) <= 255
        && preg_match('/^[A-Za-z0-9-]{3,20}$/', $doc_identidad);

    if (!$datos_validos) {
        $guardado = false;
        $mensaje = '<div class="perfil-feedback error">Revisa los datos: el documento solo admite letras, números y guiones (máximo 20 caracteres).</div>';
    } else {
        $stmt = $db->prepare("UPDATE usuarios SET nombre_fiscal = ?, documento_identidad = ?, direccion_completa = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nombre_fiscal, $doc_identidad, $direccion, $user_id);
        $guardado = $stmt->execute();
        $stmt->close();
        $mensaje = $guardado
            ? '<div class="perfil-feedback success">Datos fiscales actualizados correctamente.</div>'
            : '<div class="perfil-feedback error">Error al actualizar los datos.</div>';
    }

    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($guardado ? 200 : 422);
        echo json_encode(['ok' => $guardado], JSON_UNESCAPED_UNICODE);
        $db->close();
        exit;
    }
}

// Obtener datos actuales
$res = $db->query("SELECT nombre_fiscal, documento_identidad, direccion_completa FROM usuarios WHERE id = $user_id LIMIT 1");
$datos = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : [];
$db->close();

/* ============================================================
   ESTILOS DE LA PAGINA: perfil_facturacion.php
   Solo afectan a esta vista. Los estilos compartidos estan en estilos.css.
   ============================================================ */
$css_pagina = <<<'CSS'
body.page-perfil-facturacion {
    --surface: #0b0b0f;
    --surface2: #13131a;
    --border: #2a2a2a;
    --accent: #c8a96e;
    --text: #e8e4dc;
  }
  body.page-perfil-facturacion .perfil-wrap {
    max-width: 800px;
    margin: 8rem auto 4rem;
    padding: 0 2rem;
  }
  body.page-perfil-facturacion .form-card {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 2.5rem;
  }
  body.page-perfil-facturacion .form-group {
    margin-bottom: 1.5rem;
  }
  body.page-perfil-facturacion .form-group label {
    display: block;
    color: var(--accent);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 0.5rem;
  }
  body.page-perfil-facturacion .form-group input {
    width: 100%;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--border);
    color: #fff;
    padding: 0.8rem 1rem;
    border-radius: 8px;
    font-family: inherit;
    transition: all 0.3s ease;
  }
  body.page-perfil-facturacion .form-group input:focus {
    outline: none;
    border-color: var(--accent);
    background: rgba(200,169,110,0.05);
  }
  body.page-perfil-facturacion .btn-submit {
    background: var(--accent);
    color: #000;
    border: none;
    padding: 0.8rem 2rem;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    transition: all 0.3s;
  }
  body.page-perfil-facturacion .btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(200,169,110,0.3);
  }

body.page-perfil-facturacion .perfil-back {
  color: var(--accent);
  text-decoration: none;
  margin-bottom: 2rem;
  display: inline-block;
}
body.page-perfil-facturacion .perfil-title {
  color: var(--accent);
  font-family: 'Bebas Neue', sans-serif;
  font-size: 2.5rem;
  letter-spacing: 1px;
  margin-bottom: 0.5rem;
}
body.page-perfil-facturacion .perfil-description { color: var(--muted); margin-bottom: 2rem; }
body.page-perfil-facturacion .perfil-feedback { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
body.page-perfil-facturacion .perfil-feedback.success {
  background: rgba(46, 204, 113, 0.15);
  color: #2ecc71;
  border: 1px solid rgba(46, 204, 113, 0.3);
}
body.page-perfil-facturacion .perfil-feedback.error {
  background: rgba(231, 76, 60, 0.15);
  color: #e74c3c;
  border: 1px solid rgba(231, 76, 60, 0.3);
}
CSS;

$titulo_pagina = 'Datos Fiscales';
require_once 'includes/header.php';
?>

<div class="perfil-wrap">
    <a href="panel.php" class="perfil-back">&larr; Volver al Panel</a>
    
    <h2 class="perfil-title">Perfil de Facturación</h2>
    <p class="perfil-description">Actualiza tus datos fiscales para que aparezcan correctamente en tus facturas.</p>

    <?php echo $mensaje; ?>

    <div class="form-card">
        <form method="POST" action="" id="form-datos-fiscales">
            <div class="form-group">
                <label>Nombre Fiscal o Razón Social</label>
                <input type="text" name="nombre_fiscal" maxlength="150" value="<?php echo htmlspecialchars($datos['nombre_fiscal'] ?? ''); ?>" placeholder="Ej: Empresa S.L. o Juan Pérez" required>
            </div>
            
            <div class="form-group">
                <label>Documento de Identidad (NIF/CIF/DNI)</label>
                <input type="text" name="documento_identidad" maxlength="20" pattern="[A-Za-z0-9-]{3,20}" title="Usa letras, números y guiones. Máximo 20 caracteres." value="<?php echo htmlspecialchars($datos['documento_identidad'] ?? ''); ?>" placeholder="Ej: B-12345678" required>
            </div>
            
            <div class="form-group">
                <label>Dirección Completa</label>
                <input type="text" name="direccion_completa" maxlength="255" value="<?php echo htmlspecialchars($datos['direccion_completa'] ?? ''); ?>" placeholder="Calle, Número, Ciudad, C.P., País" required>
            </div>
            
            <button type="submit" class="btn-submit">Guardar Datos Fiscales</button>
        </form>
    </div>
</div>

<script>
  document.getElementById('form-datos-fiscales').addEventListener('submit', async function (event) {
    event.preventDefault();
    try {
      const respuesta = await fetch('perfil_facturacion.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: new FormData(this)
      });
      const resultado = await respuesta.json();
      if (!respuesta.ok || !resultado.ok) throw new Error();
      Swal.fire({
        title: 'Datos actualizados',
        text: 'Tus datos fiscales se han guardado correctamente.',
        icon: 'success',
        confirmButtonColor: 'var(--accent)',
        background: 'var(--surface)',
        color: 'var(--text)'
      });
    } catch (error) {
      Swal.fire({
        title: 'No se pudo guardar',
        text: 'Revisa los datos o intentalo de nuevo.',
        icon: 'error',
        confirmButtonColor: 'var(--accent)',
        background: 'var(--surface)',
        color: 'var(--text)'
      });
    }
  });
</script>

<?php require_once 'includes/footer.php'; ?>