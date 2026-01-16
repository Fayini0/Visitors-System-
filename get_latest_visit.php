<?php
require_once 'config/database.php';

$db = (new Database())->getConnection();
$stmt = $db->query('SELECT MAX(visit_id) as max_id FROM visits');
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo 'Latest visit_id: ' . $result['max_id'] . PHP_EOL;

// Get the latest visit
$stmt = $db->prepare('SELECT * FROM visits WHERE visit_id = ?');
$stmt->execute([$result['max_id']]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Latest visit:\n";
print_r($visit);
?>
