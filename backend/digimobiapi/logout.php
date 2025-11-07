<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_err(405, 'Method not allowed');
}

// Destroy session
session_destroy();

json_ok(['message' => 'Logged out successfully']);
?>

