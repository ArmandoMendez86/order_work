<?php
// api/controllers/UserController.php

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../models/User.php';

class UserController
{
    private $db;
    private $userModel;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->userModel = new User($this->db);
    }

    // Función de ayuda para verificar la sesión de administrador
    private function checkAdminSession()
    {
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403); // Prohibido
            echo json_encode(["success" => false, "message" => "Unauthorized Access. Admin role required."]);
            exit();
        }
    }

    // [GET] /api/users
    public function readAll()
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");
        
        $stmt = $this->userModel->readAll();
        $num = $stmt->rowCount();

        $user_arr = [];
        if ($num > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Capitalizar el rol para mejor visualización en el frontend
                $row['role_display'] = ucfirst($row['role']);
                $user_arr[] = $row;
            }
        }
        
        http_response_code(200);
        echo json_encode(["success" => true, "data" => $user_arr]);
    }

    // [POST] /api/users/create
    public function create()
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['full_name']) || empty($data['email']) || empty($data['password']) || empty($data['role'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Full Name, Email, Password, and Role are required."]);
            return;
        }
        
        // El modelo ya maneja la verificación de duplicados mediante la excepción 23000
        if ($this->userModel->create($data)) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "User created successfully."]);
        } else {
            http_response_code(409); // Conflict
            echo json_encode(["success" => false, "message" => "Unable to create user. Email already exists."]);
        }
    }

    // [POST] /api/users/update/{id}
    public function update($id)
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['full_name']) || empty($data['email']) || empty($data['role'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Full Name, Email, and Role are required."]);
            return;
        }

        if ($this->userModel->update($id, $data)) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "User updated successfully."]);
        } else {
            http_response_code(409); // Conflict
            echo json_encode(["success" => false, "message" => "Unable to update user. Email might be a duplicate."]);
        }
    }

    // [DELETE] /api/users/delete/{id}
    public function delete($id)
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");
        
        if ($this->userModel->delete($id)) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "User deleted successfully."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Unable to delete user. The user might have assigned work orders."]);
        }
    }
}