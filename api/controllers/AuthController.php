<?php
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../models/User.php';

class AuthController
{

    public function login()
    {
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Methods: POST");

        $json_data = file_get_contents("php://input");
        $data = json_decode($json_data, true);


        // Verificar que los datos JSON existan y sean válidos
        if (empty($data) || !isset($data['email']) || !isset($data['password'])) {
            http_response_code(400); // Bad Request
            echo json_encode(["success" => false, "message" => "Datos de login incompletos o formato inválido (JSON esperado)."]);
            return;
        }

        // Obtener datos
        $email = $data['email'];
        $password = $data['password'];
        // --- FIN MODIFICACIÓN ---

        // Obtener conexión a la BD
        // El bloque try/catch de la conexión está ahora en database.php
        $database = new Database();
        $db = $database->getConnection();

        // Verificación de conexión (Aunque ya probamos que funciona, se mantiene por seguridad)
        if ($db === null) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Error de servicio. No se pudo obtener la conexión a la base de datos."]);
            return;
        }

        // Instanciar objeto User
        $user = new User($db);

        // 1. Buscar al usuario por email
        if (!$user->findByEmail($email)) {
            // Usuario no encontrado
            http_response_code(401); // Unauthorized
            echo json_encode(["success" => false, "message" => "Email o contraseña incorrecta."]);
            return;
        }

        // 2. Verificar la contraseña
        // $user->password_hash ahora contiene el hash de la BD
        if (password_verify($password, $user->password_hash)) {
            // Contraseña correcta

            // 3. Iniciar la sesión de PHP (Si no se hizo en index.php)
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            // 4. Guardar datos en la sesión
            $_SESSION['user_id'] = $user->user_id;
            $_SESSION['email'] = $user->email;
            $_SESSION['full_name'] = $user->full_name;
            $_SESSION['role'] = $user->role;

            // 5. Enviar respuesta JSON de éxito al frontend
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "role" => $user->role,
                "message" => "Inicio de sesión exitoso."
            ]);
        } else {
            // Contraseña incorrecta
            http_response_code(401); // Unauthorized
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
