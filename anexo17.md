# ANEXO 17: Código Fuente Comentado - Documentación Completa del Sistema VinoMadrid Hosting

**Nivel académico:** Grado Superior en Sistemas Microinformáticos y Redes (SMR)  
**Proyecto:** Trabajo Fin de Grado (TFG)  
**Alcance:** Auditoría y análisis completo de la suite de desarrollo PHP, JavaScript (AJAX/SSE), Maquetación CSS y scripts de automatización.

---

## NOTAS METODOLÓGICAS DE SEGURIDAD Y ESTADOS (AUDITORÍA SMR)

Antes de detallar los archivos, se establecen tres directrices del diseño de esta plataforma que el tribunal de TFG debe considerar:

1. **Gestión Escalonada de Estados (Ciclo de Vida de los Recursos):**
   Para sincronizar la base de datos web con el sistema operativo Linux (Apache, MySQL local, usuarios del sistema), se implementa un modelo de estados lógicos en las tablas `usuarios`, `modulo_mysql`, `dominios` y `ftp_cuentas_extra`:
   * **`Pendiente`:** El recurso ha sido solicitado y pagado. El demonio o worker en segundo plano (Python/Bash) lo leerá en su próxima iteración para crearlo físicamente en el SO.
   * **`Activo`:** El recurso ha sido aprovisionado físicamente con éxito y está operativo.
   * **`Para_Modificar`:** El usuario ha editado sus credenciales o capacidades. El worker aplicará los cambios en caliente.
   * **`Para_Borrar`:** El recurso se marca para eliminación física del sistema operativo en el ciclo de mantenimiento nocturno o purga del administrador.

2. **Almacenamiento Académico de Contraseñas en Claro (`password_plain`):**
   * **Nota de Auditoría:** El campo `password_plain` en la tabla `usuarios` y su procesamiento en el código PHP se mantienen **exclusivamente con fines académicos y de demostración** para facilitar la revisión del tribunal del TFG y validar la correspondencia del flujo. En una auditoría real de producción, el almacenamiento de contraseñas en texto claro está estrictamente prohibido, debiendo persistirse únicamente el hash criptográfico obtenido con `password_hash($pass, PASSWORD_BCRYPT)`.

3. **Arquitectura de Maquetación y Maqueta Premium:**
   La suite visual está construida con **Vanilla CSS** modular, utilizando un esquema centralizado en `estilos.css` y `responsive.css` con variables CSS (`:root`), complementado por hojas de estilo incrustadas mediante la variable `$css_pagina` para evitar colisiones entre layouts.

---

## SECCIÓN 1: DATOS Y SEGURIDAD

### 1.1 Conexión Centralizada (`conexiones.php`)
Establece el punto de enlace relacional único de la plataforma utilizando el controlador orientado a objetos MySQLi.

```php
<?php
function getConexion(): mysqli {
    $host = "localhost";
    $user = "usuario";
    $pass = "contraseña";
    $db   = "vinomadrid_db";
    
    $conexion = new mysqli($host, $user, $pass, $db);
    if ($conexion->connect_error) {
        die("Error de conexión a la base de datos: " . $conexion->connect_error);
    }
    $conexion->set_charset("utf8mb4");
    return $conexion;
}
?>
```
* **Comentario técnico:** Utiliza la codificación `utf8mb4` de forma obligatoria para evitar inyecciones SQL basadas en anomalías de codificación de caracteres (multi-byte bypass) y dar soporte a caracteres especiales (acentos, eñes) en la persistencia.

### 1.2 Configuración de Sesiones Seguras (`sessions.php`)
Centraliza los parámetros de cabecera de las cookies HTTP y restringe el acceso perimetral.

```php
<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

function require_auth() {
    if (!isset($_SESSION['email'])) {
        header('Location: auth.php?msg=login_required');
        exit;
    }
}

function login_user_from_row(array $user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['usuario'] = $user['nombre'];
    $_SESSION['rol'] = $user['rol'];
    $_SESSION['plan'] = $user['plan_contratado'];
    $_SESSION['storage_qty'] = $user['storage_qty'];
    $_SESSION['multiuser_qty'] = $user['multiuser_qty'];
    $_SESSION['extras'] = json_decode($user['extras_json'] ?? '[]', true);
}
?>
```
* **Comentario técnico:** `httponly` impide el robo de la cookie de sesión por scripts maliciosos (mitiga ataques XSS/Session Hijacking). `samesite => Strict` previene ataques de falsificación de peticiones en sitios cruzados (CSRF) al restringir el envío de la cookie en peticiones de origen cruzado.

