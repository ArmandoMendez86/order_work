<?php
// api/controllers/CategoryController.php

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../models/Category.php';

class CategoryController
{
    private $db;
    private $categoryModel;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->categoryModel = new Category($this->db);
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

    // [GET] /api/categories
    public function readAll()
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");
        
        $data = $this->categoryModel->readAll();
        http_response_code(200);
        echo json_encode(["success" => true, "data" => $data]);
    }

    // [POST] /api/categories/create
    public function create()
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");
        $data = json_decode(file_get_contents("php://input"), true);

        $name = $data['name'] ?? null;
        $parentId = $data['parent_id'] ?? null; // ID de la categoría padre

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Name is required."]);
            return;
        }

        $success = false;
        if (empty($parentId)) {
            // Es una Categoría principal
            $success = $this->categoryModel->createCategory($name);
            $message = "Category created successfully.";
        } else {
            // Es una Subcategoría
            $success = $this->categoryModel->createSubcategory($parentId, $name);
            $message = "Subcategory created successfully.";
        }

        if ($success) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => $message]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Unable to create item. Name might be a duplicate."]);
        }
    }

    // [POST] /api/categories/update/{type}/{id}
    public function update($type, $id)
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");
        $data = json_decode(file_get_contents("php://input"), true);

        $name = $data['name'] ?? null;

        if (empty($name) || empty($id) || empty($type)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Type, ID, and Name are required."]);
            return;
        }

        $success = false;
        if ($type == 'category') {
            $success = $this->categoryModel->updateCategory($id, $name);
            $message = "Category updated.";
        } elseif ($type == 'subcategory') {
            $success = $this->categoryModel->updateSubcategory($id, $name);
            $message = "Subcategory updated.";
        } else {
             http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid type specified."]);
            return;
        }
        
        if ($success) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => $message]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Unable to update item. Name might be a duplicate."]);
        }
    }

    // [DELETE] /api/categories/delete/{type}/{id}
    public function delete($type, $id)
    {
        $this->checkAdminSession();
        header("Content-Type: application/json; charset=UTF-8");

        if (empty($id) || empty($type)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Type and ID are required."]);
            return;
        }

        $success = false;
         if ($type == 'category') {
            $success = $this->categoryModel->deleteCategory($id);
            $message = "Category (and its subcategories) deleted.";
            $fail_message = "Unable to delete category. It might be in use in a work order.";
        } elseif ($type == 'subcategory') {
            $success = $this->categoryModel->deleteSubcategory($id);
            $message = "Subcategory deleted.";
            $fail_message = "Unable to delete subcategory. It might be in use in a work order.";
        } else {
             http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid type specified."]);
            return;
        }

        if ($success) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => $message]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => $fail_message]);
        }
    }
}