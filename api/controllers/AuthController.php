<?php
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../models/User.php';

class AuthController
{

    public function login()
    {
        // Headers (Aseguran que la respuesta es JSON)
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Methods: POST");

        // 1. Leer el cuerpo de la solicitud CRUDA (la que envía auth.js)
        $json_data = file_get_contents("php://input");

        // 2. Intentar decodificar el JSON
        $data = json_decode($json_data, true);
        $json_error = json_last_error_msg();

        // 3. Evaluar el resultado y enviarlo al cliente
        if ($data === null && $json_data !== "") {
            // Falla la decodificación, pero el cuerpo no estaba vacío
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "ERROR DE JSON: El cuerpo de la solicitud no es JSON válido.",
                "php_error" => $json_error, // Indica la razón del fallo (Ej: Syntax error)
                "raw_input_start" => substr($json_data, 0, 50) // Muestra los primeros 50 caracteres
            ]);
            return;
        }

        if (empty($data)) {
            // El cuerpo estaba vacío o $data es un array vacío
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "ERROR DE INPUT: Cuerpo de la solicitud vacío (0 bytes leídos).",
                "raw_input_size" => strlen($json_data)
            ]);
            return;
        }

        // Si llega aquí, hay datos y son JSON válido. Mostrar las claves.
        $email_key_present = isset($data['email']);
        $password_key_present = isset($data['password']);

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "DEBUG OK: Datos JSON recibidos.",
            "keys_found" => array_keys($data),
            "email_status" => $email_key_present ? "RECEIVED" : "MISSING",
            "password_status" => $password_key_present ? "RECEIVED" : "MISSING",
            "received_email" => $email_key_present ? $data['email'] : null
        ]);
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
