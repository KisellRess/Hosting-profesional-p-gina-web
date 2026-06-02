"""ARCHIVO: crear_usuarios.py
FUNCION: aplicar altas, cambios y bajas de cuentas FTP en Linux.
SECCIONES: utilidades de nombres, operaciones del sistema y cola pendiente.
"""

import subprocess
import os
import re
import datetime
import traceback
import secrets
from tfg_lib import DB, Logger, lock
import shutil
import pwd

LISTA_FTP  = "/etc/ftp/listaUsuarioAceptados"
BASE_WEB   = "/var/www/hosting_tfg"

logger = Logger.get_logger("UserProvisioning", "creacion_usuarios")

def sanitizar_nombre(nombre):
    return re.sub(r'[^a-z0-9]', '', nombre.lower().replace(" ", ""))

def usuario_existe_en_so(username):
    return subprocess.run(["id", "-u", username], capture_output=True).returncode == 0

def home_canonico(folder_web):
    return os.path.join(BASE_WEB, folder_web)

def obtener_home_linux(username):
    try:
        return pwd.getpwnam(username).pw_dir
    except KeyError:
        return None
    except Exception as e:
        logger.warning(f"Error leyendo home de {username}: {e}")
        return None

def obtener_usuario_por_home(home_dir):
    try:
        for entry in pwd.getpwall():
            if entry.pw_dir == home_dir:
                return entry.pw_name
    except Exception as e:
        logger.warning(f"Error buscando usuario por home {home_dir}: {e}")
    return None

def obtener_usuario_antiguo(u_id, nuevo_nombre):
    """Localiza el login anterior solo con una asociacion verificable."""
    try:
        # Las cuentas staff conservan el owner anterior hasta terminar el cambio.
        with DB() as (conn, cur):
            cur.execute(
                "SELECT DISTINCT owner_ftp FROM ftp_cuentas_extra "
                "WHERE user_id = %s AND owner_ftp IS NOT NULL AND owner_ftp != %s",
                (u_id, nuevo_nombre)
            )
            for row in cur.fetchall():
                candidato = row['owner_ftp']
                if candidato and usuario_existe_en_so(candidato):
                    return candidato

            cur.execute(
                "SELECT ftp_user FROM usuarios WHERE id != %s AND ftp_user IS NOT NULL",
                (u_id,)
            )
            reservados = {row['ftp_user'] for row in cur.fetchall()}

        # Compatibilidad con cuentas antiguas sin staff: solo se acepta una
        # unica carpeta canonica huerfana; con ambiguedad no se renombra nada.
        candidatos = []
        for entry in pwd.getpwall():
            if (entry.pw_name != nuevo_nombre
                    and entry.pw_name not in reservados
                    and entry.pw_dir == home_canonico(entry.pw_name)
                    and os.path.isdir(entry.pw_dir)):
                candidatos.append(entry.pw_name)
        if len(candidatos) == 1:
            logger.warning(f"Renombrado asociado por unica carpeta huerfana: {candidatos[0]}.")
            return candidatos[0]
        if len(candidatos) > 1:
            logger.error("No se puede determinar el usuario anterior: hay varios homes huerfanos.")
    except Exception as e:
        logger.warning(f"Error buscando usuario anterior: {e}")
    return None

