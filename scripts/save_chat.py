import sys
import datetime
import json
from tfg_lib import DB, Logger, lock

logger = Logger.get_logger("ChatSystem", "chat_system")

def save_chat_to_db(username, role, message):
    try:
        with DB() as (conexion, cursor):
            cursor.execute("SELECT id FROM usuarios WHERE nombre = %s OR ftp_user = %s", (username, username))
            user_data = cursor.fetchone()

            if not user_data:
                logger.error(f"No se encontró el usuario '{username}'")
                return 0, 1

            user_id = user_data['id']
            query = "INSERT INTO chats (user_id, role, mensaje) VALUES (%s, %s, %s)"
            cursor.execute(query, (user_id, role, message))
            logger.info(f"✓ Chat guardado: {username} ({role})")
            return 1, 0
    except Exception as e:
        logger.error(f"Error guardando chat: {e}")
        return 0, 1

@lock("save_chat")
def procesar_chat():
    exitos = 0
    errores = 0
    
    # 1. Intentamos leer en formato JSON si se pasa como 3 argumentos (sys.argv[2] es el JSON)
    if len(sys.argv) == 3:
        try:
            user = sys.argv[1]
            data = json.loads(sys.argv[2])
            role = data.get("emisor", "usuario")
            
            # Si hay cuestionario, lo formateamos de forma legible
            if "cuestionario" in data:
                cuest = data["cuestionario"]
                msg = (
                    "Cuestionario Inicial Completado:\n"
                    f"- Showcase: {cuest.get('showcase', '')}\n"
                    f"- Colores: {cuest.get('colores', '')}\n"
                    f"- Fuentes: {cuest.get('fuentes', '')}"
                )
            else:
                msg = data.get("mensaje", "")
                
            exitos, errores = save_chat_to_db(user, role, msg)
        except Exception as e_json:
            logger.error(f"Error parseando JSON de chat: {e_json}")
            errores += 1
            
    # 2. Formato alternativo compatible con argumentos tradicionales (3 o más argumentos en la shell)
    elif len(sys.argv) >= 4:
        user = sys.argv[1]
        role = sys.argv[2]
        msg = " ".join(sys.argv[3:])
        exitos, errores = save_chat_to_db(user, role, msg)

    # Lógica de Log Inteligente
    minuto = datetime.datetime.now().minute
    if (exitos + errores > 0) or (minuto in [0, 30]):
        logger.info(f"Resumen Chat: {exitos} mensajes guardados, {errores} fallos.")

if __name__ == "__main__":
    procesar_chat()