<?php
// api/controllers/DataController.php

include_once __DIR__ . '/../config/database.php';
// --- INCLUIR LOS MODELOS NECESARIOS ---
include_once __DIR__ . '/../models/Customer.php'; 

class DataController {

    public function getFormInitData() {
        header("Content-Type: application/json; charset=UTF-8");
        $database = new Database();
        $db = $database->getConnection();
        
        $response = [
            "success" => false,
            "categories" => [],
            "technicians" => [],
            "customers" => [] 
        ];

        // --- VERIFICACIÓN DE SESIÓN (MODIFICADA) ---
        if (session_status() == PHP_SESSION_NONE) { session_start(); }

        // <<< CAMBIO: Permitir 'admin' O 'technician' >>>
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'technician')) {
            http_response_code(403);
            // Mensaje de error más genérico
            $response["message"] = "Unauthorized. Admin or Technician role required."; 
            echo json_encode($response);
            exit();
        }
        // --- FIN VERIFICACIÓN DE SESIÓN ---

        try {
            // 1. Get Categories and Subcategories
            $category_query = "SELECT c.category_name, s.subcategory_name 
                               FROM categories c 
                               LEFT JOIN subcategories s ON c.category_id = s.category_id
                               ORDER BY c.category_name, s.subcategory_name";
            $cat_stmt = $db->prepare($category_query);
            $cat_stmt->execute();

            $categoriesData = [];
            while ($row = $cat_stmt->fetch(PDO::FETCH_ASSOC)) {
                $cat_name = $row['category_name'];
                if (!isset($categoriesData[$cat_name])) {
                    $categoriesData[$cat_name] = [];
                }
                if ($row['subcategory_name']) {
                    $categoriesData[$cat_name][] = $row['subcategory_name'];
                }
            }
            $response["categories"] = $categoriesData;

            // 2. Get Technicians
            $tech_query = "SELECT user_id, full_name, email 
                           FROM users 
                           WHERE role = 'technician' 
                           ORDER BY full_name";
                           
            $tech_stmt = $db->prepare($tech_query);
            $tech_stmt->execute();
            
            $response["technicians"] = $tech_stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. OBTENER CLIENTES
            $customerModel = new Customer($db);
            $customer_stmt = $customerModel->readAll();
            $response["customers"] = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);
            // --- FIN BLOQUE ---


            // 4. Send successful response
            $response["success"] = true;
            http_response_code(200);
            echo json_encode($response);

        } catch (Exception $e) {
            http_response_code(500);
            $response["message"] = $e->getMessage();
            echo json_encode($response);
        }
    }
}