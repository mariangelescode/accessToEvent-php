<?php
class ValidateModel {
    private $mysqli;

    public function __construct($config) {
        $this->mysqli = new mysqli(
            $config->db_host,
            $config->db_user,
            $config->db_pass,
            $config->db_name
        );

        if ($this->mysqli->connect_errno) {
            throw new Exception("Error MySQL: " . $this->mysqli->connect_error);
        }

        $this->mysqli->set_charset("utf8mb4");
    }

    public function findTicket($user) {
        $stmt = $this->mysqli->prepare("SELECT sap, name, center FROM tickets WHERE sap = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function alreadyRegistered($user) {
        $stmt = $this->mysqli->prepare("SELECT id FROM registro WHERE sap = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function registerUser($user, $name, $center) {
        $stmt = $this->mysqli->prepare("INSERT INTO registro(sap, name, center) VALUES(?,?,?)");
        $stmt->bind_param("sss", $user, $name, $center);
        $stmt->execute();
        $stmt->close();
    }
}
