<?php
// TicketModel.php
// Requiere: composer autoload + endroid/qr-code + setasign/fpdf
// Ruta: /var/www/html/access/app/models/TicketModel.php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';

date_default_timezone_set('America/Mexico_City'); // <-- esta línea

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class TicketModel {
    private $mysqli;
    private $storageQr;
    private $storagePdf;

    public function __construct($config) {
        $this->mysqli = new mysqli(
            $config['db_host'] ?? '',
            $config['db_user'] ?? '',
            $config['db_pass'] ?? '',
            $config['db_name'] ?? ''
        );

        if ($this->mysqli->connect_errno) {
            throw new Exception("Error MySQL: " . $this->mysqli->connect_error);
        }

        $this->mysqli->set_charset("utf8mb4");

        $this->storageQr  = __DIR__ . '/../../storage/qr';
        $this->storagePdf = __DIR__ . '/../../storage/pdf';

        if (!is_dir($this->storageQr)) mkdir($this->storageQr, 0777, true);
        if (!is_dir($this->storagePdf)) mkdir($this->storagePdf, 0777, true);
    }

    // --------------------------------------------------------------------
    // Ajustar texto a 3 líneas máximo, nunca se desborda, con …
    // --------------------------------------------------------------------
    private function fitTextStrict($pdf, $text, $ticketTextWidth, $maxHeight) {
        $text = trim(iconv('UTF-8','ISO-8859-1//TRANSLIT',$text));
        if ($text === "") return ["lines" => ["—","—","—"], "font" => 11];

        $fontSize = 11;
        $lineHeight = 5;
        $maxLines = floor($maxHeight / $lineHeight);
        $maxLines = max(1, $maxLines);

        while ($fontSize >= 5) {
            $pdf->SetFont('Arial', '', $fontSize);
            $lines = $this->wordWrapLines($pdf, $text, $ticketTextWidth);
            if (count($lines) <= $maxLines) break;
            $fontSize--;
        }

        // Recortar líneas si sobrepasa maxLines
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lastLine = $lines[$maxLines - 1];
            while ($pdf->GetStringWidth($lastLine.'…') > $ticketTextWidth && strlen($lastLine) > 0) {
                $lastLine = mb_substr($lastLine, 0, -1);
            }
            $lines[$maxLines - 1] = $lastLine.'…';
        }

        return ["lines" => $lines, "font" => $fontSize];
    }

    // --------------------------------------------------------------------
    // Word wrap que devuelve líneas como array
    // --------------------------------------------------------------------
    private function wordWrapLines($pdf, $text, $maxWidth) {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';
        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine.' '.$word;
            if ($pdf->GetStringWidth($testLine) <= $maxWidth) {
                $currentLine = $testLine;
            } else {
                if ($currentLine === '') {
                    // palabra demasiado larga, partir en partes
                    $split = str_split($word, 5);
                    $lines[] = $split[0];
                    $currentLine = $split[1] ?? '';
                } else {
                    $lines[] = $currentLine;
                    $currentLine = $word;
                }
            }
        }
        if ($currentLine !== '') $lines[] = $currentLine;
        return $lines;
    }

    // --------------------------------------------------------------------
    // GENERAR BOLETOS DESDE CSV
    // --------------------------------------------------------------------
    public function createTicketsFromCSV($csvFile) {
        if (!file_exists($csvFile)) throw new Exception("CSV no encontrado: $csvFile");

        $rows = [];
        if (($handle = fopen($csvFile, "r")) !== false) {
            while (($data = fgetcsv($handle, 10000, ",")) !== false) {
                if (count(array_filter($data, fn($c) => trim($c) !== '')) === 0) continue;
                $rows[] = $data;
            }
            fclose($handle);
        }

        $pdf = new \FPDF('P', 'mm', 'LETTER');
        $pdf->SetMargins(0,0,0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $ticketWidth  = 50;
        $ticketHeight = 85;
        $colX = [5, 55, 105, 155];
        $rowY = [5, 90, 175];

        $i = 0;
        foreach ($rows as $r) {
            $user   = trim($r[0] ?? '');
            $name   = trim($r[1] ?? '');
            $center = trim($r[2] ?? '');

            if ($user !== '' || $name !== '') {
                $stmt = $this->mysqli->prepare("INSERT INTO tickets(sap, name, center) VALUES(?,?,?)");
                if ($stmt) {
                    $stmt->bind_param("sss", $user, $name, $center);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Generar QR
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

            $col = $i % 4;
            $row = intdiv($i % 12, 4);
            if ($i > 0 && $i % 12 == 0) $pdf->AddPage();

            $x = $colX[$col];
            $y = $rowY[$row];

            // Fondo
            $plantilla = __DIR__ . '/../../storage/qr/ticket.png';
            $pdf->Image($plantilla, $x, $y, $ticketWidth, $ticketHeight);

            // QR centrado
            $qrSize = 25;
            $qrX = $x + ($ticketWidth/2) - ($qrSize/2);
            $qrY = $y + 26;
            $pdf->Image($qrFile, $qrX, $qrY, $qrSize, $qrSize);

            // Nombre ajustado con 1 cm debajo del QR
            $spaceBelowQR = 10; // 10 mm = 1 cm
            $startY = $qrY + $qrSize + $spaceBelowQR;

            $maxTextHeight = $ticketHeight - ($startY - $y) - 5; // margen inferior
            $resultText = $this->fitTextStrict($pdf, $name, $ticketWidth - 4, $maxTextHeight);

            $pdf->SetFont('Arial', '', $resultText['font']);
            $pdf->SetTextColor(0,0,0);

            $lineHeight = 5;
            foreach ($resultText['lines'] as $index => $line) {
                $pdf->SetXY($x + 2, $startY + ($index * $lineHeight));
                $pdf->Cell($ticketWidth - 4, $lineHeight, $line, 0, 1, 'C');
            }

            if (file_exists($qrFile)) @unlink($qrFile);
            $i++;
        }

        // Nombre del PDF con hora, minuto y segundo
        $pdfFile = $this->storagePdf . "/boletos_" . date('His') . ".pdf";
        $pdf->Output('F', $pdfFile);
        if (!file_exists($pdfFile)) throw new Exception("No se pudo crear el PDF");
        return $pdfFile;
    }
}
