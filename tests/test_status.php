<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('SELECT visit_id, visit_status FROM visits');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($results as $result) {
    echo 'Visit ID: ' . $result['visit_id'] . ', Status: ' . $result['visit_status'] . "\n";
}
?>
