<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../models/ValidateModel.php';

use Dotenv\Dotenv;

class ValidateController {
    private $config; // 👈 Eliminamos la declaración estricta 'array' para aceptar objetos o arrays
    private ValidateModel $model;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->safeLoad();

        $configPath = $_ENV['CONFIG_PATH'] ?? null;

        if (!$configPath || !file_exists($configPath)) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                "status" => "error", 
                "message" => "Error de configuración: No se encontró config.php en " . ($configPath ?? 'Ruta no definida')
            ]);
            exit;
        }

        // Cargamos la configuración (objeto o array)
        $loadedConfig = require $configPath;
        
        // Convertimos a array si viene como objeto/stdClass
        $this->config = is_object($loadedConfig) ? (array) $loadedConfig : $loadedConfig;

        try {
            // Instanciamos el modelo una sola vez
            $this->model = new ValidateModel($this->config);
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                "status" => "error", 
                "message" => "Error de base de datos: " . $e->getMessage()
            ]);
            exit;
        }
    }

    public function index(): void {
        $totalRegistrados = $this->model->countRegistered();
        include __DIR__ . '/../views/validate.php';
    }

    public function check(): void {
        // Manejo y liberación rápida de sesión
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lastScan = $_SESSION['last_scan'] ?? 0;
        $now = time();

        // 🔒 Anti-spam
        if (($now - $lastScan) < 2) {
            session_write_close();
            
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

        $parts = array_map('trim', explode('|', $qr));
        if (count($parts) < 3) {
            echo json_encode([
                "status" => "error", 
                "message" => "El formato del código QR es inválido"
            ]);
            return;
        }

        [$user, $name, $center] = $parts;

        // 1. Validar ticket existente
        $ticket = $this->model->findTicket($user);
        if (!$ticket) {
            echo json_encode(["status" => "error", "message" => "Usuario no encontrado"]);
            return;
        }

        // 2. Intentar registrar (Aprovecha el índice UNIQUE de MySQL)
        $registerStatus = $this->model->registerUser($user, $ticket['name'], $ticket['center']);

        if ($registerStatus === 'exists') {
            echo json_encode([
                "status" => "exists",
                "message" => "⚠️ Usuario ya registrado anteriormente",
                "data" => $ticket
            ]);
            return;
        }

        if ($registerStatus === 'error') {
            echo json_encode([
                "status" => "error",
                "message" => "Ocurrió un error al guardar el registro en la base de datos"
            ]);
            return;
        }

        echo json_encode([
            "status" => "success",
            "message" => "✅ Usuario registrado correctamente",
            "data" => $ticket
        ]);
    }
}