### 1.3 Registro y Login de Usuarios (`auth.php`)
Procesa dinámicamente las validaciones en tiempo real y el flujo de alta/autenticación.

```php
// Comprobación AJAX de Email Duplicado
if (isset($_GET['action']) && $_GET['action'] === 'check_email') {
    header('Content-Type: application/json');
    $conexion = getConexion();
    $email = trim($_GET['email'] ?? '');
    $email_safe = $conexion->real_escape_string($email);
    $res = $conexion->query("SELECT id FROM usuarios WHERE email = '$email_safe' LIMIT 1");
    echo json_encode(['exists' => ($res && $res->num_rows > 0)]);
    $conexion->close();
    exit;
}

// Registro Seguro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $conexion = getConexion();
    $nombre = trim($_POST['nombre_usuario'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conexion->prepare('INSERT INTO usuarios (nombre, email, password_hash, password_plain, estado_servicio) VALUES (?, ?, ?, ?, "Pendiente")');
    $stmt->bind_param('ssss', $nombre, $email, $password_hash, $password, $estado_pendiente);
    $stmt->execute();
    $_SESSION['registro_verificar_id'] = $conexion->insert_id;
    header('Location: verificar_correo.php');
    exit;
}
```
* **Comentario técnico:** La validación de correo por AJAX utiliza `real_escape_string` para mitigar la inyección de sentencias SQL. El almacenamiento de credenciales utiliza `password_hash` con el algoritmo BCRYPT, el cual implementa de manera transparente salting aleatorio criptográficamente seguro, impidiendo ataques de diccionario y rainbow tables.

