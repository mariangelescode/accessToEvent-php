<?php

class Database {
    private static $connection = null;

    public static function getConnection($config) {
        if (self::$connection === null) {
            self::$connection = new mysqli(
                $config->db_host,
                $config->db_user,
                $config->db_pass,
                $config->db_name
            );

            if (self::$connection->connect_errno) {
                throw new Exception(
                    "Error MySQL: " . self::$connection->connect_error
                );
            }

            self::$connection->set_charset("utf8mb4");
        }

        return self::$connection;
    }
}
