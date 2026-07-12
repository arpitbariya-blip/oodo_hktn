<?php
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/utils/Response.php';

// A simple way to handle routing without hardcoding the base path:
$scriptName = $_SERVER['SCRIPT_NAME']; // e.g. /Enterprise Asset & Resource Management System/backend/index.php
$baseDir = dirname($scriptName); 

$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
// Remove baseDir from path
if (strpos($path, $baseDir) === 0) {
    $route = substr($path, strlen($baseDir));
} else {
    $route = $path;
}

// Remove trailing slash
$route = rtrim($route, '/');
if (empty($route)) {
    $route = '/';
}

$method = $_SERVER['REQUEST_METHOD'];

// Load routes
require_once __DIR__ . '/routes/api.php';

// Dispatch
dispatch($method, $route);

function dispatch($method, $route) {
    global $routes;
    
    // Simple exact match routing for now
    foreach ($routes as $pattern => $handler) {
        list($routeMethod, $routePath) = explode(' ', $pattern, 2);
        if ($routeMethod === $method && $routePath === $route) {
            list($controllerName, $action) = explode('@', $handler);
            require_once __DIR__ . "/controllers/{$controllerName}.php";
            $controller = new $controllerName();
            $controller->$action();
            return;
        }
    }
    
    Response::error("Route not found: $method $route", 404);
}