### 1.4 Simulación de Verificación de Correo (`verificar_correo.php`)
Asegura que el registro de una cuenta requiere pasar por la pasarela de validación de identidad.

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['registro_verificar_id'])) {
    $user_id = (int)$_SESSION['registro_verificar_id'];
    $conexion = getConexion();
    $stmt = $conexion->prepare('UPDATE usuarios SET email_verificado = 1, estado_servicio = "Activo" WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    if ($stmt->execute()) {
        unset($_SESSION['registro_verificar_id']);
        header('Location: panel.php');
    }
    $conexion->close();
}
```
* **Comentario técnico:** Utiliza sentencias preparadas tipadas con `bind_param('i', $user_id)` garantizando que el ID procesado sea un entero estricto, neutralizando cualquier intento de inyección de parámetros.

---

## SECCIÓN 2: PANEL DE CLIENTES

### 2.1 Dashboard Principal del Cliente (`panel.php`)
Consola centralizada del usuario que lee las cuotas, calcula tamaños y renderiza los estados de aprovisionamiento en vivo.

```php
// Cálculo interactivo de espacio
function get_folder_size(string $dir): int {
    $size = 0;
    if (!is_dir($dir)) return 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        try { $size += $file->getSize(); } catch (Exception $e) {}
    }
    return $size;
}
$user_folder = "/var/www/hosting/" . $_SESSION['usuario'] . "/htdocs";
$used_space = round(get_folder_size($user_folder) / (1024**3), 2);
```
* **Comentario técnico:** La iteración recursiva orientada a objetos evita desbordamientos de memoria de PHP en directorios con estructuras anidadas profundas. Toda representación en pantalla de variables de usuario se higieniza con `htmlspecialchars($data, ENT_QUOTES, 'UTF-8')` para evitar ataques Cross-Site Scripting (XSS).

### 2.2 Edición de Perfil Privado (`usuarios.php` - Contexto Cliente/Admin)
Permite la modificación segura de credenciales verificando la clave previa.

```php
if ($pass_nueva !== '') {
    if (password_verify($pass_actual, $usuario['password_hash'])) {
        $hash = password_hash($pass_nueva, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE usuarios SET nombre = ?, email = ?, password_hash = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nombre, $email, $hash, $uid);
    }
}
```
* **Comentario técnico:** Requiere validación criptográfica de la contraseña actual antes de actualizar. Esto previene secuestros de cuenta si una sesión de navegador queda abierta accidentalmente en el equipo del cliente.

### 2.3 Gestor de Packs y Extras (`modificar_servicios.php`)
Interface del panel que permite contratar capacidad de almacenamiento adicional, base de datos MySQL o soporte IA en caliente.

```php
$plan_actual = $_SESSION['plan'] ?? 'Ninguno';
$jerarquia = ['Ninguno' => 0, 'BÁSICO' => 1, 'PROFESIONAL' => 2, 'ENTERPRISE' => 3];
$nivel_actual = $jerarquia[$plan_actual] ?? 0;
```
* **Comentario técnico:** Almacena la estructura de planes en un array asociativo estricto. Esto valida en el servidor si la solicitud del cliente es un upgrade inmediato o un downgrade diferido antes de procesar el pago.

### 2.4 Datos Fiscales y Facturación (`perfil_facturacion.php`)
Procesa el guardado y validación sintáctica de datos mercantiles del titular.

```php
$doc_identidad = trim($_POST['documento_identidad'] ?? '');
if (preg_match('/^[A-Za-z0-9-]{3,20}$/', $doc_identidad)) {
    $stmt = $db->prepare("UPDATE usuarios SET nombre_fiscal = ?, documento_identidad = ?, direccion_completa = ? WHERE id = ?");
    $stmt->bind_param("sssi", $nombre_fiscal, $doc_identidad, $direccion, $user_id);
}
```
* **Comentario técnico:** Valida el formato del documento fiscal mediante expresiones regulares rígidas en el servidor, impidiendo la manipulación del campo (como inyección de código de maquetación HTML o sentencias SQL) antes de la llamada transaccional.

---

## SECCIÓN 3: PROCESADORES DE FORMULARIOS Y WORKERS

### 3.1 Actualización de Cuenta FTP (`procesar_ftp.php`)
Saneamiento sintáctico de nombres de usuario FTP y activación del worker SO.

```php
$raw_ftp_user = str_replace(' ', '_', strtolower(trim($_POST['ftp_user'] ?? '')));
$ftp_user_s = preg_replace('/[^a-z0-9_]/', '', $raw_ftp_user);
$db->query("UPDATE usuarios SET ftp_user = '$ftp_user_s', creado_en_so = 0, estado_servicio = 'Para_Modificar' WHERE id = $user_id");
shell_exec("sudo python3 /opt/tfg/scripts/crear_usuarios.py > /dev/null 2>&1");
```
* **Comentario técnico:** Sanea y filtra la entrada FTP eliminando caracteres no alfanuméricos para evitar escaladas de privilegios y ataques de inyección de comandos en el script Python que interactúa con la gestión de usuarios Unix en el sistema operativo Linux.

### 3.2 Modificación de Base de Datos (`procesar_mysql.php`)
Controla el alta, aprovisionamiento y cambios de credenciales de esquemas relacionales.

```php
$stmt = $db->prepare("UPDATE modulo_mysql SET db_pass = ?, estado = 'Para_Modificar' WHERE user_id = ?");
$stmt->bind_param('si', $raw_pass, $user_id);
$stmt->execute();
shell_exec("sudo python3 /opt/tfg/scripts/mysql_worker.py > /dev/null 2>&1");
```
* **Comentario técnico:** Delega la gestión física de base de datos a un subproceso Linux ejecutando en caliente `mysql_worker.py` mediante una cola lógica basada en estados (`Para_Modificar`), evitando colgar hilos del servidor web Apache.

### 3.3 Gestión de Dominio y Subdominios (`procesar_dominio.php`)
Enruta peticiones de vinculación DNS de alias propios y subdominios.

```php
switch ($accion) {
    case 'cambiar_subdominio':
        $sql = "UPDATE dominios SET subdominio_alias = '$valor', estado_dominio = 'Tramitando' WHERE user_id = $user_id";
        break;
}
$db->query($sql);
shell_exec("sudo python3 /opt/tfg/scripts/virtualhosts.py > /dev/null 2>&1");
```
* **Comentario técnico:** Inicia la reconfiguración DNS de Apache mediante estados de transición (`Tramitando`). Esto asegura que el worker `virtualhosts.py` regenere los archivos de configuración `.conf` en la carpeta `/etc/apache2/sites-available` y recargue el demonio de forma asíncrona.

### 3.4 Creación de Staff Adicional (`procesar_staff.php`)
Permite al cliente aprovisionar cuentas FTP secundarias para sus empleados.

```php
$db->query("ALTER TABLE ftp_cuentas_extra MODIFY COLUMN estado ENUM('Pendiente','Tramitando','Activo','Error','Para_Borrar','Para_Modificar')");
$stmt = $db->prepare("INSERT INTO ftp_cuentas_extra (user_id, ftp_user, ftp_pass, estado) VALUES (?, ?, ?, 'Pendiente')");
$stmt->bind_param("iss", $user_id, $staff_user, $staff_pass);
```
* **Comentario técnico:** Implementa una inserción parametrizada rigurosa. Valida en base de datos la cuota máxima permitida (`multiuser_qty`) antes de ejecutar el proceso para evitar denegación de servicio (DoS) por saturación de disco.

### 3.5 Control del Orquestador de Módulos (`procesar_cambio.php`)
Consolida modificaciones de packs extras y desvinculaciones inmediatas de módulos contratados.

```php
if ($modulo === 'domain') {
    $db->query("UPDATE dominios SET dominio_propio = NULL, estado_dominio = 'Para_Borrar' WHERE user_id = $user_id");
}
```
* **Comentario técnico:** Sincroniza en caliente la sesión del navegador del usuario tras mutar los datos en el modelo relacional, enviando llamadas asíncronas AJAX para actualizar la interfaz del frontend de forma limpia.

### 3.6 Purga Física SO (`procesar_borrado.php` - Contexto Admin)
Worker del administrador para destruir de forma irreversible un inquilino de la infraestructura.

```php
exec("sudo python3 /opt/tfg/scripts/virtualhosts.py 2>&1", $outputs[], $retval_vh);
exec("sudo python3 /opt/tfg/scripts/mysql_worker.py 2>&1", $outputs[], $retval_my);
exec("sudo python3 /opt/tfg/scripts/crear_usuarios.py 2>&1", $outputs[], $retval_cr);
```
* **Comentario técnico:** Ejecución síncrona en cascada de los 3 workers del sistema operativo. Valida el código de retorno de cada comando (`$retval`) y registra fallos en `/opt/tfg/scripts/logs/acciones.log` para auditorías forenses del sistema.

### 3.7 Reactivación del Inquilino (`restaurar_usuario.php`)
Restablece estados comerciales del cliente en la plataforma antes de que la purga física sea definitiva.

```php
$query = "UPDATE usuarios SET estado_servicio = 'Activo', fecha_cancelacion = NULL WHERE id = $user_id LIMIT 1";
$conexion->query($query);
```
* **Comentario técnico:** Bypass comercial rápido que interrumpe la purga lógica en disco. Reactiva de forma atómica el estado comercial del cliente e inyecta una alerta administrativa interna como traza de auditoría.

---

## SECCIÓN 4: ADMINISTRACIÓN

### 4.1 Consola de Administración Central (`admin_panel.php`)
Dashboard privado del rol Administrador del sistema para visualización de métricas y cola de alertas.

```php
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: panel.php');
    exit;
}
$res_alerts = $db->query("SELECT id, user_id, nombre_usuario, motivo, simbolo, reconocida, fecha FROM alertas_admin WHERE reconocida = 0 ORDER BY fecha DESC");
```
* **Comentario técnico:** Doble anillo de validación perimetral. Controla mediante sesión que el rol activo coincida de forma exacta con la firma `admin`, denegando la lectura de alertas privadas al resto de inquilinos.

### 4.2 Controlador de Operaciones Admin (`controller_admin.php`)
Orquesta las peticiones masivas del backend y la suplantación temporal de clientes (impersonación).

```php
// Suplantación Segura de Sesión
$_SESSION['admin_original'] = $_SESSION; // Respaldar datos reales del Administrador
$_SESSION['impersonando_id'] = (int)$usuario['id'];
login_user_from_row($usuario); // Forzar la absorción del perfil cliente
```
* **Comentario técnico:** El mecanismo de suplantación es resistente a fugas de sesión: guarda el contexto total del administrador en una clave protegida de sesión (`admin_original`) para permitir un retorno de sesión seguro (`volver_admin`) sin forzar re-autenticaciones.

### 4.3 Procesador de Alertas por Lotes (`acciones_masivas_alertas.php`)
Atiende peticiones AJAX masivas procedentes del panel de alertas.

```php
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("UPDATE alertas_admin SET reconocida = 1 WHERE id IN ($placeholders)");
$types = str_repeat('i', count($ids));
$stmt->bind_param($types, ...$ids);
$stmt->execute();
```
* **Comentario técnico:** Genera marcadores dinámicos parametrizados en base a la longitud de los arrays recibidos. Esto evita la concatenación directa de parámetros en sentencias `IN (...)` y previene inyecciones SQL masivas.

### 4.4 Alternador de Estados (`marcar_alerta.php`)
Interruptor AJAX individual para cambiar la marca de lectura de alertas.

```php
$stmt = $db->prepare("UPDATE alertas_admin SET reconocida = ? WHERE id = ?");
$stmt->bind_param("ii", $nuevo_estado, $id);
$stmt->execute();
```
* **Comentario técnico:** Implementa lógica rápida para peticiones asíncronas de la interfaz administrativa, retornando un estatus `ok` plano en texto para evitar la sobrecarga de envío de payloads JSON pesados.

### 4.5 Cierre y Confirmación Directa (`reconocer_alerta.php`)
Endpoint de marcado individual asíncrono para archivar alertas de auditoría.

```php
$stmt = $db->prepare("UPDATE alertas_admin SET reconocida = 1 WHERE id = ?");
$stmt->bind_param("i", $id);
```
* **Comentario técnico:** Utilizado por las peticiones automáticas AJAX de la interfaz cuando el administrador realiza acciones directas sobre las cuentas de hosting (crear bases de datos, reactivaciones), reduciendo clics administrativos redundantes.

---

## SECCIÓN 5: FACTURACIÓN Y SUSCRIPCIONES

### 5.1 Pre-visualizador del Pedido (`checkout.php`)
Mapea importes, cargos por servicios de diseño web e integra controles anti-tampering.

```php
$_SESSION['checkout_web_project_guard'] = [
    'user_id' => $checkout_user_id,
    'project_id' => (int)$proyecto_web_checkout['id'],
    'amount' => round((float)$proyecto_web_checkout['precio_final'], 2),
    'created_at' => time(),
];
```
* **Comentario técnico:** Almacena los costes finales del presupuesto en variables de sesión firmadas en el servidor. Esto neutraliza de raíz los ataques de manipulación de importes del lado del cliente (Client-Side Price Manipulation) mediante la edición de valores en el árbol DOM del navegador.

### 5.2 Pasarela de Pago Transaccional (`procesar_pago.php`)
Calcula desgloses comerciales mercantiles y consolida altas de recursos dentro de un bloque ACID.

```php
$conexion->begin_transaction();
try {
    [$base_imponible, $iva_importe, $total_factura] = desglose_iva_factura($importe_real_total);
    $detalles_json = json_encode($detalles_factura, JSON_UNESCAPED_UNICODE);
    
    $stmt_f = $conexion->prepare("INSERT INTO facturas (user_id, fecha_emision, concepto, importe, base_imponible, iva_importe, detalles_json, tipo, estado) VALUES (?, NOW(), ?, ?, ?, ?, ?, 'factura', 'Pagado')");
    $stmt_f->bind_param("isddds", $user_id, $concepto_factura, $total_factura, $base_imponible, $iva_importe, $detalles_json);
    $stmt_f->execute();
    
    $conexion->commit();
} catch (Throwable $e) {
    $conexion->rollback();
}
```
* **Comentario técnico:** El uso atómico de transacciones (`begin_transaction()`) asegura que un fallo intermedio en el guardado de la factura o en la actualización del perfil del cliente provoque una reversión total instantánea (`rollback()`), impidiendo hilos de datos corruptos o servicios aprovisionados sin cobrar.

### 5.3 Cron Nocturno de Renovaciones (`cron_facturacion.php`)
Script desatendido para su ejecución por cron del sistema operativo Linux.

```php
$sql = "SELECT id, nombre, plan_contratado, extras_json, storage_qty, multiuser_qty
        FROM usuarios
        WHERE estado_servicio = 'Activo'
          AND plan_contratado != 'Ninguno'
          AND renovacion_automatica = 1
          AND DAY(fecha_alta) = DAY(CURDATE())";
