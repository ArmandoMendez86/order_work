<?php
// api/models/Customer.php

class Customer
{
    private $conn;
    private $table_name = "customers"; // <-- Respeta el nombre de tu archivo

    // Propiedades
    public $customer_id;
    public $customer_name;
    public $customer_city;
    public $customer_phone;
    public $customer_email;
    public $customer_type;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // [C] CREATE (Versión mejorada)
    // Crea un cliente o encuentra uno duplicado, y devuelve el ID.
    public function create($data)
    {
        // Intenta insertar
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                    customer_name = :customer_name,
                    customer_city = :customer_city,
                    customer_phone = :customer_phone,
                    customer_email = :customer_email,
                    customer_type = :customer_type
                  ON DUPLICATE KEY UPDATE
                    customer_id = LAST_INSERT_ID(customer_id)
                  ";
        
        $stmt = $this->conn->prepare($query);

        // Limpiar datos (usando null si están vacíos)
        $name = htmlspecialchars(strip_tags($data['customer_name']));
        $city = htmlspecialchars(strip_tags($data['customer_city']));
        $phone = !empty($data['customer_phone']) ? htmlspecialchars(strip_tags($data['customer_phone'])) : null;
        $email = !empty($data['customer_email']) ? htmlspecialchars(strip_tags($data['customer_email'])) : null;
        $type = !empty($data['customer_type']) ? htmlspecialchars(strip_tags($data['customer_type'])) : null;

        // Vincular
        $stmt->bindParam(":customer_name", $name);
        $stmt->bindParam(":customer_city", $city);
        $stmt->bindParam(":customer_phone", $phone);
        $stmt->bindParam(":customer_email", $email);
        $stmt->bindParam(":customer_type", $type);

        if ($stmt->execute()) {
            $lastId = $this->conn->lastInsertId();
            if ($lastId > 0) {
                return $lastId; // Devolvió el ID (nuevo o existente)
            } else {
                // Si 'ON DUPLICATE KEY' no devolvió un ID, búscalo (fallback)
                $findQuery = "SELECT customer_id FROM " . $this->table_name . " WHERE customer_name = :customer_name AND customer_city = :customer_city";
                $findStmt = $this->conn->prepare($findQuery);
                $findStmt->bindParam(":customer_name", $name);
                $findStmt->bindParam(":customer_city", $city);
                $findStmt->execute();
                $result = $findStmt->fetch(PDO::FETCH_ASSOC);
                return $result['customer_id'] ?? 0;
            }
        }
        return 0; // Fallo
    }

    // [R] READ ALL
    public function readAll()
    {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY customer_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // [R] READ ONE
    public function readOne($id)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE customer_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->customer_id = $row['customer_id'];
            $this->customer_name = $row['customer_name'];
            $this->customer_city = $row['customer_city'];
            $this->customer_phone = $row['customer_phone'];
            $this->customer_email = $row['customer_email'];
            $this->customer_type = $row['customer_type'];
            return true;
        }
        return false;
    }

    // [U] UPDATE
    public function update($id, $data)
    {
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                    customer_name = :customer_name, 
                    customer_city = :customer_city, 
                    customer_phone = :customer_phone, 
                    customer_email = :customer_email, 
                    customer_type = :customer_type 
                  WHERE 
                    customer_id = :id";

        $stmt = $this->conn->prepare($query);

        // Limpiar datos
        $data['customer_name'] = htmlspecialchars(strip_tags($data['customer_name']));
        $data['customer_city'] = htmlspecialchars(strip_tags($data['customer_city']));
        $data['customer_phone'] = htmlspecialchars(strip_tags($data['customer_phone']));
        $data['customer_email'] = htmlspecialchars(strip_tags($data['customer_email']));
        $data['customer_type'] = htmlspecialchars(strip_tags($data['customer_type']));

        // Vincular datos
        $stmt->bindParam(":customer_name", $data['customer_name']);
        $stmt->bindParam(":customer_city", $data['customer_city']);
        $stmt->bindParam(":customer_phone", $data['customer_phone']);
        $stmt->bindParam(":customer_email", $data['customer_email']);
        $stmt->bindParam(":customer_type", $data['customer_type']);
        $stmt->bindParam(":id", $id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // [D] DELETE
    public function delete($id)
    {
        // Primero, verificamos si el cliente está en uso
        $checkQuery = "SELECT COUNT(*) as count FROM workorders WHERE customer_id = :id";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($row['count'] > 0) {
            // Cliente en uso, no se puede borrar
             return false;
        }

        // Si no está en uso, se borra
        $query = "DELETE FROM " . $this->table_name . " WHERE customer_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}