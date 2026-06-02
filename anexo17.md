# ANEXO: Código Fuente Comentado de la Suite PHP de la Página Principal
=======

Esta sección documenta de forma exhaustiva la arquitectura y lógica de backend en PHP de todos los ficheros que integran la página principal y el panel de control de **VinoMadrid Hosting**. Se detallan a continuación los bloques de código más críticos, su función, y sus respectivos comentarios técnicos de diseño, seguridad, interacción con el Kernel de Linux y consistencia relacional.

---

## 1. Capa de Datos y Control de Sesiones

### Conexión Centralizada (`conexiones.php`)
Este archivo centraliza el socket de comunicación relacional con el motor de base de datos local.
A continuación, se documenta la función real del archivo:

```php
function getConexion(): mysqli {
    $host = "localhost";
<<<<<<< HEAD
    $user = "usuario";
    $pass = "contras";
    $db   = "vinomadrid_db";
    $conexion = new mysqli($host, $user, $pass, $db);
    if ($conexion->connect_error) {
        die("Error de conexión: " . $conexion->connect_error);
    }
    $conexion->set_charset("utf8mb4");

    return $conexion;
}
```
**Comentario técnico:** Abre un contexto de comunicación con la base de datos `vinomadrid_db` usando el driver MySQLi nativo de PHP. Establece de forma explícita el conjunto de caracteres a `utf8mb4`, permitiendo la persistencia íntegra de cadenas con codificación multibyte avanzada (incluyendo emojis y caracteres especiales locales) y previniendo fallos sintácticos colaterales por disparidad de charset en la codificación de las tablas relacionales.

### Control y Seguridad de Sesiones (`sessions.php`)
Este módulo se encarga de configurar las cabeceras de persistencia de sesión y centralizar los permisos de acceso del área privada.
A continuación, se documentan las directivas y rutinas que componen el script:

```php
session_set_cookie_params([
    'lifetime' => 0,                    // Cookie de sesión (se borra al cerrar navegador)
    'path'     => '/',                  // Disponible en toda la aplicación
    'domain'   => '',                   // Se auto-detecta el dominio actual
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',  // Solo HTTPS
    'httponly' => true,                 // No accesible desde JavaScript (previene XSS)
    'samesite' => 'Strict'              // Previene ataques CSRF
]);

session_start();

function require_auth() {
    if (!isset($_SESSION['email'])) {
        $plan = isset($_GET['plan']) ? '&redirect_plan=' . urlencode($_GET['plan']) : '';
        header('Location: auth.php?msg=login_required' . $plan);
        exit;
    }
}
```
**Comentario técnico:** Define parámetros estrictos en la cabecera `Set-Cookie` enviada al cliente. Al activar `httponly`, bloquea la accesibilidad de la cookie de sesión desde llamadas de scripts cliente (previniendo secuestros de sesión en vulnerabilidades XSS). El parámetro `samesite => 'Strict'` instruye al navegador para no adjuntar la cookie en solicitudes procedentes de portales externos, mitigando ataques de falsificación de solicitudes en sitios cruzados (CSRF). La función `require_auth` intercepta peticiones no autenticadas, redirigiéndolas con preservación del plan solicitado.

---

## 2. Gestión de Autenticación, Acceso y Verificación

### Registro y Login de Usuarios (`auth.php`)
Este módulo valida las solicitudes de alta y autenticación, realizando comprobaciones dinámicas por AJAX contra la base de datos relacional.
A continuación, se documentan las rutinas lógicas fundamentales del archivo:

```php
// Comprobación AJAX de Email Duplicado (check_email)
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

// Cifrado y Hashing en Registro
if ($action === 'register') {
    // ... Validaciones sintácticas y de políticas de contraseña ...
    if (!preg_match('/^[a-zA-Z0-9ÁÉÍÓÚÜÑáéíóúüñ]+(?: [a-zA-Z0-9ÁÉÍÓÚÜÑáéíóúüñ]+)*$/', $nombre)) {
        header('Location: auth.php?error=nombre_invalido'); exit;
    }
    if (strlen($password) < 8 || !preg_match('/[-_.,()$^*\[\]]/', $password)) {
        header('Location: auth.php?error=password_simbolo'); exit;
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conexion->prepare(
        'INSERT INTO usuarios (nombre, email, password_hash, password_plain, plan_contratado, ...) 
         VALUES (?, ?, ?, ?, ?, ...)'
    );
    // ... execute() y redirección a verificación ...
}
```
**Comentario técnico:** Implementa controles defensivos en dos capas. Primero, los endpoints AJAX interrogan la base de datos aplicando `real_escape_string` para anular la manipulación de caracteres. Segundo, durante el flujo de registro, se fuerzan expresiones regulares restrictivas para impedir inyecciones de código HTML en el nombre de usuario y se exige el uso obligatorio de símbolos especiales en la clave. El almacenamiento de contraseñas se realiza utilizando la función `password_hash` con el algoritmo criptográfico seguro `BCRYPT`, que genera de forma nativa un valor hash unidireccional y salteado en el servidor, protegiendo las credenciales contra ataques de fuerza bruta basados en tablas de arcoíris (rainbow tables).