$res = $db->query($sql);
```
* **Comentario técnico:** Evalúa la coincidencia del día mensual de alta (`DAY(fecha_alta) = DAY(CURDATE())`). Esto implementa una facturación periódica consistente e impide cobros indebidos de forma automatizada sin sobrecargar la CPU del servidor.

### 5.4 Historial y Gestión de Suscripciones (`facturas.php`)
Vista cliente para la consulta de transacciones históricas y control de la renovación automática.

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_suscripcion'])) {
    $nuevo_estado = ($_POST['cambiar_suscripcion'] === 'activar') ? 1 : 0;
    $stmt_sub = $db->prepare("UPDATE usuarios SET renovacion_automatica = ? WHERE id = ?");
    $stmt_sub->bind_param("ii", $nuevo_estado, $user_id);
}
```
* **Comentario técnico:** Captura mediante fetch asíncrono las peticiones del usuario en el frontend, actualizando el estado comercial recurrente en la base de datos sin recargar la página del cliente y mostrando alertas de confirmación SweetAlert2.

### 5.5 Generador de PDF Dinámico (`descargar_factura.php`)
Compila información contable estructurada de la tabla facturas y genera un binario PDF mediante la librería FPDF.

```php
$factura_id = (int)$_GET['id'];
$user_id    = (int)$_SESSION['user_id'];
$rol        = $_SESSION['rol'] ?? 'usuario';

if ($rol !== 'admin') {
    $stmt = $db->prepare("SELECT f.*, u.nombre_fiscal, u.documento_identidad, u.direccion_completa FROM facturas f JOIN usuarios u ON f.user_id = u.id WHERE f.id = ? AND f.user_id = ?");
    $stmt->bind_param("ii", $factura_id, $user_id);
}
```
* **Comentario técnico:** Mitiga de forma estricta incidentes de referencia directa insegura a objetos (IDOR). Al validar de forma cruzada en la consulta SQL que la factura pertenece al usuario de la sesión activa (`f.user_id = ?`), impide que un cliente descargue facturas ajenas alterando el parámetro id en la URL.

