<?php
$page = $_GET['p'] ?? '';

if (!$page) {
    header("Location: index.php?p=validate");
    exit;
}

switch ($page) {
    case 'validate':
        require_once __DIR__ . '/../app/controllers/ValidateController.php';
        $controller = new ValidateController();
        $controller->index();
        break;
    case 'validate_check':
        require_once __DIR__ . '/../app/controllers/ValidateController.php';
        $controller = new ValidateController();
        $controller->check();
        break;
    default:
        require_once __DIR__ . '/../app/controllers/UploadController.php';
        $controller = new UploadController();
        $controller->processFile();
        break;
}
