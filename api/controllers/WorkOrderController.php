<?php
// api/controllers/WorkOrderController.php

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../models/WorkOrder.php';
include_once __DIR__ . '/../models/WorkOrderPhoto.php'; // Incluir modelo de fotos
include_once __DIR__ . '/../models/Customer.php'; // <<< CAMBIO: Incluir modelo de Cliente

define('UPLOAD_TEMP_DIR', __DIR__ . '/../../uploads/temp/');
define('UPLOAD_FINAL_DIR', __DIR__ . '/../../uploads/');

class WorkOrderController
{

    private $db;
    private $workOrder;
    private $customerModel; // <<< CAMBIO

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->workOrder = new WorkOrder($this->db);
        $this->customerModel = new Customer($this->db); // <<< CAMBIO
    }

    private function checkSession()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "No autorizado. Inicie sesión."]);
            exit();
        }
    }

    // Función para sincronizar técnicos involucrados (tags)
    private function syncInvolvedTechnicians($work_order_id, $technician_ids)
    {
        $delete_query = "DELETE FROM workordertechnicians WHERE work_order_id = :work_order_id";
        $stmt = $this->db->prepare($delete_query);
        $stmt->bindParam(':work_order_id', $work_order_id);
        $stmt->execute();

        if (is_array($technician_ids) && !empty($technician_ids)) {
            $insert_query = "INSERT INTO workordertechnicians (work_order_id, user_id) VALUES (:work_order_id, :user_id)";
            $stmt = $this->db->prepare($insert_query);

            foreach ($technician_ids as $user_id) {
                $stmt->bindParam(':work_order_id', $work_order_id);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
            }
        }
    }

    // (La función syncPhotos y syncPhotoDeletion no necesitan cambios)
    private function syncPhotos($work_order_id, $file_data, $photo_type)
    {
        $photoModel = new WorkOrderPhoto($this->db);
        $all_success = true;

        $FINAL_DEST_DIR = UPLOAD_FINAL_DIR . $photo_type . '/';
        if (!is_dir($FINAL_DEST_DIR)) {
            if (!mkdir($FINAL_DEST_DIR, 0777, true)) {
                error_log("FATAL: Failed to create upload directory: " . $FINAL_DEST_DIR);
                $all_success = false;
            }
        }

        if (is_array($file_data)) {
            foreach ($file_data as $file_info) {
                if (strpos($file_info, 'uploads/') === 0) {
                    continue; 
                }
                $temp_file_name = $file_info;
                $source_path = UPLOAD_TEMP_DIR . basename($temp_file_name);
                $final_file_name = $work_order_id . '_' . basename($temp_file_name);
                $db_path_to_save = 'uploads/' . $photo_type . '/' . $final_file_name;

                if (file_exists($source_path)) {
                    if (rename($source_path, $FINAL_DEST_DIR . $final_file_name)) {
                        if (!$photoModel->savePhotoPath($work_order_id, $photo_type, $db_path_to_save)) {
                            error_log("SQL FAIL on new photo: " . $this->db->errorInfo()[2]);
                            $all_success = false;
                        }
                    } else {
                        error_log("RENAME FAILURE for new file {$photo_type}. Source: {$source_path}");
                        $all_success = false;
                    }
                }
            }
        }
        return $all_success;
    }
    
    // [GET] /api/workorders/all - (Sin cambios, el modelo fue actualizado)
    public function listAll()
    {
        $this->checkSession();
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Acceso denegado."]);
            return;
        }
        $stmt = $this->workOrder->getAll();
        $num = $stmt->rowCount();
        if ($num > 0) {
            $orders_arr = ["data" => []];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                array_push($orders_arr["data"], $row);
            }
            http_response_code(200);
            echo json_encode($orders_arr);
        } else {
            echo json_encode(["data" => []]);
        }
    }

    // [GET] /api/workorders/assigned - (Sin cambios, el modelo fue actualizado)
    public function listAssigned()
    {
        $this->checkSession();
        $user_id = $_SESSION['user_id'];
        $stmt = $this->workOrder->getAssignedTo($user_id);
        $num = $stmt->rowCount();
        if ($num > 0) {
            $orders_arr = ["data" => []];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                array_push($orders_arr["data"], $row);
            }
            http_response_code(200);
            echo json_encode($orders_arr);
        } else {
            echo json_encode(["data" => []]);
        }
    }

    // [GET] /api/workorders/next-number - (Sin cambios)
    public function getNextWorkOrderNumber()
    {
        header("Content-Type: application/json; charset=UTF-8");
        $prefix = "WO-" . date('Y') . "-" . strtoupper(date('M')) . "-";
        $last_order = $this->workOrder->findLastByMonth($prefix);
        $next_number = 1;
        if ($last_order) {
            $last_num_str = substr($last_order['work_order_number'], -5);
            $next_number = intval($last_num_str) + 1;
        }
        $next_wo_number = $prefix . str_pad($next_number, 5, '0', STR_PAD_LEFT);
        echo json_encode(["success" => true, "next_number" => $next_wo_number]);
    }


    // [GET] /api/workorders/details/{id} - (Sin cambios, el modelo fue actualizado)
    public function getDetails($id)
    {
        $this->checkSession();
        header("Content-Type: application/json; charset=UTF-8");

        $data = $this->workOrder->findById($id); // findById ahora hace el JOIN

        if ($data) {
            $tech_query = "SELECT user_id FROM workordertechnicians WHERE work_order_id = ?";
            $stmt = $this->db->prepare($tech_query);
            $stmt->execute([$id]);
            $data['involved_technicians'] = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            $photoModel = new WorkOrderPhoto($this->db);
            $data['photos'] = $photoModel->getPhotosByWorkOrder($id);

            http_response_code(200);
            echo json_encode(["success" => true, "data" => $data]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Work Order not found."]);
        }
    }

    // [POST] /api/workorders/create - (ACTUALIZADO para manejar customer_id)
    public function create()
    {
        $this->checkSession();
        header("Content-Type: application/json; charset=UTF-8");

        date_default_timezone_set('America/Mexico_City');

        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Access denied."]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        // --- CAMBIO: Lógica de Cliente ---
        $customer_id = $data['customer_id'] ?? null;

        // Si customer_id NO es un número (es una 'tag' nueva) o es nulo
        if (!is_numeric($customer_id)) {
            
            if (empty($data['customer_name_new']) || empty($data['customer_city'])) {
                 http_response_code(400);
                 echo json_encode(["success" => false, "message" => "New customer name and city are required."]);
                 exit();
            }
            $customerData = [
                'customer_name' => $data['customer_name_new'],
                'customer_city' => $data['customer_city'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_email' => null, // El formulario no lo pide
                'customer_type' => $data['customer_type'] ?? null
            ];
            
            // Usamos el modelo Customer para crear/encontrar el cliente
            $customer_id = $this->customerModel->create($customerData);

            if ($customer_id == 0) {
                 http_response_code(500);
                 echo json_encode(["success" => false, "message" => "Failed to create or find the customer."]);
                 exit();
            }
        }
        // --- FIN: Lógica de Cliente ---


        if (empty($data['category']) || empty($data['assignToEmail'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Incomplete data (Category or Assigned Tech)."]);
            return;
        }

        $user_stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ? AND role = 'technician'");
        $user_stmt->execute([$data['assignToEmail']]);
        $tech = $user_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tech) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Technician email not found."]);
            return;
        }

        // --- CAMBIO: Preparar datos para el modelo WorkOrder ---
        $wo_data = [
            "workOrderNumber" => $data['workOrderNumber'],
            "admin_id" => $_SESSION['user_id'],
            "assigned_to_user_id" => $tech['user_id'],
            
            "customer_id" => $customer_id, // <<< CAMBIO
            
            "serviceDate" => $data['serviceDate'],
            "category" => $data['category'],
            "subcategory" => $data['subcategory'],
            "totalCost" => $data['totalCost'],
            "activityDescription" => $data['activityDescription'],
            "isEmergency" => $data['isEmergency'] ? 1 : 0
        ];

        if ($this->workOrder->create($wo_data)) {
            $work_order_id = $this->db->lastInsertId();
            $this->syncInvolvedTechnicians($work_order_id, $data['assignedTechnicians']);

            $photoModel = new WorkOrderPhoto($this->db);
            $photoModel->deletePhotosByWorkOrder($work_order_id); 

            $sync_before_success = $this->syncPhotos($work_order_id, $data['photosBefore'] ?? [], 'before');
            $sync_after_success = $this->syncPhotos($work_order_id, $data['photosAfter'] ?? [], 'after');

            if ($sync_before_success && $sync_after_success) {
                http_response_code(201);
                echo json_encode(["success" => true, "message" => "Work Order Created."]);
            } else {
                http_response_code(201); 
                echo json_encode(["success" => true, "message" => "Work Order Created. WARNING: Failed to save some photos."]);
            }
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Unable to create work order."]);
        }
    }


    // [POST] /api/workorders/update/{id} - (ACTUALIZADO para manejar customer_id)
    public function update($id)
    {
        $this->checkSession();
        header("Content-Type: application/json; charset=UTF-8");

        $role = $_SESSION['role'];
        $data = json_decode(file_get_contents("php://input"), true);

        $is_success = false;
        $work_order_id = $id; 

        if ($role === 'admin') {
            
            // --- CAMBIO: Lógica de Cliente (igual a create) ---
            $customer_id = $data['customer_id'] ?? null;
            if (!is_numeric($customer_id)) {
                if (empty($data['customer_name_new']) || empty($data['customer_city'])) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "New customer name and city are required."]);
                    exit();
                }
                $customerData = [
                    'customer_name' => $data['customer_name_new'],
                    'customer_city' => $data['customer_city'],
                    'customer_phone' => $data['customer_phone'] ?? null,
                    'customer_email' => null,
                    'customer_type' => $data['customer_type'] ?? null
                ];
                $customer_id = $this->customerModel->create($customerData);
                if ($customer_id == 0) {
                    http_response_code(500);
                    echo json_encode(["success" => false, "message" => "Failed to create or find the customer."]);
                    exit();
                }
            }
            // --- FIN: Lógica de Cliente ---

            // 1. Obtener el ID del técnico
            $user_stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ? AND role = 'technician'");
            $user_stmt->execute([$data['assignToEmail']]);
            $tech = $user_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tech) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Technician email not found."]);
                return;
            }

            // --- CAMBIO: Preparar datos de Admin ---
            $wo_data = [
                "customer_id" => $customer_id, // <<< CAMBIO
                
                "serviceDate" => $data['serviceDate'],
                "category" => $data['category'],
                "subcategory" => $data['subcategory'],
                "totalCost" => $data['totalCost'],
                "assigned_to_user_id" => $tech['user_id'],
                "activityDescription" => $data['activityDescription'],
                "isEmergency" => $data['isEmergency'] ? 1 : 0
            ];

            $is_success = $this->workOrder->updateAdminFields($work_order_id, $wo_data);
            $this->syncInvolvedTechnicians($work_order_id, $data['assignedTechnicians']);

        } elseif ($role === 'technician') {
            
            // LÓGICA DE ACTUALIZACIÓN DEL TÉCNICO (Sin cambios)
            $data['workAfter5PM'] = $data['workAfter5PM'] ? 1 : 0;
            $data['workWeekend'] = $data['workWeekend'] ? 1 : 0;
            $data['isEmergency'] = $data['isEmergency'] ? 1 : 0;

            $is_success = $this->workOrder->updateTechFields($work_order_id, $data);
            $this->syncInvolvedTechnicians($work_order_id, $data['assignedTechnicians']);
        }

        if ($is_success) {
            // (Lógica de notificación y sincronización de fotos sin cambios)
            $creator_query = "SELECT u.email, u.full_name 
                              FROM workorders wo
                              JOIN users u ON wo.created_by_admin_id = u.user_id
                              WHERE wo.work_order_id = ?";
            $creator_stmt = $this->db->prepare($creator_query);
            $creator_stmt->execute([$work_order_id]);
            $creator_data = $creator_stmt->fetch(PDO::FETCH_ASSOC);
            $creator_email = $creator_data['email'] ?? null;
            $creator_name = $creator_data['full_name'] ?? 'Admin Dispatcher';

            $paths_before = $data['photosBefore'] ?? [];
            $paths_after = $data['photosAfter'] ?? [];
            $all_paths_to_keep = array_merge($paths_before, $paths_after);

            $this->syncPhotoDeletion($work_order_id, $all_paths_to_keep);
            $sync_before_success = $this->syncPhotos($work_order_id, $paths_before, 'before');
            $sync_after_success = $this->syncPhotos($work_order_id, $paths_after, 'after');

            if ($sync_before_success && $sync_after_success) {
                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "message" => "Work Order Updated.",
                    "adminEmail" => $creator_email,
                    "adminName" => $creator_name,
                ]);
            } else {
                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "message" => "Work Order Updated. WARNING: Failed to save some photos.",
                    "adminEmail" => $creator_email,
                    "adminName" => $creator_name,
                ]);
            }
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Unable to update work order."]);
        }
    }


    // (appendDebugLog y syncPhotoDeletion sin cambios)
    private function appendDebugLog($work_order_id, $message)
    {
        $safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $query = "UPDATE workorders 
              SET activity_description = CONCAT(IFNULL(activity_description, ''), '\n', :message)
              WHERE work_order_id = :work_order_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':message', $safe_message);
        $stmt->bindParam(':work_order_id', $work_order_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function syncPhotoDeletion($work_order_id, $paths_to_keep)
    {
        $photoModel = new WorkOrderPhoto($this->db);
        $all_current_photos_db = $photoModel->getPhotosByWorkOrder($work_order_id);
        $existing_paths = array_map(fn($p) => $p['file_path'], $all_current_photos_db);
        $paths_to_delete = array_diff($existing_paths, $paths_to_keep);
        foreach ($paths_to_delete as $path_to_delete) {
            $photoModel->deleteSpecificPhotoByPath($path_to_delete);
        }
    }
}