<?php
$routes = [
    // Auth Routes (Public)
    'POST /api/auth/login' => ['controller' => 'AuthController@login'],
    'POST /api/auth/logout' => ['controller' => 'AuthController@logout'],
    
    // Protected Routes
    'GET /api/auth/me' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'AuthController@me'
    ],
    
    'GET /api/dashboard/kpis' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'DashboardController@getKpis'
    ],
    'GET /api/dashboard/overdue' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'DashboardController@getOverdue'
    ],
    'GET /api/dashboard/alerts' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'DashboardController@getAlerts'
    ],
    
    // Org Setup Routes
    'GET /api/departments' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'DepartmentController@getAll'
    ],
    'GET /api/departments/details' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'DepartmentController@getDetails'
    ],
    'GET /api/categories' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'CategoryController@getAll'
    ],
    'GET /api/employees' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'EmployeeController@getDirectory'
    ],
    'POST /api/employees/promote' => [
        'middleware' => [
            'AuthMiddleware',
            ['RoleMiddleware', ['Admin']] // Only Admins can promote
        ],
        'controller' => 'EmployeeController@promote'
    ]
];
