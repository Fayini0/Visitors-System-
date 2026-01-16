<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Create a test visitor if not exists
$query = "INSERT IGNORE INTO visitors (visitor_id, first_name, last_name, id_number, email, phone, created_at)
          VALUES (1, 'John', 'Doe', '9001015009088', 'john.doe@example.com', '1234567890', NOW())";

$db->exec($query);

// Create a test room if not exists
$query = "INSERT IGNORE INTO rooms (room_id, room_number, room_type_id, is_occupied, created_at)
          VALUES (1, 'A101', 1, TRUE, NOW())";

$db->exec($query);

// Create a test resident if not exists
$query = "INSERT IGNORE INTO residents (resident_id, first_name, last_name, email, phone, room_id, created_at)
          VALUES (1, 'Jane', 'Smith', 'jane.smith@example.com', '0987654321', 1, NOW())";

$db->exec($query);

// Create an active visit
$query = "INSERT INTO visits (visitor_id, room_id, host_name, purpose, visit_status, expected_checkin, expected_checkout, actual_checkin, created_at)
          VALUES (1, 1, 'Jane Smith', 'Meeting', 'checked_in', NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW(), NOW())";

$db->exec($query);

echo "Test active visit created for visitor ID: 9001015009088\n";
?>
