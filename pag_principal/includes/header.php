<?php
/* ============================================================
   ARCHIVO: includes\header.php
   FUNCION: renderizar la cabecera y navegacion comun.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

// Cabecera común a todas las páginas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>VinoMadrid Hosting — <?php echo $titulo_pagina ?? 'Potencia tu presencia online'; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,700;1,300&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="estilos.css"/>
  <link rel="stylesheet" href="responsive.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

  <link rel="icon" type="image/x-icon" href="favicoin/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="favicoin/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="favicoin/favicon-16x16.png">
  <link rel="apple-touch-icon" href="favicoin/apple-touch-icon.png">
  <link rel="manifest" href="favicoin/site.webmanifest">
  <link rel="icon" type="image/x-icon" href="favicoin/favicon.ico?v=2">

  <?php if (!empty($css_pagina)): ?>
  <!-- ESTILOS DE LA VISTA: definidos en el archivo PHP que ha cargado esta cabecera. -->
  <style>
<?php echo $css_pagina; ?>
  </style>
  <?php endif; ?>

  <!-- ESTILOS PROPIOS: NAVEGACION. No se comparten con otras vistas. -->
  <style>
  /* Estilos base de la navegación integrados */
      nav {
        position: fixed;
        top: 0; left: 0; right: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.2rem 4rem;
        background: rgba(11,11,15,0.95);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(200,169,110,0.15);
      }
      
      .nav-logo {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 1.8rem;
        letter-spacing: 0.08em;
        color: var(--accent);
        text-decoration: none;
      }
      .nav-logo span { color: var(--text); }

      .nav-links {
        display: flex;
        gap: 2.5rem;
        list-style: none;
      }
      .nav-links a {
        text-decoration: none;
        color: var(--muted);
        font-size: 0.85rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        transition: color 0.3s;
      }
      .nav-links a:hover { color: var(--accent); }

      .nav-cta {
        background: var(--accent);
        color: var(--bg) !important;
        padding: 0.55rem 1.4rem;
        border-radius: 2px;
        font-weight: 500 !important;
        transition: background 0.3s !important;
      }
      .nav-cta:hover { 
        background: var(--accent2) !important; 
        color: var(--bg) !important; 
      }
      .nav-inline-form { display: inline-flex; margin: 0; }
      .nav-return-admin {
        border: 1px solid var(--accent);
        background: transparent;
        color: var(--accent);
        padding: 0.55rem 1rem;
        border-radius: 2px;
        font: inherit;
        font-size: 0.8rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        cursor: pointer;
      }
      .nav-return-admin:hover { background: var(--accent); color: var(--bg); }

      .nav-toggle {
        display: none;
        cursor: pointer;
        font-size: 1.8rem;
        color: #c8a96e;
        z-index: 10001;
      }

      /* Escritorio */
      @media (min-width: 901px) {
        .nav-links {
          display: flex;
          gap: 2.5rem;
          list-style: none;
          align-items: center;
        }
        .nav-links a {
          text-decoration: none;
          color: #ffffff;
          font-size: 0.85rem;
          letter-spacing: 0.1em;
          text-transform: uppercase;
          transition: color 0.3s;
        }
        .nav-links a:hover { color: #c8a96e; }
      }

      /* Móvil */
      @media (max-width: 900px) {
        nav { padding: 1rem 1.5rem; }
        
        .nav-toggle {
          display: block !important;
        }

        .nav-links {
          display: flex;
          flex-direction: column;
          position: fixed;
          top: 0;
          right: 0;
          width: 280px;
          height: 100vh;
          background: #13131a;
          padding: 6rem 2rem;
          gap: 2rem;
          transform: translateX(100%);
          transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
          z-index: 10000;
          border-left: 1px solid rgba(200,169,110,0.15);
          list-style: none;
        }

        .nav-links.active {
          transform: translateX(0) !important;
        }

        .nav-links a {
          color: #ffffff;
          text-decoration: none;
          font-size: 1.1rem;
          text-transform: uppercase;
          letter-spacing: 0.1em;
        }
      }

  .brand-link {
    color: inherit;
    text-decoration: none;
  }
  .nav-links a.is-current { color: var(--accent); }
  .nav-links a.is-muted { color: var(--muted); }
  </style>
</head>
<body class="page-<?php echo htmlspecialchars(str_replace(['.php', '_'], ['', '-'], $pagina_actual)); ?>">

<!-- ─── NAVBAR ─── -->
<nav>
  <div class="nav-logo"><a href="index.php" class="brand-link">Vino<span>Madrid</span> Hosting</a></div>
  
  <div class="nav-toggle" id="mobile-menu">
    <i class="fa-solid fa-bars"></i>
  </div>

  <ul class="nav-links">
    <li><a href="inicio.php" <?php if($pagina_actual=='inicio.php') echo 'class="is-current"'; ?>>Inicio</a></li>
    <li><a href="planes.php"   <?php if($pagina_actual=='planes.php')   echo 'class="is-current"'; ?>>Planes</a></li>
    <li><a href="galeria.php"   <?php if($pagina_actual=='galeria.php')   echo 'class="is-current"'; ?>>Showcase</a></li>

    <?php if (isset($_SESSION['email'])): ?>
      <li><a href="panel.php" <?php if($pagina_actual=='panel.php') echo 'class="is-current"'; ?>>Mi Panel</a></li>
      <?php if (!empty($_SESSION['admin_original'])): ?>
        <li>
          <form method="POST" action="controller_admin.php?action=volver_admin" class="nav-inline-form">
            <button type="submit" class="nav-return-admin">Volver Admin</button>
          </form>
        </li>
      <?php elseif (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
        <li><a href="admin_panel.php" <?php if($pagina_actual=='admin_panel.php') echo 'class="is-current"'; else echo 'class="is-muted"'; ?>>⚙️ Admin</a></li>
      <?php endif; ?>
      <li><a href="auth.php?action=logout" class="nav-cta">Salir</a></li>
    <?php else: ?>
      <li><a href="auth.php" class="nav-cta">Acceder</a></li>
    <?php endif; ?>
  </ul>
</nav>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const mobileMenu = document.getElementById('mobile-menu');
    const navLinks = document.querySelector('.nav-links');

    if(mobileMenu && navLinks) {
      mobileMenu.onclick = () => {
        navLinks.classList.toggle('active');
        const icon = mobileMenu.querySelector('i');
        if(icon) {
          icon.classList.toggle('fa-bars');
          icon.classList.toggle('fa-xmark');
        }
      };
    }
  });
</script>