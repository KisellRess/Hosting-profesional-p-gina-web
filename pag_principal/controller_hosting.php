<?php
/* ============================================================
   ARCHIVO: controller_hosting.php
   FUNCION: procesar cambios de servicios del cliente.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

/* SECCION 1: seguridad de acceso y formato de respuesta (pagina o JSON). */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/sessions.php';
require_auth();
require_once __DIR__ . '/conexiones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel.php');
    exit;
}

function es_peticion_ajax(): bool {
    $requested_with = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');

    return $requested_with === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

function volver_panel(string $query = ''): void {
    if (es_peticion_ajax()) {
        parse_str(ltrim($query, '?'), $params);
        $hay_error = isset($params['error']);
        $codigo = $params['error'] ?? $params['status'] ?? $params['ok'] ?? 'ok';

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($hay_error ? 422 : 200);
        echo json_encode([
            'ok' => !$hay_error,
            'code' => $codigo,
            'redirect' => 'panel.php' . $query,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: panel.php' . $query);
    exit;
}

/* SECCION 2: registro de acciones, alertas y ejecucion controlada de workers. */
function registrar_accion(string $message): void {
    $log_dir = '/opt/tfg/scripts/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $date_str = date('Y-m-d H:i:s');
    @file_put_contents("$log_dir/acciones.log", "[$date_str] $message\n", FILE_APPEND);
}

function registrar_alerta(mysqli $db, int $user_id, string $nombre, string $motivo, string $simbolo): void {
    try {
        $stmt = $db->prepare(
            'INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha)
             VALUES (?, ?, ?, ?, 0, NOW())'
        );
        $stmt->bind_param('isss', $user_id, $nombre, $motivo, $simbolo);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // Una alerta es informativa: no debe convertir una gestion aplicada en fallo.
        registrar_accion("[ALERTA_ERROR] No se pudo guardar alerta de usuario ID $user_id: " . $e->getMessage());
        error_log('[controller_hosting:alerta] ' . $e->getMessage());
    }
}

function ejecutar_worker(string $worker): bool {
    $permitidos = ['crear_usuarios.py', 'mysql_worker.py', 'virtualhosts.py'];
    if (!in_array($worker, $permitidos, true)) {
        return false;
    }

    $salida = [];
    $codigo = 0;
    exec("sudo python3 /opt/tfg/scripts/$worker 2>&1", $salida, $codigo);
    if ($codigo !== 0) {
        $detalle = implode(' | ', array_slice($salida, -3));
        registrar_accion("[WORKER_ERROR] $worker termino con codigo $codigo. $detalle");
    }

    return $codigo === 0;
}

function obtener_nombre_usuario(mysqli $db, int $user_id): string {
    $stmt = $db->prepare('SELECT nombre FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row['nombre'] ?? 'Usuario';
}

function password_mysql_segura(string $password): bool {
    // El panel exige la misma politica que el worker antes de crear el usuario fisico.
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9\s]/', $password)
        && !preg_match('/\s/', $password);
}

/* SECCION 3: modulo de base de datos MySQL contratado por el cliente. */
function procesar_mysql(): void {
    $db = null;

    try {
        $db = getConexion();
        $user_id = (int)$_SESSION['user_id'];

        $stmt = $db->prepare('SELECT estado_servicio FROM usuarios WHERE id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user_data || $user_data['estado_servicio'] === 'Para_Borrar') {
            $db->close();
            volver_panel('?error=servicio_baja');
        }

        $stmt = $db->prepare('SELECT id, db_name, db_user FROM modulo_mysql WHERE user_id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $db_existente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $raw_pass = trim($_POST['db_pass'] ?? '');
        $es_creacion = false;

        if ($raw_pass !== '' && !password_mysql_segura($raw_pass)) {
            $db->close();
            volver_panel('?error=mysql_password_insegura');
        }

        if ($db_existente) {
            $prefijo = 'u' . $user_id . '_';
            $raw_name_post = strtolower(trim($_POST['db_name'] ?? ''));
            $raw_user_post = strtolower(trim($_POST['db_user'] ?? ''));

            if (strpos($raw_name_post, $prefijo) === 0) {
                $submitted_name = $prefijo . preg_replace('/[^a-z0-9_]/', '', substr($raw_name_post, strlen($prefijo)));
            } else {
                $submitted_name = $raw_name_post !== ''
                    ? $prefijo . preg_replace('/[^a-z0-9_]/', '', $raw_name_post)
                    : '';
            }

            if (strpos($raw_user_post, $prefijo) === 0) {
                $submitted_user = $prefijo . preg_replace('/[^a-z0-9_]/', '', substr($raw_user_post, strlen($prefijo)));
            } else {
                $submitted_user = $raw_user_post !== ''
                    ? $prefijo . preg_replace('/[^a-z0-9_]/', '', $raw_user_post)
                    : '';
            }

            if (($submitted_name !== '' && $submitted_name !== $db_existente['db_name'])
                || ($submitted_user !== '' && $submitted_user !== $db_existente['db_user'])) {
                $db->close();
                volver_panel('?error=edicion_capada');
            }

            if ($raw_pass === '') {
                $db->close();
                volver_panel('?status=sin_cambios');
            }

            /* El worker reaplica la clave al procesar una base ya existente en estado Pendiente. */
            $stmt = $db->prepare("UPDATE modulo_mysql SET db_pass = ?, estado = 'Pendiente' WHERE user_id = ?");
            $stmt->bind_param('si', $raw_pass, $user_id);
        } else {
            $raw_name = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['db_name'] ?? ''));
            $raw_user = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['db_user'] ?? ''));

            if ($raw_name === '' || $raw_user === '') {
                $db->close();
                volver_panel('?error=mysql_vacio');
            }
            if ($raw_pass === '') {
                $db->close();
                volver_panel('?error=mysql_password_insegura');
            }

            $db_name_s = $raw_name;
            $db_user_s = $raw_user;

            $check_db = $db->prepare('SELECT id FROM modulo_mysql WHERE db_name = ?');
            $check_db->bind_param('s', $raw_name);
            $check_db->execute();
            if ($check_db->get_result()->num_rows > 0) {
                $db_name_s = $raw_name . '_u' . $user_id;
            }
            $check_db->close();

            $check_user = $db->prepare('SELECT id FROM modulo_mysql WHERE db_user = ?');
            $check_user->bind_param('s', $raw_user);
            $check_user->execute();
            if ($check_user->get_result()->num_rows > 0) {
                $db_user_s = $raw_user . '_u' . $user_id;
            }
            $check_user->close();

            $stmt = $db->prepare(
                "INSERT INTO modulo_mysql (user_id, db_name, db_user, db_pass, estado)
                 VALUES (?, ?, ?, ?, 'Pendiente')"
            );
            $stmt->bind_param('isss', $user_id, $db_name_s, $db_user_s, $raw_pass);
            $es_creacion = true;
        }

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            registrar_accion("[MYSQL_ERROR] Error SQL al procesar MySQL para usuario ID $user_id: $error");
            error_log("[controller_hosting:mysql] Error SQL execute: $error | user_id=$user_id");
            $db->close();
            volver_panel('?error=db_error');
        }
        $stmt->close();

        $u_nom = obtener_nombre_usuario($db, $user_id);
        if ($es_creacion) {
            registrar_accion("[MYSQL_CREAR] Usuario '$u_nom' (ID: $user_id) activo MySQL. DB: $db_name_s, User: $db_user_s");
            registrar_alerta($db, $user_id, $u_nom, "Usuario '$u_nom' activó base de datos MySQL (DB: $db_name_s, User: $db_user_s)", '🗄️');
        } else {
            registrar_accion("[MYSQL_EDITAR] Usuario '$u_nom' (ID: $user_id) actualizó su contraseña de MySQL");
            registrar_alerta($db, $user_id, $u_nom, "Usuario '$u_nom' modificó la contraseña de su base de datos MySQL", '🗄️');
        }

        $db->close();
        ejecutar_worker('mysql_worker.py');
        volver_panel('?status=provisioning');
    } catch (Throwable $e) {
        if ($db instanceof mysqli) {
            $db->close();
        }
        error_log('[controller_hosting:mysql] Excepcion inesperada: ' . $e->getMessage());
        volver_panel('?error=db_excepcion');
    }
}

