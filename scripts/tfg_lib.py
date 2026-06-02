"""ARCHIVO: tfg_lib.py
FUNCION: compartir conexion, registro y bloqueo entre scripts Python.
SECCIONES: base de datos, logs y exclusiones de ejecucion.
"""

import os
import sys
import fcntl
import logging
import mysql.connector
from functools import wraps

class DB:
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

    def __init__(self, admin=False):
        self.admin = admin
        self.conn = None
        self.cursor = None

    @classmethod
    def get_connection(cls, admin=False):
        config = cls.ADMIN_CONFIG if admin else cls.DB_CONFIG
        # Añadimos timeouts de conexión para evitar que se quede pillado en bucle
        config["connect_timeout"] = 10
        return mysql.connector.connect(**config)

    def __enter__(self):
        self.conn = self.get_connection(self.admin)
        # Forzamos que el cursor sea resiliente y limpie buffers
        self.cursor = self.conn.cursor(dictionary=True, buffered=True)
        return self.conn, self.cursor

    def __exit__(self, exc_type, exc_val, exc_tb):
        try:
            if self.cursor:
                self.cursor.close()
            if self.conn:
                if exc_type is None:
                    self.conn.commit()
                else:
                    self.conn.rollback()
                self.conn.close()
        except Exception as e:
            # Si el socket ya se había caído, evitamos que lance una excepción secundaria
            pass

class Logger:
    @staticmethod
    def get_logger(script_name, log_name_base):
        log_dir = "/opt/tfg/scripts/logs"
        if not os.path.exists(log_dir):
            try:
                os.makedirs(log_dir)
                os.chmod(log_dir, 0o777)
            except:
                pass

        logger = logging.getLogger(script_name)
        
        if not logger.handlers:
            logger.setLevel(logging.DEBUG)
            formatter = logging.Formatter('[%(asctime)s] %(levelname)s - %(message)s', datefmt='%Y-%m-%d %H:%M:%S')

            master_handler = logging.FileHandler(f"{log_dir}/{log_name_base}_master.log", mode='a', encoding='utf-8')
            master_handler.setFormatter(formatter)
            logger.addHandler(master_handler)

            diario_handler = logging.FileHandler(f"{log_dir}/{log_name_base}.txt", mode='a', encoding='utf-8')
            diario_handler.setFormatter(formatter)
            logger.addHandler(diario_handler)

        return logger

_lock_fps = {}

def lock(lock_name):
    def decorator(func):
        @wraps(func)
        def wrapper(*args, **kwargs):
            lock_file = f"/tmp/{lock_name}.lock"
            fp = open(lock_file, 'w')
            try:
                fcntl.lockf(fp, fcntl.LOCK_EX | fcntl.LOCK_NB)
                _lock_fps[lock_name] = fp
            except IOError:
                sys.exit(0)
            return func(*args, **kwargs)
        return wrapper
    return decorator