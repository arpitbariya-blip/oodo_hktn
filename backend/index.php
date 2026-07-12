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

// If the URL explicitly contains /index.php, strip it out
if (strpos($route, '/index.php') === 0) {
    $route = substr($route, 10);
}

// Remove trailing slash
$route = rtrim($route, '/');
if (empty($route)) {
    $route = '/';
}

$method = $_SERVER['REQUEST_METHOD'];

// Load routes
require_once __DIR__ . '/routes/api.php';

// Load Middleware
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/middleware/RoleMiddleware.php';

// Dispatch
dispatch($method, $route);

function dispatch($method, $route) {
    global $routes;
    
    foreach ($routes as $pattern => $config) {
        list($routeMethod, $routePath) = explode(' ', $pattern, 2);
        if ($routeMethod === $method && $routePath === $route) {
            
            // Run Middleware
            if (isset($config['middleware']) && is_array($config['middleware'])) {
                foreach ($config['middleware'] as $mw) {
                    if (is_array($mw)) {
                        // e.g. ['RoleMiddleware', ['Admin']]
                        $mwClass = $mw[0];
                        $args = $mw[1];
                        call_user_func([$mwClass, 'handle'], $args);
                    } else {
                        // e.g. 'AuthMiddleware'
                        call_user_func([$mw, 'handle']);
                    }
                }
            }

            // Run Controller
            $handler = is_array($config) ? $config['controller'] : $config;
            list($controllerName, $action) = explode('@', $handler);
            require_once __DIR__ . "/controllers/{$controllerName}.php";
            $controller = new $controllerName();
            $controller->$action();
            return;
        }
    }
    
    Response::error("Route not found: $method $route", 404);
}
