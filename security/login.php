<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && $_SESSION['role_id'] == 2) {
    header('Location: index.php');
    exit();
}

// Handle login form submission
if ($_POST && isset($_POST['username']) && isset($_POST['password'])) {
    require_once '../config/database.php';
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
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
            $logQuery = "INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, 'security_login', ?)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->execute([$user['user_id'], $_SERVER['REMOTE_ADDR']]);
            
            header('Location: index.php');
            exit();
        } else {
            $error = "Invalid username or password";
        }
    } catch (Exception $e) {
        $error = "Login system temporarily unavailable";
        error_log("Security login error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Login - Sophen Residence</title>
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
            --security-color: #e67e22;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
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

        .security-icon {
            width: 80px;
            height: 80px;
            background: var(--security-color);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(230, 126, 34, 0.3);
        }

        .security-icon i {
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

        .shift-info {
            background: rgba(230, 126, 34, 0.1);
            border: 1px solid var(--security-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .shift-info h6 {
            color: var(--security-color);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .shift-info p {
            margin: 0;
            color: var(--dark-gray);
            font-size: 0.85rem;
        }

        .current-time {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--security-color);
            margin: 5px 0;
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
            border-color: var(--security-color);
            box-shadow: 0 0 0 0.2rem rgba(230, 126, 34, 0.25);
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
            color: var(--security-color);
        }

        .login-button {
            width: 100%;
            padding: 15px;
            background: var(--security-color);
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
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(230, 126, 34, 0.4);
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
            color: var(--security-color);
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

            .security-icon {
                width: 70px;
                height: 70px;
            }

            .security-icon i {
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
            <div class="security-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="login-title">Security Portal</h1>
            <p class="login-subtitle">Sophen Residence Security System</p>
        </div>

        <div class="shift-info">
            <h6><i class="fas fa-clock me-2"></i>Current Time</h6>
            <div class="current-time" id="currentTime"></div>
            <p>Your shift will begin once you log in</p>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        

        <form id="loginForm" method="POST" action="">
            <div class="form-group">
                <label for="username" class="form-label">Security ID / Username</label>
                <input type="text" class="form-control" id="username" name="username" required 
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                <i class="fas fa-user input-icon"></i>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <i class="fas fa-eye password-toggle input-icon" id="passwordToggle"></i>
            </div>

            <button type="submit" class="login-button" id="loginBtn">
                <i class="fas fa-sign-in-alt me-2"></i>
                Start Shift
            </button>
        </form>

        <div class="back-link">
            <a href="../index.php">
                <i class="fas fa-arrow-left me-1"></i>
                Back to Home
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Update time every second
            updateTime();
            setInterval(updateTime, 1000);

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
                      .html('<div class="loading-spinner"></div>Starting shift...');
                
                // Re-enable button after 10 seconds in case of issues
                setTimeout(() => {
                    button.prop('disabled', false).html(originalText);
                }, 10000);
            });

            

            // Focus first empty field
            if (!$('#username').val()) {
                $('#username').focus();
            } else {
                $('#password').focus();
            }
        });

        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-ZA', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            $('#currentTime').text(timeString);
        }
    </script>
</body>
</html>