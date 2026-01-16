<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db) {
    $query = "SELECT COUNT(*) as count FROM rooms r INNER JOIN residents res ON r.room_id = res.room_id WHERE res.is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Connected successfully. Number of rooms with active residents: " . $result['count'];
    
    // Also test residents
    $res_query = "SELECT COUNT(*) as res_count FROM residents WHERE is_active = TRUE";
    $res_stmt = $db->prepare($res_query);
    $res_stmt->execute();
    $res_result = $res_stmt->fetch(PDO::FETCH_ASSOC);
    echo "<br>Number of active residents: " . $res_result['res_count'];
} else {
    echo "Database connection failed";
}
?>
