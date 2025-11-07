<?php
require_once __DIR__ . '/../models/TicketModel.php';

class UploadController {
    private $model;

    public function __construct() {
        // $config = require __DIR__ . '/../../../../config.php';
        $configPath = realpath(__DIR__ . '/../../../../config.php');

        if (!$configPath || !file_exists($configPath)) {
            die('Error: No se encontró el archivo config.php');
        }

        $config = require $configPath;
        $this->model = new TicketModel($config);
    }

    public function processFile() {
        if (!isset($_FILES['csv_file'])) { // o 'excelFile' según tu formulario
            die("No se ha subido ningún archivo");
        }

        $file = $_FILES['csv_file']['tmp_name']; // coincide con el name del input
        $pdfFile = $this->model->createTicketsFromCSV($file);

        echo "Tickets generados correctamente. <a href='/access/storage/pdf/" . basename($pdfFile) . "' target='_blank'>Descargar PDF</a>";
    }
}
