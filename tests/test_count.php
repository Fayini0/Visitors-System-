<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('SELECT COUNT(*) as count FROM visits');
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo 'Total visits: ' . $result['count'];
?>
