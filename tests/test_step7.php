<?php
// Simulate POST data for step7.php
$_POST = [
    'visitor_id' => '1',
    'room_number' => 'A101',
    'host_name' => 'Jane Smith',
    'phone' => '1234567890'
];

$_SERVER['REQUEST_METHOD'] = 'POST';

// Include step7.php to test the functionality
include 'visitor/step7.php';
?>
