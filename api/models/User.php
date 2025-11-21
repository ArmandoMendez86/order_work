<?php
// api/models/User.php

class User
{
  private $conn;
  private $table_name = "users";

  // Constructor con la conexión a la BD
  public function __construct($db)
  {
    $this->conn = $db;
  }

  // --- [R] READ ALL ---
  public function readAll()
  {
    // NO exponemos el password_hash
    $query = "SELECT
                    user_id, full_name, email, `role`, created_at
                  FROM
                    " . $this->table_name . "
                  ORDER BY
                    user_id ASC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt;
  }

  // --- [C] CREATE ---
  public function create($data)
  {
    $query = "INSERT INTO " . $this->table_name . "
                  SET
                    full_name = :full_name,
                    email = :email,
                    password_hash = :password_hash,
                    `role` = :role";

    $stmt = $this->conn->prepare($query);

    // Limpiar y vincular (incluyendo hashing de la contraseña)
    $full_name = htmlspecialchars(strip_tags($data['full_name']));
    $email = htmlspecialchars(strip_tags($data['email']));
    $role = htmlspecialchars(strip_tags($data['role']));
    
    // Hash de la contraseña (CRÍTICO)
    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

    $stmt->bindParam(':full_name', $full_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password_hash', $password_hash);
    $stmt->bindParam(':role', $role);

    try {
        return $stmt->execute();
    } catch (PDOException $e) {
        // Error de clave duplicada (ej: email)
        if ($e->getCode() === '23000') return false; 
        throw $e;
    }
  }

  // --- [U] UPDATE ---
  public function update($id, $data)
  {
    $set_parts = [];
    
    // Si la contraseña existe, la hasheamos
    if (!empty($data['password'])) {
        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $set_parts[] = "password_hash = :password_hash";
    }

    $set_parts[] = "full_name = :full_name";
    $set_parts[] = "email = :email";
    $set_parts[] = "`role` = :role";
    
    $query = "UPDATE " . $this->table_name . "
                  SET " . implode(', ', $set_parts) . "
                  WHERE user_id = :user_id";

    $stmt = $this->conn->prepare($query);

    // Limpiar y vincular
    $full_name = htmlspecialchars(strip_tags($data['full_name']));
    $email = htmlspecialchars(strip_tags($data['email']));
    $role = htmlspecialchars(strip_tags($data['role']));
    $id = htmlspecialchars(strip_tags($id));

    $stmt->bindParam(':full_name', $full_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':role', $role);
    $stmt->bindParam(':user_id', $id);

    if (isset($data['password_hash'])) {
        $stmt->bindParam(':password_hash', $data['password_hash']);
    }

    try {
        return $stmt->execute();
    } catch (PDOException $e) {
        // Error de clave duplicada (ej: email)
        if ($e->getCode() === '23000') return false;
        throw $e;
    }
  }
  
  // --- [D] DELETE ---
  public function delete($id)
  {
    $query = "DELETE FROM " . $this->table_name . " WHERE user_id = :id";
    $stmt = $this->conn->prepare($query);
    $id = htmlspecialchars(strip_tags($id));
    $stmt->bindParam(':id', $id);

    try {
        return $stmt->execute();
    } catch (PDOException $e) {
        // Error 23000 es típicamente un error de clave foránea (uso: WO asignada)
        if ($e->getCode() === '23000') {
            return false; 
        }
        throw $e;
    }
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
}