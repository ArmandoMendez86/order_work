<?php
// api/controllers/CustomerController.php

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../models/Customer.php';

class CustomerController
{
    private $db;
    private $customerModel;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->customerModel = new Customer($this->db);
    }

    // Función de ayuda para verificar la sesión de administrador
    private function checkAdminSession()
    {
        // (Asumimos que la sesión ya está iniciada por index.php)
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403); // Prohibido
            echo json_encode(["success" => false, "message" => "Unauthorized Access. Admin role required."]);
            exit();
        }
    }

    // [GET] /api/customers
    public function readAll()
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");
        
        $stmt = $this->customerModel->readAll();
        $num = $stmt->rowCount();

        if ($num > 0) {
            $customer_arr = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $customer_arr[] = $row;
            }
            http_response_code(200);
            echo json_encode(["success" => true, "data" => $customer_arr]);
        } else {
            http_response_code(200);
            echo json_encode(["success" => true, "data" => []]);
        }
    }

    // [GET] /api/customers/details/{id}
    public function readOne($id)
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");

        if ($this->customerModel->readOne($id)) {
            $customer_data = [
                "customer_id" => $this->customerModel->customer_id,
                "customer_name" => $this->customerModel->customer_name,
                "customer_city" => $this->customerModel->customer_city,
                "customer_phone" => $this->customerModel->customer_phone,
                "customer_email" => $this->customerModel->customer_email,
                "customer_type" => $this->customerModel->customer_type
            ];
            http_response_code(200);
            echo json_encode(["success" => true, "data" => $customer_data]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Client not found."]);
        }
    }

    // [POST] /api/customers/create
    public function create()
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['customer_name']) || empty($data['customer_city'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Name and City are required."]);
            return;
        }

        if ($this->customerModel->create($data)) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Client created successfully."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Unable to create client."]);
        }
    }

    // [POST] /api/customers/update/{id}
    public function update($id)
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['customer_name']) || empty($data['customer_city'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Name and City are required."]);
            return;
        }

        if ($this->customerModel->update($id, $data)) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Client updated successfully."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Unable to update client."]);
        }
    }

    // [POST] /api/customers/delete/{id}
    public function delete($id)
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");
        
        if ($this->customerModel->delete($id)) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Client deleted successfully."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Unable to delete client. It might be in use in a work order."]);
        }
    }
}