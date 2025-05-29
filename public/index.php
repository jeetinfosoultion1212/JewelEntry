<?php
// Load configuration
$config = require_once __DIR__ . '/../app/config/App.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Initialize application
\App\Config\App::init();

// Basic routing
$request = $_SERVER['REQUEST_URI'];
$basePath = '/JewelEntry/public';
$request = str_replace($basePath, '', $request);

// Parse the request
$parts = explode('/', trim($request, '/'));
$controller = ucfirst($parts[0] ?? 'home') . 'Controller';
$action = $parts[1] ?? 'index';
$params = array_slice($parts, 2);

// Load the controller
$controllerClass = "\\App\\Controllers\\{$controller}";
if (class_exists($controllerClass)) {
    $controllerInstance = new $controllerClass();
    if (method_exists($controllerInstance, $action)) {
        try {
            echo call_user_func_array([$controllerInstance, $action], $params);
        } catch (\Exception $e) {
            http_response_code(500);
            echo "Error: " . $e->getMessage();
        }
    } else {
        http_response_code(404);
        require __DIR__ . '/../app/views/404.php';
    }
} else {
    http_response_code(404);
    require __DIR__ . '/../app/views/404.php';
} 