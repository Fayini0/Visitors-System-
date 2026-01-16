<?php
session_start();
require_once '../../includes/functions.php';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = 'Please enter both username and password.';
        header('Location: ../login.php');
        exit();
    }

    try {
        $database = new Database();
        $db = $database->getConnection();

        // Get user with security role
        $query = "SELECT u.*, r.role_name FROM users u
                  JOIN roles r ON u.role_id = r.role_id
                  WHERE u.username = ? AND u.is_active = 1 AND r.role_name = 'Security'";
        $stmt = $db->prepare($query);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['shift_start'] = time(); // Track when shift started

            // Update last login
            $updateQuery = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->execute([$user['user_id']]);

            // Log activity
            log_activity($user['user_id'], 'security_login', 'Security officer logged in');

            header('Location: ../index.php');
            exit();
        } else {
            $_SESSION['login_error'] = 'Invalid username or password.';
            header('Location: ../login.php');
            exit();
        }
    } catch (Exception $e) {
        error_log("Security login error: " . $e->getMessage());
        $_SESSION['login_error'] = 'Login system temporarily unavailable. Please try again later.';
        header('Location: ../login.php');
        exit();
    }
} else {
    // If not POST, redirect to login
    header('Location: ../login.php');
    exit();
}
?>
