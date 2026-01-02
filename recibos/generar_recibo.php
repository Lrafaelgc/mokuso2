<?php
// recibos/generar_recibo.php
require('../lib/fpdf/fpdf.php');
include '../config/db.php';

$pago_id = isset($_GET['pago_id']) ? (int)$_GET['pago_id'] : 0;

// Obtener datos del pago y del alumno
$stmt = $conn->prepare("SELECT p.*, a.nombre, a.apellidos FROM pagos p JOIN alumnos a ON p.alumno_id = a.id WHERE p.id = ?");
$stmt->bind_param("i", $pago_id);
$stmt->execute();
$result = $stmt->get_result();
$pago = $result->fetch_assoc();

if (!$pago) {
    die('Recibo no encontrado.');
}

class PDF extends FPDF {
    // Cabecera de página
    function Header() {
        // Logo
        $this->Image('../assets/img/logo2.png', 10, 6, 30);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80);
        $this->Cell(30, 10, 'Recibo de Pago - Mokuso', 0, 0, 'C');
        $this->Ln(20);
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(40, 10, 'Recibo para: ' . utf8_decode($pago['nombre'] . ' ' . $pago['apellidos']));
$pdf->Ln();
$pdf->Cell(40, 10, 'Fecha de Pago: ' . date("d/m/Y", strtotime($pago['fecha_pago'])));
$pdf->Ln();
$pdf->Cell(40, 10, 'Concepto: ' . utf8_decode($pago['concepto']));
$pdf->Ln();
$pdf->Cell(40, 10, 'Mes Correspondiente: ' . utf8_decode($pago['mes_correspondiente']));
$pdf->Ln();
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(40, 10, 'Monto Pagado: $' . number_format($pago['monto'], 2) . ' MXN');
$pdf->Ln();

// Salida del PDF
$pdf->Output('D', 'Recibo_Mokuso_'.$pago_id.'.pdf'); // 'D' fuerza la descarga
?>