<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed!");
}

echo "Checking database...\n";

// Check visits table
$stmt = $db->query("SELECT COUNT(*) as count FROM visits");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total visits: " . $result['count'] . "\n";

// Check if visit_id 9 exists
$stmt = $db->prepare("SELECT * FROM visits WHERE visit_id = ?");
$stmt->execute([9]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if ($visit) {
    echo "Visit ID 9 found:\n";
    print_r($visit);
} else {
    echo "Visit ID 9 not found.\n";
}

// Check residents
$stmt = $db->query("SELECT COUNT(*) as count FROM residents");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total residents: " . $result['count'] . "\n";

// Check rooms
$stmt = $db->query("SELECT COUNT(*) as count FROM rooms");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total rooms: " . $result['count'] . "\n";
?>
