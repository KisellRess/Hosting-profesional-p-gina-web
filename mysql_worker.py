import mysql.connector
import logging
import os
import traceback
import re
import datetime

# --- CONFIGURACIÓN DB ---
DB_CONFIG = {
    "host": "localhost",
    "user": "ubuntu",
    "password": "ubuntu123",
    "database": "vinomadrid_db"
}
ADMIN_CONFIG = {
    "host": "localhost",
    "user": "ubuntu",
    "password": "ubuntu123" 
}

# --- CONFIGURACIÓN DE RUTAS Y LOGS ---
LOG_DIR = "/opt/tfg/scripts/logs"
try:
    os.makedirs(LOG_DIR, exist_ok=True)
    os.chmod(LOG_DIR, 0o777)
except: pass

logger = logging.getLogger("MySQLWorker")
logger.setLevel(logging.DEBUG)
formatter = logging.Formatter('[%(asctime)s] %(levelname)s - %(message)s', datefmt='%Y-%m-%d %H:%M:%S')

for h_file in [os.path.join(LOG_DIR, "mysql_worker_master.log"),
              os.path.join(LOG_DIR, "mysql_worker.txt")]:
    handler = logging.FileHandler(h_file, encoding='utf-8', delay=False)
    handler.setFormatter(formatter)
    logger.addHandler(handler)

# ─── FUNCIONES ───

def sanitizar(texto):
    if not texto: return "default_db"
    return re.sub(r'[^a-z0-9_]', '', texto.lower().replace(" ", "_"))

def crear_db_fisica(db_name, db_user, db_pass):
    admin_conn = None
    try:
        admin_conn = mysql.connector.connect(**ADMIN_CONFIG)
        cursor = admin_conn.cursor()
        cursor.execute(f"CREATE DATABASE IF NOT EXISTS `{db_name}` CHARACTER SET utf8mb4")
        
        try:
            cursor.execute("CREATE USER %s@'localhost' IDENTIFIED BY %s", (db_user, db_pass))
        except mysql.connector.Error:
            cursor.execute("ALTER USER %s@'localhost' IDENTIFIED BY %s", (db_user, db_pass))
        
        cursor.execute(f"GRANT ALL PRIVILEGES ON `{db_name}`.* TO %s@'localhost'", (db_user,))
        cursor.execute("FLUSH PRIVILEGES")
        admin_conn.commit()
        return True
    except Exception as e:
        logger.error(f"FALLO FÍSICO MySQL en {db_name}: {str(e)}")
        return False
    finally:
        if admin_conn and admin_conn.is_connected():
            admin_conn.close()

def procesar_cola_mysql():
    conexion = None
    exitos = 0
    errores = 0
    try:
        conexion = mysql.connector.connect(**DB_CONFIG)
        cursor = conexion.cursor(dictionary=True)

        query = """
            SELECT m.id, m.db_name, m.db_user, m.db_pass, m.estado, u.nombre as owner_name
            FROM modulo_mysql m
            JOIN usuarios u ON m.user_id = u.id
            WHERE m.estado IN ('Tramitando', 'Cancelado')
        """
        cursor.execute(query)
        pendientes = cursor.fetchall()

        if not pendientes:
            return

        for p in pendientes:
            nombre_base = p['db_name'] if p['db_name'] else sanitizar(p['owner_name'])
            user_db = p['db_user'] if p['db_user'] else f"{nombre_base}_u"

            if p['estado'] == 'Cancelado':
                try:
                    admin_conn = mysql.connector.connect(**ADMIN_CONFIG)
                    adm_cur = admin_conn.cursor()
                    adm_cur.execute(f"DROP DATABASE IF EXISTS `{nombre_base}`")
                    adm_cur.execute(f"DROP USER IF EXISTS %s@'localhost'", (user_db,))
                    admin_conn.commit()
                    admin_conn.close()
                    
                    cursor.execute("DELETE FROM modulo_mysql WHERE id = %s", (p['id'],))
                    conexion.commit()
                    logger.info(f"✓ DB Eliminada: {nombre_base} (Solicitado por {p['owner_name']})")
                    exitos += 1
                except Exception as e:
                    logger.error(f"Error borrando DB {nombre_base}: {e}")
                    errores += 1
            else:
                if crear_db_fisica(nombre_base, user_db, p['db_pass']):
                    cursor.execute("UPDATE modulo_mysql SET estado = 'Activo' WHERE id = %s", (p['id'],))
                    conexion.commit()
                    logger.info(f"✓ ÉXITO: DB {nombre_base} activada para {p['owner_name']}")
                    exitos += 1
                else:
                    errores += 1

        # Lógica de Log Inteligente
        minuto = datetime.datetime.now().minute
        if (exitos + errores > 0) or (minuto in [0, 30]):
            logger.info(f"Resumen MySQL: {exitos} procesados, {errores} fallos.")

    except mysql.connector.Error as err:
        logger.error(f"ERROR DE CONEXIÓN DB: {err}")
    except Exception as e:
        logger.error(f"ERROR IMPREVISTO: {traceback.format_exc()}")
    finally:
        if 'conexion' in locals() and conexion.is_connected():
            cursor.close()
            conexion.close()
            logger.debug("Conexión cerrada correctamente.")

if __name__ == "__main__":
    procesar_cola_mysql()