### Simulación de Validación de Correo (`verificar_correo.php`)
Este archivo actúa como un puente transaccional intermedio para consolidar la verificación de identidad del cliente recién registrado.
A continuación, se documenta la consulta del bloque lógico:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['registro_verificar_id'])) {
    $user_id = (int)$_SESSION['registro_verificar_id'];
    $conexion = getConexion();
    $stmt = $conexion->prepare('UPDATE usuarios SET email_verificado = 1 WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    if ($stmt->execute()) {
        // Carga de sesión, limpieza de variables temporales y redirección
    }
}
```
**Comentario técnico:** Asegura que una cuenta creada en la plataforma no pueda saltarse el paso de confirmación simulado. Utiliza sentencias preparadas parametrizadas para actualizar el campo `email_verificado` en la base de datos. Solo tras esta ejecución satisfactoria, el backend permite al usuario el acceso definitivo al área del panel, limpiando de la memoria de sesión las credenciales transitorias (`registro_verificar_id`).

---

## 3. Panel de Control de Clientes e Interfaz del Dashboard

### Visualización y Gestión de Recursos (`panel.php`)
Es la consola de administración del cliente que lee y presenta las cuotas de almacenamiento, base de datos activa, credenciales de FTP y dominios vinculados.
A continuación, se detalla la lógica de sincronización:

```php
// Cálculo interactivo de recursos contratados
$user_id_panel = (int)$_SESSION['user_id'];
$res_db = $db->query("SELECT db_name, db_user, db_pass, estado FROM modulo_mysql WHERE user_id = $user_id_panel LIMIT 1");
$mysql_data = $res_db->fetch_assoc();

$res_ftp = $db->query("SELECT id, ftp_user, ftp_pass, estado FROM ftp_cuentas_extra WHERE user_id = $user_id_panel");
$cuentas_ftp_adicionales = $res_ftp->fetch_all(MYSQLI_ASSOC);
```
**Comentario técnico:** Centraliza la lectura relacional de los recursos contratados para representarlos visualmente. Carga dinámicamente las credenciales actuales, evaluando si el estado del aprovisionamiento está en fase 'Pendiente' o 'Para_Borrar', lo que permite inhabilitar botones de edición temporal en el frontend del cliente mientras los workers de Python operan a nivel del sistema operativo.

### Edición del Perfil de Usuario (`usuarios.php`)
Módulo encargado de actualizar los datos de la cuenta activa (nombre, email y contraseña).
A continuación, se documenta el bloque de actualización:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_nuevo = trim($_POST['nombre'] ?? '');
    $email_nuevo = trim($_POST['email'] ?? '');
    $pass_nueva = trim($_POST['password'] ?? '');
    // ...
    if ($pass_nueva !== '') {
        $hash = password_hash($pass_nueva, PASSWORD_BCRYPT);
        $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, email = ?, password_hash = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nombre_nuevo, $email_nuevo, $hash, $uid);
    } else {
        $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nombre_nuevo, $email_nuevo, $uid);
    }
    $stmt->execute();
}
```
**Comentario técnico:** Permite la actualización selectiva de la contraseña. Si la clave se introduce en blanco, el backend actualiza únicamente el nombre y dirección de correo. Si se detecta entrada en el campo de clave, genera un hash `BCRYPT` seguro antes de persistirlo parametrizadamente, evitando fugas de contraseñas en texto claro en base de datos.

---

## 4. Controladores de Servicios y Enlaces con Workers (Backend)

### Controlador del Lado del Servidor de Hosting (`controller_hosting.php`)
Este archivo centraliza las solicitudes del panel de control de los clientes. Funciona como un orquestador, procesando los cambios de los servicios contratados (FTP, MySQL, Dominios) y delegando las tareas de configuración del Kernel del SO a los scripts de Python.
A continuación, se documentan las rutinas y lógica de este controlador:

