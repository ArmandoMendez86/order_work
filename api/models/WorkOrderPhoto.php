<?php
// api/models/WorkOrderPhoto.php

class WorkOrderPhoto
{
    private $conn;
    private $table_name = "WorkOrderPhotos";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Guarda la ruta de la foto en la BD
    public function savePhotoPath($work_order_id, $photo_type, $file_path)
    {
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                    work_order_id = :work_order_id,
                    photo_type = :photo_type,
                    file_path = :file_path";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':work_order_id', $work_order_id);
        $stmt->bindParam(':photo_type', $photo_type);
        $stmt->bindParam(':file_path', $file_path);

        return $stmt->execute();
    }

    // Obtiene las fotos de una orden para cargarlas en el frontend
    public function getPhotosByWorkOrder($work_order_id)
    {
        $query = "SELECT photo_type, file_path FROM " . $this->table_name . "
                  WHERE work_order_id = :work_order_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':work_order_id', $work_order_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deletePhotosByWorkOrder($work_order_id)
    {

        // 1. OBTENER LAS RUTAS DE LOS ARCHIVOS
        $select_query = "SELECT file_path FROM " . $this->table_name . " WHERE work_order_id = :work_order_id";
        $select_stmt = $this->conn->prepare($select_query);
        $select_stmt->bindParam(':work_order_id', $work_order_id);
        $select_stmt->execute();
        $files_to_delete = $select_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        // Define la raíz de tu proyecto para poder construir la ruta absoluta.
        // NOTA: Asume que 'uploads/' es la raíz del proyecto. Ajustar si es necesario.
        $project_root = dirname(__DIR__, 2); // Sube dos niveles desde /api/models a la raíz /order_work/

        // 2. ELIMINAR LOS ARCHIVOS FÍSICOS
        foreach ($files_to_delete as $file_path_db) {
            // Ejemplo de $file_path_db: 'uploads/before/2_fp_123.png'
            $full_path = $project_root . '/' . $file_path_db;

            if (file_exists($full_path) && is_file($full_path)) {
                // Usamos @unlink para suprimir errores de permisos, que deben ser manejados por logs.
                @unlink($full_path);
            }
        }

        // 3. ELIMINAR LOS REGISTROS DE LA BASE DE DATOS
        $delete_query = "DELETE FROM " . $this->table_name . " WHERE work_order_id = :work_order_id";
        $delete_stmt = $this->conn->prepare($delete_query);
        $delete_stmt->bindParam(':work_order_id', $work_order_id);
        return $delete_stmt->execute();
    }

    public function deleteSpecificPhotoByPath($file_path)
    {
        // Define la raíz de tu proyecto
        // NOTA: Ajusta el número '2' si la ruta de tu proyecto no es /api/models/ -> /
        $project_root = dirname(__DIR__, 2);
        $full_path = $project_root . '/' . $file_path;

        // 1. Eliminar archivo físico
        if (file_exists($full_path) && is_file($full_path)) {
            @unlink($full_path);
        }

        // 2. Eliminar registro de la BD
        $delete_query = "DELETE FROM " . $this->table_name . " WHERE file_path = :file_path";
        $delete_stmt = $this->conn->prepare($delete_query);
        $delete_stmt->bindParam(':file_path', $file_path);
        return $delete_stmt->execute();
    }
}
