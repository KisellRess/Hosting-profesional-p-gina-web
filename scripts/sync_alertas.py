import mysql.connector
import logging
import os
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

logger = logging.getLogger("AlertSync")
logger.setLevel(logging.DEBUG)
formatter = logging.Formatter('[%(asctime)s] %(levelname)s - %(message)s', datefmt='%Y-%m-%d %H:%M:%S')

for h_file in [os.path.join(LOG_DIR, "alerta_actual_master.log"),
              os.path.join(LOG_DIR, "alerta_actual.txt")]:
    handler = logging.FileHandler(h_file, encoding='utf-8', delay=False)
    handler.setFormatter(formatter)
    logger.addHandler(handler)

def sync_alerts():
    conn = None
    exitos = 0
    errores = 0
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)

        # Auditoría Dominios
        cursor.execute("SELECT u.id, u.nombre, d.estado_dominio, d.subdominio_alias FROM usuarios u JOIN dominios d ON u.id = d.user_id")
        for r in cursor.fetchall():
            if r['estado_dominio'] in ['Proc', 'Pendiente']:
                motivo = f"Suscripción dominio {r['subdominio_alias']}"
                if check_and_insert(conn, cursor, r['id'], r['nombre'], motivo, "❔"):
                    exitos += 1

        # Auditoría Presupuestos
        cursor.execute("SELECT id, nombre FROM usuarios WHERE extras_json LIKE '%web_ai|100.00%'")
        for r in cursor.fetchall():
            if check_and_insert(conn, cursor, r['id'], r['nombre'], "Solicitud presupuesto", "!"):
                exitos += 1

        # Lógica de Log Inteligente
        minuto = datetime.datetime.now().minute
        if (exitos + errores > 0) or (minuto in [0, 30]):
            logger.info(f"Resumen Alertas: {exitos} generadas, {errores} fallos.")

    except mysql.connector.Error as err:
        logger.error(f"Error de base de datos: {err}")
    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()
            logger.debug("Conexión cerrada correctamente.")

def check_and_insert(conn, cursor, user_id, nombre, motivo, simbolo):
    try:
        query_check = "SELECT reconocida FROM alertas_admin WHERE user_id = %s AND motivo = %s"
        cursor.execute(query_check, (user_id, motivo))
        result = cursor.fetchone()
        
        if not result:
            query_ins = "INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida) VALUES (%s, %s, %s, %s, 0)"
            cursor.execute(query_ins, (user_id, nombre, motivo, simbolo))
            conn.commit()
            logger.info(f"[+] NUEVA ALERTA: {nombre} - {motivo} (ID: {user_id})")
            return True
        return False
    except Exception as e:
        logger.error(f"Error insertando alerta para {nombre}: {e}")
        return False

if __name__ == "__main__":
    sync_alerts()