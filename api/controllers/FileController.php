<?php
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
define('UPLOAD_TEMP_DIR', UPLOAD_DIR . 'temp/'); 

class FileController {

    public function __construct() {
        // Asegura que la carpeta temporal exista
        if (!is_dir(UPLOAD_TEMP_DIR)) {
            mkdir(UPLOAD_TEMP_DIR, 0777, true);
        }
    }

    // [POST] /api/file-upload/process: Maneja la subida temporal
    public function processUpload() {
        // FilePond espera una respuesta simple (el nombre del archivo)
        header('Content-Type: text/plain'); 
        
        if (empty($_FILES) || !isset($_FILES['file'])) {
            http_response_code(400);
            echo 'Error: No file uploaded.';
            return;
        }

        $file = $_FILES['file'];
        
        $safe_name = preg_replace("/[^a-zA-Z0-9\.]/", "_", basename($file['name']));
        $unique_id = uniqid('fp_');
        $unique_name = $unique_id . '_' . $safe_name;
        $target_file = UPLOAD_TEMP_DIR . $unique_name;

        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            // Devuelve el nombre único. Este es el serverId de FilePond.
            echo $unique_name; 
        } else {
            http_response_code(500);
            echo 'Error: Failed to move uploaded file.';
        }
    }
    
    // [DELETE] /api/file-upload/revert: Maneja la eliminación temporal
    public function processRevert() {
        // El nombre del archivo temporal viene en el cuerpo de la petición
        $file_name = file_get_contents('php://input'); 
        $file_path = UPLOAD_TEMP_DIR . basename($file_name); 

        if (file_exists($file_path)) {
            unlink($file_path);
            http_response_code(200);
            echo 'Revert successful.';
        } else {
            http_response_code(404);
            echo 'File not found.';
        }
    }
}
?>