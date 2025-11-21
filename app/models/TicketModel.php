<?php
// TicketModel.php
// Requiere: composer autoload + endroid/qr-code + setasign/fpdf
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
        $this->storageQr  = __DIR__ . '/../../storage/qr';
        $this->storagePdf = __DIR__ . '/../../storage/pdf';

        if (!is_dir($this->storageQr)) mkdir($this->storageQr, 0777, true);
        if (!is_dir($this->storagePdf)) mkdir($this->storagePdf, 0777, true);
    }

    // -------------------------------------------------------------
    // Ajustar nombre a 1 o 2 líneas sin desbordar
    // -------------------------------------------------------------
    private function fitNameTwoLines($pdf, $text, $maxWidth)
    {
        $text = trim(iconv('UTF-8','ISO-8859-1//TRANSLIT',$text));
        if ($text === "") return ["",""];

        $words = explode(" ", $text);
        $line1 = "";
        $line2 = "";

        foreach ($words as $w) {
            $try = trim($line1 . " " . $w);

            if ($pdf->GetStringWidth($try) <= $maxWidth) {
                $line1 = $try;
            } else {
                $line2 .= $w . " ";
            }
        }

        return [trim($line1), trim($line2)];
    }

    // --------------------------------------------------------------------
    // GENERAR BOLETOS DESDE CSV
    // --------------------------------------------------------------------
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
                    if (trim((string)$c) !== '') { 
                        $allEmpty = false; 
                        break; 
                    }
                }
                if ($allEmpty) continue;

                $rows[] = $data;
            }
            fclose($handle);
        }

        // ----------------------------------------------------------------
        //  CONFIGURAR PDF (LETTER)
        // ----------------------------------------------------------------
        $pdf = new \FPDF('P', 'mm', 'LETTER');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // Layout: 12 boletos por página
        $ticketWidth  = 50;
        $ticketHeight = 85;

        $colX = [5, 55, 105, 155];
        $rowY = [5, 90, 175];

        $i = 0;

        foreach ($rows as $r) {

            $user   = trim($r[0] ?? '');
            $name   = trim($r[1] ?? '');
            $center = trim($r[2] ?? '');

            // Guardar en la BD
            if ($user !== '' || $name !== '') {
                $stmt = $this->mysqli->prepare("INSERT INTO tickets(sap, name, center) VALUES(?,?,?)");
                if ($stmt) {
                    $stmt->bind_param("sss", $user, $name, $center);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // ----------------------------------------------------------------
            // GENERAR QR
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

            // Posiciones
            $col = $i % 4;
            $row = intdiv($i % 12, 4);

            if ($i > 0 && $i % 12 == 0) {
                $pdf->AddPage();
            }

            $x = $colX[$col];
            $y = $rowY[$row];

            // Fondo del boleto
            $plantilla = __DIR__ . '/../../storage/qr/ticket.png';
            $pdf->Image($plantilla, $x, $y, $ticketWidth, $ticketHeight);

            // QR centrado
            $qrSize = 25;
            $qrX = $x + ($ticketWidth / 2) - ($qrSize / 2);
            $qrY = $y + 25;

            $pdf->Image($qrFile, $qrX, $qrY, $qrSize, $qrSize);

            // ------------------------------------------------------------
            // NOMBRE (2 líneas máximo, centrado, 2 cm más abajo del QR)
            // ------------------------------------------------------------
            $fontSize = 13;
            $minFont  = 7;
            $maxWidth = 44;

            // Ajustar nombre a 2 líneas como máximo
            do {
                $pdf->SetFont('Arial', '', $fontSize);
                list($l1, $l2) = $this->fitNameTwoLines($pdf, $name, $maxWidth);

                $ok1 = ($pdf->GetStringWidth($l1) <= $maxWidth);
                $ok2 = ($l2 === "" || $pdf->GetStringWidth($l2) <= $maxWidth);

                if ($ok1 && $ok2) break;

                $fontSize -= 0.5;

            } while ($fontSize >= $minFont);

            // NUEVO: bajar el nombre 2 cm extra
            $baseY = $y + 25 + $qrSize + 35; // QR inicia en y+25, QR mide 25, +20mm

            $pdf->SetFont('Arial', '', $fontSize);
            $pdf->SetTextColor(0,0,0);

            // Línea 1
            $pdf->SetXY($x, $baseY);
            $pdf->Cell($ticketWidth, 5, $l1, 0, 1, 'C');

            // Línea 2
            if ($l2 !== "") {
                $pdf->SetXY($x, $baseY + 5);
                $pdf->Cell($ticketWidth, 5, $l2, 0, 1, 'C');
            }

            // Limpiar QR temporal
            if (file_exists($qrFile)) @unlink($qrFile);

            $i++;
        }

        // ----------------------------------------------------------------
        // GUARDAR PDF
        // ----------------------------------------------------------------
        $pdfFile = $this->storagePdf . '/boletos.pdf';
        $pdf->Output('F', $pdfFile);

        if (!file_exists($pdfFile)) {
            throw new Exception("No se pudo crear el PDF");
        }

        return $pdfFile;
    }
}
