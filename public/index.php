<?php
// INSERTAR TEMPORALMENTE EN LA PRIMERA LÍNEA DE public/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$page = $_GET['p'] ?? ''; // igual que tu estructura actual con ?p=upload o ?p=validate

switch ($page) {
    case 'validate':
        require_once __DIR__ . '/../app/controllers/ValidateController.php';
        $controller = new ValidateController();
        $controller->index(); // método principal del lector QR
        break;
    case 'validate_check':
        require_once __DIR__ . '/../app/controllers/ValidateController.php';
        $controller = new ValidateController();
        $controller->check(); // nuevo método para procesar el QR
        break;

    default:
        require_once __DIR__ . '/../app/controllers/UploadController.php';
        $controller = new UploadController();
        $controller->processFile();
        break;
}
