<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$db->exec("ALTER TABLE visits MODIFY visit_status ENUM('pending', 'approved', 'declined', 'checked_in', 'checked_out', 'overstayed') DEFAULT 'pending'");
echo 'Schema updated successfully.';
?>
