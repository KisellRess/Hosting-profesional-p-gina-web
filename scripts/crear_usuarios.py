import mysql.connector
import subprocess
import os
import logging
import re
import datetime

# --- CONFIGURACIÓN DB ---
DB_CONFIG = {
    "host": "localhost",
    "user": "ubuntu",
    "password": "ubuntu123",
    "database": "vinomadrid_db"
}

# --- CONFIGURACIÓN DE RUTAS Y LOGS ---
LOG_DIR = "/opt/tfg/scripts/logs"
try:
    os.makedirs(LOG_DIR, exist_ok=True)
    os.chmod(LOG_DIR, 0o777)
except: pass

logger = logging.getLogger("UserProvisioning")
logger.setLevel(logging.DEBUG)
formatter = logging.Formatter('[%(asctime)s] %(levelname)s - %(message)s', datefmt='%Y-%m-%d %H:%M:%S')

# Master (.log) y Diario (.txt)
for h_file in [os.path.join(LOG_DIR, "creacion_usuarios_master.log"),
              os.path.join(LOG_DIR, "creacion_usuarios.txt")]:
    handler = logging.FileHandler(h_file, encoding='utf-8', delay=False)
    handler.setFormatter(formatter)
    logger.addHandler(handler)

def sanitizar_nombre(nombre):
    return re.sub(r'[^a-z0-9]', '', nombre.lower().replace(" ", ""))

def crear_usuario_linux(username, folder_web, password):
    try:
        home_dir = f"/var/www/hosting_tfg/{folder_web}"
        # Crear carpeta raíz y htdocs
        subprocess.run(["sudo", "mkdir", "-p", f"{home_dir}/htdocs"], check=True)
        
        # Crear el usuario si no existe
        ret = subprocess.run(["id", "-u", username], capture_output=True)
        if ret.returncode != 0:
            subprocess.run(["sudo", "useradd", "-m", "-d", home_dir, "-s", "/bin/bash", username], check=True)
        
        # Establecer contraseña
        subprocess.run(["sudo", "chpasswd"], input=f"{username}:{password}", text=True, check=True)
        
        # PERMISOS: Usuario dueño de su carpeta y Apache (www-data) acceso
        subprocess.run(["sudo", "chown", "-R", f"{username}:ftp", home_dir], check=True)
        subprocess.run(["sudo", "chmod", "-R", "775", home_dir], check=True)
        
        return True
    except Exception as e:
        logger.error(f"Error creando usuario {username}: {e}")
        return False


def procesar_usuarios():
    exitos = 0
    errores = 0
    conexion = None
    try:
        conexion = mysql.connector.connect(**DB_CONFIG)
        cursor = conexion.cursor(dictionary=True)

        # 1. Usuarios principales
        cursor.execute("SELECT id, nombre, ftp_user, ftp_pass FROM usuarios WHERE creado_en_so = 0")
        for row in cursor.fetchall():
            # El nombre Linux siempre será el nombre sanitizado limpio
            u_web = sanitizar_nombre(row['nombre'])
            u_ftp = u_web  # Forzamos nombre limpio sin sufijos
            if crear_usuario_linux(u_ftp, u_web, row['ftp_pass']):
                cursor.execute("UPDATE usuarios SET creado_en_so = 1, ftp_user = %s WHERE id = %s", (u_ftp, row['id']))
                conexion.commit()
                logger.info(f"✓ Usuario {u_ftp} activado.")
                exitos += 1
            else: errores += 1

        # 2. Staff (Vinculado por owner_ftp)
        cursor.execute("SELECT id, ftp_user, ftp_pass, owner_ftp FROM ftp_cuentas_extra WHERE estado IN ('Pendiente', 'Tramitando')")
        for ex in cursor.fetchall():
            # Seguimos quitando _ftp por si acaso hay registros antiguos
            folder_web = ex['owner_ftp'].replace('_ftp', '')
            u_staff = ex['ftp_user'].replace('_ftp', '')
            if crear_usuario_linux(u_staff, folder_web, ex['ftp_pass']):
                cursor.execute("UPDATE ftp_cuentas_extra SET estado = 'Activo' WHERE id = %s", (ex['id'],))
                conexion.commit()
                logger.info(f"✓ Staff {u_staff} activado en {folder_web}.")
                exitos += 1
            else: errores += 1

        # 3. Limpieza de Usuarios (Borrado Atómico de todos los servicios)
        cursor.execute("SELECT id, ftp_user FROM usuarios WHERE estado_servicio = 'Para_Borrar'")
        for d in cursor.fetchall():
            u_del = d['ftp_user'].replace('_ftp', '')
            u_id = d['id']
            try:
                logger.info(f"🚀 Iniciando purga total para usuario: {u_del}")

                # A. LIMPIEZA APACHE (Vhosts individuales)
                conf_path = f"/etc/apache2/sites-available/{u_del}.conf"
                subprocess.run(["sudo", "a2dissite", f"{u_del}.conf"], capture_output=True)
                if os.path.exists(conf_path):
                    subprocess.run(["sudo", "rm", conf_path], check=False)
                
                # B. LIMPIEZA MYSQL (Bases de datos y usuarios)
                cursor.execute("SELECT db_name, db_user FROM modulo_mysql WHERE user_id = %s", (u_id,))
                db_info = cursor.fetchone()
                if db_info:
                    try:
                        # Usamos sentencias directas para asegurar la purga
                        cursor.execute(f"DROP DATABASE IF EXISTS {db_info['db_name']}")
                        cursor.execute(f"DROP USER IF EXISTS '{db_info['db_user']}'@'localhost'")
                        logger.info(f"✓ MySQL purgado para {u_del}")
                    except Exception as e_sql:
                        logger.error(f"Error purgando SQL de {u_del}: {e_sql}")

                # C. LIMPIEZA LINUX (Archivos y cuenta)
                subprocess.run(["sudo", "userdel", "-r", u_del], check=True, capture_output=True)
                logger.info(f"✓ Usuario Linux y archivos eliminados para {u_del}")

                # D. LIMPIEZA FINAL DB (Orden jerárquico)
                cursor.execute("DELETE FROM dominios WHERE user_id = %s", (u_id,))
                cursor.execute("DELETE FROM modulo_mysql WHERE user_id = %s", (u_id,))
                cursor.execute("DELETE FROM usuarios WHERE id = %s", (u_id,))
                conexion.commit()
                
                logger.info(f"⚠ PURGA COMPLETADA: {u_del} ha sido eliminado del sistema.")
                exitos += 1

            except Exception as e:
                logger.error(f"Error crítico en la purga de {u_del}: {e}")
                errores += 1

        # Lógica de Log Inteligente
        minuto = datetime.datetime.now().minute
        if (exitos + errores > 0) or (minuto in [0, 30]):
            logger.info(f"Resumen Usuarios: {exitos} procesados/borrados, {errores} fallos.")

    except Exception as e: logger.error(f"Error: {e}")
    finally:
        if 'conexion' in locals() and conexion.is_connected():
            cursor.close()
            conexion.close()
            logger.debug("Conexión cerrada correctamente.")

if __name__ == "__main__":
    procesar_usuarios()