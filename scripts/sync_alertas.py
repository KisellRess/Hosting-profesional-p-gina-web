"""ARCHIVO: sync_alertas.py
FUNCION: convertir estados pendientes en avisos para administracion.
SECCIONES: recorrido de estados y alta controlada de alertas.
"""

import mysql.connector
import datetime
from tfg_lib import DB, Logger, lock

logger = Logger.get_logger("AlertSync", "alerta_actual")

@lock("sync_alertas")
def sync_alerts():
    exitos = 0
    errores = 0
    try:
        with DB() as (conn, cursor):
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

def check_and_insert(conn, cursor, user_id, nombre, motivo, simbolo):
    try:
        query_check = "SELECT reconocida FROM alertas_admin WHERE user_id = %s AND motivo = %s"
        cursor.execute(query_check, (user_id, motivo))
        result = cursor.fetchone()
        
        if not result:
            query_ins = "INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida) VALUES (%s, %s, %s, %s, 0)"
            cursor.execute(query_ins, (user_id, nombre, motivo, simbolo))
            logger.info(f"[+] NUEVA ALERTA: {nombre} - {motivo} (ID: {user_id})")
            return True
        return False
    except Exception as e:
        logger.error(f"Error insertando alerta para {nombre}: {e}")
        return False

if __name__ == "__main__":
    sync_alerts()