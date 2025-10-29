<?php
// api/models/WorkOrder.php

class WorkOrder
{
  private $conn;
  private $table_name = "workorders";

  public function __construct($db)
  {
    $this->conn = $db;
  }

  // Método para OBTENER TODAS las órdenes (para el Admin)
  public function getAll()
  {
    $query = "SELECT
                    wo.work_order_id,
                    wo.work_order_number,
                    wo.customer_name,
                    wo.`status`,
                    c.category_name,
                    tech.full_name AS technician_name
                  FROM
                    " . $this->table_name . " wo
                    LEFT JOIN categories c ON wo.category_id = c.category_id
                    LEFT JOIN users tech ON wo.assigned_to_user_id = tech.user_id
                  ORDER BY
                    wo.created_at DESC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt;
  }

  // Método para OBTENER ÓRDENES ASIGNADAS (para el Técnico)
  public function getAssignedTo($user_id)
  {
    $query = "SELECT
                    wo.work_order_id,
                    wo.work_order_number,
                    wo.customer_name,
                    wo.customer_city,
                    wo.`status`,
                    c.category_name
                  FROM
                    " . $this->table_name . " wo
                    LEFT JOIN categories c ON wo.category_id = c.category_id
                  WHERE
                    wo.assigned_to_user_id = :user_id
                  ORDER BY
                    wo.created_at DESC";

    $stmt = $this->conn->prepare($query);
    // Sanitizar y vincular
    $user_id = htmlspecialchars(strip_tags($user_id));
    $stmt->bindParam(':user_id', $user_id);

    $stmt->execute();
    return $stmt;
  }

  // Método para encontrar la última orden del mes actual (para tu numeración)
  public function findLastByMonth($prefix)
  {
    // $prefix será "WO-YYYY-MMM-"
    $query = "SELECT work_order_number 
                  FROM " . $this->table_name . " 
                  WHERE work_order_number LIKE :prefix 
                  ORDER BY work_order_id DESC 
                  LIMIT 1";

    $stmt = $this->conn->prepare($query);
    $like_prefix = $prefix . '%';
    $stmt->bindParam(':prefix', $like_prefix);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
      return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
  }

