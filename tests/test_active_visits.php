<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$query = "SELECT vi.id_number, vi.first_name, vi.last_name, v.actual_checkin, r.room_number, v.host_name
          FROM visits v
          INNER JOIN visitors vi ON v.visitor_id = vi.visitor_id
          LEFT JOIN rooms r ON v.room_id = r.room_id
          WHERE v.visit_status = 'checked_in'";

$stmt = $db->prepare($query);
$stmt->execute();
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Active Visits:\n";
foreach ($visits as $visit) {
    echo "ID: {$visit['id_number']}, Name: {$visit['first_name']} {$visit['last_name']}, Room: {$visit['room_number']}, Host: {$visit['host_name']}\n";
}
?>
