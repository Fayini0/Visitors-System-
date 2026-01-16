<?php
session_start();

// Check if user is logged in and has security role
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header('Location: login.php');
    exit();
}

// Get alerts data
try {
    require_once '../includes/functions.php';
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    // Refresh checkout reminder alerts on page load
    process_checkout_alerts();
    // Refresh overdue (overstay) alerts on page load
    process_overstay_alerts();
    
    // Fetch active alerts
    $stmt = $db->prepare("
        SELECT sa.alert_id, sa.alert_type, sa.severity, sa.message, sa.created_at,
               v.first_name, v.last_name, v.id_number,
               r.room_number, vi.actual_checkin, vi.expected_checkout
        FROM security_alerts sa
        LEFT JOIN visitors v ON sa.visitor_id = v.visitor_id
        LEFT JOIN rooms r ON sa.room_id = r.room_id
        LEFT JOIN visits vi ON sa.visit_id = vi.visit_id
        WHERE sa.alert_status IN ('new', 'acknowledged')
        ORDER BY sa.created_at DESC
    ");
    $stmt->execute();
    $activeAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch alert history
    $stmt = $db->prepare("
        SELECT sa.alert_id, sa.alert_type, sa.severity, sa.message, sa.created_at, sa.alert_status,
               v.first_name, v.last_name, v.id_number,
               r.room_number
        FROM security_alerts sa
        LEFT JOIN visitors v ON sa.visitor_id = v.visitor_id
        LEFT JOIN rooms r ON sa.room_id = r.room_id
        ORDER BY sa.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $alertHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate shift duration
    $shiftStart = $_SESSION['shift_start'] ?? time();
    $shiftDuration = time() - $shiftStart;
    $shiftHours = floor($shiftDuration / 3600);
    $shiftMinutes = floor(($shiftDuration % 3600) / 60);
    $shiftSeconds = $shiftDuration % 60;
    
} catch (Exception $e) {
    error_log("Security alerts error: " . $e->getMessage());
    $activeAlerts = $alertHistory = [];
    $shiftHours = $shiftMinutes = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Alerts - Sophen Residence</title>
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

        .shift-info {
            position: absolute;
            bottom: 60px;
            left: 0;
            right: 0;
            padding: 15px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(0, 0, 0, 0.1);
        }

        .shift-time {
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            text-align: center;
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
            color: var(--security-color);
        }

        .dashboard-content {
            padding: 30px;
        }

        .alerts-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .alerts-title {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .current-time {
            color: var(--dark-gray);
            font-size: 1rem;
            font-weight: 500;
        }

        .mark-all-read-btn {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mark-all-read-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .alert-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            overflow: hidden;
            border-left: 5px solid;
        }

        .alert-card.urgent {
            border-left-color: var(--danger-color);
            background: rgba(231, 76, 60, 0.02);
        }

        .alert-card.warning {
            border-left-color: var(--warning-color);
            background: rgba(243, 156, 18, 0.02);
        }

        .alert-card.blocked {
            border-left-color: var(--danger-color);
            background: rgba(231, 76, 60, 0.02);
        }

        .alert-content {
            padding: 20px 25px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .alert-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .alert-card.urgent .alert-icon {
            background: var(--danger-color);
        }

        .alert-card.warning .alert-icon {
            background: var(--warning-color);
        }

        .alert-card.blocked .alert-icon {
            background: var(--danger-color);
        }

        .alert-details {
            flex: 1;
        }

        .alert-type {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }

        .alert-message {
            font-size: 1rem;
            color: var(--primary-color);
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .alert-actions {
            display: flex;
            gap: 10px;
        }

        .alert-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .alert-btn.force-checkout {
            background: var(--danger-color);
            color: white;
        }

        .alert-btn.force-checkout:hover {
            background: #c0392b;
        }

        .alert-btn.extend {
            background: var(--warning-color);
            color: white;
        }

        .alert-btn.extend:hover {
            background: #e67e22;
        }

        .alert-btn.reminder {
            background: var(--warning-color);
            color: white;
        }

        .alert-btn.reminder:hover {
            background: #e67e22;
        }

        .alert-btn.details {
            background: var(--danger-color);
            color: white;
        }

        .alert-btn.details:hover {
            background: #c0392b;
        }

        .alert-history {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .history-header {
            background: var(--light-gray);
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
        }

        .history-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }

        .alerts-table {
            width: 100%;
            border-collapse: collapse;
        }

        .alerts-table th {
            background: var(--light-gray);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--primary-color);
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
        }

        .alerts-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }

        .alerts-table tr:hover {
            background: rgba(230, 126, 34, 0.05);
        }

        .status-badge {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-new {
            background: var(--danger-color);
        }

        .status-acknowledged {
            background: var(--warning-color);
        }

        .status-resolved {
            background: var(--success-color);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--dark-gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
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

            .dashboard-content {
                padding: 20px;
            }

            .alert-actions {
                flex-wrap: wrap;
            }

            .alert-btn {
                flex: 1;
                min-width: calc(50% - 5px);
            }
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
            .mobile-menu-btn {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h3 class="sidebar-title">Sophen Security</h3>
            <p class="sidebar-subtitle">Officer Dashboard</p>
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
                <a href="alerts.php" class="nav-link active">
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
                <a href="settings.php" class="nav-link">
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

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div style="display: flex; align-items: center;">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Security Alerts</h1>
            </div>
            
            <div class="navbar-actions">
                <button class="action-btn" title="Back to Dashboard">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <a href="logout.php" class="action-btn" title="End Shift">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Alerts Header -->
            <div class="alerts-header">
                <div>
                    <h2 class="alerts-title">Security Alerts</h2>
                    <div class="current-time" id="currentTime">
                        <?= date('H:i:s l, d F Y') ?>
                    </div>
                </div>
                <button class="mark-all-read-btn" onclick="markAllRead()">
                    <i class="fas fa-check me-2"></i>
                    Mark All Read
                </button>
            </div>

            <!-- Active Alerts -->
            <?php if (!empty($activeAlerts)): ?>
            <?php foreach ($activeAlerts as $alert): ?>
            <div class="alert-card <?= strtolower($alert['severity']) ?>">
                <div class="alert-content">
                    <div class="alert-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="alert-details">
                        <div class="alert-type">
                            <?= strtoupper($alert['alert_type']) ?>: Visitor '<?= htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']) ?>' 
                            <?php if ($alert['alert_type'] == 'overstay'): ?>
                            is <?= floor((strtotime('now') - strtotime($alert['expected_checkout'])) / 3600) ?> hour(s) overdue 
                            (checked in at <?= date('H:i', strtotime($alert['actual_checkin'])) ?>, should have left by <?= date('H:i', strtotime($alert['expected_checkout'])) ?>).
                            <?php else: ?>
                            <?= htmlspecialchars($alert['message']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="alert-message">
                            <?php if ($alert['room_number']): ?>
                            Room: <?= htmlspecialchars($alert['room_number']) ?> • 
                            <?php endif; ?>
                            ID: <?= htmlspecialchars($alert['id_number']) ?>
                        </div>
                        <div class="alert-actions">
                            <?php if ($alert['alert_type'] == 'overstay'): ?>
                            <button class="alert-btn force-checkout" onclick="forceCheckout(<?= $alert['alert_id'] ?>)">
                                Force Check-Out
                            </button>
                            <button class="alert-btn extend" onclick="extendVisit(<?= $alert['alert_id'] ?>)">
                                Extend
                            </button>
                            <?php elseif ($alert['alert_type'] == 'blocked_visitor'): ?>
                            <button class="alert-btn details" onclick="viewDetails(<?= $alert['alert_id'] ?>)">
                                Details
                            </button>
                            <?php else: ?>
                            <button class="alert-btn reminder" onclick="sendReminder(<?= $alert['alert_id'] ?>)">
                                Send Reminder
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-shield-alt"></i>
                <h5>All Clear</h5>
                <p>No active security alerts at this time.</p>
            </div>
            <?php endif; ?>

            <!-- Alert History -->
            <div class="alert-history">
                <div class="history-header">
                    <h3 class="history-title">Alert History</h3>
                </div>
                <div class="table-responsive">
                    <table class="alerts-table">
                        <thead>
                            <tr>
                                <th>Time In</th>
                                <th>Type</th>
                                <th>Visitor</th>
                                <th>Room Number</th>
                                <th>Message</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($alertHistory)): ?>
                            <?php foreach ($alertHistory as $alert): ?>
                            <tr>
                                <td><?= date('H:i', strtotime($alert['created_at'])) ?></td>
                                <td><?= ucfirst($alert['alert_type']) ?></td>
                                <td>
                                    <?php if ($alert['first_name'] && $alert['last_name']): ?>
                                    <?= htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']) ?>
                                    <?php else: ?>
                                    N/A
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($alert['room_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars(substr($alert['message'], 0, 50)) ?><?php if (strlen($alert['message']) > 50) echo '...'; ?></td>
                                <td>
                                    <span class="status-badge status-<?= $alert['alert_status'] ?>"></span>
                                    <?= ucfirst($alert['alert_status']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <p>No alert history available.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Base shift start timestamp from server (epoch seconds)
        const SHIFT_START_TS = <?= json_encode($shiftStart) ?>;

        $(document).ready(function() {
            // Update time every second
            updateTime();
            setInterval(updateTime, 1000);

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

        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-ZA', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }) + ' ' + now.toLocaleDateString('en-ZA', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
            $('#currentTime').text(timeString);
        }

        function updateShiftDuration() {
            const nowSeconds = Math.floor(Date.now() / 1000);
            const elapsed = Math.max(0, nowSeconds - SHIFT_START_TS);
            const hours = Math.floor(elapsed / 3600);
            const minutes = Math.floor((elapsed % 3600) / 60);
            const seconds = elapsed % 60;
            const pad = (n) => n.toString().padStart(2, '0');
            $('#shiftDurationDisplay').text(`${pad(hours)}:${pad(minutes)}:${pad(seconds)}`);
        }

        function markAllRead() {
            if (confirm('Are you sure you want to mark all alerts as read?')) {
                $.post('process/alert-actions.php', { action: 'mark_all_read' }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error marking alerts as read: ' + response.message);
                    }
                }, 'json');
            }
        }

        function forceCheckout(alertId) {
            if (confirm('Are you sure you want to force check-out this visitor?')) {
                $.post('process/alert-actions.php', { action: 'force_checkout', alert_id: alertId }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error forcing check-out: ' + response.message);
                    }
                }, 'json');
            }
        }

        function extendVisit(alertId) {
            const hours = prompt('How many hours would you like to extend the visit?');
            if (hours && !isNaN(hours)) {
                $.post('process/alert-actions.php', { action: 'extend_visit', alert_id: alertId, hours: hours }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error extending visit: ' + response.message);
                    }
                }, 'json');
            }
        }

        function sendReminder(alertId) {
            $.post('process/alert-actions.php', { action: 'send_reminder', alert_id: alertId }, function(response) {
                if (response.success) {
                    alert('Reminder sent successfully');
                } else {
                    alert('Error sending reminder: ' + response.message);
                }
            }, 'json');
        }

        function viewDetails(alertId) {
            window.location.href = 'alert-details.php?id=' + alertId;
        }

        
    </script>
</body>
</html>
