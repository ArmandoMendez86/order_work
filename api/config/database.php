<?php
// api/config/database.php

class Database {
    // Variables de conexión a la BD
    private $host = "localhost"; // O tu host de BD
    private $db_name = "order_work"; // El nombre de tu BD
    private $username = "root"; // Tu usuario de BD
    private $password = ""; // Tu contraseña de BD
    public $conn;

    // Método para obtener la conexión
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            // Configura PDO para que lance excepciones en caso de error
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
?>