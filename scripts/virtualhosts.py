import mysql.connector
import os
import subprocess
import logging
import tempfile
import datetime

# --- CONFIGURACIÓN DB ---
DB_CONFIG = {
    "host": "localhost",
    "user": "ubuntu",
    "password": "ubuntu123",
    "database": "vinomadrid_db"
}
APACHE_CONF_MAIN = "/etc/apache2/sites-available/000-hosting_tfg.conf"

# --- CONFIGURACIÓN DE RUTAS Y LOGS ---
LOG_DIR = "/opt/tfg/scripts/logs"
try:
    os.makedirs(LOG_DIR, exist_ok=True)
    os.chmod(LOG_DIR, 0o777)
except: pass

logger = logging.getLogger("VHostManager")
logger.setLevel(logging.DEBUG)
formatter = logging.Formatter('[%(asctime)s] %(levelname)s - %(message)s', datefmt='%Y-%m-%d %H:%M:%S')

for h_file in [os.path.join(LOG_DIR, "virtualhosts_master.log"),
              os.path.join(LOG_DIR, "virtualhosts.txt")]:
    handler = logging.FileHandler(h_file, encoding='utf-8', delay=False)
    handler.setFormatter(formatter)
    logger.addHandler(handler)

# --- FUNCIONES DE APOYO ---

def leer_archivo_root(ruta):
    try:
        return subprocess.run(["sudo", "cat", ruta], capture_output=True, text=True, check=True).stdout
    except:
        return None

def escribir_archivo_root(ruta, contenido):
    try:
        fd, tmp_path = tempfile.mkstemp(suffix='.conf')
        with os.fdopen(fd, 'w') as tmp_file:
            tmp_file.write(contenido)
        subprocess.run(["sudo", "cp", tmp_path, ruta], check=True)
        os.remove(tmp_path)
        return True
    except Exception as e:
        logger.error(f"Error escribiendo {ruta}: {e}")
        return False

