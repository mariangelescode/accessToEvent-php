<?php
require_once __DIR__ . '/../../vendor/autoload.php'; // cargar autoload

require_once __DIR__ . '/../models/ValidateModel.php';

// class ValidateController {
//     private $model;

//     public function __construct() {

//         // -----------------------------------------
//         // CARGAR VARIABLES .env
//         // -----------------------------------------
//         $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2)); 
//         $dotenv->load();

//         $configPath = $_ENV['CONFIG_PATH'];

//         if (!file_exists($configPath)) {
//             die("Error: No se encontró el archivo config.php en $configPath");
//         }

//         // -----------------------------------------
//         // CARGAR CONFIG.PHP DESDE RUTA DEFINIDA EN .env
//         // -----------------------------------------

//         if (!file_exists($configPath)) {
//             die("Error: No se encontró el archivo config.php en $configPath");
//         }

//         // $config = require $configPath;

//         // // Convertir objeto → array
//         // $config = (array) $config;

//         // // Crear modelo
//         // $this->model = new ValidateModel($config);
//         $config = require $configPath;
//         $this->model = new ValidateModel($config);

//     }

//     public function index() {
//         // include __DIR__ . '/../views/validate.php';
//         // Obtener número de registrados
//         $totalRegistrados = $this->model->countRegistered();

//         // Enviar a la vista
//         include __DIR__ . '/../views/validate.php';
//     }

//     public function check() {
//         header('Content-Type: application/json');

//         $qr = $_POST['qr'] ?? null;
//         if (!$qr) {
//             echo json_encode(["status" => "error", "message" => "No se recibió QR"]);
//             return;
//         }

//         // El QR contiene: user | name | center
//         [$user, $name, $center] = array_map('trim', explode('|', $qr));

//         $ticket = $this->model->findTicket($user);

//         if (!$ticket) {
//             echo json_encode(["status" => "error", "message" => "Usuario no encontrado"]);
//             return;
//         }

//         if ($this->model->alreadyRegistered($user)) {
//             echo json_encode([
//                 "status" => "exists",
//                 "message" => "⚠️ Usuario ya registrado anteriormente",
//                 "data" => $ticket
//             ]);
//             return;
//         }

//         // Registrar asistencia
//         $this->model->registerUser($user, $ticket['name'], $ticket['center']);

//         echo json_encode([
//             "status" => "success",
//             "message" => "✅ Usuario registrado correctamente",
//             "data" => $ticket
//         ]);
//     }
// }
class ValidateController {
    private $config;

    public function __construct() {

        require_once __DIR__ . '/../../vendor/autoload.php';

        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->load();

        $configPath = $_ENV['CONFIG_PATH'];

        if (!file_exists($configPath)) {
            die("Error: No se encontró el archivo config.php en $configPath");
        }

        $this->config = require $configPath;
    }

    private function model() {
        return new ValidateModel($this->config);
    }

    public function index() {
        $model = $this->model();
        $totalRegistrados = $model->countRegistered();
        include __DIR__ . '/../views/validate.php';
    }

    public function check() {
        session_start();
        header('Content-Type: application/json');

        // 🔒 Anti-spam QR (OBLIGATORIO)
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
            echo json_encode(["status" => "error", "message" => "No se recibió QR"]);
            return;
        }

        [$user, $name, $center] = array_map('trim', explode('|', $qr));

        $model = $this->model();

        $ticket = $model->findTicket($user);

        if (!$ticket) {
            echo json_encode(["status" => "error", "message" => "Usuario no encontrado"]);
            return;
        }

        if ($model->alreadyRegistered($user)) {
            echo json_encode([
                "status" => "exists",
                "message" => "⚠️ Usuario ya registrado anteriormente",
                "data" => $ticket
            ]);
            return;
        }

        $model->registerUser($user, $ticket['name'], $ticket['center']);

        echo json_encode([
            "status" => "success",
            "message" => "✅ Usuario registrado correctamente",
            "data" => $ticket
        ]);
    }
}

