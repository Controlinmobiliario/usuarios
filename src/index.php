<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$allowed_origins = ['https://mi-frontend.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config/database.php';
require_once 'models/User.php';
require_once 'controllers/UserController.php';
require_once 'middleware/Auth.php';

// Simple router
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_method = $_SERVER['REQUEST_METHOD'];

// Remove base path if exists
$base_path = '';
if (!empty($base_path)) {
    $request_uri = str_replace($base_path, '', $request_uri);
}

$path_parts = explode('/', trim($request_uri, '/'));

try {
    $database = new Database();
    $db = $database->getConnection();
    $userController = new UserController($db);

    // Route handling
    switch ($request_method) {
        case 'GET':
            if ($path_parts[0] === 'users') {
                if (isset($path_parts[1])) {
                    // GET /users/{id}
                    $userController->getUser($path_parts[1]);
                } else {
                    // GET /users (requires auth)
                    Auth::authenticate($db);
                    $userController->getUsers();
                }
            } elseif ($path_parts[0] === 'me') {
                // GET /me (requires auth)
                $user = Auth::authenticate($db);
                $userController->getCurrentUser($user['id']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;

        case 'POST':
            if ($path_parts[0] === 'register') {
                $userController->register();
            } elseif ($path_parts[0] === 'login') {
                $userController->login();
            } elseif ($path_parts[0] === 'logout') {
                Auth::authenticate($db);
                $userController->logout();
            } elseif ($path_parts[0] === 'refresh') {
                $userController->refreshToken();
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;

        case 'PUT':
            if ($path_parts[0] === 'users' && isset($path_parts[1])) {
                // PUT /users/{id} (requires auth)
                $user = Auth::authenticate($db);
                $userController->updateUser($path_parts[1], $user);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;

        case 'DELETE':
            if ($path_parts[0] === 'users' && isset($path_parts[1])) {
                // DELETE /users/{id} (requires auth)
                $user = Auth::authenticate($db);
                $userController->deleteUser($path_parts[1], $user);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
