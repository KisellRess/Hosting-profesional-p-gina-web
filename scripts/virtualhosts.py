"""ARCHIVO: virtualhosts.py
FUNCION: aplicar dominios y subdominios en la configuracion Apache.
SECCIONES: ficheros, bloques virtuales y procesamiento de la cola.
"""

import os
import subprocess
import tempfile
import datetime
from tfg_lib import DB, Logger, lock

APACHE_CONF_MAIN = "/etc/apache2/sites-available/000-hosting_tfg.conf"
logger = Logger.get_logger("VHostManager", "virtualhosts")

# --- FUNCIONES DE APOYO ---

def ejecutar_root(comando, descripcion, registrar_error=True):
    """Ejecuta una orden con sudo no interactivo si el proceso no es root."""
    prefijo = [] if getattr(os, "geteuid", lambda: 1)() == 0 else ["sudo", "-n"]
    resultado = subprocess.run(prefijo + comando, capture_output=True, text=True)
    if resultado.returncode != 0 and registrar_error:
        detalle = (resultado.stderr or resultado.stdout or "sin detalle").strip()
        logger.error(f"{descripcion} fallo ({resultado.returncode}): {detalle}")
        if prefijo:
            logger.error("Comprueba permisos sudo NOPASSWD para administrar Apache.")
    return resultado

def leer_archivo_root(ruta):
    try:
        resultado = ejecutar_root(["cat", ruta], f"Lectura de {ruta}")
        return resultado.stdout if resultado.returncode == 0 else None
    except OSError as e:
        logger.error(f"No se pudo ejecutar la lectura de {ruta}: {e}")
        return None

def escribir_archivo_root(ruta, contenido):
    tmp_path = None
    try:
        fd, tmp_path = tempfile.mkstemp(suffix='.conf')
        with os.fdopen(fd, 'w') as tmp_file:
            tmp_file.write(contenido)
        operaciones = (
            (["cp", tmp_path, ruta], f"Copia de configuracion a {ruta}"),
            (["chown", "ubuntu:root", ruta], f"Propietario de {ruta}"),
            (["chmod", "644", ruta], f"Permisos de {ruta}"),
        )
        for comando, descripcion in operaciones:
            if ejecutar_root(comando, descripcion).returncode != 0:
                return False
        return True
    except Exception as e:
        logger.error(f"Error escribiendo {ruta}: {e}")
        return False
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.remove(tmp_path)

def validar_configuracion_apache():
    """Valida todos los sitios habilitados antes de aplicar una recarga."""
    test = ejecutar_root(["apache2ctl", "configtest"], "Validacion de configuracion Apache")
    if test.returncode == 0:
        logger.info("Sintaxis Apache validada correctamente.")
        return True
    return False

def recargar_apache():
    """Recarga Apache y solo reinicia si la recarga segura falla."""
    recarga = ejecutar_root(["systemctl", "reload", "apache2"], "Recarga de Apache")
    if recarga.returncode == 0:
        logger.info("Apache recargado correctamente.")
        return True

    logger.warning("La recarga de Apache fallo; se intenta un reinicio de recuperacion.")
    reinicio = ejecutar_root(["systemctl", "restart", "apache2"], "Reinicio de recuperacion de Apache")
    if reinicio.returncode == 0:
        logger.info("Apache reiniciado correctamente tras fallar la recarga.")
        return True
    return False

def configurar_individual(u_web, dominio):
    if not dominio: return True
    path = f"/etc/apache2/sites-available/{u_web}.conf"
    origen = f"/var/www/hosting_tfg/{u_web}/htdocs"
    
    contenido = f"""<VirtualHost *:8080>
    ServerName {dominio}
    ServerAlias www.{dominio}
    DocumentRoot "{origen}"
    ErrorLog ${{APACHE_LOG_DIR}}/{u_web}_error.log
    <Directory "{origen}">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>"""

    if escribir_archivo_root(path, contenido):
        habilitar = ejecutar_root(["a2ensite", f"{u_web}.conf"], f"Activacion de sitio {u_web}")
        if habilitar.returncode == 0 and validar_configuracion_apache():
            logger.info(f"Vhost individual creado: {dominio}")
            return True
        else:
            ejecutar_root(["a2dissite", f"{u_web}.conf"], f"Rollback del sitio {u_web}", registrar_error=False)
            ejecutar_root(["rm", "-f", path], f"Eliminacion de configuracion no valida {u_web}")
            logger.error(f"No se pudo activar con seguridad el vhost individual {u_web}.")
    return False

