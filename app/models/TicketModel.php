<?php
// TicketModel.php

require_once __DIR__ . '/../../../vendor/autoload.php'; // vendor un nivel arriba

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class TicketModel {
    private $mysqli;
    private $storageQr;
    private $storagePdf;

    public function __construct($config) {
        $this->mysqli = new mysqli(
            $config->db_host,
            $config->db_user,
            $config->db_pass,
            $config->db_name
        );

        if ($this->mysqli->connect_errno) {
            throw new Exception("Error MySQL: " . $this->mysqli->connect_error);
        }

        $this->mysqli->set_charset("utf8mb4");

        $this->storageQr = $config->storage_qr;
        $this->storagePdf = $config->storage_pdf;

        if (!is_dir($this->storageQr)) mkdir($this->storageQr, 0777, true);
        if (!is_dir($this->storagePdf)) mkdir($this->storagePdf, 0777, true);
    }

    
    public function createTicketsFromCSV($file) {
    $rows = array_map('str_getcsv', file($file));

    // Inicializar PDF
    $pdf = new \FPDF();
    $pdf->AddPage();
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->SetFont('Arial', '', 11);

    $column = 0; // columna actual (0 o 1)
    $x = 10;
    $y = 20;
    $colWidth = 95; // ancho de cada boleto
    $rowHeight = 70; // alto del boleto

    foreach ($rows as $i => $row) {
        // Quitar BOM si existe
        $row[0] = preg_replace('/^\x{FEFF}/u', '', $row[0]);
        [$user, $name, $center] = $row;

        // Insertar en DB
        $stmt = $this->mysqli->prepare("INSERT INTO tickets(user, name, center) VALUES(?,?,?)");
        $stmt->bind_param("sss", $user, $name, $center);
        $stmt->execute();
        $stmt->close();

        // Generar QR
        $builder = new Builder();
        $result = $builder
            ->writer(new PngWriter())
            ->data("$user | $name | $center")
            ->size(100)
            ->margin(5)
            ->build();

        $qrFile = $this->storageQr . "/ticket_{$user}.png";
        $result->saveToFile($qrFile);

        // Posición X del boleto (columna izquierda o derecha)
        $x = ($column == 0) ? 10 : 110;
        $pdf->SetXY($x, $y);

        // Dibujar marco del boleto
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Rect($x, $y, $colWidth - 5, $rowHeight);

        // QR a la izquierda
        $pdf->Image($qrFile, $x + 5, $y + 10, 40, 40);

        // Texto a la derecha del QR
        $pdf->SetXY($x + 50, $y + 15);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 6, "Usuario: $user", 0, 1);

        $pdf->SetX($x + 50);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 6, "Nombre: $name", 0, 1);

        $pdf->SetX($x + 50);
        $pdf->Cell(0, 6, "Centro: $center", 0, 1);

        // Cambiar de columna o fila
        if ($column == 0) {
            $column = 1;
        } else {
            $column = 0;
            $y += $rowHeight + 10; // siguiente fila
        }

        // Nueva página si se llena
        if ($y + $rowHeight > 270) {
            $pdf->AddPage();
            $y = 20;
            $column = 0;
        }
    }

    // Guardar PDF
    $pdfFile = $this->storagePdf . '/boletos.pdf';
    $pdf->Output('F', $pdfFile);

    return $pdfFile;
}

}
