<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../models/ValidateModel.php';

class ValidateController {

    private $config;
    private $model;

    public function __construct() {

        // Cargar variables .env
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->load();

        $configPath = $_ENV['CONFIG_PATH'];

        if (!file_exists($configPath)) {
            die("Error: No se encontró el archivo config.php en $configPath");
        }

        $this->config = require $configPath;

        // 🔑 INSTANCIA ÚNICA DEL MODELO
        $this->model = new ValidateModel($this->config);
    }

    public function index() {
        $totalRegistrados = $this->model->countRegistered();
        include __DIR__ . '/../views/validate.php';
    }

    public function check() {
        session_start();
        header('Content-Type: application/json');

        // 🔒 Anti-spam QR
        if (isset($_SESSION['last_scan']) && time() - $_SESSION['last_scan'] < 2) {
            http_response_code(429);
            echo json_encode([
                "status" => "error",
                "message" => "⏳ Espera un momento antes de volver a escanear"
            ]);
            return;
        }
        $_SESSION['last_scan'] = time();

        $qr = $_POST['qr'] ?? null;
        if (!$qr) {
            echo json_encode([
                "status" => "error",
                "message" => "No se recibió QR"
            ]);
            return;
        }

        [$user, $name, $center] = array_map(
            'trim',
            explode('|', $qr)
        );

        $ticket = $this->model->findTicket($user);

        if (!$ticket) {
            echo json_encode([
                "status" => "error",
                "message" => "Usuario no encontrado"
            ]);
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

        $this->model->registerUser(
            $user,
            $ticket['name'],
            $ticket['center']
        );

        echo json_encode([
            "status" => "success",
            "message" => "✅ Usuario registrado correctamente",
            "data" => $ticket
        ]);
    }
}