def configurar_maestro(u_web, alias_solicitado):
    alias_final = alias_solicitado or u_web
    origen = f"/var/www/hosting_tfg/{u_web}/htdocs"
    
    contenido = leer_archivo_root(APACHE_CONF_MAIN)
    if not contenido or f"Alias /{alias_final}" in contenido:
        return True 

    nuevo_bloque = (
        f"\n    # Configuración para {u_web}\n"
        f"    Alias /{alias_final} \"{origen}\"\n"
        f"    <Directory \"{origen}\">\n"
        f"        Options -Indexes +FollowSymLinks\n"
        f"        AllowOverride All\n"
        f"        Require all granted\n"
        f"    </Directory>\n"
    )

    if "</VirtualHost>" in contenido:
        partes = contenido.rsplit("</VirtualHost>", 1)
        nuevo_contenido = f"{partes[0].strip()}\n{nuevo_bloque}\n</VirtualHost>{partes[1]}"
        if escribir_archivo_root(APACHE_CONF_MAIN, nuevo_contenido):
            test = subprocess.run(["sudo", "apache2ctl", "-t"], capture_output=True, text=True)
            if test.returncode == 0:
                logger.info(f"Alias /{alias_final} añadido al maestro.")
                return True
            else:
                escribir_archivo_root(APACHE_CONF_MAIN, contenido) # Rollback
                logger.error(f"Error sintaxis maestro: {test.stderr}")
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
        test = subprocess.run(["sudo", "apache2ctl", "-t"], capture_output=True, text=True)
        if test.returncode == 0:
            subprocess.run(["sudo", "a2ensite", f"{u_web}.conf"], capture_output=True)
            logger.info(f"Vhost individual creado: {dominio}")
            return True
        else:
            subprocess.run(["sudo", "rm", path], check=False)
            logger.error(f"Error sintaxis individual {u_web}: {test.stderr}")
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

    # Configuración específica manuales para que funcionen los clientes
   Alias /elirosellbonavina /var/www/hosting_tfg/elirosellbonavina/html
    <Directory /var/www/hosting_tfg/elirosellbonavina/html>
        ErrorDocument 404 /error404.html
        ErrorDocument 403 /error404.html
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    # --- CONFIGURACIONES DINÁMICAS (SCRIPTS) ---
"""
    bloques = ""
    # Nombres que NO queremos duplicar porque podrían estar en la cabecera o ser especiales
    ignorados = [] 
    
    for d in filas_activas:
        u_web = d['ftp_user']
        if u_web in ignorados: continue
        
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

def procesar_cola():
    conexion = None
    exitos = 0
    errores = 0
    try:
        conexion = mysql.connector.connect(**DB_CONFIG)
        cursor = conexion.cursor(dictionary=True)

        # 1. Nuevas activaciones
        cursor.execute("SELECT d.id, d.subdominio_alias, d.dominio_propio, u.ftp_user "
                       "FROM dominios d JOIN usuarios u ON d.user_id = u.id "
                       "WHERE d.estado_dominio IN ('Tramitando', 'Proc', 'Pendiente')")
        pendientes = cursor.fetchall()
        for d in pendientes:
            if d['dominio_propio']:
                configurar_individual(d['ftp_user'], d['dominio_propio'])
            cursor.execute("UPDATE dominios SET estado_dominio = 'Activo' WHERE id = %s", (d['id'],))
            conexion.commit()
            exitos += 1

        # 2. BORRADO DE DOMINIOS (Confirmado por Admin)
        cursor.execute("SELECT d.id, d.dominio_propio, u.ftp_user "
                       "FROM dominios d JOIN usuarios u ON d.user_id = u.id "
                       "WHERE d.estado_dominio = 'Para_Borrar'")
        para_borrar = cursor.fetchall()
        for b in para_borrar:
            try:
                if b['dominio_propio']:
                    conf_path = f"/etc/apache2/sites-available/{b['ftp_user']}.conf"
                    # Desactivar y borrar vhost individual
                    subprocess.run(["sudo", "a2dissite", f"{b['ftp_user']}.conf"], capture_output=True)
                    if os.path.exists(conf_path):
                        subprocess.run(["sudo", "rm", conf_path], check=True)
                
                # Limpiar en DB
                cursor.execute("DELETE FROM dominios WHERE id = %s", (b['id'],))
                conexion.commit()
                logger.info(f"⚠ Dominio {b['dominio_propio'] or 'Alias'} eliminado de Apache y DB.")
                exitos += 1
            except Exception as e:
                logger.error(f"Error borrando dominio {b['id']}: {e}")
                errores += 1

        # 3. RECONSTRUCCIÓN TOTAL (Sincronización destructiva)
        cursor.execute("SELECT d.subdominio_alias, u.ftp_user FROM dominios d "
                       "JOIN usuarios u ON d.user_id = u.id WHERE d.estado_dominio = 'Activo'")
        activos = cursor.fetchall()
        
        if escribir_archivo_root(APACHE_CONF_MAIN, generar_conf_completo(activos)):
            test = subprocess.run(["sudo", "apache2ctl", "-t"], capture_output=True, text=True)
            if test.returncode == 0:
                subprocess.run(["sudo", "systemctl", "reload", "apache2"], check=False)
            else:
                logger.error(f"Error Apache sintaxis: {test.stderr}")
                errores += 1

        # Lógica de Log Inteligente
        minuto = datetime.datetime.now().minute
        if (exitos + errores > 0) or (minuto in [0, 30]):
            logger.info(f"Resumen VHosts: {exitos} activados/sincronizados, {errores} fallos.")

    except Exception as e: logger.error(f"Error: {e}")
    finally:
        if 'conexion' in locals() and conexion.is_connected():
            cursor.close()
            conexion.close()
            logger.debug("Conexión cerrada correctamente.")

if __name__ == "__main__":
    procesar_cola()