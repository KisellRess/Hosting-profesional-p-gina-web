<?php
/* ============================================================
   ARCHIVO: cron_facturacion.php
   FUNCION: crear las facturas periodicas.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

require_once __DIR__ . '/conexiones.php';

$db = getConexion();
$hoy = new DateTime();
$fecha_emision = $hoy->format('Y-m-d');

echo "Iniciando cron de facturacion: $fecha_emision...\n";

$PRECIO_PLAN = [
    'BASICO'       => 7.50,
    'BÁSICO'       => 7.50,
    'PROFESIONAL'  => 15.00,
    'ENTERPRISE'   => 25.00,
];
const STORAGE_PRECIO   = 3.00;
const MULTIUSER_PRECIO = 2.00;

function desglose_iva(float $total): array {
    $total = round($total, 2);
    $base = round($total / 1.21, 2);
    return [$base, round($total - $base, 2), $total];
}

function etiqueta_modulo(string $modulo): string {
    return match ($modulo) {
        'sql_php' => 'Acceso SQL/PHP',
        'domain'  => 'Gestion de Dominio',
        'web_ai'  => 'Diseño Web - Tramitando propuesta',
        default   => ucfirst(str_replace('_', ' ', $modulo)),
    };
}

$sql = "SELECT id, nombre, plan_contratado, extras_json, storage_qty, multiuser_qty
        FROM usuarios
        WHERE estado_servicio = 'Activo'
          AND plan_contratado != 'Ninguno'
          AND renovacion_automatica = 1
          AND DAY(fecha_alta) = DAY(CURDATE())";

$res = $db->query($sql);

if (!$res || $res->num_rows === 0) {
    echo "No hay renovaciones programadas para el dia de hoy.\n";
    $db->close();
    exit;
}

$facturas_generadas = 0;

while ($user = $res->fetch_assoc()) {
    $user_id  = (int)$user['id'];
    $plan_raw = $user['plan_contratado'];
    $plan     = strtoupper(trim($plan_raw));

    $plan_total = $PRECIO_PLAN[$plan] ?? 0.00;
    if ($plan_total === 0.00) {
        echo "  [SKIP] Usuario #$user_id - plan '$plan_raw' no reconocido.\n";
        continue;
    }

    $storage_qty   = max(0, (int)($user['storage_qty'] ?? 0));
    $multiuser_qty = max(0, (int)($user['multiuser_qty'] ?? 0));
    $extras_total  = 0.00;
    $cron_items    = [];
    $detalles      = [
        ['item' => 'Plan ' . ucfirst(strtolower($plan_raw)), 'precio' => round($plan_total, 2)],
    ];

    if ($storage_qty > 0) {
        $precio_storage = round($storage_qty * STORAGE_PRECIO, 2);
        $extras_total += $precio_storage;
        $cron_items[] = "Almacenamiento Extra x$storage_qty";
        $detalles[] = ['item' => "Almacenamiento Extra x$storage_qty", 'precio' => $precio_storage];
    }

    if ($multiuser_qty > 0) {
        $precio_multiuser = round($multiuser_qty * MULTIUSER_PRECIO, 2);
        $extras_total += $precio_multiuser;
        $cron_items[] = "Multi-usuarios x$multiuser_qty";
        $detalles[] = ['item' => "Multi-usuarios x$multiuser_qty", 'precio' => $precio_multiuser];
    }

    $extras_arr = json_decode($user['extras_json'] ?? '[]', true);
    if (is_array($extras_arr)) {
        foreach ($extras_arr as $extra) {
            $parts = explode('|', (string)$extra, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $m_name  = trim($parts[0]);
            $m_price = round((float)$parts[1], 2);

            if ($m_name === 'sql_php' && in_array($plan, ['PROFESIONAL', 'ENTERPRISE'], true)) {
                continue;
            }
            if ($m_name === 'domain' && $plan === 'ENTERPRISE') {
                continue;
            }

            $item_label = etiqueta_modulo($m_name);
            if ($m_name === 'web_ai') {
                $cron_items[] = 'Diseño Web';
                $detalles[] = ['item' => 'Modulo Diseño Web - Tramitando propuesta', 'precio' => 0.00];
                continue;
            }

            $extras_total += $m_price;
            $cron_items[] = $item_label;
            $detalles[] = ['item' => $item_label, 'precio' => $m_price];
        }
    }

    $importe_total = round($plan_total + $extras_total, 2);
    [$base_imponible, $iva_importe, $importe_total] = desglose_iva($importe_total);
    $detalles_json = json_encode($detalles, JSON_UNESCAPED_UNICODE);

    $concepto = "Renovacion mensual - Plan " . ucfirst(strtolower($plan_raw));
    if (!empty($cron_items)) {
        $concepto .= " (+ " . implode(", ", array_unique($cron_items)) . ")";
    }

    $stmt = $db->prepare(
        "INSERT INTO facturas (user_id, fecha_emision, concepto, importe, base_imponible, iva_importe, detalles_json, tipo, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'factura', 'Pagado')"
    );
    $stmt->bind_param("issddds", $user_id, $fecha_emision, $concepto, $importe_total, $base_imponible, $iva_importe, $detalles_json);
    if ($stmt->execute()) {
        $facturas_generadas++;
        echo "  [OK] Usuario #$user_id - $concepto - {$importe_total} EUR\n";
    } else {
        echo "  [ERROR] Usuario #$user_id - " . $stmt->error . "\n";
    }
    $stmt->close();
}

echo "Proceso finalizado. Facturas generadas hoy: $facturas_generadas\n";
$db->close();