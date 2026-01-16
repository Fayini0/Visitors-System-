<?php
require_once 'config/database.php';

$db = (new Database())->getConnection();
$stmt = $db->prepare('SELECT * FROM visits WHERE visit_id = ?');
$stmt->execute([9]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Visit data:\n";
print_r($visit);

echo "\nChecking resident for room_id {$visit['room_id']} and host_name '{$visit['host_name']}'\n";
$stmt = $db->prepare("SELECT * FROM residents WHERE room_id = ? AND CONCAT(first_name, ' ', last_name) = ? AND is_active = TRUE");
$stmt->execute([$visit['room_id'], $visit['host_name']]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Resident data:\n";
print_r($resident);
?>
