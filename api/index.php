<?php
// --- BLOQUE CRÍTICO: Configurar y luego iniciar la sesión ---
if (session_status() == PHP_SESSION_NONE) {
    // 1. Configurar la cookie de sesión para la raíz antes de iniciar
    $session_params = session_get_cookie_params();
    session_set_cookie_params(
        $session_params["lifetime"],
        '/', // <<<< PATH DE LA COOKIE ESTABLECIDO A LA RAÍZ DEL DOMINIO
        $session_params["domain"],
        $session_params["secure"],
        $session_params["httponly"]
    );

    // 2. Iniciar la sesión
    session_start();
}
// --- FIN BLOQUE CRÍTICO ---

$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];
$path_only = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace(dirname($script_name), '', $path_only);
$path = trim($path, '/');
$segments = explode('/', $path);
$endpoint = $segments[0];

// Enrutamiento simple
switch ($endpoint) {
    case 'login':
        require_once __DIR__ . '/controllers/AuthController.php';
        $controller = new AuthController();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $controller->login();
        } else {
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Método no permitido."]);
        }
        break;

    case 'logout':
        require_once __DIR__ . '/controllers/AuthController.php';
        $controller = new AuthController();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') $controller->logout();
        break;

    case 'auth':
        require_once __DIR__ . '/controllers/AuthController.php';
        $controller = new AuthController();
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $action = $segments[1] ?? '';
            if ($action == 'status') {
                $controller->status();
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Auth action not found."]);
            }
        }
        break;

    case 'data':
        require_once __DIR__ . '/controllers/DataController.php';
        $controller = new DataController();
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $action = $segments[1] ?? '';
            if ($action == 'form-init') {
                $controller->getFormInitData();
            }
        }
        break;

    case 'workorders':
        require_once __DIR__ . '/controllers/WorkOrderController.php';
        require_once __DIR__ . '/controllers/WorkOrderPdfController.php';
        $controller = new WorkOrderController();
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $segments[1] ?? '';
        $id = $segments[2] ?? null;

        if ($method == 'GET') {
            if ($action == 'all') {
                $controller->listAll(); // < Tu función
            } elseif ($action == 'assigned') {
                $controller->listAssigned(); // < Tu función
            } elseif ($action == 'next-number') {
                $controller->getNextWorkOrderNumber();
            } elseif ($action == 'details' && $id) {
                $controller->getDetails($id); // < Tu función
            } elseif ($action == 'pdf' && $id) {
                $pdfController = new WorkOrderPdfController();
                $pdfController->generate($id);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Acción 'GET workorders' no encontrada."]);
            }
        } elseif ($method == 'POST') {
            if ($action == 'create') {
                $controller->create();
            } elseif ($action == 'update' && $id) {
                $controller->update($id);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Acción 'POST workorders' no encontrada."]);
            }
        } else {
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Método no permitido para 'workorders'."]);
        }
        break;

    // --- NUEVO CASO PARA SUBIDA DE ARCHIVOS (FILEPOND) ---
    case 'file-upload':
        require_once __DIR__ . '/controllers/FileController.php';
        $controller = new FileController();

        $action = $segments[1] ?? '';

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'process') {
            $controller->processUpload(); // Sube archivo temporal
        } elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE' && $action == 'revert') {
            $controller->processRevert(); // Elimina archivo temporal
        } else {
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Método no permitido para esta acción."]);
        }
        break;

    // ==========================================================
    // --- INICIO: NUEVAS RUTAS CRUD AÑADIDAS ---
    // ==========================================================

    case 'customers':
        require_once __DIR__ . '/controllers/CustomerController.php';
        $controller = new CustomerController();
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $segments[1] ?? '';
        $id = $segments[2] ?? null;

        if ($method == 'GET' && empty($action)) {
            $controller->readAll(); // GET /api/customers
        } elseif ($method == 'GET' && $action == 'details' && $id) {
            $controller->readOne($id); // GET /api/customers/details/{id}
        } elseif ($method == 'POST' && $action == 'create') {
            $controller->create(); // POST /api/customers/create
        } elseif ($method == 'POST' && $action == 'update' && $id) {
            $controller->update($id); // POST /api/customers/update/{id}
        } elseif ($method == 'DELETE' && $action == 'delete' && $id) {
            // Usamos DELETE ya que file-upload lo usa y tu servidor lo soporta
            $controller->delete($id); // DELETE /api/customers/delete/{id}
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Acción 'customers' no encontrada."]);
        }
        break;

    case 'users':
        require_once __DIR__ . '/controllers/UserController.php';
        $controller = new UserController();
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $segments[1] ?? '';
        $id = $segments[2] ?? null;

        if ($method == 'GET' && empty($action)) {
            $controller->readAll(); // GET /api/users
        } elseif ($method == 'POST' && $action == 'create') {
            $controller->create(); // POST /api/users/create
        } elseif ($method == 'POST' && $action == 'update' && $id) {
            $controller->update($id); // POST /api/users/update/{id}
        } elseif ($method == 'DELETE' && $action == 'delete' && $id) {
            $controller->delete($id); // DELETE /api/users/delete/{id}
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Acción 'users' no encontrada."]);
        }
        break;

    case 'categories':
        require_once __DIR__ . '/controllers/CategoryController.php';
        $controller = new CategoryController();
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $segments[1] ?? '';
        $type = $segments[2] ?? null; // 'category' o 'subcategory'
        $id = $segments[3] ?? null;   // ID del item

        if ($method == 'GET' && empty($action)) {
            $controller->readAll(); // GET /api/categories
        } elseif ($method == 'POST' && $action == 'create') {
            $controller->create(); // POST /api/categories/create
        } elseif ($method == 'POST' && $action == 'update' && $type && $id) {
            $controller->update($type, $id); // POST /api/categories/update/{type}/{id}
        } elseif ($method == 'DELETE' && $action == 'delete' && $type && $id) {
            $controller->delete($type, $id); // DELETE /api/categories/delete/{type}/{id}
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Acción 'categories' no encontrada."]);
        }
        break;

    // ==========================================================
    // --- FIN: NUEVAS RUTAS CRUD ---
    // ==========================================================

    default:
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Endpoint no encontrado."]);
        break;
}
