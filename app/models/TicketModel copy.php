<?php
// TicketModel.php
// Requiere: composer autoload + endroid/qr-code + setasign/fpdf instalados
// Ruta: /var/www/html/access/app/models/TicketModel.php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class TicketModel {
    private $mysqli;
    private $storageQr;
    private $storagePdf;

    public function __construct($config) {
        $this->mysqli = new mysqli(
            $config['db_host'] ?? $config->db_host ?? '',
            $config['db_user'] ?? $config->db_user ?? '',
            $config['db_pass'] ?? $config->db_pass ?? '',
            $config['db_name'] ?? $config->db_name ?? ''
        );

        if ($this->mysqli->connect_errno) {
            throw new Exception("Error MySQL: " . $this->mysqli->connect_error);
        }
        $this->mysqli->set_charset("utf8mb4");

        $this->storageQr  = $config['storage_qr']  ?? __DIR__ . '/../../storage/qr';
        $this->storagePdf = $config['storage_pdf'] ?? __DIR__ . '/../../storage/pdf';

        if (!is_dir($this->storageQr)) mkdir($this->storageQr, 0777, true);
        if (!is_dir($this->storagePdf)) mkdir($this->storagePdf, 0777, true);
    }

    public function createTicketsFromCSV($csvFile) {

        if (!file_exists($csvFile)) {
            throw new Exception("CSV no encontrado: $csvFile");
        }

        // Leer CSV
        $rows = [];
        if (($handle = fopen($csvFile, "r")) !== false) {
            while (($data = fgetcsv($handle, 10000, ",")) !== false) {
                $allEmpty = true;
                foreach ($data as $c) {
                    if (trim((string)$c) !== '') { $allEmpty = false; break; }
                }
                if ($allEmpty) continue;
                $rows[] = $data;
            }
            fclose($handle);
        }

        // PDF
        $pdf = new \FPDF('P', 'mm', 'LETTER');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);

        // ----------------------------------------------
        //  🔵 NUEVO LAYOUT — 12 BOLETOS 50×85 mm
        //  🔵 SIN ESPACIO ENTRE BOLETOS
        // ----------------------------------------------

        $ticketWidth  = 50;
        $ticketHeight = 85;

        // 3 columnas exactas sin separación
        $colX = [5, 55, 105];   // Ajustado para LETTER (216 mm)

        // 4 filas exactas sin separación
        $rowY = [5, 90, 175, 260];  // 4 filas de 85 mm (5 + 85 + 85 + 85 = 260)

        $i = 0;

        // Comenzar página
        $pdf->AddPage();

        foreach ($rows as $rIndex => $r) {

            $user   = isset($r[0]) ? trim((string)$r[0]) : '';
            $name   = isset($r[1]) ? trim((string)$r[1]) : '';
            $center = isset($r[2]) ? trim((string)$r[2]) : '';

            // Guardar DB
            if ($user !== '' || $name !== '') {
                $stmt = $this->mysqli->prepare("INSERT INTO tickets(user, name, center) VALUES(?,?,?)");
                if ($stmt) {
                    $stmt->bind_param("sss", $user, $name, $center);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // QR
            $qrData = trim("$user|$name|$center");
            $builder = new Builder();
            $result = $builder
                ->writer(new PngWriter())
                ->data($qrData === '' ? "empty" : $qrData)
                ->size(300)
                ->margin(0)
                ->build();

            $qrFile = $this->storageQr . "/qr_$i.png";
            $result->saveToFile($qrFile);

            // Calcular posición por índice
            $col = $i % 3;
            $row = intdiv($i % 12, 3);

            // Si empieza una nueva página
            if ($i > 0 && $i % 12 == 0) {
                $pdf->AddPage();
            }

            $x = $colX[$col];
            $y = $rowY[$row];

            // Plantilla
            $plantilla = __DIR__ . '/../../storage/qr/ticket.png';
            $pdf->Image($plantilla, $x, $y, $ticketWidth, $ticketHeight);

            // QR centrado
            $qrSize = 28;
            $qrX = $x + ($ticketWidth / 2) - ($qrSize / 2);
            $qrY = $y + 28;

            $pdf->Image($qrFile, $qrX, $qrY, $qrSize, $qrSize);

            // Texto del ticket
            $pdf->SetFont('Arial', 'I', 7);
            $pdf->SetTextColor(0, 0, 0);

            $pdf->SetXY($x, $y + $ticketHeight - 12);
            $pdf->Cell($ticketWidth, 4, iconv('UTF-8','ISO-8859-1//TRANSLIT', $name ?: '-'), 0, 1, 'C');

            // Eliminar QR temporal
            if (file_exists($qrFile)) @unlink($qrFile);

            $i++;
        }

        // Guardar PDF
        $pdfFile = $this->storagePdf . '/boletos.pdf';
        $pdf->Output('F', $pdfFile);

        if (!file_exists($pdfFile)) {
            throw new Exception("No se pudo crear el PDF");
        }

        return $pdfFile;
    }
}