```php
// Ejecución segura de Scripts en Bash y Python
function ejecutar_worker(string $worker): bool {
    $permitidos = ['crear_usuarios.py', 'mysql_worker.py', 'virtualhosts.py'];
    if (!in_array($worker, $permitidos, true)) {
        return false;
    }
    $salida = [];
    $codigo = 0;
    exec("sudo python3 /opt/tfg/scripts/$worker 2>&1", $salida, $codigo);
    if ($codigo !== 0) {
        registrar_accion("[WORKER_ERROR] $worker termino con codigo $codigo.");
    }
    return $codigo === 0;
}

// Alta y Modificación de Base de Datos MySQL
function procesar_mysql(): void {
    // ...
    if ($db_existente) {
        // Comprobación de prefijo de seguridad obligatoria
        if (($submitted_name !== '' && $submitted_name !== $db_existente['db_name']) || ...) {
            volver_panel('?error=edicion_capada');
        }
        $stmt = $db->prepare("UPDATE modulo_mysql SET db_pass = ?, estado = 'Pendiente' WHERE user_id = ?");
        $stmt->bind_param('si', $raw_pass, $user_id);
    } else {
        // ...
        $stmt = $db->prepare("INSERT INTO modulo_mysql (user_id, db_name, ... estado) VALUES (?, ?, ?, ?, 'Pendiente')");
    }
    $stmt->execute();
    $db->close();
    ejecutar_worker('mysql_worker.py');
}
```
**Comentario técnico:** La función `ejecutar_worker` implementa una política estricta de mitigación frente a inyecciones de comandos del sistema operativo (OS Command Injection). Al verificar el nombre del script mediante una lista blanca de comparación estricta (`in_array(..., true)`), anula la posibilidad de ejecutar binarios arbitrarios mediante el comando `sudo exec`. 
Por otro lado, `procesar_mysql` y `procesar_ftp` actúan como colas lógicas: en lugar de intentar realizar la administración del sistema directamente (lo cual dejaría hilos colgados en el navegador de Apache), actualizan las tablas correspondientes a un estado transitorio ('Pendiente' / 'Para_Modificar') y disparan los demonios en segundo plano de Python (`mysql_worker.py` y `crear_usuarios.py`). Esto mantiene el aislamiento y asegura que las operaciones de Kernel ocurran en un subproceso controlado.

### Enrutadores de Solicitudes y Enlaces a la Cola (`procesar_ftp.php`, `procesar_mysql.php`, `procesar_dominio.php`, `procesar_staff.php` & `procesar_borrado.php`)
Ficheros ligeros que actúan como sumideros de peticiones HTTP POST procedentes del panel de control de usuarios.
A continuación, se documenta la estructura lógica del procesador de borrado de cuenta voluntario (`procesar_borrado.php`):

```php
$user_id = (int)$_SESSION['user_id'];
$db = getConexion();
$db->query("UPDATE usuarios SET estado_servicio = 'Para_Borrar', fecha_cancelacion = NOW() WHERE id = $user_id");
$db->query("INSERT INTO alertas_admin (user_id, motivo, reconocida) VALUES ($user_id, 'Solicitud borrado cuenta', 0)");
$db->close();
header('Location: panel.php?msg=borrado_pendiente');
```
**Comentario técnico:** Estos módulos intermedios evitan sobrecargar el hilo del servidor web realizando configuraciones del sistema operativo. Capturan los datos del formulario, realizan validaciones sintácticas rápidas, actualizan los estados a fases lógicas de transición en la base de datos (`Para_Borrar`, `Para_Modificar` o `Tramitando`) y levantan alertas de control del administrador, dejando la reconfiguración física de los servicios a los workers de Python.

---

## 5. Panel Administrativo, Resiliencia y Controladores de Administración

### Panel Administrativo y Suplantaciones (`controller_admin.php`)
Este archivo contiene las acciones exclusivas para el rol de administrador del sistema. Permite procesar la resolución de alertas, purgar inquilinos, forzar facturas y realizar suplantaciones de cuentas de clientes.
A continuación, se documenta la lógica de suplantación y purga del archivo:

