<?php
ob_start();
/**
 * Dynamic API Gateway
 */
define('API_MODE', true);

// Enable Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

$controller_name = $_GET['controller'] ?? '';
$action = $_GET['action'] ?? 'index';

if (empty($controller_name)) {
    json_response(['success' => false, 'message' => 'Controller name is required'], 400);
}

// Map controller name to file
$controller_file = __DIR__ . '/controllers/' . ucfirst($controller_name) . 'Controller.php';
$class_name = ucfirst($controller_name) . 'Controller';

if (!file_exists($controller_file)) {
    json_response(['success' => false, 'message' => "Controller '$controller_name' not found"], 404);
}

require_once $controller_file;

if (!class_exists($class_name)) {
    json_response(['success' => false, 'message' => "Class '$class_name' not found"], 500);
}

$controller = new $class_name();

if (!method_exists($controller, $action)) {
    json_response(['success' => false, 'message' => "Action '$action' not found in $class_name"], 404);
}

try {
    // Execute action
    $controller->$action();
} catch (Exception $e) {
    json_response([
        'success' => false,
        'message' => 'Server Error: ' . $e->getMessage()
    ], 500);
}
