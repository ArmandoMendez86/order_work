<?php
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../models/User.php';

class AuthController
{

    public function login()
    {
        // Headers
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Methods: POST");

        // --- 1. Leer JSON del Body ---
        $json_data = file_get_contents("php://input");
        $data = json_decode($json_data, true);

        if (empty($data) || !isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Datos de login incompletos o formato inválido (JSON esperado)."]);
            return;
        }

        $email = $data['email'];
        $password = $data['password'];

        // 2. Obtener conexión a la BD
        $database = new Database();
        $db = $database->getConnection();

        if ($db === null) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Error de servicio. No se pudo obtener la conexión a la base de datos."]);
            return;
        }

        // 3. Instanciar objeto User
        $user = new User($db);

        // --- 4. MODIFICADO: Usar Método 2 (Obtener el registro) ---
        $user_data = $user->findByEmail($email); // $user_data es ahora un array (el registro) o false

        if (!$user_data) {
            // Si $user_data es false (email no encontrado)
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Email o contraseña incorrecta."]);
            return;
        }

        // --- 5. MODIFICADO: Verificar usando el array $user_data ---
        if (password_verify($password, $user_data['password_hash'])) {
            // Contraseña correcta
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            // Guardar datos del array en la sesión
            $_SESSION['user_id'] = $user_data['user_id'];
            $_SESSION['email'] = $user_data['email'];
            $_SESSION['full_name'] = $user_data['full_name'];
            $_SESSION['role'] = $user_data['role'];

            http_response_code(200);
            echo json_encode(["success" => true, "role" => $user_data['role'], "message" => "Inicio de sesión exitoso."]);
        } else {
            // Contraseña incorrecta
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Email o contraseña incorrecta."]);
        }
    }

    public function logout()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Destruir todas las variables de sesión.
        $_SESSION = [];

        // Si se desea destruir la sesión completamente, borre también la cookie de sesión.
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Finalmente, destruir la sesión.
        session_destroy();

        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Sesión cerrada."]);
    }

    // --- NUEVO MÉTODO PARA VERIFICAR LA SESIÓN ---
    public function status()
    {
        header("Content-Type: application/json; charset=UTF-8");
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Verificar si la sesión existe y tiene los datos
        if (isset($_SESSION['user_id']) && isset($_SESSION['full_name']) && isset($_SESSION['role'])) {
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "user" => [
                    "user_id" => $_SESSION['user_id'],
                    "full_name" => $_SESSION['full_name'],
                    "email" => $_SESSION['email'],
                    "role" => $_SESSION['role']
                ]
            ]);
        } else {
            // Si no hay sesión, enviar un error
            http_response_code(401); // Unauthorized
            echo json_encode([
                "success" => false,
                "message" => "No active session found."
            ]);
        }
    }
}