/* SECCION 4: credenciales de la cuenta FTP principal del cliente. */
function procesar_ftp(): void {
    $db = null;

    try {
        $db = getConexion();
        $user_id = (int)$_SESSION['user_id'];
        $raw_ftp_user = str_replace(' ', '_', strtolower(trim($_POST['ftp_user'] ?? '')));
        $ftp_user = preg_replace('/[^a-z0-9_]/', '', $raw_ftp_user);
        $ftp_pass = trim($_POST['ftp_pass'] ?? '');

        if ($ftp_user === '') {
            $db->close();
            volver_panel('?error=ftp_vacio');
        }

        $stmt = $db->prepare('SELECT ftp_user, ftp_pass FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$current) {
            $db->close();
            volver_panel('?error=usuario_no_encontrado');
        }

        $name_changed = ($current['ftp_user'] ?? '') !== $ftp_user;
        $pass_changed = $ftp_pass !== '' && ($current['ftp_pass'] ?? '') !== $ftp_pass;

        if (!$name_changed && !$pass_changed) {
            $db->close();
            volver_panel('?ok=ftp_pendiente');
        }

        if ($ftp_pass === '') {
            $stmt = $db->prepare(
                "UPDATE usuarios SET ftp_user = ?, creado_en_so = 0, estado_servicio = 'Para_Modificar' WHERE id = ?"
            );
            $stmt->bind_param('si', $ftp_user, $user_id);
        } else {
            $stmt = $db->prepare(
                "UPDATE usuarios SET ftp_user = ?, ftp_pass = ?, creado_en_so = 0, estado_servicio = 'Para_Modificar' WHERE id = ?"
            );
            $stmt->bind_param('ssi', $ftp_user, $ftp_pass, $user_id);
        }

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            registrar_accion("[FTP_ERROR] Error SQL al actualizar FTP para usuario (ID: $user_id): $error");
            error_log("[controller_hosting:ftp] Error SQL UPDATE: $error | user_id=$user_id");
            $db->close();
            volver_panel('?error=ftp_error');
        }
        $stmt->close();

        $u_nom = $_SESSION['usuario'] ?? 'Usuario';
        registrar_accion(
            "[FTP_CAMBIO] Usuario (ID: $user_id) actualizó su FTP a: $ftp_user. Cambios: Nombre="
            . ($name_changed ? 'SI' : 'NO') . ', Pass=' . ($pass_changed ? 'SI' : 'NO')
        );
        registrar_alerta($db, $user_id, $u_nom, "Usuario '$u_nom' actualizó sus credenciales FTP (User: $ftp_user)", '⚙️');
        $db->close();
        ejecutar_worker('crear_usuarios.py');
        volver_panel('?status=provisioning');
    } catch (Throwable $e) {
        if ($db instanceof mysqli) {
            $db->close();
        }
        error_log('[controller_hosting:ftp] Excepcion inesperada: ' . $e->getMessage());
        volver_panel('?error=ftp_excepcion');
    }
}

