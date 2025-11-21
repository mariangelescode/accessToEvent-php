// ------------------------------------------------------------
// NOMBRE (1 o 2 líneas, centrado abajo, sin desbordar)
// ------------------------------------------------------------
$fontSize = 13;      // tamaño inicial
$minFont  = 7;       // tamaño mínimo
$maxWidth = 44;      // ancho útil dentro del ticket (50mm – 6mm de márgenes)

do {
    $pdf->SetFont('Arial', '', $fontSize);
    list($l1, $l2) = $this->fitNameTwoLines($pdf, $name, $maxWidth);

    $ok1 = ($pdf->GetStringWidth($l1) <= $maxWidth);
    $ok2 = ($l2 === "" || $pdf->GetStringWidth($l2) <= $maxWidth);

    if ($ok1 && $ok2) break;

    $fontSize -= 0.5;

} while ($fontSize >= $minFont);

// Posición centrada abajo
$baseY = $y + 62;

$pdf->SetFont('Arial', '', $fontSize);
$pdf->SetTextColor(0,0,0);

// Línea 1
$pdf->SetXY($x, $baseY);
$pdf->Cell($ticketWidth, 5, $l1, 0, 1, 'C');

// Línea 2 (solo si existe)
if ($l2 !== "") {
    $pdf->SetXY($x, $baseY + 5);
    $pdf->Cell($ticketWidth, 5, $l2, 0, 1, 'C');
}