def generar_conf_completo(filas_activas):
    """Genera el contenido completo del VirtualHost maestro con cabecera estática limpia"""
    cabecera = """<VirtualHost *:8080>
    ServerAlias vinomadrid.es
    ServerName kisellress.ddns.net
    DocumentRoot /var/www/hosting_tfg/html/pag_principal
    DirectoryIndex index.php index.html

    # Páginas de error
    ErrorDocument 404 /error404.html
    ErrorDocument 403 /error404.html
    ErrorLog ${APACHE_LOG_DIR}/hosting_error.log
    CustomLog ${APACHE_LOG_DIR}/hosting_access.log combined
    
    # Configuración de Seguridad para el directorio principal
    <Directory /var/www/hosting_tfg/html/pag_principal>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Permisos para que los alias funcionen
    <Directory /var/www/hosting_tfg/html>
        Options +FollowSymLinks
        Require all granted
    </Directory>

    # --- CONFIGURACIONES DINÁMICAS (TODOS LOS USUARIOS CON SERVICIO) ---
"""
    bloques = ""
    
    for d in filas_activas:
        u_web = d['ftp_user']
        
        alias = d['subdominio_alias'] or u_web
        path = f"/var/www/hosting_tfg/{u_web}/htdocs"
        
        bloques += (
            f"\n    # Alias para {u_web}\n"
            f"    Alias /{alias} \"{path}\"\n"
            f"    <Directory \"{path}\">\n"
            f"        Options -Indexes +FollowSymLinks\n"
            f"        AllowOverride All\n"
            f"        Require all granted\n"
            f"    </Directory>\n"
        )
    return cabecera + bloques + "\n</VirtualHost>"

def registrar_alerta_dominio(conexion, cursor, user_id, nombre, motivo):
    """Guarda una alerta visible en administracion sin repetir el mismo aviso pendiente."""
    try:
        cursor.execute(
            "SELECT id FROM alertas_admin WHERE user_id = %s AND motivo = %s AND reconocida = 0 LIMIT 1",
            (user_id, motivo)
        )
        if not cursor.fetchone():
            cursor.execute(
                "INSERT INTO alertas_admin "
                "(user_id, nombre_usuario, motivo, simbolo, reconocida, fecha) "
                "VALUES (%s, %s, %s, %s, 0, NOW())",
                (user_id, nombre, motivo, "DOM")
            )
            conexion.commit()
    except Exception as e:
        logger.warning(f"No se pudo guardar alerta de dominio: {e}")

