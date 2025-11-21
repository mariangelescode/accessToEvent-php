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

        // Construir línea 1
        foreach ($words as $i => $w) {
            $try = trim($line1 . " " . $w);

            if ($pdf->GetStringWidth($try) <= $maxWidth) {
                $line1 = $try;
            } else {
                $remainingWords = array_slice($words, $i);
                break;
            }
        }

        // Construir línea 2 con lo que sobra
        if (!empty($remainingWords)) {
            foreach ($remainingWords as $w) {
                $try = trim($line2 . " " . $w);

                if ($pdf->GetStringWidth($try) <= $maxWidth) {
                    $line2 = $try;
                } else {
                    break;
                }
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
            // NOMBRE (2 líneas máx, centrado, sin desbordes)
            // ------------------------------------------------------------
            // ------------------------------------------------------------
// NOMBRE (máximo 3 líneas reales sin desbordar NUNCA)
// ------------------------------------------------------------
$maxWidth = 44;          // ancho máximo permitido
$lineHeight = 5;         // alto por línea
$fontSize = 13;          // tamaño inicial
$minFont  = 6;           // tamaño mínimo para no romper diseño

// Posición debajo del QR (ajustada hacia abajo)
$startX = $x + 3;
$startY = $y + 25 + $qrSize + 35; 

do {
    // Setear fuente actual
    $pdf->SetFont('Arial', '', $fontSize);

    // Hacemos una simulación para saber cuántas líneas genera MultiCell
    $pdf->SetXY($startX, $startY);

    // Bordes de margen
    $pdf->SetLeftMargin($startX);
    $pdf->SetRightMargin(0);

    // Iniciar buffer para capturar salida de MultiCell
    ob_start();

    // MultiCell de prueba
    $pdf->MultiCell($maxWidth, $lineHeight, $name, 0, 'C');

    // Capturar resultado
    $simulated = ob_get_clean();

    // Contar cuántas líneas realmente se generaron
    $lineCount = substr_count($simulated, "\n") + 1;

    if ($lineCount <= 3) {
        break; // El nombre cabe en máximo 3 líneas
    }

    // Si no cabe → reducir fuente
    $fontSize -= 0.5;

} while ($fontSize >= $minFont);

// --------------------------------------
// Ya con un tamaño válido, dibujar texto real
// --------------------------------------
$pdf->SetXY($startX, $startY);
$pdf->SetFont('Arial', '', $fontSize);
$pdf->MultiCell($maxWidth, $lineHeight, $name, 0, 'C');

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