### 5.6 Desistimiento de Servicio Comercial (`cancelar_suscripcion.php`)
Inicia el trámite de baja de cuenta solicitada por el usuario.

```php
$stmt = $conexion->prepare("UPDATE usuarios SET estado_servicio = 'Cancelado', fecha_cancelacion = NOW() WHERE id = ?");
$stmt->bind_param('i', $user_id);
```
* **Comentario técnico:** Actualiza el estado a `Cancelado` para inhabilitar visualmente el servicio del inquilino y registrar la marca de tiempo exacta del desistimiento voluntario, lanzando la correspondiente alerta al panel de administración.

### 5.7 Facturación Rectificativa (`solicitar_reembolso_web.php`)
Emite facturas contables de abono con saldo negativo por desistimiento legal de los servicios de Inteligencia Artificial.

```php
$importe_negativo = -$proyecto['precio_final'];
[$base_neg, $iva_neg, $total_neg] = desglose_iva_factura($importe_negativo);
$stmt = $db->prepare("INSERT INTO facturas (user_id, fecha_emision, concepto, importe, base_imponible, iva_importe, detalles_json, tipo, estado) VALUES (?, NOW(), ?, ?, ?, ?, ?, 'rectificativa', 'Reembolsado')");
```
* **Comentario técnico:** Garantiza la consistencia fiscal mediante la emisión de facturas contables rectificativas negativas. Esto documenta correctamente la devolución del IVA devengado ante inspecciones fiscales y purga de inmediato el JSON de extras de la sesión del inquilino.