```php
// Mecanismo de Suplantación Segura (Impersonación)
function impersonar_usuario(): void {
    // ...
    $admin_id = (int)($_SESSION['user_id'] ?? 0);
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    // ...
    $_SESSION['admin_original'] = $admin; // Guardamos el admin original
    $_SESSION['impersonando_id'] = (int)$usuario['id'];
    registrar_accion_admin("[IMPERSONACION] Admin '{$admin['nombre']}' accede como '{$usuario['nombre']}'");
    login_user_from_row($usuario); // Absorbe la identidad del cliente
    redirigir_admin('panel.php?msg=impersonando');
}

// Purga Atómica de Clientes dados de Baja
function purgar_usuario(): void {
    // ...
    // 1. Marcamos todo en cascada como 'Para_Borrar' en caliente
    $db->query("UPDATE usuarios SET estado_servicio = 'Para_Borrar' WHERE id = $user_id");
    $db->query("UPDATE dominios SET estado_dominio = 'Para_Borrar' WHERE user_id = $user_id");
    $db->query("UPDATE modulo_mysql SET estado = 'Para_Borrar' WHERE user_id = $user_id");
    
    // 2. Invocamos en caliente los scripts que purgan el hardware
    exec('sudo python3 /opt/tfg/scripts/virtualhosts.py 2>&1', $salida_vh, $retval_vh);
    exec('sudo python3 /opt/tfg/scripts/mysql_worker.py 2>&1', $salida_my, $retval_my);
    exec('sudo python3 /opt/tfg/scripts/crear_usuarios.py 2>&1', $salida_us, $retval_us);
    // ... Comprobamos códigos de retorno y confirmamos la eliminación final ...
}
```
**Comentario técnico:** El mecanismo de impersonación implementa un control defensivo de preservación de estado: almacena el array del registro del administrador en `$_SESSION['admin_original']` antes de sobreescribir las variables de sesión del usuario autenticado. Esto permite el soporte directo de fallos del cliente y proporciona una pasarela de retorno segura (`volver_admin`) que limpia las banderas temporales y restaura el contexto del administrador. 
La función `purgar_usuario` realiza una destrucción coordinada y transaccional: reescribe en caliente los registros lógicos a `Para_Borrar` y despierta sucesivamente la suite de aprovisionamiento de Python para que elimine los registros DNS en Apache, borre los esquemas físicos en MySQL y elimine el home y usuario en Linux, manteniendo los registros de auditoría consistentes y registrando detalladamente cualquier código de salida fallido.

### Consola de Operaciones de Administración (`admin_panel.php` & `acciones_masivas_alertas.php` & `marcar_alerta.php` & `reconocer_alerta.php` & `restaurar_usuario.php`)
Vistas y procesadores que gestionan la bandeja de alertas recibidas.
A continuación, se documenta la lógica de restauración manual de usuarios (`restaurar_usuario.php`):

```php
$user_id = (int)$_POST['user_id'];
$db = getConexion();
$db->query("UPDATE usuarios SET estado_servicio = 'Activo', fecha_cancelacion = NULL WHERE id = $user_id");
$db->query("INSERT INTO alertas_admin (user_id, motivo, reconocida) VALUES ($user_id, 'Restauración de cuenta por Admin', 0)");
$db->close();
header('Location: admin_panel.php?msg=restaurado');
```
**Comentario técnico:** `restaurar_usuario.php` actúa como un interruptor de emergencia. Si un usuario solicita suspender su baja, el administrador puede revertir el estado de servicio a 'Activo' directamente en base de datos antes de que el cron nocturno purgue físicamente sus ficheros en el disco, inyectando una alerta interna de auditoría para registrar el evento de reactivación.

---

## 6. Ciclo de Vida de Suscripciones y Cambios de Extras

### Modificación de Servicios y Packs Extras (`modificar_servicios.php`)
Interfaz cliente que permite añadir o disminuir capacidades.
A continuación, se documenta la lógica de bloqueo de módulos base:

```php
$res = $db->query("SELECT plan_contratado, storage_qty, multiuser_qty, extras_json FROM usuarios WHERE id = $uid LIMIT 1");
$usuario = $res->fetch_assoc();
$plan = $usuario['plan_contratado'];

$sql_php_incluido = in_array($plan, ['PROFESIONAL', 'ENTERPRISE'], true);
$dominio_incluido = ($plan === 'ENTERPRISE');
```
**Comentario técnico:** Protege la interfaz del cliente impidiendo que éste solicite el downgrade de características o complementos integrados de manera nativa en el plan base contratado (como el acceso SQL/PHP en los planes Profesional y Enterprise, o la gestión del dominio en Enterprise).

### Procesamiento de Bajas de Módulos (`procesar_cambio.php`)
Consolida la persistencia del cambio en caliente.
A continuación, se detalla la lógica de desvinculación de dominios:

