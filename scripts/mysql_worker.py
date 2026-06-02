"""ARCHIVO: mysql_worker.py
FUNCION: crear o actualizar bases de datos solicitadas desde la web.
SECCIONES: saneado, operacion MySQL y procesamiento de la cola.
"""

import traceback
import re
import datetime
from tfg_lib import DB, Logger, lock

logger = Logger.get_logger("MySQLWorker", "mysql_worker")

def identificador_valido(texto):
    """Acepta solo identificadores ya introducidos y normalizados desde el panel."""
    return bool(texto and re.fullmatch(r'[a-z0-9_]{1,64}', texto))

def password_segura(password):
    """La politica se exige aqui aunque MySQL tenga validate_password en LOW."""
    return bool(
        password
        and len(password) >= 8
        and not any(c.isspace() for c in password)
        and any(c.isalpha() for c in password)
        and any(c.isdigit() for c in password)
        and any(not c.isalnum() and not c.isspace() for c in password)
    )

def solicitud_manual_valida(db_name, db_user, db_pass):
    if not identificador_valido(db_name) or not identificador_valido(db_user):
        logger.error("Solicitud MySQL incompleta o no normalizada; no se generan nombres automaticamente.")
        return False
    if not password_segura(db_pass):
        logger.error("Solicitud MySQL rechazada: la clave debe incluir letras, numeros y simbolos (minimo 8).")
        return False
    return True

def crear_db_fisica(db_name, db_user, db_pass):
    """Crea la base fisica en el MySQL local mediante la conexion administrativa."""
    if not solicitud_manual_valida(db_name, db_user, db_pass):
        return False
    try:
        with DB(admin=True) as (admin_conn, cursor):
            # DB(admin=True) conecta al servidor local sin base seleccionada y con privilegios DDL.
            cursor.execute(f"CREATE DATABASE IF NOT EXISTS `{db_name}` CHARACTER SET utf8mb4")
            cursor.execute("CREATE USER IF NOT EXISTS %s@'localhost' IDENTIFIED BY %s", (db_user, db_pass))
            cursor.execute("ALTER USER %s@'localhost' IDENTIFIED BY %s", (db_user, db_pass))
            cursor.execute(f"GRANT ALL PRIVILEGES ON `{db_name}`.* TO %s@'localhost'", (db_user,))
            cursor.execute("FLUSH PRIVILEGES")
            admin_conn.commit()
        logger.info(f"Base fisica MySQL aplicada en localhost: {db_name} / {db_user}.")
        return True
    except Exception as e:
        logger.error(f"Error físico MySQL en {db_name}: {str(e)}")
        return False

@lock("mysql_worker")
def procesar_mysql():
    exitos = 0
    errores = 0
    try:
        with DB() as (conexion, cursor):
            # Solo Pendiente es una solicitud manual insertada desde el formulario del panel.
            query = """
                SELECT m.id, m.user_id, m.db_name, m.db_user, m.db_pass, m.estado
                FROM modulo_mysql m
                JOIN usuarios u ON m.user_id = u.id
                WHERE m.estado = 'Pendiente'
                AND u.estado_servicio IN ('Activo', 'Pendiente')
            """
            cursor.execute(query)
            tramites = cursor.fetchall()
            
        for t in tramites:
            db_id   = t['id']
            u_id    = t['user_id']
            db_name = t['db_name']
            db_user = t['db_user']
            db_pass = t['db_pass']

            try:
                if not solicitud_manual_valida(db_name, db_user, db_pass):
                    logger.error(f"Registro MySQL pendiente ID {db_id} ignorado: requiere envio manual valido.")
                    errores += 1
                    continue

                logger.info(f"[SOLICITUD MANUAL SQL] Procesando DB: {db_name} para usuario ID: {u_id}")
                if crear_db_fisica(db_name, db_user, db_pass):
                    with DB() as (conexion, cursor):
                        cursor.execute("UPDATE modulo_mysql SET estado = 'Activo' WHERE id = %s", (db_id,))
                        conexion.commit()
                    logger.info(f"  ✓ DB {db_name} activada con éxito.")
                    exitos += 1
                else:
                    errores += 1
            except Exception as e_db:
                logger.error(f"❌ Error procesando registro de DB ID {db_id}: {e_db}")
                logger.error(traceback.format_exc())
                errores += 1

        # 2. Borrar DBs físicas marcadas como 'Para_Borrar'
        with DB() as (conn_select, cur_select):
            cur_select.execute("SELECT m.id, m.db_name, m.db_user FROM modulo_mysql m WHERE m.estado = 'Para_Borrar'")
            purgas = cur_select.fetchall()

        for p in purgas:
            p_id = p['id']
            p_dbname = p['db_name']
            p_dbuser = p['db_user']
            try:
                logger.info(f"[*] Eliminando base de datos física: {p_dbname}")
                
                # Eliminación en el servidor físico MySQL usando la conexión admin
                with DB(admin=True) as (admin_conn, adm_cur):
                    if p_dbname and identificador_valido(p_dbname):
                        adm_cur.execute(f"DROP DATABASE IF EXISTS `{p_dbname}`")
                    if p_dbuser and identificador_valido(p_dbuser):
                        adm_cur.execute(f"DROP USER IF EXISTS %s@'localhost'", (p_dbuser,))
                    adm_cur.execute("FLUSH PRIVILEGES")
                
                # Eliminación del registro en la tabla de la aplicación usando conexión normal
                with DB() as (conn_main, cur_main):
                    cur_main.execute("DELETE FROM modulo_mysql WHERE id = %s", (p_id,))
                
                logger.info(f"✓ Registro eliminado de modulo_mysql para: {p_dbname}")
                exitos += 1
            except Exception as e_del:
                logger.error(f"Error borrando DB {p_dbname if p_dbname else 'None'}: {e_del}")
                errores += 1

        # Heartbeat
        ahora = datetime.datetime.now()
        if (exitos + errores > 0) or (ahora.minute in [0, 30]):
            logger.info(f"Resumen MySQL: {exitos} procesados, {errores} fallos.")

    except Exception as e_global:
        logger.error(f"Error general en MySQL Worker: {e_global}")

if __name__ == "__main__":
    procesar_mysql()