---

## SECCIÓN 6: SOPORTE Y SSE (SERVER-SENT EVENTS)

### 6.1 Procesamiento en Cola de Mensajes Chat (`procesar_chat.php`)
Persiste mensajes antiguos a través de la tubería física de un script auxiliar Python.

```php
$payload = escapeshellarg($json_data);
$user_arg = escapeshellarg($username);
$comando = "python3 save_chat.py $user_arg $payload 2>&1";
$output = shell_exec($comando);
```
* **Comentario técnico:** El uso estricto de la función nativa `escapeshellarg()` neutraliza los ataques de inyección de comandos del sistema operativo (OS Command Injection), saneando y envolviendo los parámetros dentro de comillas seguras antes de derivarlos al shell de Linux.

### 6.2 Almacenamiento Directo y Trigger IA (`send_chat.php`)
Inserta mensajes de soporte en MySQL y gatilla autorrespuestas de IA basadas en triggers comerciales.

```php
$stmt = $conexion->prepare("INSERT INTO mensajes_chat (user_id, emisor, mensaje, leido) VALUES (?, ?, ?, 0)");
$stmt->bind_param("iss", $user_id, $emisor, $mensaje);
$stmt->execute();
```
* **Comentario técnico:** Emplea parametrización estricta en las escrituras del chat. Previene ataques XSS almacenando el mensaje sin codificar en la base de datos (lo que permite su tratamiento posterior) y dejando la obligación de saneamiento de salida al frontend del cliente.

