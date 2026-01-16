<?php
session_start();
require_once '../includes/functions.php';
require_admin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'default_checkout_time' => sanitize_input($_POST['default_checkout_time']),
        'checkout_alert_minutes' => (int)sanitize_input($_POST['checkout_alert_minutes']),
        'max_visit_duration_hours' => (float)sanitize_input($_POST['max_visit_duration_hours'])
    ];

    if (update_system_settings($settings)) {
        $success_message = "Settings updated successfully!";
        // Log the activity
        log_activity($_SESSION['user_id'], 'settings_updated', 'System settings were updated');
    } else {
        $error_message = "Failed to update settings. Please try again.";
    }
}

// Get current settings
$current_settings = get_system_settings();
if (!$current_settings) {
    $error_message = "Failed to load settings. Please check database connection.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Sophen Residence</title>
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
            background-color: #f8f9fa;
            margin: 0;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--admin-color), #7d3c98);
            width: 280px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-logo {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-logo i {
            font-size: 30px;
            color: white;
        }

        .sidebar-title {
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
        }

        .sidebar-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
            margin: 5px 0 0 0;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            margin: 5px 15px;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9);
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(5px);
        }

        .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }

        .user-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(0, 0, 0, 0.1);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .main-content {
            margin-left: 280px;
            padding: 0;
            min-height: 100vh;
        }

        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .action-btn {
            background: none;
            border: none;
            color: var(--dark-gray);
            font-size: 1.2rem;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .action-btn:hover {
            background: var(--light-gray);
            color: var(--admin-color);
        }

        .settings-content {
            padding: 30px;
        }

        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            background: var(--light-gray);
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
        }

        .card-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 10px;
            color: var(--admin-color);
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--admin-color);
            box-shadow: 0 0 0 0.2rem rgba(142, 68, 173, 0.25);
        }

        .btn-primary {
            background: var(--admin-color);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #7d3c98;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .settings-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .settings-section h5 {
            color: var(--admin-color);
            font-weight: 700;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--admin-color);
            padding-bottom: 8px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .settings-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .settings-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-home"></i>
            </div>
            <h3 class="sidebar-title">Sophen</h3>
            <p class="sidebar-subtitle">Administration Panel</p>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="overview.php" class="nav-link">
                    <i class="fas fa-eye"></i>
                    Overview
                </a>
            </div>
            <div class="nav-item">
                <a href="manage-visitors.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    Manage Visitors
                </a>
            </div>
            <div class="nav-item">
                <a href="manage-residents.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Manage Residents
                </a>
            </div>
            <div class="nav-item">
                <a href="profile.php" class="nav-link">
                    <i class="fas fa-user-cog"></i>
                    Profile Management
                </a>
            </div>
            <div class="nav-item">
                <a href="blocked-visitors.php" class="nav-link">
                    <i class="fas fa-user-slash"></i>
                    Blocked Visitors
                </a>
            </div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Reports
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link active">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </div>
        </nav>

        <div class="user-info">
            <div style="display: flex; align-items: center;">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div style="color: white; font-weight: 600; font-size: 0.9rem;">
                        <?= htmlspecialchars($_SESSION['full_name']) ?>
                    </div>
                    <div style="color: rgba(255,255,255,0.7); font-size: 0.8rem;">
                        <?= htmlspecialchars($_SESSION['role_name']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div style="display: flex; align-items: center;">
                <button class="mobile-menu-btn d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">System Settings</h1>
            </div>
            
            <div class="navbar-actions">
                <button class="action-btn" title="Notifications">
                    <i class="fas fa-bell"></i>
                </button>
                <button class="action-btn" title="Messages">
                    <i class="fas fa-envelope"></i>
                </button>
                <button class="action-btn" title="Settings">
                    <i class="fas fa-cog"></i>
                </button>
                <a href="logout.php" class="action-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="settings-content">
            <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= $error_message ?>
            </div>
            <?php endif; ?>

            <?php if ($current_settings): ?>
            <form method="POST" action="">
                <div class="settings-grid">
                    <!-- General Settings -->
                    <div class="settings-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-sliders-h"></i>
                                General Settings
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="default_checkout_time" class="form-label">Default Checkout Time</label>
                                <input type="time" class="form-control" id="default_checkout_time" name="default_checkout_time" 
                                       value="<?= htmlspecialchars($current_settings['default_checkout_time'] ?? '23:00:00') ?>" required>
                                <small class="form-text text-muted">Default time when visitors should check out</small>
                            </div>

                            <div class="form-group">
                                <label for="checkout_alert_minutes" class="form-label">Checkout Alert Minutes</label>
                                <input type="number" class="form-control" id="checkout_alert_minutes" name="checkout_alert_minutes" 
                                       value="<?= htmlspecialchars($current_settings['checkout_alert_minutes'] ?? 30) ?>" min="1" max="1440" required>
                                <small class="form-text text-muted">Minutes before checkout time to send alerts</small>
                            </div>

                            <div class="form-group">
                                <label for="max_visit_duration_hours" class="form-label">Maximum Visit Duration (Hours)</label>
                                <input type="number" class="form-control" id="max_visit_duration_hours" name="max_visit_duration_hours" 
                                       value="<?= htmlspecialchars($current_settings['max_visit_duration_hours'] ?? 8) ?>" min="1" max="24" step="0.5" required>
                                <small class="form-text text-muted">Maximum allowed duration for a visit</small>
                            </div>
                        </div>
                    </div>

                    
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-lg" style="margin-left: 15px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
            <?php else: ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> Unable to load system settings. Please check your database connection and try again.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Mobile menu toggle
            $('#sidebarToggle').click(function() {
                $('#sidebar').toggleClass('show');
            });

            // Close sidebar when clicking outside on mobile
            $(document).click(function(e) {
                if ($(window).width() <= 768) {
                    if (!$(e.target).closest('#sidebar, #sidebarToggle').length) {
                        $('#sidebar').removeClass('show');
                    }
                }
            });

            // Form validation
            $('form').submit(function(e) {
                let isValid = true;
                
                // Validate required fields
                $(this).find('input[required]').each(function() {
                    if ($(this).val().trim() === '') {
                        $(this).addClass('is-invalid');
                        isValid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });

                // Validate email format
                const emailField = $('#email_from');
                if (emailField.val() && !isValidEmail(emailField.val())) {
                    emailField.addClass('is-invalid');
                    isValid = false;
                } else {
                    emailField.removeClass('is-invalid');
                }

                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields correctly.');
                }
            });

            // Email validation helper
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }

            // Show/hide password toggle
            $('#togglePassword').click(function() {
                const passwordField = $('#smtp_password');
                const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', type);
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });
        });
    </script>
</body>
</html>
