<?php
/* ============================================================
   ARCHIVO: descargar_factura.php
   FUNCION: generar el PDF de una factura autorizada.
   SECCIONES: preparacion de datos, vista o respuesta, y acciones necesarias.
   ============================================================ */

require_once 'sessions.php';
require_auth();
require_once 'conexiones.php';

if (!file_exists('fpdf/fpdf.php')) {
    die("La libreria FPDF no esta instalada en la carpeta 'fpdf/'.");
}
require('fpdf/fpdf.php');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de factura invalido.");
}

$factura_id = (int)$_GET['id'];
$user_id    = (int)$_SESSION['user_id'];
$is_admin   = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');

$db = getConexion();

if ($is_admin) {
    $stmt = $db->prepare(
        "SELECT f.*, u.nombre_fiscal, u.documento_identidad, u.direccion_completa, u.nombre, u.email
         FROM facturas f
         JOIN usuarios u ON f.user_id = u.id
         WHERE f.id = ?"
    );
    $stmt->bind_param("i", $factura_id);
} else {
    $stmt = $db->prepare(
        "SELECT f.*, u.nombre_fiscal, u.documento_identidad, u.direccion_completa, u.nombre, u.email
         FROM facturas f
         JOIN usuarios u ON f.user_id = u.id
         WHERE f.id = ? AND f.user_id = ?"
    );
    $stmt->bind_param("ii", $factura_id, $user_id);
}

$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    $stmt->close();
    $db->close();
    die("Factura no encontrada o no tienes permisos.");
}

$factura = $res->fetch_assoc();
$stmt->close();
$db->close();

function money_pdf(float $value): string {
    return number_format($value, 2, ',', '.') . ' EUR';
}

function iva_desde_total(float $total): array {
    $total = round($total, 2);
    $base = round($total / 1.21, 2);
    return [$base, round($total - $base, 2), $total];
}

$total = round((float)($factura['importe'] ?? 0), 2);
$base_imponible = isset($factura['base_imponible']) && $factura['base_imponible'] !== null
    ? round((float)$factura['base_imponible'], 2)
    : round($total / 1.21, 2);
$iva = isset($factura['iva_importe']) && $factura['iva_importe'] !== null
    ? round((float)$factura['iva_importe'], 2)
    : round($total - $base_imponible, 2);

$detalles = json_decode($factura['detalles_json'] ?? '', true);
if (!is_array($detalles) || $detalles === []) {
    $detalles = [
        ['item' => $factura['concepto'], 'precio' => $total],
    ];
}

$tipo = strtolower((string)($factura['tipo'] ?? 'factura'));
$titulo_documento = ($tipo === 'reembolso') ? 'FACTURA RECTIFICATIVA / ABONO' : 'FACTURA OFICIAL';

class PDF extends FPDF
{
    function Header()
    {
        global $titulo_documento;
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(200, 169, 110);
        $this->Cell(100, 10, 'VINOMADRID PLATFORM', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(90, 10, utf8_decode($titulo_documento), 0, 1, 'R');
        $this->Line(10, 22, 200, 22);
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Pagina ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(100, 6, 'Emisor: VinoMadrid Hosting S.L.', 0, 0);
$pdf->Cell(90,  6, 'Factura: #' . str_pad($factura['id'], 5, '0', STR_PAD_LEFT), 0, 1, 'R');
$pdf->Cell(100, 6, 'NIF: B12345678', 0, 0);
$pdf->Cell(90,  6, 'Fecha: ' . date('d/m/Y', strtotime($factura['fecha_emision'])), 0, 1, 'R');
$pdf->Ln(10);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(190, 6, 'DATOS DEL CLIENTE', 0, 1);
$pdf->SetFont('Arial', '', 10);
$nombre = !empty($factura['nombre_fiscal']) ? $factura['nombre_fiscal'] : $factura['nombre'];
$pdf->Cell(190, 6, 'Cliente: ' . utf8_decode($nombre), 0, 1);
$pdf->Cell(190, 6, 'NIF/DNI: ' . utf8_decode($factura['documento_identidad'] ?? '-'), 0, 1);
$pdf->Cell(190, 6, 'Direccion: ' . utf8_decode($factura['direccion_completa'] ?? '-'), 0, 1);
$pdf->Ln(10);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(19, 19, 26);
$pdf->SetTextColor(200, 169, 110);
$pdf->Cell(85, 8, ' Concepto', 0, 0, 'L', true);
$pdf->Cell(35, 8, 'Base', 0, 0, 'R', true);
$pdf->Cell(35, 8, 'IVA (21%)', 0, 0, 'R', true);
$pdf->Cell(35, 8, 'Total cobrado', 0, 1, 'R', true);

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);
foreach ($detalles as $detalle) {
    $item = trim((string)($detalle['item'] ?? $factura['concepto']));
    $precio = round((float)($detalle['precio'] ?? 0), 2);
    [$line_base, $line_iva, $line_total] = iva_desde_total($precio);

    $pdf->Cell(85, 9, ' ' . utf8_decode(mb_substr($item, 0, 48)), 'B', 0, 'L');
    $pdf->Cell(35, 9, money_pdf($line_base), 'B', 0, 'R');
    $pdf->Cell(35, 9, money_pdf($line_iva), 'B', 0, 'R');
    $pdf->Cell(35, 9, money_pdf($line_total), 'B', 1, 'R');
}
$pdf->Ln(6);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(85, 8, '', 0, 0);
$pdf->Cell(35, 8, 'Total Base', 'T', 0, 'R');
$pdf->Cell(35, 8, 'IVA (21%)', 'T', 0, 'R');
$pdf->Cell(35, 8, 'Total Cobrado', 'T', 1, 'R');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(85, 9, '', 0, 0);
$pdf->Cell(35, 9, money_pdf($base_imponible), 0, 0, 'R');
$pdf->Cell(35, 9, money_pdf($iva), 0, 0, 'R');
$pdf->Cell(35, 9, money_pdf($total), 0, 1, 'R');

if ($tipo === 'reembolso') {
    $pdf->Ln(8);
    $pdf->SetFillColor(245, 236, 218);
    $pdf->SetTextColor(70, 55, 25);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->MultiCell(190, 8, utf8_decode('Abono autorizado. El dinero se hara efectivo en su cuenta bancaria en un plazo maximo de 2 dias habiles.'), 1, 'L', true);
}

$pdf->Output('I', 'Factura_' . str_pad($factura['id'], 5, '0', STR_PAD_LEFT) . '.pdf');
exit;