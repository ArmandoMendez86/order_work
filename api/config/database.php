<?php
class Database
{
    // Variables de conexión a la BD
    private $host = "localhost";
    public $conn;

    private $db_name = "order_work"; 
    private $username = "root"; 
    private $password = ""; 

    //private $db_name = "u916760597_order_work";
    //private $username = "u916760597_order_work";
    //private $password = "Order861215";

    public function getConnection()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            // CRÍTICO: Lanzamos una nueva excepción capturable con el error de PDO
            throw new Exception("Database connection failed: " . $exception->getMessage());
        }

        return $this->conn;
    }
}
