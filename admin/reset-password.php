<?php
session_start();

$token = $_GET['token'] ?? null;
$error = null;
$success = null;

if (!$token) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['confirm_password'])) {
    require_once '../config/database.php';

    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($password) || empty($confirmPassword)) {
        $error = "Both password fields are required";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();

            // Verify token and get user
            $query = "SELECT user_id, first_name FROM users WHERE reset_token = ? AND reset_expires > NOW() AND is_active = 1";
            $stmt = $db->prepare($query);
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Hash new password
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Update password and clear token
                $updateQuery = "UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->execute([$passwordHash, $user['user_id']]);

                $success = "Password has been reset successfully. You can now log in with your new password.";
            } else {
                $error = "Invalid or expired reset token";
            }
        } catch (Exception $e) {
            $error = "System error. Please try again later.";
            error_log("Reset password error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Sophen Residence</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-gray: #ecf0f1;
            --dark-gray: #34495e;
            --admin-color: #8e44ad;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .reset-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .reset-icon {
            width: 80px;
            height: 80px;
            background: var(--admin-color);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(142, 68, 173, 0.3);
        }

        .reset-icon i {
            font-size: 40px;
            color: white;
        }

        .reset-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .reset-subtitle {
            color: var(--dark-gray);
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-label {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 45px 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--admin-color);
            box-shadow: 0 0 0 0.2rem rgba(142, 68, 173, 0.25);
            outline: none;
        }

        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-gray);
            opacity: 0.7;
        }

        .password-toggle {
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--admin-color);
        }

        .reset-button {
            width: 100%;
            padding: 15px;
            background: var(--admin-color);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .reset-button:hover:not(:disabled) {
            background: #7d3c98;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(142, 68, 173, 0.4);
        }

        .reset-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 12px 15px;
            font-size: 0.9rem;
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .back-link a {
            color: var(--dark-gray);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: var(--admin-color);
        }

        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #ffffff;
            animation: spin 1s ease-in-out infinite;
            display: inline-block;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .reset-container {
                margin: 20px;
                padding: 30px 25px;
            }

            .reset-title {
                font-size: 1.5rem;
            }

            .reset-icon {
                width: 70px;
                height: 70px;
            }

            .reset-icon i {
                font-size: 35px;
            }
        }

        .animate-entrance {
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="reset-container animate-entrance">
        <div class="reset-header">
            <div class="reset-icon">
                <i class="fas fa-key"></i>
            </div>
            <h1 class="reset-title">Reset Password</h1>
            <p class="reset-subtitle">Enter your new password below</p>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($success) ?>
        </div>
        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left me-1"></i>
                Back to Login
            </a>
        </div>
        <?php exit(); ?>
        <?php endif; ?>

        <form id="resetForm" method="POST" action="">
            <div class="form-group">
                <label for="password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                <i class="fas fa-eye password-toggle input-icon" id="passwordToggle"></i>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required minlength="8">
                <i class="fas fa-eye password-toggle input-icon" id="confirmPasswordToggle"></i>
            </div>

            <button type="submit" class="reset-button" id="resetBtn">
                <i class="fas fa-save me-2"></i>
                Reset Password
            </button>
        </form>

        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left me-1"></i>
                Back to Login
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
            // Password visibility toggle
            $('#passwordToggle, #confirmPasswordToggle').click(function() {
                const targetId = $(this).attr('id') === 'passwordToggle' ? '#password' : '#confirmPassword';
                const icon = $(this);

                if ($(targetId).attr('type') === 'password') {
                    $(targetId).attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    $(targetId).attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Form submission with loading state
            $('#resetForm').submit(function() {
                const button = $('#resetBtn');
                const originalText = button.html();

                button.prop('disabled', true)
                      .html('<div class="loading-spinner"></div>Resetting...');

                // Re-enable button after 10 seconds in case of issues
                setTimeout(() => {
                    button.prop('disabled', false).html(originalText);
                }, 10000);
            });

            // Password strength indicator
            $('#password').on('input', function() {
                const password = $(this).val();
                if (password.length >= 8) {
                    $(this).css('border-color', 'var(--success-color)');
                } else {
                    $(this).css('border-color', 'var(--danger-color)');
                }
            });

            // Confirm password match
            $('#confirmPassword').on('input', function() {
                const password = $('#password').val();
                const confirmPassword = $(this).val();
                if (password === confirmPassword && confirmPassword.length > 0) {
                    $(this).css('border-color', 'var(--success-color)');
                } else {
                    $(this).css('border-color', 'var(--danger-color)');
                }
            });
        });
    </script>
</body>
