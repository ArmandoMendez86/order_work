<?php
// api/controllers/DataController.php

include_once __DIR__ . '/../config/database.php';

class DataController {

    public function getFormInitData() {
        header("Content-Type: application/json; charset=UTF-8");
        $database = new Database();
        $db = $database->getConnection();
        
        $response = [
            "success" => false,
            "categories" => [],
            "technicians" => []
        ];

        try {
            // 1. Get Categories and Subcategories
            $category_query = "SELECT c.category_name, s.subcategory_name 
                               FROM categories c 
                               LEFT JOIN Subcategories s ON c.category_id = s.category_id
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

            // 2. Get Technicians (UPDATED QUERY)
            // We now select user_id, full_name, AND email
            $tech_query = "SELECT user_id, full_name, email 
                           FROM users 
                           WHERE role = 'technician' 
                           ORDER BY full_name";
                           
            $tech_stmt = $db->prepare($tech_query);
            $tech_stmt->execute();
            
            // This now sends an array of objects: [{user_id, full_name, email}, ...]
            $response["technicians"] = $tech_stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Send successful response
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
?>