<?php
session_start();

// Check for "Remember me" cookie
if (isset($_COOKIE['admin_remember']) && !isset($_SESSION['user_id'])) {
    // Restore session from cookie
    session_id($_COOKIE['admin_remember']);
    session_start();

    // Verify the session is still valid (check database)
    if (isset($_SESSION['user_id']) && $_SESSION['role_id'] == 1) {
        header('Location: index.php');
        exit();
    } else {
        // Invalid cookie, delete it
        setcookie('admin_remember', '', time() - 3600, '/', '', true, true);
    }
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && $_SESSION['role_id'] == 1) {
    header('Location: index.php');
    exit();
}

// Get error from session if set
$error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : null;
unset($_SESSION['login_error']); // Clear the error after displaying
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Sophen Residence</title>
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

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .admin-icon {
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

        .admin-icon i {
            font-size: 40px;
            color: white;
        }

        .login-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-subtitle {
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

        .login-button {
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

        .login-button:hover:not(:disabled) {
            background: #7d3c98;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(142, 68, 173, 0.4);
        }

        .login-button:disabled {
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

        .remember-me {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 15px 0;
            font-size: 0.9rem;
        }

        .form-check {
            display: flex;
            align-items: center;
        }

        .form-check-input {
            margin-right: 8px;
        }

        .forgot-password {
            color: var(--admin-color);
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-password:hover {
            color: #7d3c98;
            text-decoration: underline;
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
            .login-container {
                margin: 20px;
                padding: 30px 25px;
            }

            .login-title {
                font-size: 1.5rem;
            }

            .admin-icon {
                width: 70px;
                height: 70px;
            }

            .admin-icon i {
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
    <div class="login-container animate-entrance">
        <div class="login-header">
            <div class="admin-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <h1 class="login-title">Administrator</h1>
            <p class="login-subtitle">Sophen Residence Management System</p>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['forgot_success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['forgot_success']) ?>
        </div>
        <?php unset($_SESSION['forgot_success']); endif; ?>

        <?php if (isset($_SESSION['forgot_error'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($_SESSION['forgot_error']) ?>
        </div>
        <?php unset($_SESSION['forgot_error']); endif; ?>

        

        <form id="loginForm" method="POST" action="process/login-process.php">
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required 
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                <i class="fas fa-user input-icon"></i>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <i class="fas fa-eye password-toggle input-icon" id="passwordToggle"></i>
            </div>

            <div class="remember-me">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                    <label class="form-check-label" for="rememberMe">Remember me</label>
                </div>
                <a href="#" class="forgot-password" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Forgot password?</a>
            </div>

            <button type="submit" class="login-button" id="loginBtn">
                <i class="fas fa-sign-in-alt me-2"></i>
                Sign In
            </button>
        </form>

        <div class="back-link">
            <a href="../index.php">
                <i class="fas fa-arrow-left me-1"></i>
                Back to Home
            </a>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="forgotPasswordModalLabel">Forgot Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Enter your email address and we'll send you a link to reset your password.</p>
                    <form id="forgotPasswordForm" method="POST" action="process/forgot-password.php">
                        <div class="mb-3">
                            <label for="forgotEmail" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="forgotEmail" name="email" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Password visibility toggle
            $('#passwordToggle').click(function() {
                const passwordField = $('#password');
                const icon = $(this);
                
                if (passwordField.attr('type') === 'password') {
                    passwordField.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordField.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Form submission with loading state
            $('#loginForm').submit(function() {
                const button = $('#loginBtn');
                const originalText = button.html();
                
                button.prop('disabled', true)
                      .html('<div class="loading-spinner"></div>Signing in...');
                
                // Re-enable button after 10 seconds in case of issues
                setTimeout(() => {
                    button.prop('disabled', false).html(originalText);
                }, 10000);
            });

            

            // Enhanced form validation
            $('input[required]').blur(function() {
                if (!$(this).val()) {
                    $(this).css('border-color', 'var(--danger-color)');
                } else {
                    $(this).css('border-color', '');
                }
            });

            // Clear validation on input
            $('input').on('input', function() {
                $(this).css('border-color', '');
            });

            // Focus first empty field
            if (!$('#username').val()) {
                $('#username').focus();
            } else {
                $('#password').focus();
            }
        });
    </script>
</body>
</html>