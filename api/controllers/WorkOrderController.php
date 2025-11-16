<?php
// api/controllers/WorkOrderController.php

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../models/WorkOrder.php';
include_once __DIR__ . '/../models/WorkOrderPhoto.php'; // Incluir modelo de fotos

define('UPLOAD_TEMP_DIR', __DIR__ . '/../../uploads/temp/');
define('UPLOAD_FINAL_DIR', __DIR__ . '/../../uploads/');

class WorkOrderController
{

    private $db;
    private $workOrder;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->workOrder = new WorkOrder($this->db);
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

    private function syncPhotos($work_order_id, $file_data, $photo_type)
    {
        $photoModel = new WorkOrderPhoto($this->db);
        $all_success = true;

        // 1. Definir la subcarpeta final
        $FINAL_DEST_DIR = UPLOAD_FINAL_DIR . $photo_type . '/';
        if (!is_dir($FINAL_DEST_DIR)) {
            if (!mkdir($FINAL_DEST_DIR, 0777, true)) {
                error_log("FATAL: Failed to create upload directory: " . $FINAL_DEST_DIR);
                $all_success = false;
            }
        }

        if (is_array($file_data)) {
            foreach ($file_data as $file_info) {

                // Si el archivo ya tiene la ruta final, significa que el usuario NO LO CAMBIÓ.
                // La función syncPhotoDeletion se encargó de verificar si debe mantenerse o eliminarse.
                if (strpos($file_info, 'uploads/') === 0) {
                    continue; // ⬅️ OMITIMOS archivos ya existentes en la BD.
                }

                // Caso: Archivo NUEVO (nombre temporal de FilePond)
                $temp_file_name = $file_info;
                $source_path = UPLOAD_TEMP_DIR . basename($temp_file_name);

                // Generar nombre de archivo final y ruta de BD
                $final_file_name = $work_order_id . '_' . basename($temp_file_name);
                $db_path_to_save = 'uploads/' . $photo_type . '/' . $final_file_name;

                // Mover el archivo temporal a la ruta final
                if (file_exists($source_path)) {
                    if (rename($source_path, $FINAL_DEST_DIR . $final_file_name)) {
                        // Guardar la ruta del nuevo archivo
                        if (!$photoModel->savePhotoPath($work_order_id, $photo_type, $db_path_to_save)) {
                            error_log("SQL FAIL on new photo: " . $this->db->errorInfo()[2]);
                            $all_success = false;
                        }
                    } else {
                        error_log("RENAME FAILURE for new file {$photo_type}. Source: {$source_path}");
                        $all_success = false;
                    }
                }
                // Si el archivo temporal no existe, simplemente lo omitimos (pudo haber sido revertido).
            }
        }
        return $all_success;
    }
    // [GET] /api/workorders/all - Lista todas las órdenes (Asume que WorkOrder.php tiene getAll())
    public function listAll()
    {
        $this->checkSession();
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Acceso denegado."]);
            return;
        }

        // Implementación simplificada
        $stmt = $this->workOrder->getAll(); // Asume que getAll() existe
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