/* SECCION 5: cuentas FTP adicionales para miembros del equipo. */
function procesar_staff(): void {
    $db = null;

    try {
        $db = getConexion();
        $user_id = (int)$_SESSION['user_id'];
        $accion = $_POST['accion'] ?? '';

        switch ($accion) {
            case 'crear_staff':
                $nombre = preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', strtolower($_POST['nombre_staff'] ?? '')));
                $password = $_POST['pass_staff'] ?? '';

                if (strlen($nombre) < 3 || strlen($password) < 6) {
                    $db->close();
                    volver_panel('?error=datos_invalidos');
                }

                $stmt = $db->prepare('SELECT id FROM ftp_cuentas_extra WHERE ftp_user = ?');
                $stmt->bind_param('s', $nombre);
                $stmt->execute();
                $en_staff = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                $stmt = $db->prepare('SELECT id FROM usuarios WHERE ftp_user = ?');
                $stmt->bind_param('s', $nombre);
                $stmt->execute();
                $en_usuario = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                $ftp_user_final = ($en_staff || $en_usuario) ? $nombre . '_u' . $user_id : $nombre;

                $stmt = $db->prepare('SELECT id FROM ftp_cuentas_extra WHERE ftp_user = ?');
                $stmt->bind_param('s', $ftp_user_final);
                $stmt->execute();
                $ya_existe = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                if ($ya_existe) {
                    $db->close();
                    volver_panel('?error=usuario_existente');
                }

                $stmt = $db->prepare('SELECT ftp_user, nombre FROM usuarios WHERE id = ?');
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $owner = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $owner_ftp = $owner['ftp_user'] ?? '';
                $u_nom = $owner['nombre'] ?? 'Usuario';

                if ($owner_ftp === '') {
                    $db->close();
                    volver_panel('?error=owner_ftp_missing');
                }

                $stmt = $db->prepare(
                    "INSERT INTO ftp_cuentas_extra (user_id, ftp_user, ftp_pass, owner_ftp, estado)
                     VALUES (?, ?, ?, ?, 'Pendiente')"
                );
                $stmt->bind_param('isss', $user_id, $ftp_user_final, $password, $owner_ftp);
                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    registrar_accion("[STAFF_CREAR_ERROR] Error SQL al insertar staff '$ftp_user_final' para usuario ID $user_id: $error");
                    error_log("[controller_hosting:staff] Error SQL INSERT: $error | user_id=$user_id");
                    $db->close();
                    volver_panel('?error=db_error');
                }
                $stmt->close();

                registrar_accion("[STAFF_CREAR] Usuario '$u_nom' (ID: $user_id) solicitó la creación del staff '$ftp_user_final'");
                registrar_alerta($db, $user_id, $u_nom, "Usuario '$u_nom' solicitó crear cuenta Staff '$ftp_user_final'", '👥');
                $db->close();
                ejecutar_worker('crear_usuarios.py');
                volver_panel('?status=provisioning');

            case 'editar_staff':
                $staff_id = (int)($_POST['staff_id'] ?? 0);
                $nombre = preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', strtolower($_POST['nombre_staff'] ?? '')));
                $password = $_POST['pass_staff'] ?? '';

                if ($staff_id <= 0 || strlen($nombre) < 3) {
                    $db->close();
                    volver_panel('?error=datos_invalidos');
                }

                $stmt = $db->prepare('SELECT ftp_user, ftp_pass, owner_ftp FROM ftp_cuentas_extra WHERE id = ? AND user_id = ? LIMIT 1');
                $stmt->bind_param('ii', $staff_id, $user_id);
                $stmt->execute();
                $current = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$current) {
                    $db->close();
                    volver_panel('?error=staff_no_encontrado');
                }

                $stmt = $db->prepare('SELECT id FROM ftp_cuentas_extra WHERE ftp_user = ? AND id != ?');
                $stmt->bind_param('si', $nombre, $staff_id);
                $stmt->execute();
                $en_staff = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                $stmt = $db->prepare('SELECT id FROM usuarios WHERE ftp_user = ?');
                $stmt->bind_param('s', $nombre);
                $stmt->execute();
                $en_usuario = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                $ftp_user_final = ($en_staff || $en_usuario) ? $nombre . '_u' . $user_id : $nombre;
                $name_changed = $current['ftp_user'] !== $ftp_user_final;
                $pass_changed = $password !== '' && $current['ftp_pass'] !== $password;

                if (!$name_changed && !$pass_changed) {
                    $db->close();
                    volver_panel('?ok=ftp_pendiente');
                }

                $u_nom = obtener_nombre_usuario($db, $user_id);
                if ($name_changed) {
                    $stmt = $db->prepare('SELECT id FROM ftp_cuentas_extra WHERE ftp_user = ? AND id != ?');
                    $stmt->bind_param('si', $ftp_user_final, $staff_id);
                    $stmt->execute();
                    $duplicado = $stmt->get_result()->num_rows > 0;
                    $stmt->close();
                    if ($duplicado) {
                        $db->close();
                        volver_panel('?error=usuario_existente');
                    }

                    $final_pass = $pass_changed ? $password : $current['ftp_pass'];
                    $stmt = $db->prepare("UPDATE ftp_cuentas_extra SET estado = 'Para_Borrar' WHERE id = ? AND user_id = ?");
                    $stmt->bind_param('ii', $staff_id, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $db->prepare(
                        "INSERT INTO ftp_cuentas_extra (user_id, ftp_user, ftp_pass, owner_ftp, estado)
                         VALUES (?, ?, ?, ?, 'Pendiente')"
                    );
                    $stmt->bind_param('isss', $user_id, $ftp_user_final, $final_pass, $current['owner_ftp']);
                } else {
                    $stmt = $db->prepare("UPDATE ftp_cuentas_extra SET ftp_pass = ?, estado = 'Para_Modificar' WHERE id = ? AND user_id = ?");
                    $stmt->bind_param('sii', $password, $staff_id, $user_id);
                }

                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    registrar_accion("[STAFF_EDITAR_ERROR] Error SQL al editar staff '{$current['ftp_user']}' para usuario ID $user_id: $error");
                    $db->close();
                    volver_panel('?error=db_error');
                }
                $stmt->close();

                registrar_accion(
                    "[STAFF_EDITAR] Usuario '$u_nom' (ID: $user_id) editó el staff '{$current['ftp_user']}' "
                    . "(nuevo nombre: '$ftp_user_final'). Cambios: Nombre=" . ($name_changed ? 'SI' : 'NO')
                    . ', Pass=' . ($pass_changed ? 'SI' : 'NO')
                );
                registrar_alerta($db, $user_id, $u_nom, "Usuario '$u_nom' editó cuenta Staff '{$current['ftp_user']}' (nuevo: '$ftp_user_final')", '👥');
                $db->close();
                ejecutar_worker('crear_usuarios.py');
                volver_panel('?status=provisioning');

            case 'borrar_staff':
                $staff_id = (int)($_POST['staff_id'] ?? 0);
                if ($staff_id <= 0) {
                    $db->close();
                    volver_panel('?error=staff_id_invalido');
                }

                $stmt = $db->prepare('SELECT ftp_user FROM ftp_cuentas_extra WHERE id = ? AND user_id = ? LIMIT 1');
                $stmt->bind_param('ii', $staff_id, $user_id);
                $stmt->execute();
                $staff = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $staff_user = $staff['ftp_user'] ?? "ID $staff_id";
                $u_nom = obtener_nombre_usuario($db, $user_id);

                $stmt = $db->prepare("UPDATE ftp_cuentas_extra SET estado = 'Para_Borrar' WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $staff_id, $user_id);
                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    registrar_accion("[STAFF_BORRAR_ERROR] Error SQL al borrar staff '$staff_user' para usuario ID $user_id: $error");
                    $db->close();
                    volver_panel('?error=db_error');
                }
                $stmt->close();

                registrar_accion("[STAFF_BORRAR] Usuario '$u_nom' (ID: $user_id) solicitó borrar el staff '$staff_user'");
                registrar_alerta($db, $user_id, $u_nom, "Usuario '$u_nom' eliminó la cuenta Staff '$staff_user'", '👥');
                $db->close();
                ejecutar_worker('crear_usuarios.py');
                volver_panel('?status=provisioning');

            default:
                $db->close();
                volver_panel();
        }
    } catch (Throwable $e) {
        $uid_error = (int)($_SESSION['user_id'] ?? 0);
        registrar_accion("[STAFF_EXCEPCION] Usuario ID $uid_error: " . $e->getMessage());
        error_log('[controller_hosting:staff] Excepcion inesperada: ' . $e->getMessage());
        if ($db instanceof mysqli) {
            try {
                $db->close();
            } catch (Throwable $close_error) {
                // La conexion puede haberse cerrado durante el trabajo de provision.
            }
        }
        volver_panel('?error=staff_excepcion');
    }
}

