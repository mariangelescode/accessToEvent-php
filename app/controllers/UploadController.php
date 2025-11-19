<?php
require_once __DIR__ . '/../../vendor/autoload.php'; // cargar autoload
require_once __DIR__ . '/../models/TicketModel.php';

class UploadController {
    private $model;

    public function __construct() {
        // Carga del .env que está fuera de access
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 1)); 


        $dotenv->load();

        // Obtiene la ruta del config desde la variable de entorno
        $configPath = $_ENV['CONFIG_PATH'];

        if (!file_exists($configPath)) {
            die("Error: No se encontró el archivo config.php en $configPath");
        }

        $config = require $configPath;
        $this->model = new TicketModel($config);
    }

    public function processFile() {
        if (!isset($_FILES['csv_file'])) {
            die("No se ha subido ningún archivo");
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $pdfFile = $this->model->createTicketsFromCSV($file);

        echo "Tickets generados correctamente. <a href='/access/storage/pdf/" . basename($pdfFile) . "' target='_blank'>Descargar PDF</a>";
    }
}