    // [GET] /api/workorders/assigned - Lista órdenes asignadas al técnico (Asume que WorkOrder.php tiene getAssignedTo())
    public function listAssigned()
    {
        $this->checkSession();
        $user_id = $_SESSION['user_id'];

        // Implementación simplificada
        $stmt = $this->workOrder->getAssignedTo($user_id); // Asume que getAssignedTo() existe
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

    // [GET] /api/workorders/next-number - Obtiene el siguiente número de orden (sin cambios)
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


    // [GET] /api/workorders/details/{id} - Obtiene todos los detalles incluyendo fotos
    public function getDetails($id)
    {
        $this->checkSession();
        header("Content-Type: application/json; charset=UTF-8");

        $data = $this->workOrder->findById($id);

        if ($data) {
            // Obtener Involved Technicians (Tags)
            $tech_query = "SELECT user_id FROM workordertechnicians WHERE work_order_id = ?";
            $stmt = $this->db->prepare($tech_query);
            $stmt->execute([$id]);
            $data['involved_technicians'] = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            // OBTENER FOTOS
            $photoModel = new WorkOrderPhoto($this->db);
            $data['photos'] = $photoModel->getPhotosByWorkOrder($id);

            http_response_code(200);
            echo json_encode(["success" => true, "data" => $data]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Work Order not found."]);
        }
    }

    // [POST] /api/workorders/create - Crea una nueva orden
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

        if (empty($data['category']) || empty($data['customerName']) || empty($data['assignToEmail'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Incomplete data."]);
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

        $wo_data = [
            "workOrderNumber" => $data['workOrderNumber'],
            "admin_id" => $_SESSION['user_id'],
            "assigned_to_user_id" => $tech['user_id'],
            "customerName" => $data['customerName'],
            "city" => $data['city'],
            "phoneNumber" => $data['phoneNumber'],
            "customerType" => $data['customerType'],
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

            // PASO 1: ELIMINAR TODOS LOS REGISTROS ANTIGUOS DE FOTOS (Se ejecuta solo UNA vez)
            $photoModel = new WorkOrderPhoto($this->db);
            $photoModel->deletePhotosByWorkOrder($work_order_id); // ¡Nueva ubicación!

            // PASO 2: SINCRONIZAR Y REGISTRAR EL NUEVO SET DE FOTOS
            $sync_before_success = $this->syncPhotos($work_order_id, $data['photosBefore'] ?? [], 'before');
            $sync_after_success = $this->syncPhotos($work_order_id, $data['photosAfter'] ?? [], 'after');

            // Si la creación de la WO fue exitosa, pero falló la sincronización de fotos
            if ($sync_before_success && $sync_after_success) {
                http_response_code(201);
                echo json_encode(["success" => true, "message" => "Work Order Created."]);
            } else {
                // En este punto, la orden se creó, pero la foto 'before' falló.
                http_response_code(201); // Mantenemos 201 ya que la WO sí se creó
                echo json_encode(["success" => true, "message" => "Work Order Created. WARNING: Failed to save some photos. Check activity description."]);
            }
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Unable to create work order."]);
        }
    }


    public function update($id)
    {
        $this->checkSession();
        header("Content-Type: application/json; charset=UTF-8");

        $role = $_SESSION['role'];
        $data = json_decode(file_get_contents("php://input"), true);

        $is_success = false;
        $work_order_id = $id; // Usamos $work_order_id para claridad en las fotos

        // --- LÓGICA DE ACTUALIZACIÓN (depende del rol) ---
        if ($role === 'admin') {
            // 1. Obtener el ID del técnico
            $user_stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ? AND role = 'technician'");
            $user_stmt->execute([$data['assignToEmail']]);
            $tech = $user_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tech) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Technician email not found."]);
                return;
            }

            // 2. Preparar datos de Admin
            $wo_data = [
                "customerName" => $data['customerName'],
                "city" => $data['city'],
                "phoneNumber" => $data['phoneNumber'],
                "customerType" => $data['customerType'],
                "serviceDate" => $data['serviceDate'],
                "category" => $data['category'],
                "subcategory" => $data['subcategory'],
                "totalCost" => $data['totalCost'],
                "assigned_to_user_id" => $tech['user_id'],
                "activityDescription" => $data['activityDescription'],
                "isEmergency" => $data['isEmergency'] ? 1 : 0
            ];

            // 3. Actualizar
            $is_success = $this->workOrder->updateAdminFields($work_order_id, $wo_data);
            $this->syncInvolvedTechnicians($work_order_id, $data['assignedTechnicians']);
        } elseif ($role === 'technician') {
            // LÓGICA DE ACTUALIZACIÓN DEL TÉCNICO

            // 1. Convertir booleanos
            $data['workAfter5PM'] = $data['workAfter5PM'] ? 1 : 0;
            $data['workWeekend'] = $data['workWeekend'] ? 1 : 0;
            $data['isEmergency'] = $data['isEmergency'] ? 1 : 0;

            // 2. Actualizar
            $is_success = $this->workOrder->updateTechFields($work_order_id, $data);

            // 3. Sincronizar tags
            $this->syncInvolvedTechnicians($work_order_id, $data['assignedTechnicians']);
        }

        // --- MANEJO DE RESPUESTA Y NOTIFICACIÓN ---
        if ($is_success) {
            // 1. OBTENER EMAIL Y NOMBRE DEL CREADOR (ADMIN) - ¡CRÍTICO PARA LA NOTIFICACIÓN!
            $creator_query = "SELECT u.email, u.full_name 
                              FROM workorders wo
                              JOIN users u ON wo.created_by_admin_id = u.user_id
                              WHERE wo.work_order_id = ?";
            $creator_stmt = $this->db->prepare($creator_query);
            $creator_stmt->execute([$work_order_id]);
            $creator_data = $creator_stmt->fetch(PDO::FETCH_ASSOC);

            $creator_email = $creator_data['email'] ?? null;
            $creator_name = $creator_data['full_name'] ?? 'Admin Dispatcher';
            // ------------------------------------------------------------------------

            // 2. Sincronización de fotos
            $paths_before = $data['photosBefore'] ?? [];
            $paths_after = $data['photosAfter'] ?? [];
            $all_paths_to_keep = array_merge($paths_before, $paths_after);

            $this->syncPhotoDeletion($work_order_id, $all_paths_to_keep);
            $sync_before_success = $this->syncPhotos($work_order_id, $paths_before, 'before');
            $sync_after_success = $this->syncPhotos($work_order_id, $paths_after, 'after');

            // 3. Devolver éxito con el email del admin
            if ($sync_before_success && $sync_after_success) {
                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "message" => "Work Order Updated.",
                    "adminEmail" => $creator_email, // <<< Correo del creador para JS
                    "adminName" => $creator_name,   // <<< Nombre del creador para JS
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



    private function appendDebugLog($work_order_id, $message)
    {
        // Escapa el mensaje para evitar inyecciones SQL en la actualización
        $safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        // Consulta para actualizar y concatenar el nuevo log al campo activity_description
        $query = "UPDATE workorders 
              SET activity_description = CONCAT(IFNULL(activity_description, ''), '\n', :message)
              WHERE work_order_id = :work_order_id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':message', $safe_message);
        $stmt->bindParam(':work_order_id', $work_order_id, PDO::PARAM_INT);

        // Ejecuta sin verificar el éxito, ya que es solo un log de depuración
        $stmt->execute();
    }

    private function syncPhotoDeletion($work_order_id, $paths_to_keep)
    {
        $photoModel = new WorkOrderPhoto($this->db);

        // Obtener todas las fotos actuales de la BD
        $all_current_photos_db = $photoModel->getPhotosByWorkOrder($work_order_id);
        $existing_paths = array_map(fn($p) => $p['file_path'], $all_current_photos_db);

        // Las rutas a eliminar son las que están en la BD, pero NO en el payload de FilePond
        $paths_to_delete = array_diff($existing_paths, $paths_to_keep);

        // Iterar y eliminar cada foto removida
        foreach ($paths_to_delete as $path_to_delete) {
            $photoModel->deleteSpecificPhotoByPath($path_to_delete);
        }
    }
}
