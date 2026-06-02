#!/usr/bin/env python3
"""
ARCHIVO: check_cuotas.py
FUNCION: revisar el espacio ocupado por cada cuenta activa.
SECCIONES: medida de carpetas, limite contratado y alertas.

check_cuotas.py — Revisión de cuotas de disco por cliente (sin quota Linux)
Revisa /var/www/hosting_tfg/{ftp_user}/htdocs y compara con el límite calculado
a partir de plan_contratado + storage_qty.

Usos:
  python3 check_cuotas.py            → revisa todos los usuarios activos
  python3 check_cuotas.py --dry-run  → muestra resultado sin insertar alertas
"""

import os
import argparse
import datetime
from tfg_lib import DB, Logger, lock

# ─── Configuración ─────────────────────────────────────────────────────────────
BASE_WEB    = "/var/www/hosting_tfg"
HTDOCS_DIR  = "htdocs"
LOG_NAME    = "check_cuotas"

LIMITE_POR_PLAN_GB = {
    "BÁSICO":       1,
    "BASICO":       1,   # alias sin tilde
    "PROFESIONAL":  3,
    "ENTERPRISE":   5,
}
STORAGE_PACK_GB = 2   # GB por cada pack extra (storage_qty)

# Umbrales de aviso (en porcentaje)
UMBRAL_WARNING  = 80
UMBRAL_CRITICO  = 100

# ─── Logger ────────────────────────────────────────────────────────────────────
logger = Logger.get_logger("CheckCuotas", LOG_NAME)


# ─── Helpers ───────────────────────────────────────────────────────────────────
def get_folder_size_bytes(path: str) -> int:
    """Suma recursiva del tamaño de todos los archivos bajo path."""
    total = 0
    if not os.path.isdir(path):
        return 0
    for dirpath, _, filenames in os.walk(path):
        for fname in filenames:
            fpath = os.path.join(dirpath, fname)
            try:
                total += os.path.getsize(fpath)
            except OSError:
                pass
    return total


def calcular_limite_gb(plan: str, storage_qty: int) -> float:
    """Devuelve el límite total en GB para un usuario."""
    plan_upper = (plan or "").upper().strip()
    base = LIMITE_POR_PLAN_GB.get(plan_upper, 1)
    return base + (storage_qty * STORAGE_PACK_GB)


def insertar_alerta(cur, conn, user_id: int, nombre: str, motivo: str, simbolo: str = "💾"):
    """Inserta alerta en alertas_admin si no existe una igual no reconocida."""
    cur.execute(
        """SELECT id FROM alertas_admin
           WHERE user_id = %s AND motivo = %s AND reconocida = 0 LIMIT 1""",
        (user_id, motivo)
    )
    if cur.fetchone():
        return  # ya existe, evitamos duplicados
    cur.execute(
        """INSERT INTO alertas_admin (user_id, nombre_usuario, motivo, simbolo, reconocida, fecha)
           VALUES (%s, %s, %s, %s, 0, NOW())""",
        (user_id, nombre, motivo, simbolo)
    )
    conn.commit()


# ─── Lógica principal ──────────────────────────────────────────────────────────
@lock("check_cuotas")
def revisar_cuotas(dry_run: bool = False):
    ahora = datetime.datetime.now()
    mostrar_revision = dry_run or ahora.minute in [0, 30]
    if mostrar_revision:
        fecha = ahora.strftime("%Y-%m-%d %H:%M:%S")
        logger.info(f"=== INICIO revisión de cuotas [{fecha}] {'[DRY-RUN]' if dry_run else ''} ===")

    alertas_generadas  = 0
    usuarios_revisados = 0
    usuarios_ok        = 0

    with DB() as (conn, cur):
        cur.execute(
            """SELECT id, nombre, ftp_user, plan_contratado, storage_qty
               FROM usuarios
               WHERE estado_servicio = 'Activo'
                 AND ftp_user IS NOT NULL
                 AND ftp_user != ''"""
        )
        usuarios = cur.fetchall()

        for u in usuarios:
            u_id        = u["id"]
            u_nombre    = u["nombre"]
            u_ftp       = u["ftp_user"]
            u_plan      = u["plan_contratado"] or "Ninguno"
            u_storage   = int(u["storage_qty"] or 0)

            htdocs_path = os.path.join(BASE_WEB, u_ftp, HTDOCS_DIR)
            bytes_usados = get_folder_size_bytes(htdocs_path)
            mb_usados    = bytes_usados / (1024 * 1024)
            gb_usados    = bytes_usados / (1024 * 1024 * 1024)

            limite_gb  = calcular_limite_gb(u_plan, u_storage)
            limite_mb  = limite_gb * 1024
            porcentaje = round((mb_usados / limite_mb * 100), 1) if limite_mb > 0 else 0

            usuarios_revisados += 1

            # ── Determinar estado ──────────────────────────────────────────────
            if porcentaje >= UMBRAL_CRITICO:
                nivel   = "CRÍTICO"
                simbolo = "🔴"
                motivo  = f"CUOTA SUPERADA: Usuario '{u_nombre}' usa {round(gb_usados,2)} GB de {limite_gb} GB ({porcentaje}%) en {htdocs_path}"
                logger.error(f"[CUOTA-CRÍTICA] ID={u_id} | {u_nombre} | {round(gb_usados,3)} GB / {limite_gb} GB ({porcentaje}%) — {htdocs_path}")
            elif porcentaje >= UMBRAL_WARNING:
                nivel   = "ADVERTENCIA"
                simbolo = "⚠️"
                motivo  = f"Espacio bajo: Usuario '{u_nombre}' usa {round(mb_usados,1)} MB de {limite_mb:.0f} MB ({porcentaje}%)"
                logger.warning(f"[CUOTA-WARNING] ID={u_id} | {u_nombre} | {round(mb_usados,1)} MB / {limite_mb:.0f} MB ({porcentaje}%) — {htdocs_path}")
            else:
                nivel   = "OK"
                usuarios_ok += 1
                if mostrar_revision:
                    logger.info(f"[CUOTA-OK]      ID={u_id} | {u_nombre} | {round(mb_usados,1)} MB / {limite_mb:.0f} MB ({porcentaje}%)")
                continue  # nada más que hacer en este caso

            # ── Insertar alerta en BD ──────────────────────────────────────────
            if not dry_run:
                try:
                    insertar_alerta(cur, conn, u_id, u_nombre, motivo, simbolo)
                    alertas_generadas += 1
                    logger.info(f"  → Alerta creada en alertas_admin (user_id={u_id})")
                except Exception as e:
                    logger.error(f"  → Error al insertar alerta user_id={u_id}: {e}")
            else:
                logger.info(f"  → [DRY-RUN] Alerta no insertada: {motivo}")

    if mostrar_revision or alertas_generadas > 0:
        logger.info(
            f"=== FIN revisión | Revisados: {usuarios_revisados} | OK: {usuarios_ok} "
            f"| Alertas: {alertas_generadas} ==="
        )
    return usuarios_revisados, usuarios_ok, alertas_generadas


# ─── Entry point ───────────────────────────────────────────────────────────────
if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Revisión de cuotas de disco por usuario")
    parser.add_argument("--dry-run", action="store_true", help="Solo mostrar resultado, sin insertar alertas")
    args = parser.parse_args()

    revisados, ok, alertas = revisar_cuotas(dry_run=args.dry_run)

    # Solo imprime en consola si se ejecuta manualmente con --dry-run
    if args.dry_run:
        print(f"[check_cuotas] Revisados: {revisados} | OK: {ok} | Alertas generadas: {alertas}")