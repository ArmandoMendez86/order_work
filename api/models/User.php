<?php
// api/models/User.php

class User {
    private $conn;
    private $table_name = "Users";

    // Propiedades del objeto
    public $user_id;
    public $full_name;
    public $email;
    public $password_hash;
    public $role;

    // Constructor con la conexión a la BD
    public function __construct($db) {
        $this->conn = $db;
    }

    // Método para buscar un usuario por Email
    public function findByEmail($email) {
        $query = "SELECT
                    user_id, full_name, email, password_hash, `role`
                  FROM
                    " . $this->table_name . "
                  WHERE
                    email = :email
                  LIMIT 1";

        // Preparar la consulta
        $stmt = $this->conn->prepare($query);

        // Limpiar datos (sanitizar)
        $email = htmlspecialchars(strip_tags($email));
        
        // Vincular el parámetro
        $stmt->bindParam(':email', $email);

        // Ejecutar la consulta
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            // Obtener el registro
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Asignar valores a las propiedades del objeto
            $this->user_id = $row['user_id'];
            $this->full_name = $row['full_name'];
            $this->email = $row['email'];
            $this->password_hash = $row['password_hash'];
            $this->role = $row['role'];

            return true; // Usuario encontrado
        }

        return false; // Usuario no encontrado
    }
}
?>