<?php
session_start();
require_once '../includes/functions.php';
require_once '../config/database.php';

// Ensure security user
require_security();

// Calculate shift duration for display
$shiftStart = $_SESSION['shift_start'] ?? time();
$shiftDuration = time() - $shiftStart;
$shiftHours = floor($shiftDuration / 3600);
$shiftMinutes = floor(($shiftDuration % 3600) / 60);
$shiftSeconds = $shiftDuration % 60;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mirror Admin Settings fields
    $settings = [
        'default_checkout_time' => sanitize_input($_POST['default_checkout_time'] ?? ''),
        'checkout_alert_minutes' => (int)sanitize_input($_POST['checkout_alert_minutes'] ?? '0'),
        'max_visit_duration_hours' => (float)sanitize_input($_POST['max_visit_duration_hours'] ?? '0')
    ];

    if (update_system_settings($settings)) {
        $success_message = "Settings updated successfully!";
        // Log the activity
        log_activity($_SESSION['user_id'], 'security_settings_updated', 'System settings updated from Security');
    } else {
        $error_message = "Failed to update settings. Please try again.";
    }
}

// Get current settings (same as Admin)
$current_settings = get_system_settings();
if (!$current_settings) {
    $error_message = "Failed to load settings. Please check database connection.";
}

// Using shared system settings helpers from includes/functions.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - Sophen Residence</title>
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
            background-color: #f8f9fa;
            margin: 0;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--security-color), #d35400);
            width: 280px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            transform: translateX(0);
            transition: transform 0.3s ease;
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
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin: 5px 0 0;
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
            text-align: center;
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
            color: var(--security-color);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
            font-weight: 600;
        }

        .form-label {
            font-weight: 500;
        }

        .btn-primary {
            background-color: var(--security-color);
            border-color: var(--security-color);
        }

        .btn-primary:hover {
            background-color: #d35400;
            border-color: #d35400;
        }

        .alert {
            border-radius: 8px;
        }

        /* Shift/User info styles for sidebar consistency */
        .shift-info {
            margin: 15px;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            color: white;
            font-weight: 600;
        }

        .user-info {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 12px 15px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: white;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
            cursor: pointer;
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

            .mobile-menu-btn {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="sidebar-title">Security Portal</h1>
            <p class="sidebar-subtitle">Sophen Residence</p>
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
                <a href="alerts.php" class="nav-link">
                    <i class="fas fa-exclamation-triangle"></i>
                    Alerts
                </a>
            </div>
            <div class="nav-item">
                <a href="search.php" class="nav-link">
                    <i class="fas fa-search"></i>
                    Search Visitors
                </a>
            </div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-clipboard-list"></i>
                    Reports
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link active">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </div>
            <div class="nav-item">
                <a href="end-shift.php" class="nav-link">
                    <i class="fas fa-clock"></i>
                    End Shift
                </a>
            </div>
        </nav>
        <div class="shift-info">
            <div class="shift-time">
                <i class="fas fa-clock me-2"></i>
                Shift Duration: <span id="shiftDurationDisplay"><?= sprintf('%02d:%02d:%02d', $shiftHours, $shiftMinutes, $shiftSeconds) ?></span>
            </div>
        </div>

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
                        Security Officer
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div style="display: flex; align-items: center;">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Security Settings</h1>
            </div>
            <div class="navbar-actions">
                <a href="index.php" class="action-btn" title="Back to Dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                </a>
                <a href="logout.php" class="action-btn" title="End Shift">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <div class="page-header" style="padding: 20px 30px;">
            <p>Configure security-related settings for the residence system</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-sliders-h"></i> System Settings
            </div>
            <div class="card-body">
                <?php if ($current_settings !== false): ?>
                <form method="POST" action="">
                    <div class="row">
                        <!-- General Settings -->
                        <div class="col-md-6">
                            <h5 class="mb-3" style="color: var(--security-color);"><i class="fas fa-sliders-h"></i> General Settings</h5>
                            <div class="mb-3">
                                <label for="default_checkout_time" class="form-label">Default Checkout Time</label>
                                <input type="time" class="form-control" id="default_checkout_time" name="default_checkout_time"
                                       value="<?= htmlspecialchars($current_settings['default_checkout_time'] ?? '23:00:00') ?>" required>
                                <div class="form-text">Default time when visitors should check out</div>
                            </div>

                            <div class="mb-3">
                                <label for="checkout_alert_minutes" class="form-label">Checkout Alert Minutes</label>
                                <input type="number" class="form-control" id="checkout_alert_minutes" name="checkout_alert_minutes"
                                       value="<?= htmlspecialchars($current_settings['checkout_alert_minutes'] ?? 30) ?>" min="1" max="1440" required>
                                <div class="form-text">Minutes before checkout time to send alerts</div>
                            </div>

                            <div class="mb-3">
                                <label for="max_visit_duration_hours" class="form-label">Maximum Visit Duration (Hours)</label>
                                <input type="number" class="form-control" id="max_visit_duration_hours" name="max_visit_duration_hours"
                                       value="<?= htmlspecialchars($current_settings['max_visit_duration_hours'] ?? 8) ?>" min="1" max="24" step="0.5" required>
                                <div class="form-text">Maximum allowed duration for a visit</div>
                            </div>
                        </div>

                        
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Unable to load system settings. Please check your database connection and try again.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Base shift start timestamp from server (epoch seconds)
        const SHIFT_START_TS = <?= json_encode($shiftStart) ?>;

        function updateShiftDuration() {
            const nowSeconds = Math.floor(Date.now() / 1000);
            const elapsed = Math.max(0, nowSeconds - SHIFT_START_TS);
            const hours = Math.floor(elapsed / 3600);
            const minutes = Math.floor((elapsed % 3600) / 60);
            const seconds = elapsed % 60;
            const pad = (n) => n.toString().padStart(2, '0');
            $('#shiftDurationDisplay').text(`${pad(hours)}:${pad(minutes)}:${pad(seconds)}`);
        }

        $(document).ready(function() {
            // Update shift duration every second
            updateShiftDuration();
            setInterval(updateShiftDuration, 1000);

            // Mobile menu toggle
            $('#mobileMenuBtn').click(function() {
                $('#sidebar').toggleClass('show');
            });

            // Close sidebar when clicking outside on mobile
            $(document).click(function(e) {
                if ($(window).width() <= 768) {
                    if (!$(e.target).closest('#sidebar, #mobileMenuBtn').length) {
                        $('#sidebar').removeClass('show');
                    }
                }
            });
        });
    </script>
</body>
</html>