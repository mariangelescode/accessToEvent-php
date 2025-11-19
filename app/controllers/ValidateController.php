<?php
require_once __DIR__ . '/../models/ValidateModel.php';

class ValidateController {
    private $model;

    public function __construct() {
        // Intentar localizar config.php en varias rutas posibles
        $configPath = __DIR__ . '/../../config.php'; // relativo desde app/controllers
        if (!file_exists($configPath)) {
            $configPath = __DIR__ . '/../../../config.php'; // prueba otra ruta
        }

        if (!file_exists($configPath)) {
            die("Error: No se encontró el archivo config.php");
        }

        $config = require $configPath;
        $this->model = new ValidateModel($config);
    }

    public function index() {
        include __DIR__ . '/../views/validate.php';
    }

    public function check() {
        header('Content-Type: application/json');

        $qr = $_POST['qr'] ?? null;
        if (!$qr) {
            echo json_encode(["status" => "error", "message" => "No se recibió QR"]);
            return;
        }

        // El QR contiene "user | name | center"
        [$user, $name, $center] = array_map('trim', explode('|', $qr));

        $ticket = $this->model->findTicket($user);

        if (!$ticket) {
            echo json_encode(["status" => "error", "message" => "Usuario no encontrado"]);
            return;
        }

        if ($this->model->alreadyRegistered($user)) {
            echo json_encode([
                "status" => "exists",
                "message" => "⚠️ Usuario ya registrado anteriormente",
                "data" => $ticket
            ]);
            return;
        }

        $this->model->registerUser($user, $ticket['name'], $ticket['center']);
        echo json_encode([
            "status" => "success",
            "message" => "✅ Usuario registrado correctamente",
            "data" => $ticket
        ]);
    }
}