```php
if ($modulo === 'domain') {
    // 1. Eliminación del extra en el JSON del perfil
    $extras_json = array_values(array_filter($extras_json, fn($m) => $m !== 'domain|15.00'));
    $extras_s = $db->real_escape_string(json_encode($extras_json));
    $db->query("UPDATE usuarios SET extras_json = '$extras_s' WHERE email = '$email_s'");
    
    // 2. Marcado en tabla dominios para eliminación DNS por virtualhosts.py
    $db->query("UPDATE dominios SET dominio_propio = NULL, estado_dominio = 'Para_Borrar' WHERE user_id = $u_id");
}
```
**Comentario técnico:** Realiza cambios lógicos transfronterizos. En el caso de dominios, no solo los retira del campo `extras_json` en la tabla `usuarios` (recalibrando el total cobrado en la siguiente renovación mensual), sino que inyecta la directiva `Para_Borrar` en la tabla relacional `dominios`, forzando a `virtualhosts.py` a borrar la configuración DNS y el fichero VirtualHost de Apache.

### Desistimiento Voluntario (`cancelar_suscripcion.php`)
Registra la solicitud de baja voluntaria del cliente en la plataforma.
A continuación, se documenta el bloque de código de marcado de baja:

```php
$stmt = $conexion->prepare("UPDATE usuarios SET estado_servicio = 'Cancelado', fecha_cancelacion = NOW() WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->close();
```
**Comentario técnico:** Registra de manera formal el desistimiento. Marca el estado como 'Cancelado' y guarda la marca de tiempo exacta en `fecha_cancelacion`. A partir de este momento, el servicio se detiene visualmente y queda a la espera del procesamiento de limpieza por parte del administrador o del cron del sistema.

---

## 7. Pasarela de Compras, Facturación y Reportes PDF

### Configuración del Servidor y Sandbox (`checkout.php`)
Mapea los costes del plan base y complementos, calculando el total antes del envío.
A continuación, se detalla la lógica del token de seguridad del proyecto web:

```php
if ($modo_proyecto_web) {
    unset($_SESSION['checkout_web_project_guard']);
    // ... Lectura del precio final aprobado por administración ...
    $_SESSION['checkout_web_project_guard'] = [
        'user_id' => $checkout_user_id,
        'project_id' => (int)$proyecto_web_checkout['id'],
        'amount' => round((float)$proyecto_web_checkout['precio_final'], 2),
        'created_at' => time(),
    ];
}
```
**Comentario técnico:** Previene manipulaciones de precio en el lado del cliente (Client-Side Price Manipulation). Al almacenar los datos en una variable de sesión encriptada en el servidor (`checkout_web_project_guard`) con marcas de tiempo relativas (`created_at`), el backend valida durante el procesamiento del pago que el importe remitido coincida exactamente con la propuesta autorizada en base de datos, desestimando peticiones alteradas o caducadas tras 30 minutos.

### Pasarela de Pago Simulada y Alta de Recursos (`procesar_pago.php`)
Este script actúa como el receptor central de la contratación. Valida tarjetas de crédito, calcula los importes con impuestos (21% IVA), actualiza los servicios adquiridos y genera las facturas digitales en formato estructurado JSON.
A continuación, se documentan las operaciones de consolidación e inserción de cobro:

```php
// Cálculo de impuestos y desglose comercial
function desglose_iva_factura(float $total): array {
    $total = round($total, 2);
    $base = round($total / 1.21, 2);
    return [$base, round($total - $base, 2), $total];
}

// Procesamiento de facturación y alta transaccional
$conexion->begin_transaction();
try {
    // ...
    // Guardado de datos fiscales
    $stmt_fiscal = $conexion->prepare('UPDATE usuarios SET nombre_fiscal = ?, documento_identidad = ?, ... WHERE id = ?');
    $stmt_fiscal->bind_param('sssi', $nombre_fiscal, $documento_identidad, $direccion_completa, $user_id);
    $stmt_fiscal->execute();
    
    // Inserción de factura con detalles en JSON
    [$base_imponible, $iva_importe, $total_factura] = desglose_iva_factura($importe_real_total);
    $detalles_json = json_encode($detalles_factura, JSON_UNESCAPED_UNICODE);
    
    $stmt_f = $conexion->prepare(
        "INSERT INTO facturas (user_id, fecha_emision, concepto, importe, base_imponible, iva_importe, detalles_json, tipo, estado) 
         VALUES (?, NOW(), ?, ?, ?, ?, ?, 'factura', 'Pagado')"
    );
    $stmt_f->bind_param("isddds", $user_id, $concepto_factura, $total_factura, $base_imponible, $iva_importe, $detalles_json);
    $stmt_f->execute();

    // Actualización de estado en el registro de usuario
    $conexion->query("UPDATE usuarios SET plan_contratado = '$plan', storage_qty = $storage_qty, ... estado_servicio = 'Activo' WHERE id = $user_id");
    
    $conexion->commit();
} catch (Throwable $e) {
    $conexion->rollback();
    redirigir_checkout_error('pago_fallido', $plan, $es_pago_proyecto_web);
}
```
**Comentario técnico:** Garantiza la atomicidad comercial de la plataforma mediante un bloque `try-catch` acotado a un contexto transaccional (`begin_transaction()`). Si se produce algún error en la escritura de los datos fiscales, la inyección del recibo o la actualización de los servicios, el Kernel de la base de datos realiza una reversión total instantánea (`rollback()`), impidiendo hilos de datos rotos. El almacenamiento del desglose se realiza mediante una columna estructurada `detalles_json` para mantener la consistencia histórica del cobro frente a cambios mutables de precios futuros.

