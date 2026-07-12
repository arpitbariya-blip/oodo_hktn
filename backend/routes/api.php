<?php
$routes = [
    // Auth Routes (Public)
    'POST /api/auth/login' => [
        'middleware' => [],
        'controller' => 'AuthController@login'
    ],
    'POST /api/auth/signup' => [
        'middleware' => [],
        'controller' => 'AuthController@signup'
    ],
    'POST /api/auth/forgot-password' => [
        'middleware' => [],
        'controller' => 'AuthController@forgotPassword'
    ],
    'GET /api/auth/logout' => [
        'middleware' => [],
        'controller' => 'AuthController@logout'
    ],
    'GET /api/auth/me' => [
        'middleware' => [],
        'controller' => 'AuthController@me'
    ],
    
    // Dashboard Routes (Protected)
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
    'POST /api/employees/update-role' => [
        'middleware' => [
            'AuthMiddleware',
            ['RoleMiddleware', ['Admin']] // Only Admins can promote/update roles
        ],
        'controller' => 'EmployeeController@updateRole'
    ]
];
