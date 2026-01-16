<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die('Database connection failed');
}

// Check if visitor with ID '10' exists
$query = "SELECT * FROM visitors WHERE id_number = '10'";
$stmt = $db->prepare($query);
$stmt->execute();
$visitor = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Visitor with ID '10':\n";
var_dump($visitor);

// Check all visits for this visitor
if ($visitor) {
    $query2 = "SELECT * FROM visits WHERE visitor_id = ? ORDER BY created_at DESC";
    $stmt2 = $db->prepare($query2);
    $stmt2->execute([$visitor['visitor_id']]);
    $visits = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo "\nVisits for this visitor:\n";
    var_dump($visits);
}

// Check all checked_in visits
$query3 = "SELECT v.*, vi.id_number, vi.first_name, vi.last_name
           FROM visits v
           INNER JOIN visitors vi ON v.visitor_id = vi.visitor_id
           WHERE v.visit_status = 'checked_in'";

$stmt3 = $db->prepare($query3);
$stmt3->execute();
$checked_in = $stmt3->fetchAll(PDO::FETCH_ASSOC);

echo "\nAll checked_in visits:\n";
var_dump($checked_in);

// Check all visitors to see what IDs exist
$query4 = "SELECT id_number, first_name, last_name FROM visitors LIMIT 10";
$stmt4 = $db->prepare($query4);
$stmt4->execute();
$all_visitors = $stmt4->fetchAll(PDO::FETCH_ASSOC);

echo "\nSample visitors:\n";
var_dump($all_visitors);

// Check visits for the visitor with ID '202207111' (which matches the user's input)
$query5 = "SELECT v.*, vi.id_number, vi.first_name, vi.last_name
           FROM visits v
           INNER JOIN visitors vi ON v.visitor_id = vi.visitor_id
           WHERE vi.id_number = '202207111'
           ORDER BY v.created_at DESC";

$stmt5 = $db->prepare($query5);
$stmt5->execute();
$visits_202207111 = $stmt5->fetchAll(PDO::FETCH_ASSOC);

echo "\nVisits for ID '202207111':\n";
var_dump($visits_202207111);

// Test the updated process_checkout.php GET endpoint
echo "\n\nTesting process_checkout.php GET endpoint:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/sophen-residence-system/visitor/process/process_checkout.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$response = curl_exec($ch);
curl_close($ch);

echo "Response from process_checkout.php GET:\n";
var_dump(json_decode($response, true));
?>
