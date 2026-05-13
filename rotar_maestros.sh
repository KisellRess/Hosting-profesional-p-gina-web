#!/bin/bash

# Configuración de rutas
ORIGEN="/opt/tfg/scripts/logs"
DESTINO="/opt/tfg/scripts/maestros"
# Formato de fecha: LS-17-05-2026 (LS indica Log Semanal)
FECHA="LS-$(date +%d-%m-%Y)"

# Entrar en la carpeta de logs
cd $ORIGEN

# Procesar cada archivo .log (los maestros)
for archivo in *.log; do
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
