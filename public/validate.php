<?php
// validate.php
$config = require __DIR__ . '/../../config.php';

$mysqli = new mysqli($config->db_host, $config->db_user, $config->db_pass, $config->db_name);
if ($mysqli->connect_errno) {
    die("Error MySQL: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

// Recibir QR escaneado
$qrData = $_GET['qr'] ?? '';

if (!$qrData) {
    die("No se recibió QR");
}

// Suponiendo que el QR tiene formato: "usuario | nombre | centro"
$parts = array_map('trim', explode('|', $qrData));
if (count($parts) < 3) {
    die("QR inválido");
}
list($user, $name, $center) = $parts;

// Buscar en la base de datos
$stmt = $mysqli->prepare("SELECT user, name, center FROM tickets WHERE user=? AND name=? AND center=?");
$stmt->bind_param("sss", $user, $name, $center);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "✔ Ticket válido<br>";
    echo "Usuario: " . htmlspecialchars($row['user']) . "<br>";
    echo "Nombre: " . htmlspecialchars($row['name']) . "<br>";
    echo "Centro: " . htmlspecialchars($row['center']);
} else {
    echo "❌ Ticket no encontrado";
}

$stmt->close();
$mysqli->close();