def normalizar_home_usuario(username, folder_web):
    """Alinea /etc/passwd y la carpeta fisica sin fusionar directorios distintos."""
    esperado = home_canonico(folder_web)
    actual = obtener_home_linux(username)
    if actual is None or actual == esperado:
        return esperado
    if actual.startswith(BASE_WEB + os.sep) and not os.path.exists(actual) and os.path.isdir(esperado):
        try:
            # Reparacion de cuentas ya incoherentes: apuntar al home fisico existente.
            subprocess.run(["usermod", "-d", esperado, username], check=True)
            logger.info(f"  ✓ Home de {username} corregido en /etc/passwd: {esperado}")
            return esperado
        except Exception as e:
            logger.error(f"No se pudo corregir el home registrado de {username}: {e}")
            return None
    if not actual.startswith(BASE_WEB + os.sep) or not os.path.isdir(actual):
        logger.error(f"Home no valido para {username}: {actual}. No se modifica.")
        return None
    if os.path.exists(esperado):
        logger.error(f"Conflicto de homes para {username}: existen {actual} y {esperado}.")
        return None
    try:
        # -m mueve el home real al destino a la vez que se actualiza /etc/passwd.
        subprocess.run(["usermod", "-d", esperado, "-m", username], check=True)
        logger.info(f"  ✓ Home reparado para {username}: {actual} -> {esperado}")
        return esperado
    except Exception as e:
        logger.error(f"No se pudo normalizar el home de {username}: {e}")
        return None

def actualizar_pass_so(username, password):
    res = subprocess.run(
        ["chpasswd"],
        input=f"{username}:{password}", text=True, capture_output=True
    )
    return res.returncode == 0

def asegurar_home_ftp_principal(username, folder_web):
    """Deja el home accesible para FTP y mantiene htdocs escribible por su usuario."""
    try:
        home_dir = f"{BASE_WEB}/{folder_web}"
        htdocs_dir = f"{home_dir}/htdocs"
        subprocess.run(["mkdir", "-p", htdocs_dir], check=True)
        # vsftpd debe poder entrar en el home; 755 coincide con las cuentas existentes.
        subprocess.run(["chown", "ubuntu:ftp", home_dir], check=True)
        subprocess.run(["chmod", "755", home_dir], check=True)
        subprocess.run(["chown", "-R", f"{username}:ftp", htdocs_dir], check=True)
        subprocess.run(["chmod", "775", htdocs_dir], check=True)
        return True
    except Exception as e:
        logger.error(f"Error preparando home FTP de {username}: {e}")
        return False

def añadir_lista_ftp(username):
    try:
        if not os.path.exists(LISTA_FTP):
            subprocess.run(["touch", LISTA_FTP], check=False)
            subprocess.run(["chmod", "644", LISTA_FTP], check=False)
        cmd = f'grep -qxF "{username}" {LISTA_FTP} || echo "{username}" >> {LISTA_FTP}'
        subprocess.run(cmd, shell=True, check=False)
    except Exception as e:
        logger.warning(f"No se pudo actualizar lista FTP para {username}: {e}")

def revocar_lista_ftp(username):
    subprocess.run(
        ["sed", "-i", f"/^{username}$/d", LISTA_FTP],
        check=False
    )

def crear_usuario_linux(username, folder_web, password):
    try:
        home_dir = home_canonico(folder_web)

        if usuario_existe_en_so(username):
            logger.info(f"  ↺ {username} ya existe en SO. Actualizando contraseña.")
            if normalizar_home_usuario(username, folder_web) is None:
                return False
            if not actualizar_pass_so(username, password):
                logger.error(f"  ❌ Fallo al actualizar pass de {username}")
                return False
        else:
            subprocess.run(
                ["useradd", "-m", "-d", home_dir, "-s", "/usr/sbin/nologin", username],
                check=True
            )
            subprocess.run(["chpasswd"], input=f"{username}:{password}", text=True, check=True)

        if not asegurar_home_ftp_principal(username, folder_web):
            return False

        añadir_lista_ftp(username)
        logger.info(f"  🛡️ {username} en lista de aceptados FTP.")

        return True
    except Exception as e:
        logger.error(f"Error creando usuario principal {username}: {e}")
        return False