@lock("virtualhosts_sync")
def procesar_cola():
    exitos = 0
    errores = 0
    cambios_apache = False
    try:
        with DB() as (conexion, cursor):
            # 1. Nuevas activaciones / Modificaciones
            cursor.execute("SELECT d.id, d.subdominio_alias, d.dominio_propio, "
                           "u.id AS user_id, u.nombre, u.ftp_user "
                           "FROM dominios d JOIN usuarios u ON d.user_id = u.id "
                           "WHERE d.estado_dominio IN ('Tramitando', 'Proc', 'Pendiente')")
            pendientes = cursor.fetchall()
            for d in pendientes:
                d_id = d['id']
                d_sub = d['subdominio_alias']
                d_dom = d['dominio_propio']
                u_web = d['ftp_user']
                
                if d_dom and not configurar_individual(u_web, d_dom):
                    logger.error(f"No se pudo configurar el dominio {d_dom} para {u_web}.")
                    errores += 1
                    continue
                if d_dom:
                    cambios_apache = True
                cursor.execute("UPDATE dominios SET estado_dominio = 'Activo' WHERE id = %s", (d_id,))
                conexion.commit()
                destino = d_dom or d_sub or u_web
                registrar_alerta_dominio(
                    conexion, cursor, d['user_id'], d['nombre'],
                    f"Dominio agregado o actualizado: {destino}"
                )
                exitos += 1

            # 2. BORRADO DE DOMINIOS (Confirmado por Admin o desvinculación)
            cursor.execute("SELECT d.id, d.dominio_propio, d.subdominio_alias, "
                           "u.id AS user_id, u.nombre, u.ftp_user "
                           "FROM dominios d JOIN usuarios u ON d.user_id = u.id "
                           "WHERE d.estado_dominio = 'Para_Borrar'")
            desvinculados = cursor.fetchall()
            for d in desvinculados:
                try:
                    d_id = d['id']
                    u_web = d['ftp_user']
                    destino = d['dominio_propio'] or d['subdominio_alias'] or u_web
                    
                    if u_web:
                        nombre_vhost = f"{u_web}.conf"
                        ruta_individual = f"/etc/apache2/sites-available/{nombre_vhost}"
                        ejecutar_root(["a2dissite", nombre_vhost], f"Desactivacion de sitio {u_web}", registrar_error=False)
                        if os.path.exists(ruta_individual):
                            if ejecutar_root(["rm", "-f", ruta_individual], f"Eliminacion del sitio {u_web}").returncode != 0:
                                raise RuntimeError(f"No se pudo eliminar {ruta_individual}")
                            cambios_apache = True
                            logger.info(f"  ⚠ VHost individual deshabilitado y eliminado: {nombre_vhost}")
                            
                    cursor.execute("DELETE FROM dominios WHERE id = %s", (d_id,))
                    conexion.commit()
                    registrar_alerta_dominio(
                        conexion, cursor, d['user_id'], d['nombre'],
                        f"Dominio quitado: {destino}"
                    )
                    exitos += 1
                except Exception as e:
                    logger.error(f"Error borrando dominio: {e}")
                    errores += 1

            # 3. RECONSTRUCCION TOTAL: comprueba siempre los aliases de usuarios con servicio.
            cursor.execute("SELECT u.ftp_user, d.subdominio_alias FROM usuarios u LEFT JOIN dominios d ON u.id = d.user_id AND d.estado_dominio = 'Activo' WHERE u.estado_servicio IN ('Activo', 'Pendiente')")
            activos = cursor.fetchall()

            contenido_nuevo = generar_conf_completo(activos)
            contenido_actual = leer_archivo_root(APACHE_CONF_MAIN)
            maestro_actualizado = False
            if contenido_actual is None:
                logger.error("No se pudo leer el fichero maestro; no se aplica la sincronizacion.")
                errores += 1
            elif contenido_actual != contenido_nuevo:
                if escribir_archivo_root(APACHE_CONF_MAIN, contenido_nuevo):
                    cambios_apache = True
                    maestro_actualizado = True
                    logger.info("Fichero maestro Apache actualizado con todos los usuarios activos o pendientes.")
                else:
                    errores += 1

            if cambios_apache:
                if validar_configuracion_apache():
                    if not recargar_apache():
                        errores += 1
                else:
                    # Nunca recargamos Apache si la nueva configuracion no es valida.
                    logger.error("Configuracion Apache invalida; no se aplica la recarga.")
                    if maestro_actualizado and escribir_archivo_root(APACHE_CONF_MAIN, contenido_actual):
                        logger.warning("Se restauro el fichero maestro anterior tras fallar configtest.")
                    errores += 1

            # Lógica de Log Inteligente
            minuto = datetime.datetime.now().minute
            if (exitos + errores > 0) or (minuto in [0, 30]):
                logger.info(f"Resumen VHosts: {exitos} activados/sincronizados, {errores} fallos.")

    except Exception as e: 
        logger.error(f"Error: {e}")

if __name__ == "__main__":
    procesar_cola()