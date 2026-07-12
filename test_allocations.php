<?php
require 'backend/config/db.php';

// Mock session for testing
session_start();
$_SESSION['user_id'] = 1;

// Call AllocationController@getAll directly?
// Better to just test via HTTP request to localhost:
$ch = curl_init('http://localhost/Enterprise%20Asset%20&%20Resource%20Management%20System/backend/index.php/api/allocations');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
$response = curl_exec($ch);
echo "GET /api/allocations Response:\n" . $response . "\n";

$ch2 = curl_init('http://localhost/Enterprise%20Asset%20&%20Resource%20Management%20System/backend/index.php/api/allocations/transfers');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
$resp2 = curl_exec($ch2);
echo "GET /api/allocations/transfers Response:\n" . $resp2 . "\n";
