<?php
// api/index.php

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
        if ($_SERVER['REQUEST_METHOD'] == 'POST') $controller->login();
        else {
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
                $controller->listAll();
            } elseif ($action == 'assigned') {
                $controller->listAssigned();
            } elseif ($action == 'next-number') {
                $controller->getNextWorkOrderNumber();
            } elseif ($action == 'details' && $id) {
                $controller->getDetails($id);
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

    default:
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Endpoint no encontrado."]);
        break;
}
