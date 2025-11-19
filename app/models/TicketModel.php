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

        // -----------------------------------------
        // CONEXIÓN MYSQL
        // -----------------------------------------
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

        // -----------------------------------------
        // RUTAS STORAGE
        // -----------------------------------------
        $this->storageQr  = $config['storage_qr']  ?? __DIR__ . '/../../storage/qr';
        $this->storagePdf = $config['storage_pdf'] ?? __DIR__ . '/../../storage/pdf';

        if (!is_dir($this->storageQr)) mkdir($this->storageQr, 0777, true);
        if (!is_dir($this->storagePdf)) mkdir($this->storagePdf, 0777, true);
    }

    public function createTicketsFromCSV($csvFile) {

        if (!file_exists($csvFile)) {
            throw new Exception("CSV no encontrado: $csvFile");
        }

        // ----------------------------------------------------------------
        //  LEER CSV
        // ----------------------------------------------------------------
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

        // ----------------------------------------------------------------
        //  CONFIGURAR PDF – TAMAÑO CARTA (Letter)
        // ----------------------------------------------------------------
        $pdf = new \FPDF('P', 'mm', 'LETTER');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // ----------------------------------------------------------------
        //  LAYOUT SIN ESPACIOS – 12 BOLETOS POR PÁGINA
        // ----------------------------------------------------------------
        $ticketWidth  = 50;   // ↔ ancho boleto
        $ticketHeight = 85;   // ↕ alto boleto

        // 4 columnas exactas (50mm cada una + 5mm de margen inicial)
        $colX = [5, 55, 105, 155];

        // 3 filas exactas
        $rowY = [5, 90, 175];

        $i = 0;

        foreach ($rows as $r) {

            $user   = isset($r[0]) ? trim((string)$r[0]) : '';
            $name   = isset($r[1]) ? trim((string)$r[1]) : '';
            $center = isset($r[2]) ? trim((string)$r[2]) : '';

            // Guardar en DB
            if ($user !== '' || $name !== '') {
                $stmt = $this->mysqli->prepare("INSERT INTO tickets(sap, name, center) VALUES(?,?,?)");
                if ($stmt) {
                    $stmt->bind_param("sss", $user, $name, $center);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // ----------------------------------------------------------------
            //  GENERAR QR
            // ----------------------------------------------------------------
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

            // ----------------------------------------------------------------
            //  CALCULAR POSICIÓN (4 columnas × 3 filas)
            // ----------------------------------------------------------------
            $col = $i % 4;              // 0–3
            $row = intdiv($i % 12, 4);  // 0–2

            // Cambiar página cada 12 boletos
            if ($i > 0 && $i % 12 == 0) {
                $pdf->AddPage();
            }

            $x = $colX[$col];
            $y = $rowY[$row];

            // ----------------------------------------------------------------
            //  PLANTILLA DEL BOLETO
            // ----------------------------------------------------------------
            $plantilla = __DIR__ . '/../../storage/qr/ticket.png';
            

            $pdf->Image($plantilla, $x, $y, $ticketWidth, $ticketHeight);
            if (!file_exists($qrFile)) {
                echo("NO SE CREÓ EL QR → $qrFile");
            }


            // ----------------------------------------------------------------
            //  POSICIÓN QR CENTRADO
            // ----------------------------------------------------------------
            $qrSize = 28;
            $qrX = $x + ($ticketWidth / 2) - ($qrSize / 2);
            $qrY = $y + 28;

            $pdf->Image($qrFile, $qrX, $qrY, $qrSize, $qrSize);

            // ----------------------------------------------------------------
            //  TEXTO DEL TITULAR
            // ----------------------------------------------------------------
            $pdf->SetFont('Arial', 'I', 7);
            $pdf->SetTextColor(0, 0, 0);

            // Texto del nombre 30 px más arriba
            $pdf->SetXY($x, $y + $ticketHeight - 25);
            $pdf->Cell($ticketWidth, 4, iconv('UTF-8','ISO-8859-1//TRANSLIT', $name ?: '-'), 0, 1, 'C');

            // Eliminar QR temporal
            if (file_exists($qrFile)) @unlink($qrFile);

            $i++;


        }

        // ----------------------------------------------------------------
        //  GUARDAR PDF FINAL
        // ----------------------------------------------------------------
        if (!is_dir($this->storagePdf)) mkdir($this->storagePdf, 0777, true);

        $pdfFile = $this->storagePdf . '/boletos.pdf';
        $pdf->Output('F', $pdfFile);

        if (!file_exists($pdfFile)) {
            throw new Exception("No se pudo crear el PDF");
        }

        return $pdfFile;
    }
}
