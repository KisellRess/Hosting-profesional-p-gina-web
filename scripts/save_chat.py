import mysql.connector
import logging
import os
import sys
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

logger = logging.getLogger("ChatSystem")
logger.setLevel(logging.DEBUG)
formatter = logging.Formatter('[%(asctime)s] %(levelname)s - %(message)s', datefmt='%Y-%m-%d %H:%M:%S')

# Master (.log) y Diario (.txt)
for h_file in [os.path.join(LOG_DIR, "chat_system_master.log"),
              os.path.join(LOG_DIR, "chat_system.txt")]:
    handler = logging.FileHandler(h_file, encoding='utf-8', delay=False)
    handler.setFormatter(formatter)
    logger.addHandler(handler)

def save_chat_to_db(username, role, message):
    conexion = None
    exitos = 0
    errores = 0
    try:
        conexion = mysql.connector.connect(**DB_CONFIG)
        cursor = conexion.cursor(dictionary=True)

        cursor.execute("SELECT id FROM usuarios WHERE nombre = %s OR ftp_user = %s", (username, username))
        user_data = cursor.fetchone()

        if not user_data:
            logger.error(f"No se encontró el usuario '{username}'")
            return 0, 1

        user_id = user_data['id']
        query = "INSERT INTO chats (user_id, role, mensaje) VALUES (%s, %s, %s)"
        cursor.execute(query, (user_id, role, message))
        conexion.commit()
        logger.info(f"✓ Chat guardado: {username} ({role})")
        return 1, 0
    except Exception as e:
        logger.error(f"Error guardando chat: {e}")
        return 0, 1
    finally:
        if 'conexion' in locals() and conexion.is_connected():
            cursor.close()
            conexion.close()
            logger.debug("Conexión cerrada correctamente.")

if __name__ == "__main__":
    exitos = 0
    errores = 0
    if len(sys.argv) >= 4:
        user = sys.argv[1]
        role = sys.argv[2]
        msg = " ".join(sys.argv[3:])
        exitos, errores = save_chat_to_db(user, role, msg)

    # Lógica de Log Inteligente
    minuto = datetime.datetime.now().minute
    if (exitos + errores > 0) or (minuto in [0, 30]):
        logger.info(f"Resumen Chat: {exitos} mensajes guardados, {errores} fallos.")