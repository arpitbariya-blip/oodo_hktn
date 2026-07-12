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
    ],

    // Asset Routes
    'GET /api/assets' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'AssetController@getAll'
    ],
    'GET /api/assets/details' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'AssetController@getDetails'
    ],
    'POST /api/assets' => [
        'middleware' => [
            'AuthMiddleware',
            ['RoleMiddleware', ['Admin', 'Asset Manager']] // Only Admins and Asset Managers can register assets
        ],
        'controller' => 'AssetController@create'
    ],

    // Allocation & Transfer Routes
    'GET /api/allocations' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'AllocationController@getAll'
    ],
    'POST /api/allocations' => [
        'middleware' => [
            'AuthMiddleware',
            ['RoleMiddleware', ['Admin', 'Asset Manager', 'Department Head']]
        ],
        'controller' => 'AllocationController@create'
    ],
    'GET /api/allocations/transfers' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'AllocationController@getTransfers'
    ],
    'POST /api/allocations/transfers/request' => [
        'middleware' => [
            'AuthMiddleware',
            ['RoleMiddleware', ['Admin', 'Asset Manager', 'Department Head']]
        ],
        'controller' => 'AllocationController@requestTransfer'
    ],
    'POST /api/allocations/return' => [
        'middleware' => [
            'AuthMiddleware',
            ['RoleMiddleware', ['Admin', 'Asset Manager']]
        ],
        'controller' => 'AllocationController@returnAsset'
    ],
    'POST /api/allocations/transfers/resolve' => [
        'middleware' => [
            'AuthMiddleware',
            ['RoleMiddleware', ['Admin', 'Asset Manager', 'Department Head']]
        ],
        'controller' => 'AllocationController@resolveTransfer'
    ],

    // Booking Routes
    'GET /api/assets/bookable' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'AssetController@getBookable'
    ],
    'GET /api/bookings/calendar' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'BookingController@getCalendar'
    ],
    'GET /api/bookings/upcoming' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'BookingController@getMyUpcoming'
    ],
    'POST /api/bookings' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'BookingController@create'
    ],
    'POST /api/bookings/cancel' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'BookingController@cancel'
    ],

    // Maintenance Routes
    'GET /api/maintenance' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'MaintenanceController@getAll'
    ],
    'POST /api/maintenance' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'MaintenanceController@create'
    ],
    'POST /api/maintenance/status' => [
        'middleware' => [
            'AuthMiddleware',
            ['RoleMiddleware', ['Admin', 'Asset Manager']]
        ],
        'controller' => 'MaintenanceController@updateStatus'
    ],

    // Audit Routes
    'GET /api/audits/active' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'AuditController@getActive'
    ],
    'POST /api/audits' => [
        'middleware' => [
            'AuthMiddleware',
            ['RoleMiddleware', ['Admin', 'Asset Manager', 'Department Head']]
        ],
        'controller' => 'AuditController@create'
    ],
    'POST /api/audits/items' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'AuditController@updateItem'
    ],
    'POST /api/audits/close' => [
        'middleware' => [
            'AuthMiddleware',
            ['RoleMiddleware', ['Admin', 'Asset Manager']]
        ],
        'controller' => 'AuditController@closeCycle'
    ],

    // Report Routes
    'GET /api/reports/dashboard' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'ReportController@getDashboard'
    ],
    'GET /api/reports/export' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'ReportController@export'
    ],

    // Log & Notification Routes
    'GET /api/logs' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'LogController@getLogs'
    ],
    'GET /api/notifications' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'LogController@getNotifications'
    ],
    'POST /api/notifications/mark-read' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'LogController@markAllRead'
    ],
    'POST /api/logs/seed' => [
        'middleware' => ['AuthMiddleware'],
        'controller' => 'LogController@seedData'
    ]
];
