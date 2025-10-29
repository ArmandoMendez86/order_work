<?php
// api/models/User.php

class User
{
  private $conn;
  private $table_name = "Users";

  // Constructor con la conexión a la BD
  public function __construct($db)
  {
    $this->conn = $db;
  }

  // --- MÉTODO 2: Devuelve el registro (array) o false ---
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

    // Limpiar datos (sanitizar) Y AÑADIR TRIM (para MariaDB/Hostinger)
    $clean_email = htmlspecialchars(strip_tags(trim($email)));
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

      // CRÍTICO: Limpiar el hash de la BD de cualquier espacio/carácter invisible
      $row['password_hash'] = trim($row['password_hash']);
      
      // Devolvemos el registro (array)
      return $row;
    }

    return false;
  }
} // <-- CORREGIDO: Solo una llave de cierre para la clase
?>