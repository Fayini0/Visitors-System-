<?php
session_start();

// Check if user is logged in and has security role
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header('Location: login.php');
    exit();
}

// If accessed directly (GET without finalize), redirect to end-shift page
$finalize = isset($_GET['finalize']) && $_GET['finalize'] === '1';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$finalize) {
    header('Location: end-shift.php');
    exit();
}

// Calculate shift duration
$shiftStart = $_SESSION['shift_start'] ?? time();
$shiftDuration = time() - $shiftStart;
$shiftHours = floor($shiftDuration / 3600);
$shiftMinutes = floor(($shiftDuration % 3600) / 60);

// Finalize logout: either via POST confirmation or GET finalize flag
if ($finalize || ($_POST && isset($_POST['confirm_logout']))) {
    try {
        require_once '../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        // Log the logout activity
        $logQuery = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'security_logout', ?, ?)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->execute([
            $_SESSION['user_id'], 
            "Shift duration: {$shiftHours}h {$shiftMinutes}m",
            $_SERVER['REMOTE_ADDR']
        ]);
        
    } catch (Exception $e) {
        error_log("Security logout error: " . $e->getMessage());
    }
    
    // Clear session and redirect
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['logout_message'] = 'Shift ended successfully. Thank you for your service.';
    header('Location: login.php');
    exit();
}