def crear_staff_linux(username, owner_ftp, password):
    try:
        # El directorio personal del staff debe ser la raíz del cliente
        home_dir = normalizar_home_usuario(owner_ftp, owner_ftp)
        if home_dir is None:
            return False
        if not asegurar_home_ftp_principal(owner_ftp, owner_ftp):
            return False

        if usuario_existe_en_so(username):
            logger.info(f"  ↺ Staff {username} ya existe. Actualizando pass.")
            subprocess.run(["usermod", "-d", home_dir, username], check=True)
            if not actualizar_pass_so(username, password):
                logger.error(f"  ❌ Fallo al actualizar pass de staff {username}")
                return False
        else:
            # Añadir con grupo principal 'ftp' para escritura en htdocs y home_dir en el contenedor
            subprocess.run(["useradd", "-d", home_dir, "-s", "/usr/sbin/nologin", "-g", "ftp", username], check=True)
            actualizar_pass_so(username, password)

        # El staff trabaja mediante el grupo ftp; no depende del nombre mutable del owner.
        añadir_lista_ftp(username)
        return True
    except Exception as e:
        logger.error(f"Error creando staff Linux {username}: {e}")
        return False

@lock("crear_usuarios")
def procesar_usuarios():
    exitos  = 0
    errores = 0
    comprobaciones = 0
    comprobacion_periodica = datetime.datetime.now().minute in [0, 30]
    
    # ── [1] PROCESAR USUARIOS ACTIVOS O EN MODIFICACIÓN ────────────────
    usuarios = []
    try:
        with DB() as (conexion, cursor):
            cursor.execute("SELECT id, nombre, ftp_user, ftp_pass, creado_en_so, estado_servicio FROM usuarios WHERE estado_servicio IN ('Activo', 'Pendiente', 'Para_Modificar')")
            usuarios = cursor.fetchall()
    except Exception as e_sel:
        logger.error(f"Error al obtener usuarios activos/pendientes: {e_sel}")
        logger.error(traceback.format_exc())

    for u in usuarios:
        u_id   = u['id']
        u_name = u['nombre']
        u_ftp  = u['ftp_user']
        u_pass = u['ftp_pass']
        u_est  = u['estado_servicio']

        need_update = False
        if not u_ftp or u_ftp.strip() == "":
            u_ftp = sanitizar_nombre(u_name)
            need_update = True

        if not u_ftp:
            logger.error(f"❌ Usuario ID:{u_id} tiene ftp_user y nombre nulo. Saltando.")
            continue

        if not u_pass or u_pass.strip() == "":
            u_pass = "pass_" + secrets.token_hex(3)
            need_update = True

        if need_update:
            try:
                with DB() as (up_conn, up_cur):
                    up_cur.execute("UPDATE usuarios SET ftp_user = %s, ftp_pass = %s WHERE id = %s", (u_ftp, u_pass, u_id))
                    up_conn.commit()
                logger.info(f"  💾 Guardados datos generados en BD para {u_name} (ID:{u_id}): User={u_ftp}, Pass={u_pass}")
            except Exception as e_up:
                logger.error(f"Error actualizando ftp_user/pass en BD para {u_name}: {e_up}")
                logger.error(traceback.format_exc())
                continue

        try:
            # --- CASO A: EL USUARIO ESTÁ MARCADO PARA MODIFICAR NOMBRE O CONTRASEÑA ---
            if u_est == 'Para_Modificar':
                logger.info(f"[MODIFICACIÓN] Detectado cambio solicitado para ID: {u_id}")
                if usuario_existe_en_so(u_ftp):
                    # Una ejecucion anterior pudo completar el login pero no reparar el home.
                    if normalizar_home_usuario(u_ftp, u_ftp) is None:
                        errores += 1
                        continue
                else:
                    nombre_anterior = obtener_usuario_antiguo(u_id, u_ftp)
                    if not nombre_anterior:
                        logger.error(f"  ❌ No se identifica con seguridad el login anterior de {u_ftp}; no se crea un home duplicado.")
                        errores += 1
                        continue

                    logger.info(f"  [~] Renombrando usuario en Linux: {nombre_anterior} ➔ {u_ftp}")
                    ruta_vieja = obtener_home_linux(nombre_anterior)
                    ruta_nueva = home_canonico(u_ftp)
                    if (ruta_vieja is None or not ruta_vieja.startswith(BASE_WEB + os.sep)
                            or not os.path.isdir(ruta_vieja)):
                        logger.error(f"  ❌ Home original no valido para {nombre_anterior}: {ruta_vieja}")
                        errores += 1
                        continue
                    if ruta_vieja != ruta_nueva and os.path.exists(ruta_nueva):
                        logger.error(f"  ❌ Conflicto al renombrar: el destino {ruta_nueva} ya existe.")
                        errores += 1
                        continue
                    try:
                        subprocess.run(["pkill", "-9", "-u", nombre_anterior], check=False)
                        # Cambio atomico: login y home fisico avanzan juntos, sin carpetas cruzadas.
                        subprocess.run(["usermod", "-l", u_ftp, "-d", ruta_nueva, "-m", nombre_anterior], check=True)
                        if not asegurar_home_ftp_principal(u_ftp, u_ftp):
                            raise RuntimeError("No se pudieron aplicar permisos al home renombrado")

                        revocar_lista_ftp(nombre_anterior)
                        añadir_lista_ftp(u_ftp)

                        with DB() as (sub_conn, sub_cur):
                            sub_cur.execute("UPDATE ftp_cuentas_extra SET owner_ftp = %s WHERE user_id = %s", (u_ftp, u_id))
                            sub_conn.commit()
                        logger.info(f"  ✓ Usuario renombrado y home actualizado de forma conjunta.")
                    except Exception as e_mod:
                        logger.error(f"  ❌ Fallo en usermod para {nombre_anterior}: {e_mod}")
                        logger.error(traceback.format_exc())
                        errores += 1
                        continue

                if u_pass and actualizar_pass_so(u_ftp, u_pass):
                    logger.info(f"  ✓ Credenciales/Contraseña actualizadas en el S.O. para: {u_ftp}")
                elif u_pass:
                    logger.error(f"  ❌ Fallo al actualizar contraseña en el S.O. para {u_ftp}")
                    errores += 1
                    continue

                with DB() as (fin_conn, fin_cur):
                    fin_cur.execute("UPDATE usuarios SET estado_servicio = 'Activo', creado_en_so = 1 WHERE id = %s", (u_id,))
                    fin_conn.commit()
                exitos += 1
                continue

            # --- CASO B: FLUJO NORMAL DE ALTA ---
            if not usuario_existe_en_so(u_ftp):
                logger.info(f"[PRINCIPAL] Creando nuevo entorno físico para: {u_ftp} (ID:{u_id})")
                if crear_usuario_linux(u_ftp, u_ftp, u_pass):
                    with DB() as (act_conn, act_cur):
                        act_cur.execute("UPDATE usuarios SET creado_en_so = 1, estado_servicio = 'Activo' WHERE id = %s", (u_id,))
                        act_conn.commit()
                    logger.info(f"  ✓ {u_ftp} activado con éxito.")
                    exitos += 1
                else:
                    errores += 1
            else:
                if normalizar_home_usuario(u_ftp, u_ftp) is None:
                    errores += 1
                    continue
                if not asegurar_home_ftp_principal(u_ftp, u_ftp):
                    errores += 1
                    continue

                if u_est != 'Activo':
                    if u_pass and not actualizar_pass_so(u_ftp, u_pass):
                        logger.error(f"  ❌ Falló sincronización de contraseña en el S.O. para {u_ftp}")
                        errores += 1
                        continue
                    with DB() as (act_conn, act_cur):
                        act_cur.execute("UPDATE usuarios SET creado_en_so = 1, estado_servicio = 'Activo' WHERE id = %s", (u_id,))
                        act_conn.commit()
                    logger.info(f"  ✓ Entorno FTP reparado y activado para {u_ftp}")
                    exitos += 1
                elif comprobacion_periodica:
                    if u_pass:
                        if actualizar_pass_so(u_ftp, u_pass):
                            logger.info(f"  ✓ Contraseña sincronizada rutinariamente en el S.O. para {u_ftp}")
                        else:
                            logger.error(f"  ❌ Falló sincronización de contraseña en el S.O. para {u_ftp}")
                            errores += 1
                    comprobaciones += 1
        except Exception as e:
            logger.error(f"  ❌ ERROR con {u_ftp}: {e}")
            logger.error(traceback.format_exc())
            errores += 1
            continue

    # ── [2] STAFF (ftp_cuentas_extra) ──────────────────────────────────
    staff = []
    try:
        with DB() as (conexion, cursor):
            cursor.execute("SELECT id, ftp_user, ftp_pass, owner_ftp, estado FROM ftp_cuentas_extra WHERE estado IN ('Pendiente', 'Para_Modificar')")
            staff = cursor.fetchall()
    except Exception as e_st_sel:
        logger.error(f"Error al obtener staff pendientes/modificaciones: {e_st_sel}")
        logger.error(traceback.format_exc())

    for s in staff:
        s_user   = s['ftp_user']
        s_pass   = s['ftp_pass']
        s_id     = s['id']
        owner    = s['owner_ftp']
        s_estado = s['estado']
        if not owner:
            logger.error(f"  ❌ Staff {s_user} sin owner_ftp. Saltando.")
            errores += 1
            continue
        try:
            logger.info(f"[STAFF] Procesando ({s_estado}): {s_user} → htdocs de: {owner}")
            
            if s_estado == 'Para_Modificar':
                if usuario_existe_en_so(s_user):
                    if actualizar_pass_so(s_user, s_pass):
                        with DB() as (st_conn, st_cur):
                            st_cur.execute("UPDATE ftp_cuentas_extra SET estado = 'Activo' WHERE id = %s", (s_id,))
                            st_conn.commit()
                        logger.info(f"  ✓ Staff {s_user} modificado con éxito (contraseña actualizada).")
                        exitos += 1
                    else:
                        logger.error(f"  ❌ Falló actualización de contraseña para staff {s_user}.")
                        errores += 1
                else:
                    # Si no existe por algún motivo, lo creamos
                    if crear_staff_linux(s_user, owner, s_pass):
                        with DB() as (st_conn, st_cur):
                            st_cur.execute("UPDATE ftp_cuentas_extra SET estado = 'Activo' WHERE id = %s", (s_id,))
                            st_conn.commit()
                        logger.info(f"  ✓ Staff {s_user} creado y activado (no existía en SO).")
                        exitos += 1
                    else:
                        errores += 1
            else: # Pendiente (Alta nueva)
                if crear_staff_linux(s_user, owner, s_pass):
                    with DB() as (st_conn, st_cur):
                        st_cur.execute("UPDATE ftp_cuentas_extra SET estado = 'Activo' WHERE id = %s", (s_id,))
                        st_conn.commit()
                    logger.info(f"  ✓ Staff {s_user} activado. Home: {BASE_WEB}/{owner} (trabajo en htdocs)")
                    exitos += 1
                else:
                    logger.error(f"  ✗ Falló creación de staff {s_user}. Se reintentará.")
                    errores += 1
        except Exception as e:
            logger.error(f"  ❌ ERROR con staff {s_user}: {e}")
            logger.error(traceback.format_exc())
            errores += 1
            continue

    # ── [2.5] PURGA DE CUENTAS STAFF HUÉRFANAS ─────────────────────────
    staff_purgas = []
    try:
        with DB() as (conexion, cursor):
            cursor.execute("SELECT id, ftp_user FROM ftp_cuentas_extra WHERE estado = 'Para_Borrar'")
            staff_purgas = cursor.fetchall()
    except Exception as e_st_purg:
        logger.error(f"Error al obtener staff para purgar: {e_st_purg}")
        logger.error(traceback.format_exc())

    for s in staff_purgas:
        s_id = s['id']
        s_user = s['ftp_user']
        logger.info(f"[PURGA STAFF] Eliminando cuenta física del servidor: {s_user}")
        try:
            if usuario_existe_en_so(s_user):
                revocar_lista_ftp(s_user)
                # IMPORTANTE: Eliminamos el "-r" para no borrar el htdocs compartido del dueño
                subprocess.run(["userdel", s_user], capture_output=True)
            with DB() as (del_conn, del_cur):
                del_cur.execute("DELETE FROM ftp_cuentas_extra WHERE id = %s", (s_id,))
                del_conn.commit()
            logger.info(f"  ✓ Staff {s_user} eliminado de SO y BD sin alterar la carpeta compartida.")
            exitos += 1
        except Exception as e_s:
            logger.error(f"  ❌ Error eliminando staff {s_user}: {e_s}")
            logger.error(traceback.format_exc())

    # ── [3] PURGA ATÓMICA ─────────────────────────────────────────────
    purgas = []
    try:
        with DB() as (conexion, cursor):
            cursor.execute("SELECT id, nombre, ftp_user FROM usuarios WHERE estado_servicio = 'Para_Borrar'")
            purgas = cursor.fetchall()
    except Exception as e_purg_sel:
        logger.error(f"Error al obtener usuarios para purgar: {e_purg_sel}")
        logger.error(traceback.format_exc())

    for p in purgas:
        u_id = p['id']
        u_nombre = p['nombre']
        u_web = p['ftp_user'].strip() if (p['ftp_user'] and p['ftp_user'].strip()) else sanitizar_nombre(u_nombre)
        
        logger.info(f"[PURGA] Eliminando residuos del usuario ID {u_id} (Directorio: {u_web})")
        try:
            # 1. EXPULSAR PROCESOS Y BORRAR STAFF ASOCIADO EN LINUX (Usando conexión aislada rápida)
            staff_cuentas = []
            try:
                with DB() as (s_conn, s_cur):
                    s_cur.execute("SELECT ftp_user FROM ftp_cuentas_extra WHERE user_id = %s", (u_id,))
                    staff_cuentas = s_cur.fetchall()
            except Exception as e_staff:
                logger.warning(f"No se pudieron obtener cuentas staff para ID {u_id}: {e_staff}")

            for s in staff_cuentas:
                s_user = s['ftp_user']
                subprocess.run(["pkill", "-9", "-u", s_user], check=False)
                revocar_lista_ftp(s_user)
                # Quitamos "-r" para evitar borrar el htdocs de forma no deseada
                subprocess.run(["userdel", s_user], check=False)
                logger.info(f"  ✓ Staff extra {s_user} eliminado de Linux y accesos FTP revocados.")

            if usuario_existe_en_so(u_web):
                subprocess.run(["pkill", "-9", "-u", u_web], check=False)
                revocar_lista_ftp(u_web)
                subprocess.run(["userdel", "-r", u_web], capture_output=True)
                
            ruta_web_usuario = os.path.join(BASE_WEB, u_web)
            if os.path.exists(ruta_web_usuario):
                shutil.rmtree(ruta_web_usuario, ignore_errors=True)
                
            # 2. LIMPIEZA ATÓMICA EN LA BD CON CONEXIÓN FRESCA (Evita timeouts / 2055)
            with DB() as (p_conn, p_cur):
                p_cur.execute("DELETE FROM dominios WHERE user_id = %s", (u_id,))
                p_cur.execute("DELETE FROM modulo_mysql WHERE user_id = %s", (u_id,))
                p_cur.execute("DELETE FROM ftp_cuentas_extra WHERE user_id = %s", (u_id,))
                p_cur.execute("DELETE FROM usuarios WHERE id = %s", (u_id,))
                p_conn.commit()
            
            logger.info(f"  ✓ Registro {u_id} purgado completamente de las tablas.")
            exitos += 1
        except Exception as e:
            logger.error(f"  ❌ Error crítico en purga de ID {u_id}: {e}")
            logger.error(traceback.format_exc())
            errores += 1

    # Resumen
    if (exitos + errores > 0) or comprobacion_periodica:
        logger.info(
            f"Resumen: {exitos} cambios procesados, {errores} fallos, "
            f"{comprobaciones} comprobaciones rutinarias."
        )

if __name__ == "__main__":
    procesar_usuarios()