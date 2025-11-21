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
        $this->storageQr  = __DIR__ . '/../../storage/qr';
        $this->storagePdf = __DIR__ . '/../../storage/pdf';

        if (!is_dir($this->storageQr)) mkdir($this->storageQr, 0777, true);
        if (!is_dir($this->storagePdf)) mkdir($this->storagePdf, 0777, true);
    }


    // --------------------------------------------------------------------
    // FUNCIÓN fitText: Ajusta texto a ancho y máximo 2 líneas
    // --------------------------------------------------------------------
    private function fitText($pdf, $text, $maxWidth, $maxFont = 12, $minFont = 5) {
        $text = iconv('UTF-8','ISO-8859-1//TRANSLIT',$text);
        $words = explode(" ", $text);

        for ($font = $maxFont; $font >= $minFont; $font--) {
            $pdf->SetFont('Arial', '', $font);
            $lines = [];
            $current = "";

            foreach ($words as $w) {
                $test = trim($current . " " . $w);

                if ($pdf->GetStringWidth($test) <= $maxWidth) {
                    $current = $test;
                } else {
                    $lines[] = trim($current);
                    $current = $w;
                }
            }

            if ($current !== "") {
                $lines[] = trim($current);
            }

            // Máximo 2 líneas
            if (count($lines) <= 2) {

                // Recorte seguro por si una palabra es demasiado larga
                foreach ($lines as &$ln) {
                    if (strlen($ln) > 22) {
                        $ln = substr($ln, 0, 22) . '…';
                    }
                }

                return [
                    "lines" => $lines,
                    "font"  => $font
                ];
            }
        }

        // Último recurso: recortar
        $pdf->SetFont('Arial', '', $minFont);
        return [
            "lines" => [substr($text, 0, 22) . '…'],
            "font"  => $minFont
        ];
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
        //  CONFIGURAR PDF (LETTER)
        // ----------------------------------------------------------------
        $pdf = new \FPDF('P', 'mm', 'LETTER');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // ----------------------------------------------------------------
        //  LAYOUT 12 BOLETOS POR PÁGINA
        // ----------------------------------------------------------------
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

            // Posiciones
            $col = $i % 4;
            $row = intdiv($i % 12, 4);

            if ($i > 0 && $i % 12 == 0) {
                $pdf->AddPage();
            }

            $x = $colX[$col];
            $y = $rowY[$row];

            // Plantilla del boleto
            $plantilla = __DIR__ . '/../../storage/qr/ticket.png';
            $pdf->Image($plantilla, $x, $y, $ticketWidth, $ticketHeight);

            // QR centrado
            $qrSize = 28;
            $qrX = $x + ($ticketWidth / 2) - ($qrSize / 2);
            $qrY = $y + 28;

            $pdf->Image($qrFile, $qrX, $qrY, $qrSize, $qrSize);

            // ----------------------------------------------------------------
            //  AJUSTE INTELIGENTE DEL NOMBRE (ya corregido)
            // ----------------------------------------------------------------
            $maxTextWidth = $ticketWidth - 10;

            $resultText = $this->fitText($pdf, $name ?: '-', $maxTextWidth);

            $pdf->SetFont('Arial', '', $resultText['font']);
            $pdf->SetTextColor(0, 0, 0);

            // ❗ NUEVA POSICIÓN que evita desbordes
            $startY = $y + 55;

            foreach ($resultText['lines'] as $idx => $line) {
                $pdf->SetXY($x, $startY + ($idx * 3.5));
                $pdf->Cell($ticketWidth, 4, $line, 0, 1, 'C');
            }

            // Eliminar QR temporal
            if (file_exists($qrFile)) @unlink($qrFile);

            $i++;
        }

        // ----------------------------------------------------------------
        //  GUARDAR PDF
        // ----------------------------------------------------------------
        $pdfFile = $this->storagePdf . '/boletos.pdf';
        $pdf->Output('F', $pdfFile);

        if (!file_exists($pdfFile)) {
            throw new Exception("No se pudo crear el PDF");
        }

        return $pdfFile;
    }
}
