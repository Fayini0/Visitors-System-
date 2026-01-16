<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_number'])) {
    $room_number = trim($_POST['room_number']);

$query = "SELECT CONCAT(first_name, ' ', last_name) as full_name FROM residents r
              INNER JOIN rooms rm ON r.room_id = rm.room_id
              WHERE rm.room_number = ? AND r.is_active = TRUE
              ORDER BY full_name";
    $stmt = $db->prepare($query);
    $stmt->execute([$room_number]);
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($residents);
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>
