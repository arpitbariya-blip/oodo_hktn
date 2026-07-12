<?php
$routes = [
    'GET /api/dashboard/kpis' => 'DashboardController@getKpis',
    'GET /api/dashboard/overdue' => 'DashboardController@getOverdue',
    'GET /api/dashboard/alerts' => 'DashboardController@getAlerts',
];