// Get current alerts and visitors for handover info
try {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Get pending alerts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM security_alerts WHERE alert_status = 'new'");
    $stmt->execute();
    $pendingAlerts = $stmt->fetch()['count'];
    
    // Get current visitors
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE visit_status IN ('checked_in', 'approved')");
    $stmt->execute();
    $currentVisitors = $stmt->fetch()['count'];
    
    // Get overdue visitors
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE visit_status IN ('checked_in', 'approved') AND expected_checkout < NOW()");
    $stmt->execute();
    $overdueVisitors = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    error_log("Logout page data error: " . $e->getMessage());
    $pendingAlerts = $currentVisitors = $overdueVisitors = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>End Shift - Sophen Security</title>
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

        .logout-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logout-header {
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

        .logout-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .logout-subtitle {
            color: var(--dark-gray);
            font-size: 1rem;
            opacity: 0.8;
        }

        .shift-summary {
            background: var(--light-gray);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
        }

        .summary-header {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .summary-header i {
            margin-right: 10px;
            color: var(--security-color);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .summary-value {
            color: var(--primary-color);
            font-weight: 600;
        }

        .warning-section {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid var(--danger-color);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
        }

        .warning-header {
            color: var(--danger-color);
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .warning-header i {
            margin-right: 10px;
        }

        .warning-list {
            margin: 0;
            padding-left: 20px;
            color: var(--dark-gray);
        }

        .warning-list li {
            margin-bottom: 8px;
        }

        .handover-note {
            background: rgba(52, 152, 219, 0.1);
            border-left: 4px solid var(--secondary-color);
            padding: 15px;
            border-radius: 8px;
            margin: 25px 0;
        }

        .handover-note h6 {
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .handover-note p {
            margin: 0;
            color: var(--dark-gray);
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .action-btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .confirm-btn {
            background: var(--danger-color);
            color: white;
            flex: 1;
        }

        .confirm-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
            color: white;
        }

        .cancel-btn {
            background: var(--light-gray);
            color: var(--dark-gray);
            flex: 1;
        }

        .cancel-btn:hover {
            background: #bdc3c7;
            color: var(--dark-gray);
        }

        .time-display {
            text-align: center;
            margin: 20px 0;
        }

        .current-time {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--security-color);
            margin-bottom: 5px;
        }

        .current-date {
            color: var(--dark-gray);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .logout-container {
                margin: 20px;
                padding: 30px 25px;
            }

            .logout-title {
                font-size: 1.5rem;
            }

            .security-icon {
                width: 70px;
                height: 70px;
            }

            .security-icon i {
                font-size: 35px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .summary-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 3px;
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
    </style>
</head>
<body>
    <div class="logout-container animate-entrance">
        <div class="logout-header">
            <div class="security-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <h1 class="logout-title">End Shift</h1>
            <p class="logout-subtitle">Are you sure you want to end your security shift?</p>
        </div>

        <div class="time-display">
            <div class="current-time" id="currentTime"></div>
            <div class="current-date"><?= date('l, F j, Y') ?></div>
        </div>

        <div class="shift-summary">
            <div class="summary-header">
                <i class="fas fa-clipboard-list"></i>
                Shift Summary
            </div>
            <div class="summary-row">
                <span class="summary-label">Shift Duration:</span>
                <span class="summary-value"><?= sprintf('%02d:%02d', $shiftHours, $shiftMinutes) ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Officer:</span>
                <span class="summary-value"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Current Visitors:</span>
                <span class="summary-value"><?= $currentVisitors ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Pending Alerts:</span>
                <span class="summary-value <?= $pendingAlerts > 0 ? 'text-danger' : '' ?>"><?= $pendingAlerts ?></span>
            </div>
            <?php if ($overdueVisitors > 0): ?>
            <div class="summary-row">
                <span class="summary-label">Overdue Visitors:</span>
                <span class="summary-value text-danger"><?= $overdueVisitors ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($pendingAlerts > 0 || $overdueVisitors > 0): ?>
        <div class="warning-section">
            <div class="warning-header">
                <i class="fas fa-exclamation-triangle"></i>
                Important Notes for Next Shift
            </div>
            <ul class="warning-list">
                <?php if ($pendingAlerts > 0): ?>
                <li><strong><?= $pendingAlerts ?> pending security alerts</strong> need attention</li>
                <?php endif; ?>
                <?php if ($overdueVisitors > 0): ?>
                <li><strong><?= $overdueVisitors ?> visitors are overdue</strong> for checkout</li>
                <?php endif; ?>
                <li>Please ensure proper handover to the next security officer</li>
                <li>All alerts should be addressed before shift change</li>
            </ul>
        </div>
        <?php endif; ?>

        <div class="handover-note">
            <h6><i class="fas fa-info-circle me-2"></i>Shift Handover</h6>
            <p>Ending your shift will log you out of the system. Please ensure the next security officer 
               is ready to take over and logs in within 15 minutes to maintain security coverage.</p>
        </div>

        <form method="POST" action="" id="logoutForm">
            <div class="action-buttons">
                <a href="index.php" class="action-btn cancel-btn">
                    <i class="fas fa-times me-2"></i>
                    Cancel
                </a>
                <button type="submit" name="confirm_logout" class="action-btn confirm-btn" id="confirmBtn">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    End Shift
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Update time every second
            updateTime();
            setInterval(updateTime, 1000);

            // Form submission with confirmation
            $('#logoutForm').submit(function(e) {
                const pendingAlerts = <?= $pendingAlerts ?>;
                const overdueVisitors = <?= $overdueVisitors ?>;
                
                if (pendingAlerts > 0 || overdueVisitors > 0) {
                    const message = `Warning: You have ${pendingAlerts} pending alerts and ${overdueVisitors} overdue visitors. Are you sure you want to end your shift now?`;
                    if (!confirm(message)) {
                        e.preventDefault();
                        return false;
                    }
                }

                const button = $('#confirmBtn');
                const originalText = button.html();
                
                button.prop('disabled', true)
                      .html('<div class="loading-spinner"></div>Ending shift...');
                
                // Don't re-enable button as we're logging out
            });

            // Warning if there are pending issues
            <?php if ($pendingAlerts > 0 || $overdueVisitors > 0): ?>
            setTimeout(() => {
                if (confirm('You have pending security issues. Would you like to review them before ending your shift?')) {
                    window.location.href = 'alerts.php';
                }
            }, 2000);
            <?php endif; ?>
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

        // Add text-danger class styles
        const style = document.createElement('style');
        style.textContent = `
            .text-danger {
                color: var(--danger-color) !important;
                font-weight: 700;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>