### Facturación Periódica Cíclica (`cron_facturacion.php`)
Este script se ejecuta a través del Crontab del sistema. Analiza qué inquilinos cumplen el ciclo mensual de renovación y emite de forma desatendida las facturas de cobro automático.
A continuación, se documento la consulta de coincidencia temporal del archivo:

```php
$sql = "SELECT id, nombre, plan_contratado, extras_json, storage_qty, multiuser_qty
        FROM usuarios
        WHERE estado_servicio = 'Activo'
          AND plan_contratado != 'Ninguno'
          AND renovacion_automatica = 1
          AND DAY(fecha_alta) = DAY(CURDATE())";

$res = $db->query($sql);
// ... Bucle de iteración y cálculo de precios reales base + packs extras ...
while ($user = $res->fetch_assoc()) {
    // ... desglose_iva y registro automático de la renovación mensual ...
}
```
**Comentario técnico:** Implementa un control de facturación periódica ligero que evalúa la concordancia temporal (`DAY(fecha_alta) = DAY(CURDATE())`). Esto garantiza que los clientes reciban un único cobro recurrente el mismo día del mes en que realizaron el alta en la plataforma, automatizando la renovación comercial sin sobrecargar el hardware con hilos de cron innecesarios o lecturas masivas.

### Generación Dinámica de Facturas en PDF (`descargar_factura.php`)
Este archivo extrae la información fiscal de la base de datos y utiliza la librería externa FPDF para ensamblar y forzar la descarga de la factura en formato digital.
A continuación, se documenta el constructor de cabeceras de este módulo:

```php
// Control de autorización del inquilino
$user_id = (int)$_SESSION['user_id'];
$rol = $_SESSION['rol'] ?? 'usuario';
$id_factura = (int)($_GET['id'] ?? 0);

$query = "SELECT * FROM facturas WHERE id = $id_factura LIMIT 1";
$res = $conexion->query($query);
$factura = $res->fetch_assoc();

if (!$factura || ($factura['user_id'] != $user_id && $rol !== 'admin')) {
    die("Acceso denegado o factura inexistente.");
}

// Configuración de respuesta HTTP del flujo PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="factura_'.$id_factura.'.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
// ... Inicialización de la clase PDF extends FPDF y renderizado de celdas ...
```
**Comentario técnico:** Implementa una política de seguridad perimetral a nivel de registros privados. Antes de generar el flujo binario del archivo PDF, el script valida de forma cruzada que la propiedad del ID del cliente de la factura coincida estrictamente con el identificador del usuario autenticado en la sesión (`$_SESSION['user_id']`), exceptuando a usuarios con rol 'admin'. Esto mitiga incidentes de lectura no autorizada de información confidencial (Insecure Direct Object Reference - IDOR).

