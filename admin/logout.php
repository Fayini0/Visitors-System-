<?php
session_start();

// Clear the remember me cookie
if (isset($_COOKIE['admin_remember'])) {
    setcookie('admin_remember', '', time() - 3600, '/', '', true, true);
}

// Log the logout activity
if (isset($_SESSION['user_id'])) {
    require_once '../config/database.php';
    try {
        $database = new Database();
        $db = $database->getConnection();

        $logQuery = "INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, 'logout', ?)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        // Log error but don't stop logout
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Destroy the session
session_unset();
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
?>
