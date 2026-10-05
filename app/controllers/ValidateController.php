<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../models/ValidateModel.php';

use Dotenv\Dotenv;

class ValidateController {
    private array $config;
    private ValidateModel $model;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->safeLoad();

        $configPath = $_ENV['CONFIG_PATH'] ?? null;

        if (!$configPath || !file_exists($configPath)) {
            http_response_code(500);
            die(json_encode([
                "status" => "error", 
                "message" => "Error de configuración: No se encontró config.php"
            ]));
        }

        $this->config = require $configPath;
        
        // 💡 Instanciamos el modelo UNA sola vez para reutilizar la conexión a la BD
        $this->model = new ValidateModel($this->config);
    }

    public function index(): void {
        $totalRegistrados = $this->model->countRegistered();
        include __DIR__ . '/../views/validate.php';
    }

    public function check(): void {
        // Manejo y liberación rápida de sesión para evitar bloqueos (Session Locking)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lastScan = $_SESSION['last_scan'] ?? 0;
        $now = time();

        // 🔒 Anti-spam (Sintaxis corregida)
        if (($now - $lastScan) < 2) {
            session_write_close(); // Liberamos el archivo de sesión inmediatamente
            
            header('Content-Type: application/json');
            http_response_code(429);
            echo json_encode([
                "status" => "error",
                "message" => "⏳ Espera un momento antes de volver a escanear"
            ]);
            return;
        }

        $_SESSION['last_scan'] = $now;
        session_write_close(); 

        header('Content-Type: application/json');

        $qr = trim($_POST['qr'] ?? '');
        if (empty($qr)) {
            echo json_encode(["status" => "error", "message" => "No se recibió QR"]);
            return;
        }

        // Parseo seguro del QR
        $parts = array_map('trim', explode('|', $qr));
        if (count($parts) < 3) {
            echo json_encode([
                "status" => "error", 
                "message" => "El formato del código QR es inválido"
            ]);
            return;
        }

        [$user, $name, $center] = $parts;

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