### Historial de Recibos y Fiscalidad (`facturas.php` & `perfil_facturacion.php`)
Vistas cliente que permiten revisar el desglose contable e interactuar con la renovación del plan.
A continuación, se detalla la lógica de control de la renovación automática (`facturas.php`):

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_renovacion'])) {
    $nuevo_estado = (int)$_POST['renovacion_automatica'];
    $stmt = $conexion->prepare("UPDATE usuarios SET renovacion_automatica = ? WHERE id = ?");
    $stmt->bind_param("ii", $nuevo_estado, $user_id);
    $stmt->execute();
}
```
**Comentario técnico:** Permite al inquilino activar o desactivar la renovación automática del hosting. Esta operación modifica de forma parametrizada el campo booleano `renovacion_automatica`, el cual lee el script de cron nocturno para decidir si emite un nuevo cobro al cumplirse el ciclo mensual o detiene la cuenta al expirar.

---

## 8. Chat de Soporte, Presupuestos IA y Flujo SSE

### Presupuesto Guiado por IA (`presupuestos.php`)
Este componente administra el cuestionario guiado interactivo por JavaScript en el frontend y realiza el guardado del estado de la propuesta en el servidor.
A continuación, se detalla la lógica de transición y control de datos:

```javascript
function processStep() {
    let msg = "";
    if (currentStep === 1) {
        let showcaseVal = document.querySelector('input[name="showcase_q"]:checked')?.value;
        cuestionario.showcase = showcaseVal === "si" ? "Sí, deseo aparecer" : "No, mantener privado";
        msg = "¿Qué estilo de colores prefieres?";
        // ... Avanza de paso y renderiza los botones de opción en el chat ...
    }
    // ...
}
```
**Comentario técnico:** Centraliza el flujo de respuestas secuenciales del cliente, evitando el envío de formularios desestructurados y guardando temporalmente los datos fiscales y las preferencias estéticas en variables de JavaScript. Al finalizar, empaqueta el payload completo y lo transmite mediante el controlador del chat al backend.

### Almacenamiento de Mensajería del Chat (`send_chat.php` & `procesar_chat.php`)
Ficheros encargados de persistir los mensajes que se intercambian durante la solicitud del presupuesto de diseño web.
A continuación, se documenta el bloque de inserción de mensajes en send_chat.php:

```php
$conexion = getConexion();
$stmt = $conexion->prepare("INSERT INTO mensajes_chat (user_id, emisor, mensaje, leido) VALUES (?, ?, ?, 0)");
$stmt->bind_param("iss", $user_id, $emisor, $mensaje);
$stmt->execute();
$stmt->close();
```
**Comentario técnico:** Garantiza la persistencia segura de la conversación. Utiliza sentencias preparadas para prevenir inyecciones SQL y marca por defecto los mensajes entrantes con `leido = 0` (no leído) para que el demonio SSE los pueda detectar y notificar instantáneamente al otro extremo de la conversación.

### Transmisión persistente por Server-Sent Events (`get_chat_sse.php`)
Este script mantiene un socket virtual abierto con el navegador cliente para inyectarle las respuestas de soporte o presupuestos del chat de forma instantánea.
A continuación, se documenta el bucle persistente del archivo:

```php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Evita almacenamiento en búfer de Nginx/Apache

