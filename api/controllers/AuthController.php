<?php
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../models/User.php';

class AuthController
{

    public function login()
    {
        // Headers
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Methods: POST");

        // --- 1. LECTURA Y VALIDACIÓN JSON (Confirmado que funciona) ---
        $json_data = file_get_contents("php://input");
        $data = json_decode($json_data, true);

        if (empty($data) || !isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Datos de login incompletos. Falla la lectura JSON."]);
            return;
        }

        $email = $data['email'];
        $password = $data['password'];
        // --- FIN LECTURA JSON ---

        // 2. OBTENER CONEXIÓN A LA BD (Sabemos que funciona)
        try {
            // Instanciar la DB. Si falla, el método getConnection() lanza una excepción.
            $database = new Database();
            $db = $database->getConnection();
        } catch (Exception $e) {
            // Si regresa 500 aquí, es un fallo de conexión.
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "ERROR FATAL: Fallo al conectar con la base de datos.", "detail" => $e->getMessage()]);
            return;
        }

        // 3. Instanciar objeto User
        $user = new User($db);

        // --- CHECKPOINT CRÍTICO ANTES DE LA CONSULTA SQL ---
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "CHECKPOINT OK: Conexión y Modelo creados. El fallo está en la consulta SQL."]);
        return;
        // --- FIN CHECKPOINT CRÍTICO ---


        // 4. Buscar al usuario por email (ESTA LÍNEA ES EL PRÓXIMO FALLO)
        if (!$user->findByEmail($email)) {
            // ... (rest of the logic) ...
        }
        // ... (rest of the logic) ...
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
