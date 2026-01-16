<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_number'])) {
    $id_number = trim($_POST['id_number']);

    if (empty($id_number)) {
        echo json_encode(['error' => 'ID number is required']);
        exit;
    }

    // Check if visitor exists
    $query = "SELECT visitor_id, first_name, last_name, email, phone, is_blocked, visit_count, last_visit_date, id_number
              FROM visitors
              WHERE id_number = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id_number]);
    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($visitor) {
        // Check if visitor is blocked
        if ($visitor['is_blocked']) {
            echo json_encode([
                'exists' => true,
                'blocked' => true,
                'message' => 'This visitor is currently blocked from visiting'
            ]);
        } else {
            echo json_encode([
                'exists' => true,
                'blocked' => false,
                'visitor' => [
                    'visitor_id' => $visitor['visitor_id'],
                    'first_name' => $visitor['first_name'],
                    'last_name' => $visitor['last_name'],
                    'full_name' => $visitor['first_name'] . ' ' . $visitor['last_name'],
                    'id_number' => $visitor['id_number'],
                    'email' => $visitor['email'],
                    'phone' => $visitor['phone'],
                    'visit_count' => $visitor['visit_count'],
                    'last_visit_date' => $visitor['last_visit_date']
                ]
            ]);
        }
    } else {
        echo json_encode([
            'exists' => false,
            'message' => 'Visitor not found in system'
        ]);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>
