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
    public function getConnection()
    {
        $this->conn = null;

        try {
            // Asegúrate de que todas las credenciales sean correctas (doble verificación)
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            // Configura PDO para que lance excepciones en caso de error
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            // CRÍTICO: No usamos 'echo'. Solo registramos el error si es necesario.
            error_log("DATABASE CONNECTION ERROR: " . $exception->getMessage());
            // En este punto, $this->conn sigue siendo null, lo cual es manejado en AuthController.
        }

        return $this->conn;
    }
}
