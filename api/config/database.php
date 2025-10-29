<?php
class Database
{
    // Variables de conexión a la BD
    //private $host = "localhost"; 
    //private $db_name = "order_work"; 
    //private $username = "root"; 
    //private $password = ""; 

    private $host = "localhost";
    private $db_name = "u916760597_order_work";
    private $username = "u916760597_order_work";
    private $password = "Order861215";
    public $conn;

    // Método para obtener la conexión
    // Método para obtener la conexión
    // Método para obtener la conexión
    public function getConnection()
    {
        $this->conn = null;

        try {
            // Tus credenciales (las mantengo aquí solo como referencia)
            // private $host = "localhost"; 
            // private $db_name = "u916760597_order_work"; 
            // private $username = "u916760597_order_work"; 
            // private $password = "Order861215"; 

            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            // Configura PDO para que lance excepciones en caso de error
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            // CRÍTICO: En lugar de solo loggear o hacer echo, lanzamos una excepción simple.
            // Esto permite al controlador capturar el mensaje de error exacto de PDO.
            throw new Exception("Database connection failed: " . $exception->getMessage());
        }

        return $this->conn;
    }
}