  // Método para OBTENER todos los detalles de UNA orden (ACTUALIZADO)
  public function findById($id)
  {
    // La consulta ahora incluye todos los campos nuevos
    $query = "SELECT wo.*, 
                         c.category_name, 
                         s.subcategory_name,
                         tech.email AS assign_to_email
                  FROM " . $this->table_name . " wo
                  LEFT JOIN categories c ON wo.category_id = c.category_id
                  LEFT JOIN subcategories s ON wo.subcategory_id = s.subcategory_id
                  LEFT JOIN users tech ON wo.assigned_to_user_id = tech.user_id
                  WHERE wo.work_order_id = ?
                  LIMIT 1";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(1, $id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  // Método para CREAR una nueva orden de trabajo (ACTUALIZADO)
  public function create($data)
  {
    // La consulta INSERT ahora incluye todos los campos nuevos
    $query = "INSERT INTO " . $this->table_name . "
                  SET
                    work_order_number = :work_order_number,
                    created_by_admin_id = :admin_id,
                    assigned_to_user_id = :tech_id,
                    `status` = 'Pending',
                    
                    customer_name = :customer_name,
                    customer_city = :customer_city,
                    customer_phone = :customer_phone,
                    customer_type = :customer_type,
                    service_date = :service_date,
                    category_id = (SELECT category_id FROM categories WHERE category_name = :category_name),
                    subcategory_id = (SELECT subcategory_id FROM subcategories WHERE subcategory_name = :subcategory_name),
                    total_cost = :total_cost,
                    
                    activity_description = :activity_description,
                    is_emergency = :is_emergency
                    ";

    $stmt = $this->conn->prepare($query);

    // Sanitizar y vincular datos del Admin
    $stmt->bindParam(':work_order_number', $data['workOrderNumber']);
    $stmt->bindParam(':admin_id', $data['admin_id']);
    $stmt->bindParam(':tech_id', $data['assigned_to_user_id']);
    $stmt->bindParam(':customer_name', $data['customerName']);
    $stmt->bindParam(':customer_city', $data['city']);
    $stmt->bindParam(':customer_phone', $data['phoneNumber']);
    $stmt->bindParam(':customer_type', $data['customerType']);
    $stmt->bindParam(':service_date', $data['serviceDate']);
    $stmt->bindParam(':category_name', $data['category']);
    $stmt->bindParam(':subcategory_name', $data['subcategory']);
    $stmt->bindParam(':total_cost', $data['totalCost']);
    $stmt->bindParam(':activity_description', $data['activityDescription']);
    $stmt->bindParam(':is_emergency', $data['isEmergency'], PDO::PARAM_BOOL);

    if ($stmt->execute()) {
      return true;
    }
    return false;
  }

  // NUEVO MÉTODO: Actualiza solo los campos del ADMINISTRADOR
  public function updateAdminFields($id, $data)
  {
    // Nota: El campo assigned_to_user_id se actualiza con el ID del técnico
    $query = "UPDATE " . $this->table_name . "
                  SET
                    customer_name = :customer_name,
                    customer_city = :customer_city,
                    customer_phone = :customer_phone,
                    customer_type = :customer_type,
                    service_date = :service_date,
                    category_id = (SELECT category_id FROM categories WHERE category_name = :category_name),
                    subcategory_id = (SELECT subcategory_id FROM subcategories WHERE subcategory_name = :subcategory_name),
                    total_cost = :total_cost,
                    assigned_to_user_id = :tech_id,
                    activity_description = :activity_description,
                    is_emergency = :is_emergency
                  WHERE
                    work_order_id = :work_order_id";

    $stmt = $this->conn->prepare($query);

    // Vinculación de parámetros
    $stmt->bindParam(':customer_name', $data['customerName']);
    $stmt->bindParam(':customer_city', $data['city']);
    $stmt->bindParam(':customer_phone', $data['phoneNumber']);
    $stmt->bindParam(':customer_type', $data['customerType']);
    $stmt->bindParam(':service_date', $data['serviceDate']);
    $stmt->bindParam(':category_name', $data['category']);
    $stmt->bindParam(':subcategory_name', $data['subcategory']);
    $stmt->bindParam(':total_cost', $data['totalCost']);
    $stmt->bindParam(':tech_id', $data['assigned_to_user_id']); // Ya es un ID
    $stmt->bindParam(':activity_description', $data['activityDescription']);
    $stmt->bindParam(':is_emergency', $data['isEmergency'], PDO::PARAM_BOOL);
    $stmt->bindParam(':work_order_id', $id, PDO::PARAM_INT);

    return $stmt->execute();
  }


  // NUEVO MÉTODO: Actualiza solo los campos del TÉCNICO (Versión simplificada del update original)
  public function updateTechFields($id, $data)
  {
    $query = "UPDATE " . $this->table_name . "
                  SET
                    `status` = :status,
                    materials_used = :materials_used,
                    work_description = :work_description,
                    start_datetime = :start_datetime,
                    end_datetime = :end_datetime,
                    total_hours = :total_hours,
                    estimated_duration = :estimated_duration,
                    work_after_5pm = :work_after_5pm,
                    work_weekend = :work_weekend,
                    is_emergency = :is_emergency,
                    
                    tech_signature_name_print = :tech_signature_name_print,
                    tech_signature_base64 = :tech_signature_base64,
                    manager_signature_name_print = :manager_signature_name_print,
                    manager_signature_base64 = :manager_signature_base64
                  WHERE
                    work_order_id = :work_order_id";

    $stmt = $this->conn->prepare($query);

    // Vinculación de parámetros
    $stmt->bindParam(':status', $data['workStage']);
    $stmt->bindParam(':materials_used', $data['materials']);
    $stmt->bindParam(':work_description', $data['workDescription']);
    $stmt->bindParam(':start_datetime', $data['startDate']);
    $stmt->bindParam(':end_datetime', $data['endDate']);
    $stmt->bindParam(':total_hours', $data['totalHours']);
    $stmt->bindParam(':estimated_duration', $data['estimatedDuration']);
    $stmt->bindParam(':work_after_5pm', $data['workAfter5PM'], PDO::PARAM_BOOL);
    $stmt->bindParam(':work_weekend', $data['workWeekend'], PDO::PARAM_BOOL);
    $stmt->bindParam(':is_emergency', $data['isEmergency'], PDO::PARAM_BOOL);

    $stmt->bindParam(':tech_signature_name_print', $data['techSignatureName']);
    $stmt->bindParam(':tech_signature_base64', $data['tech_signature_base64']);
    $stmt->bindParam(':manager_signature_name_print', $data['managerSignatureName']);
    $stmt->bindParam(':manager_signature_base64', $data['manager_signature_base64']);

    $stmt->bindParam(':work_order_id', $id, PDO::PARAM_INT);

    return $stmt->execute();
  }
}
