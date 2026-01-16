<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get all active visits (checked_in or approved)
    $query = "SELECT
                v.visit_id,
                v.visitor_id,
                vis.name as visitor_name,
                v.room_number,
                v.host_name,
                v.actual_checkin,
                TIMESTAMPDIFF(MINUTE, v.actual_checkin, NOW()) as duration_minutes
              FROM visits v
              JOIN visitors vis ON v.visitor_id = vis.id_number
              WHERE v.visit_status = 'checked_in'
              ORDER BY v.actual_checkin ASC";

    $stmt = $db->prepare($query);
    $stmt->execute();

    $activeVisitors = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activeVisitors[] = [
            'visit_id' => $row['visit_id'],
            'visitor_id' => $row['visitor_id'],
            'visitor_name' => $row['visitor_name'],
            'room_number' => $row['room_number'],
            'host_name' => $row['host_name'],
            'checkin_time' => $row['actual_checkin'],
            'duration' => $row['duration_minutes']
        ];
    }

    echo json_encode([
        'success' => true,
        'visitors' => $activeVisitors
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load active visitors: ' . $e->getMessage()
    ]);
}
?>
