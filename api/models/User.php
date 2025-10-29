<?php
// api/models/User.php

class User
{
  private $conn;
  private $table_name = "Users";

  // Propiedades del objeto
  public $user_id;
  public $full_name;
  public $email;
  public $password_hash;
  public $role;

  // Constructor con la conexión a la BD
  public function __construct($db)
  {
    $this->conn = $db;
  }

  // Método para buscar un usuario por Email
  public function findByEmail($email)
  {
    $query = "SELECT
                    user_id, full_name, email, password_hash, `role`
                  FROM
                    " . $this->table_name . "
                  WHERE
                    email = :email
                  LIMIT 1";

    try {
      $stmt = $this->conn->prepare($query);
    } catch (PDOException $e) {
      error_log("SQL Prepare Error (findByEmail): " . $e->getMessage());
      return false;
    }

    // Limpiar datos (sanitizar)
    $clean_email = htmlspecialchars(strip_tags($email));
    $stmt->bindParam(':email', $clean_email);

    try {
      $stmt->execute();
    } catch (PDOException $e) {
      error_log("SQL Execute Error (findByEmail): " . $e->getMessage());
      return false;
    }

    if ($stmt->rowCount() > 0) {
      // Obtener el registro
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      // Asignar valores a las propiedades del objeto
      $this->user_id = $row['user_id'];
      $this->full_name = $row['full_name'];
      $this->email = $row['email'];

      // CRÍTICO: Limpiar el hash de la BD (Solución para MariaDB vs MySQL 8)
      $this->password_hash = trim($row['password_hash']);

      $this->role = $row['role'];

      return true; // Devolvemos true si se encontró y se poblaron los datos
    }

    return false;
  }
} // <-- Solo una llave aquí