/* SECCION 6: subdominio o dominio propio del alojamiento. */
function procesar_dominio(): void {
    $db = getConexion();
    $user_id = (int)$_SESSION['user_id'];
    $accion = $_POST['accion'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $procesar_virtualhost = false;

    $stmt = $db->prepare('SELECT id FROM dominios WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $fila_existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    $sql = null;
    $types = '';

    switch ($accion) {
        case 'cambiar_subdominio':
            $sql = $fila_existe
                ? "UPDATE dominios SET subdominio_alias = ?, estado_dominio = 'Tramitando' WHERE user_id = ?"
                : "INSERT INTO dominios (subdominio_alias, estado_dominio, user_id) VALUES (?, 'Tramitando', ?)";
            $types = 'si';
            break;
        case 'conectar_dominio':
        case 'comprar_dominio':
            $sql = $fila_existe
                ? "UPDATE dominios SET dominio_propio = ?, estado_dominio = 'Tramitando' WHERE user_id = ?"
                : "INSERT INTO dominios (dominio_propio, estado_dominio, user_id) VALUES (?, 'Tramitando', ?)";
            $types = 'si';
            break;
        case 'desvincular_dominio':
            if ($fila_existe) {
                $sql = "UPDATE dominios SET dominio_propio = NULL, estado_dominio = 'Para_Borrar' WHERE user_id = ?";
                $types = 'i';
            }
            break;
        default:
            $db->close();
            volver_panel('?error=accion_invalida');
    }

    if ($sql !== null) {
        $stmt = $db->prepare($sql);
        if ($types === 'si') {
            $stmt->bind_param('si', $valor, $user_id);
        } else {
            $stmt->bind_param('i', $user_id);
        }
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            error_log("[controller_hosting:dominio] ERROR: $error");
            $db->close();
            volver_panel('?error=db_error');
        }
        $stmt->close();

        $motivo = "Gestión Dominio: $accion";
        $stmt = $db->prepare('SELECT id FROM alertas_admin WHERE user_id = ? AND motivo = ? AND reconocida = 0 LIMIT 1');
        $stmt->bind_param('is', $user_id, $motivo);
        $stmt->execute();
        $alerta_existe = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$alerta_existe) {
            registrar_alerta($db, $user_id, $_SESSION['usuario'] ?? 'Usuario', $motivo, '❓');
        }

        $procesar_virtualhost = true;
    }

    $db->close();
    if ($procesar_virtualhost) {
        ejecutar_worker('virtualhosts.py');
    }
    volver_panel('?ok=dominio_actualizado');
}

/* SECCION 7: enrutado sencillo del modulo enviado desde panel.php. */
$modulo = $_GET['modulo'] ?? '';
switch ($modulo) {
    case 'mysql':
        procesar_mysql();
        break;
    case 'ftp':
        procesar_ftp();
        break;
    case 'staff':
        procesar_staff();
        break;
    case 'dominio':
        procesar_dominio();
        break;
    default:
        volver_panel('?error=accion_invalida');
}