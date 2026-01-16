<?php
// Simulate POST data for step6.php
$_POST = [
    'full_name' => 'John Doe',
    'id_number' => '123456789',
    'room_number' => '101',
    'host_name' => 'Jane Smith',
    'email' => 'john@example.com',
    'phone' => '1234567890'
];

$_SERVER['REQUEST_METHOD'] = 'POST';

// Include step6.php to test the curl call
include 'visitor/step6.php';
?>
