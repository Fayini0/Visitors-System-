<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('SELECT visit_id FROM visits WHERE visit_status = "pending" LIMIT 1');
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo $result ? $result['visit_id'] : 'No pending visit found';
?>