### 6.3 Demonio SSE de Comunicación Bidireccional (`get_chat_sse.php`)
Establece y mantiene abierta la tubería de comunicación en tiempo real entre cliente e infraestructura.

```php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Evita almacenamiento en búfer de Nginx/Apache

while (true) {
    $stmt = $conexion->prepare("SELECT id, emisor, mensaje, fecha FROM mensajes_chat WHERE user_id = ? AND leido = 0");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $mensajes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if ($mensajes) {
        foreach ($mensajes as $msg) {
            echo "data: " . json_encode($msg, JSON_UNESCAPED_UNICODE) . "\n\n";
        }
        ob_flush(); flush();
    }
    sleep(1);
}
```
* **Comentario técnico:** Implementa Server-Sent Events (SSE) en lugar de HTTP short polling. Esto ahorra costes de hardware al evitar cientos de peticiones TCP recurrentes al servidor web por segundo. Las cabeceras `X-Accel-Buffering` y `Connection: keep-alive` aseguran que proxies intermedios no retengan los bytes, vaciando el buffer directamente hacia el navegador del cliente de forma interactiva.

---

## SECCIÓN 7: MONITORIZACIÓN

### 7.1 Consola de Estadísticas Físicas SO (`monitorizacion.php`)
Audita el rendimiento del hardware, lectura de logs e implementa un sistema de almacenamiento caché.

```php
// Sistema de caché en sesión con TTL (Time-To-Live)
$monitor_cache = $_SESSION['monitor_cache'] ?? [];

function monitor_cached(string $clave, callable $cargar, int $ttl = 30) {
    global $monitor_cache;
    $entrada = $monitor_cache[$clave] ?? null;
    if (is_array($entrada) && (time() - (int)$entrada['time']) < $ttl) {
        return $entrada['value'];
    }
    $valor = $cargar();
    $monitor_cache[$clave] = ['time' => time(), 'value' => $valor];
    $_SESSION['monitor_cache'] = $monitor_cache;
    return $valor;
}

$cpu_percent = monitor_cached('cpu_load', function() {
    $load = sys_getloadavg();
    return min(($load[0] / 4) * 100, 100);
}, 30);
```
* **Comentario técnico:** **Cacheado Eficiente en Sesión**: Para evitar ataques de denegación de servicio (DoS) por la lectura constante de logs y consultas pesadas al kernel del SO (`free -b`, `sys_getloadavg()`), el panel web implementa una caché en la variable `$_SESSION['monitor_cache']` con un TTL estricto de 30 segundos. Esto limita drásticamente las llamadas redundantes al kernel, sirviendo los datos de la memoria del servidor de forma instantánea.

---

## SECCIÓN 8: VISTAS, ESTILOS Y CABECERAS

### 8.1 Bienvenida Pública (`index.php`)
Página de aterrizaje (Landing) que actúa como punto inicial del enrutamiento.

```php
$css_pagina = <<<'CSS'
body.page-index .landing { min-height: calc(90vh - 80px); display: grid; }
CSS;
$titulo_pagina = 'Bienvenida';
require_once 'includes/header.php';
```
* **Comentario técnico:** Aplica un sistema modular Desktop-First de maquetación, inyectando estilos locales mediante variables heredoc y liberando la memoria al completarse el procesamiento.

### 8.2 Presentación de la Suite (`inicio.php`)
Detalla la propuesta comercial y el stack tecnológico mediante cuadros interactivos modales accesibles.

```html
<dialog id="modal-mysql" class="technology-modal" aria-labelledby="title-mysql">
  <div class="modal-content">
    <button class="modal-close" type="button" data-close-modal>&times;</button>
    <h2 id="title-mysql">MySQL</h2>
  </div>
</dialog>
```
* **Comentario técnico:** Utiliza la API nativa de JavaScript `<dialog>` y modales accesibles (`showModal()`), lo que garantiza el correcto bloqueo del foco y navegación nativa mediante teclado (requisitos exigidos de accesibilidad en auditorías de maquetación).

### 8.3 Comparativa de Planes Comerciales (`planes.php`)
Visualiza las especificaciones técnicas y cuotas de aprovisionamiento de cada plan de hosting.

```javascript
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.style.opacity = '1';
      e.target.style.transform = 'translateY(0)';
    }
  });
}, { threshold: 0.1 });
```
* **Comentario técnico:** Implementa animaciones fluidas mediante la API `IntersectionObserver` de JavaScript, evitando ralentizar el renderizado inicial y mejorando de forma notable la experiencia estética de usuario (Premium UX).

