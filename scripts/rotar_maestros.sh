#!/bin/bash

# ARCHIVO: rotar_maestros.sh
# FUNCION: archivar semanalmente los registros maestros y acciones web.
# SECCIONES: rutas, copia de logs y limpieza final.

# Configuración de rutas
ORIGEN="/opt/tfg/scripts/logs"
DESTINO="/opt/tfg/scripts/maestros"
# Formato de fecha: LS-17-05-2026 (LS indica Log Semanal)
FECHA="LS-$(date +%d-%m-%Y)"

# Preparar carpetas y glob seguro por si algun log aun no existe.
mkdir -p "$ORIGEN" "$DESTINO"
shopt -s nullglob

# Entrar en la carpeta de logs
cd "$ORIGEN" || exit 1

# Procesar maestros de workers y acciones web compartidas.
for archivo in *_master.log acciones.log; do
    if [ -f "$archivo" ]; then
        # Nombre base sin extensión (ej: virtualhosts_master)
        NOMBRE_BASE=$(basename "$archivo" .log)

        # 1. Copiar el archivo a la carpeta maestros con el nuevo nombre
        cp "$archivo" "$DESTINO/${FECHA}-${NOMBRE_BASE}.txt"

        # 2. Vaciar el archivo original para empezar la semana de cero
        cat /dev/null > "$archivo"

        echo "Rotado: $archivo -> ${FECHA}-${NOMBRE_BASE}.txt"
    fi
done

# Ajustar permisos por si acaso
chown -R ubuntu:ubuntu $DESTINO