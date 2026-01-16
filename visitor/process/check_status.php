<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['visit_id'])) {
    $visit_id = intval($_POST['visit_id']);

    $query = "SELECT v.visit_id, v.visit_status, v.room_id, v.host_name, v.created_at as submit_time,
                     r.room_number,
                     vi.visitor_id, vi.first_name, vi.last_name, vi.id_number, vi.email,
                     COUNT(v2.visit_id) as total_visits
              FROM visits v
              JOIN rooms r ON v.room_id = r.room_id
              JOIN visitors vi ON v.visitor_id = vi.visitor_id
              LEFT JOIN visits v2 ON vi.visitor_id = v2.visitor_id
              WHERE v.visit_id = ?
              GROUP BY v.visit_id, v.visit_status, v.room_id, v.host_name, v.created_at,
                       r.room_number, vi.visitor_id, vi.first_name, vi.last_name, vi.id_number, vi.email";

    $stmt = $db->prepare($query);
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($visit) {
        $visit['visitor_name'] = $visit['first_name'] . ' ' . $visit['last_name'];
        $visit['visitor_id'] = $visit['id_number'];  // ID Number field
        $visit['visitor_email'] = $visit['email'];   // Email field
        unset($visit['first_name'], $visit['last_name'], $visit['id_number'], $visit['email']); // Clean up unnecessary fields

        echo json_encode(['success' => true, 'visit' => $visit]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Visit not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