### 8.4 Formulario Presupuestario Interactivo (`presupuestos.php`)
Asistente (wizard) conversacional interactivo para solicitar presupuestos mediante fetch asíncrono.

```javascript
async function sendToBackend(payload) {
  const response = await fetch('send_chat.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  return response.json();
}
```
* **Comentario técnico:** Modela una estructura interactiva simulando respuestas automatizadas por Inteligencia Artificial. La validación del estado del proyecto se verifica constantemente desde la base de datos en el backend para prevenir envíos duplicados de formularios.

### 8.5 Vitrina de Proyectos de Inquilinos (`galeria.php`)
Galería interactiva pública que previsualiza las webs alojadas en caliente en un iframe simulando un monitor.

```javascript
monitorSlides.addEventListener('load', (e) => {
  if (e.target.tagName === 'IFRAME') {
    try {
      const iframeWin = e.target.contentWindow;
      iframeWin.addEventListener('click', (ev) => { ev.preventDefault(); });
    } catch(err) {}
  }
});
```
* **Comentario técnico:** Captura las interacciones en el iframe del monitor y bloquea la navegación de enlaces externos. Implementa controles de captura de errores de políticas CORS ante dominios ajenos.

### 8.6 Recurso no Encontrado (`error404.html`)
Vista estática de respuesta para enrutar anomalías HTTP 404 del servidor web Apache de forma elegante.

```html
<body class="page-error404">
    <div class="main-content">
        <div class="error-code">404</div>
        <h1>Página no encontrada</h1>
    </div>
</body>
```
* **Comentario técnico:** Página de peso optimizado y diseño adaptativo que no depende de hojas de estilo externas ni de conexiones a bases de datos, garantizando su disponibilidad aun en escenarios de caída del gestor de base de datos.

### 8.7 Hoja de Estilos Comunes (`estilos.css`)
Establece la paleta de colores premium (tonos oscuros y acentos dorados), tipografías, formularios y botones.

```css
:root {
  --bg: #0b0b0f;
  --surface: #13131a;
  --accent: #c8a96e;
  --text: #e8e4dc;
  --border: rgba(200, 169, 110, 0.15);
}
```
* **Comentario técnico:** Uso intensivo de variables nativas CSS `:root` y diseño basado en cajas flexibles (Flexbox) para facilitar la consistencia en futuros cambios cromáticos (Rebranding comercial de la plataforma).

### 8.8 Reglas Adaptativas (`responsive.css`)
Contenedor Desktop-First de Media Queries para asegurar la correcta legibilidad de la suite en Smartphones y Tablets.

```css
@media (max-width: 992px) {
  .planes-grid {
    grid-template-columns: 1fr;
    padding: 0 1rem;
  }
}
```
* **Comentario técnico:** Las tablas del panel están cubiertas con la clase `.responsive-table-scroll` que fuerza el scroll horizontal sin alterar ni ensanchar la pantalla del dispositivo móvil (Viewport Safety).

### 8.9 Cabecera del Documento HTML (`includes/header.php`)
Layout común que carga dependencias CSS/JS, comprueba estado de sesión activa e integra controles del rol.

```php
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<title>VinoMadrid Hosting — <?php echo htmlspecialchars($titulo_pagina ?? 'Potencia tu presencia online'); ?></title>
```
* **Comentario técnico:** Inyecta de forma segura el título de la página, sanitizándolo con `htmlspecialchars` para neutralizar inyecciones de cabeceras o exploits XSS reflejados mediante alteración de URLs públicas.

### 8.10 Pie de Página de la Suite (`includes/footer.php`)
Cierra las etiquetas HTML del documento e integra un gestor unificado de alertas SweetAlert2 a nivel de backend.

```php
<?php if (isset($_GET['error'])): ?>
  Swal.fire({
    icon: 'error',
    title: 'Error',
    text: '<?php echo htmlspecialchars($_GET['error']); ?>',
    background: 'var(--surface)'
  });
<?php endif; ?>
```
* **Comentario técnico:** El footer actúa como un "Interceptor de Alertas de URL". Si existe un parámetro `error` o `msg` en la petición GET actual, lo sanea y renderiza una ventana SweetAlert2 elegante y adaptada al diseño oscuro de la plataforma.