<?php
require_once __DIR__ . '/../models/TicketModel.php';

class UploadController {
    private $model;

    public function __construct() {
        // Intentar localizar config.php en varias rutas posibles
        $configPath = __DIR__ . '/../../../../config.php'; // relativo desde app/controllers
        if (!file_exists($configPath)) {
            $configPath = __DIR__ . '/../../../config.php'; // prueba otra ruta
        }

        if (!file_exists($configPath)) {
            die("Error: No se encontró el archivo config.php");
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