// Bucle infinito controlado de transmisión SSE
while (true) {
    // ... Consulta de mensajes no leídos del usuario ...
    $stmt = $conexion->prepare("SELECT id, emisor, mensaje, fecha FROM mensajes_chat WHERE user_id = ? AND leido = 0");
    // ...
    if ($mensajes) {
        foreach ($mensajes as $msg) {
            echo "data: " . json_encode($msg, JSON_UNESCAPED_UNICODE) . "\n\n";
        }
        ob_flush(); flush(); // Fuerza el vaciado del búfer de salida al navegador
    }
    sleep(1); // Latencia de control para evitar denegación de servicio en la CPU
}
```
**Comentario técnico:** Utiliza la tecnología **SSE** (Server-Sent Events) para proporcionar actualizaciones bidireccionales en tiempo real sin recurrir a consultas continuas AJAX de tipo polling corto (que saturarían el servidor web con cientos de conexiones TCP recurrentes). Las cabeceras desactivan explícitamente el almacenamiento intermedio en cachés intermedias, y las llamadas de `ob_flush()` y `flush()` obligan a PHP a vaciar su búfer interno enviando los bytes del mensaje JSON de forma inmediata al cliente. La instrucción `sleep(1)` actúa como regulador limitando las consultas del bucle a una por segundo, garantizando que el consumo de hilos del procesador se mantenga estable.

### Desistimiento de Servicios de Diseño Web (`solicitar_reembolso_web.php`)
Este módulo permite al cliente acogerse a la garantía legal de reembolso de 30 días fijada para el desarrollo de la web por inteligencia artificial, emitiendo una factura rectificativa con importes negativos.
A continuación, se documenta la rutina del desistimiento:

```php
$conexion->begin_transaction();
try {
    // 1. Inserción de Factura Rectificativa (Signo negativo)
    $importe_negativo = -$proyecto['precio_final'];
    [$base_neg, $iva_neg, $total_neg] = desglose_iva_factura($importe_negativo);
    $concepto = "Reembolso / Abono Factura de Diseño Web";
    
    $stmt = $conexion->prepare(
        "INSERT INTO facturas (user_id, fecha_emision, concepto, ... ) 
         VALUES (?, NOW(), ?, ?, ?, ?, 'rectificativa', 'Reembolsado')"
    );
    $stmt->bind_param("isddd", $user_id, $concepto, $total_neg, $base_neg, $iva_neg);
    $stmt->execute();

    // 2. Revocación de permisos en el registro de usuario
    // ... json_decode, remoción de 'web_ai|100.00' de extras_json, y guardado ...
    
    $conexion->commit();
} catch (Throwable $e) {
    $conexion->rollback();
    // ... respuesta con error AJAX ...
}
```
**Comentario técnico:** Asegura el cumplimiento de las políticas contables mediante la inyección estructurada de un recibo de tipo `rectificativa` con valor neto negativo, lo que permite deducir los impuestos devengados en el balance fiscal general. El flujo de reversión restaura el estado del proyecto en `proyectos_diseno_web` a 'reembolsado' y reescribe la columna `extras_json` en la tabla `usuarios` eliminando el módulo comprado, garantizando la consistencia lógica y comercial del perfil del cliente tras confirmarse el reembolso.

---

## 9. Monitorización, Rendimiento y Vistas de Presentación

### Panel de Control de Clientes (`panel.php`)
Este archivo contiene la interfaz central y las llamadas dinámicas para la visualización de los recursos del hosting del usuario.
A continuación, se documenta la función de cálculo recursivo del espacio consumido por la página web del inquilino:

```php
function get_folder_size(string $dir): int {
    $size = 0;
    if (!is_dir($dir)) {
        return 0;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        try {
            $size += $file->getSize();
        } catch (Exception $e) {
            // Ignoramos ficheros en tránsito o inaccesibles
        }
    }
    return $size;
}
```
**Comentario técnico:** Calcula en tiempo real y desde el lado del servidor el espacio físico consumido por la carpeta web de un usuario. Utiliza las clases orientadas a objetos nativas `RecursiveDirectoryIterator` y `RecursiveIteratorIterator` para recorrer recursivamente el árbol de subdirectorios, capturando de forma controlada excepciones producidas si un archivo es modificado o borrado por el usuario vía FTP durante la ejecución del barrido, garantizando un cálculo consistente en el navegador del inquilino.

### Estado Físico del Servidor (`monitorizacion.php`)
Este módulo del área de administración captura las bitácoras y estadísticas del hardware de la máquina.
A continuación, se documenta la función de cacheado del archivo:

```php
function monitor_cached(string $clave, int $segundos, callable $callback) {
    $archivo_cache = "/tmp/monitor_cache_" . md5($clave) . ".tmp";
    if (file_exists($archivo_cache) && (time() - filemtime($archivo_cache)) < $segundos) {
        return file_get_contents($archivo_cache);
    }
    $datos = $callback();
    file_put_contents($archivo_cache, $datos);
    return $datos;
}
```
**Comentario técnico:** Implementa un sistema de almacenamiento temporal (cacheado de archivos locales) para la información de monitorización física. La ejecución en el Kernel de comandos de consola pesados como `top`, `df` o la inspección de bitácoras del sistema en caliente consume recursos valiosos de CPU si múltiples administradores consultan el panel simultáneamente. Al cachear la salida de forma cifrada temporal en `/tmp` con una validez en segundos, el backend reduce drásticamente el consumo y la latencia del servidor.

### Vistas Públicas de Presentación (`index.php`, `inicio.php`, `galeria.php` & `includes/header.php` & `includes/footer.php`)
Representan las plantillas dinámicas y la landing page del servicio.
A continuación, se detalla la inclusión y maquetación de cabeceras seguras (`includes/header.php`):

```php
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($titulo_pagina ?? 'VinoMadrid Hosting'); ?></title>
    <link rel="stylesheet" href="estilos.css">
    <!-- ... -->
```
**Comentario técnico:** Centralizan el diseño visual responsive de la aplicación mediante Vanilla CSS. Inyectan elementos comunes y aplican la función de saneamiento `htmlspecialchars` al renderizar dinámicamente variables externas (como `$titulo_pagina`) en el HTML, previniendo inyecciones de código cruzado (XSS reflejado) en el navegador del visitante.