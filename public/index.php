<?php
$page = $_GET['p'] ?? ''; // igual que tu estructura actual con ?p=upload o ?p=validate

switch ($page) {
    case 'validate':
        require_once __DIR__ . '/../app/controllers/ValidateController.php';
        $controller = new ValidateController();
        $controller->index(); // método principal del lector QR
        break;

    default:
        require_once __DIR__ . '/../app/controllers/UploadController.php';
        $controller = new UploadController();
        $controller->processFile();
        break;
}
