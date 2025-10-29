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

        $email = null;
        $password = null;
        $debug_type = "NONE";

        // --- Intento 1: Leer POST (Formulario Tradicional) ---
        if (isset($_POST['email']) && isset($_POST['password'])) {
            $email = $_POST['email'];
            $password = $_POST['password'];
            $debug_type = "POST_TRADITIONAL";
        } 
        
        // --- Intento 2: Leer JSON (Formato API Moderno) ---
        if ($email === null) {
            $json_data = file_get_contents("php://input");
            $data = json_decode($json_data, true);
            
            if (isset($data['email']) && isset($data['password'])) {
                $email = $data['email'];
                $password = $data['password'];
                $debug_type = "JSON_BODY";
            }
        }
        
        // --- REPORTE DE DEPURACIÓN ---
        if ($email !== null) {
            http_response_code(200);
            echo json_encode([
                "success" => true, 
                "message" => "DEBUG: Entrada Exitosa",
                "received_by" => $debug_type,
                "email_received" => $email,
                "password_received" => $password
            ]);
        } else {
            http_response_code(400); 
            echo json_encode([
                "success" => false, 
                "message" => "DEBUG: No se recibieron email/password en POST ni en JSON.",
                "post_status" => empty($_POST) ? "EMPTY" : "NOT EMPTY",
                "json_status" => (isset($data) && $data !== null) ? "INVALID_JSON_KEYS" : "NO_JSON_BODY_OR_INVALID"
            ]);
        }
        return; // Detenemos el script aquí
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