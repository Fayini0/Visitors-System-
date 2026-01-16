<?php
session_start();

function processLogin() {
    if (isset($_SESSION['user_id']) && $_SESSION['role_id'] == 1) {
        header('Location: ../index.php');
        exit();
    }

    $result = ['success' => false, 'error' => null];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
        require_once '../../config/database.php';

        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $result['error'] = "Username and password are required";
            return $result;
        }

        try {
            $database = new Database();
            $db = $database->getConnection();

            // Get user with admin role
            $query = "SELECT u.*, r.role_name FROM users u
                      JOIN roles r ON u.role_id = r.role_id
                      WHERE u.username = ? AND u.is_active = 1 AND r.is_admin = 1";
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

                // Handle "Remember me" functionality
                if (isset($_POST['remember_me']) && $_POST['remember_me'] == 'on') {
                    // Set a cookie that expires in 30 days
                    setcookie('admin_remember', session_id(), time() + (30 * 24 * 60 * 60), '/', '', true, true);
                }

                // Update last login
                $updateQuery = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->execute([$user['user_id']]);

                // Log activity
                $logQuery = "INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, 'login', ?)";
                $logStmt = $db->prepare($logQuery);
                $logStmt->execute([$user['user_id'], $_SERVER['REMOTE_ADDR']]);

                $result['success'] = true;
                return $result;
            } else {
                $result['error'] = "Invalid username or password";
                return $result;
            }
        } catch (Exception $e) {
            $result['error'] = "Login system temporarily unavailable";
            error_log("Login error: " . $e->getMessage());
            return $result;
        }
    }

    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginResult = processLogin();
    if ($loginResult['success']) {
        header('Location: ../index.php');
        exit();
    } else {
        // Store error in session to display on login page
        $_SESSION['login_error'] = $loginResult['error'];
        header('Location: ../login.php');
        exit();
    }
}
?>
