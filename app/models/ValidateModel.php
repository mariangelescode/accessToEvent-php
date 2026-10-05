<?php

class ValidateModel {
    private mysqli $mysqli;

    public function __construct($config) {
        // Soporta tanto array como objeto ($config['db_host'] o $config->db_host)
        $host = is_array($config) ? $config['db_host'] : $config->db_host;
        $user = is_array($config) ? $config['db_user'] : $config->db_user;
        $pass = is_array($config) ? $config['db_pass'] : $config->db_pass;
        $name = is_array($config) ? $config['db_name'] : $config->db_name;

        $this->mysqli = new mysqli($host, $user, $pass, $name);

        if ($this->mysqli->connect_errno) {
            throw new Exception("Error MySQL: " . $this->mysqli->connect_error);
        }

        $this->mysqli->set_charset("utf8mb4");
    }

    public function __destruct() {
        if ($this->mysqli && $this->mysqli->ping()) {
            $this->mysqli->close();
        }
    }

    /**
     * Busca la información del ticket
     */
    public function findTicket(string $user): ?array {
        $stmt = $this->mysqli->prepare("SELECT sap, name, center FROM tickets WHERE sap = ? LIMIT 1");
        var_dump($stmt);
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result ?: null;
    }

    /**
     * Intenta registrar al usuario de forma atómica.
     * Devuelve:
     * - 'success': si se registró correctamente.
     * - 'exists': si ya existía en la tabla registro (gracias al índice UNIQUE).
     * - 'error': si falló la inserción por otro motivo.
     */
    public function registerUser(string $user, string $name, string $center): string {
        try {
            // Usamos INSERT IGNORE o capturamos el error de clave duplicada (1062)
            $stmt = $this->mysqli->prepare("INSERT INTO registro (sap, name, center) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $user, $name, $center);
            
            if ($stmt->execute()) {
                $stmt->close();
                return 'success';
            }
            
            $stmt->close();
            return 'error';
        } catch (mysqli_sql_exception $e) {
            // Código 1062 = Duplicate entry (El usuario ya estaba registrado)
            if ($e->getCode() === 1062) {
                return 'exists';
            }
            return 'error';
        }
    }

    /**
     * Total de usuarios registrados
     */
    public function countRegistered(): int {
        $res = $this->mysqli->query("SELECT COUNT(*) AS total FROM registro");
        if (!$res) {
            return 0;
        }
        $row = $res->fetch_assoc();
        $res->free();
        
        return (int)($row['total'] ?? 0);
    }
}