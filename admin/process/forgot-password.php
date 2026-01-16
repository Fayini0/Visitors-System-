<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    require_once '../../config/database.php';

    $email = trim($_POST['email']);

    if (empty($email)) {
        $_SESSION['forgot_error'] = "Email is required";
        header('Location: ../login.php');
        exit();
    }

    try {
        $database = new Database();
        $db = $database->getConnection();

        // Check if user exists with admin role
        $query = "SELECT u.user_id, u.email, u.first_name, u.last_name, r.is_admin
                  FROM users u
                  JOIN roles r ON u.role_id = r.role_id
                  WHERE u.email = ? AND u.is_active = 1 AND r.is_admin = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Update user with reset token
            $updateQuery = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->execute([$token, $expires, $user['user_id']]);

            // Send email (assuming SMTP is configured)
            $resetLink = "http://localhost/sophen-residence-system/admin/reset-password.php?token=" . $token;
            $subject = "Password Reset Request";
            $message = "Hello " . $user['first_name'] . ",\n\nYou have requested to reset your password. Click the link below to reset it:\n\n" . $resetLink . "\n\nThis link will expire in 1 hour.\n\nIf you did not request this, please ignore this email.";
            $headers = "From: noreply@sophen.com";

            if (mail($user['email'], $subject, $message, $headers)) {
                $_SESSION['forgot_success'] = "A password reset link has been sent to your email.";
            } else {
                $_SESSION['forgot_error'] = "Failed to send email. Please try again later.";
            }
        } else {
            $_SESSION['forgot_error'] = "No admin account found with that email address.";
        }
    } catch (Exception $e) {
        $_SESSION['forgot_error'] = "System error. Please try again later.";
        error_log("Forgot password error: " . $e->getMessage());
    }

    header('Location: ../login.php');
    exit();
} else {
    header('Location: ../login.php');
    exit();
}
?>
