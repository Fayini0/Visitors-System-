<?php
session_start();

// Check if user is logged in and has security role
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header('Location: login.php');
    exit();
}

// Get dashboard statistics for security
try {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Get today's statistics
    $today = date('Y-m-d');
    
    // Total check-ins today
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE DATE(actual_checkin) = ?");
    $stmt->execute([$today]);
    $todayCheckIns = $stmt->fetch()['count'];
    
    // Total check-outs today
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE DATE(actual_checkout) = ?");
    $stmt->execute([$today]);
    $todayCheckOuts = $stmt->fetch()['count'];
    
    // Currently checked in visitors
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE visit_status IN ('checked_in', 'approved')");
    $stmt->execute();
    $currentVisitors = $stmt->fetch()['count'];
    
    // Active alerts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM security_alerts WHERE alert_status = 'new'");
    $stmt->execute();
    $activeAlerts = $stmt->fetch()['count'];
    
    // Recent visitors (last 8)
    $stmt = $db->prepare("
        SELECT v.visit_id,
               CONCAT(vi.first_name, ' ', vi.last_name) as visitor_name,
               vi.id_number,
               r.room_number,
               v.host_name,
               v.actual_checkin,
               v.actual_checkout,
               v.visit_status,
               TIMESTAMPDIFF(HOUR, v.actual_checkin, NOW()) as hours_spent
        FROM visits v
        JOIN visitors vi ON v.visitor_id = vi.visitor_id
        LEFT JOIN rooms r ON v.room_id = r.room_id
        WHERE v.visit_status IN ('checked_in', 'approved', 'checked_out')
        ORDER BY v.actual_checkin DESC
        LIMIT 8
    ");
    $stmt->execute();
    $recentVisitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Overdue visitors
    $stmt = $db->prepare("
        SELECT v.visit_id,
               CONCAT(vi.first_name, ' ', vi.last_name) as visitor_name,
               vi.id_number,
               r.room_number,
               v.host_name,
               v.expected_checkout,
               TIMESTAMPDIFF(MINUTE, v.expected_checkout, NOW()) as overdue_minutes
        FROM visits v
        JOIN visitors vi ON v.visitor_id = vi.visitor_id
        LEFT JOIN rooms r ON v.room_id = r.room_id
        WHERE v.visit_status IN ('checked_in', 'approved') 
        AND v.expected_checkout < NOW()
        ORDER BY v.expected_checkout ASC
    ");
    $stmt->execute();
    $overdueVisitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate shift duration
    $shiftStart = $_SESSION['shift_start'] ?? time();
    $shiftDuration = time() - $shiftStart;
    $shiftHours = floor($shiftDuration / 3600);
    $shiftMinutes = floor(($shiftDuration % 3600) / 60);
    $shiftSeconds = $shiftDuration % 60;

    // Retrieve handover notes from the previous shift (latest submitted report with notes, not by current user)
    $handoverNote = null;
    try {
        $handoverStmt = $db->prepare("\n            SELECT notes, officer_name, generated_at\n            FROM daily_reports\n            WHERE report_status = 'submitted'\n              AND notes IS NOT NULL AND notes <> ''\n              AND generated_by <> ?\n            ORDER BY generated_at DESC\n            LIMIT 1\n        ");
        $handoverStmt->execute([$_SESSION['user_id']]);
        $handoverNote = $handoverStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        $handoverNote = null; // Fail quietly; dashboard continues to work
    }
    
} catch (Exception $e) {
    error_log("Security dashboard error: " . $e->getMessage());
    $todayCheckIns = $todayCheckOuts = $currentVisitors = $activeAlerts = 0;
    $recentVisitors = $overdueVisitors = [];
    $shiftHours = $shiftMinutes = 0;
    $handoverNote = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard - Sophen Residence</title>
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

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dashboard-content {
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.security { border-left-color: var(--security-color); }
        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.danger { border-left-color: var(--danger-color); }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
            color: white;
        }

        .stat-card.security .stat-icon { background: var(--security-color); }
        .stat-card.success .stat-icon { background: var(--success-color); }
        .stat-card.warning .stat-icon { background: var(--warning-color); }
        .stat-card.danger .stat-icon { background: var(--danger-color); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--dark-gray);
            font-size: 0.9rem;
            font-weight: 600;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
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
            color: var(--security-color);
        }

        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .quick-btn {
            background: var(--security-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .quick-btn:hover {
            background: #d35400;
            transform: translateY(-2px);
            color: white;
        }

        .quick-btn i {
            margin-right: 8px;
        }

        .visitor-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .visitor-item {
            padding: 15px 25px;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s ease;
        }

        .visitor-item:hover {
            background: rgba(230, 126, 34, 0.05);
        }

        .visitor-item:last-child {
            border-bottom: none;
        }

        .visitor-info h6 {
            margin: 0 0 3px 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .visitor-info p {
            margin: 0;
            font-size: 0.8rem;
            color: var(--dark-gray);
            opacity: 0.8;
        }

        .visitor-status {
            text-align: right;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-checked-in {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }

        .status-checked-out {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .status-approved {
            background: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }

        .time-text {
            font-size: 0.75rem;
            color: var(--dark-gray);
            opacity: 0.7;
            margin-top: 3px;
        }

        .alert-item {
            padding: 15px 25px;
            border-bottom: 1px solid #f1f3f4;
            border-left: 4px solid var(--danger-color);
            background: rgba(231, 76, 60, 0.02);
        }

        .alert-item:last-child {
            border-bottom: none;
        }

        .alert-content {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .alert-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--danger-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
            flex-shrink: 0;
        }

        .alert-details h6 {
            margin: 0 0 3px 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--danger-color);
        }

        .alert-details p {
            margin: 0;
            font-size: 0.8rem;
            color: var(--dark-gray);
            line-height: 1.4;
        }

        .overdue-time {
            color: var(--danger-color);
            font-weight: 600;
            font-size: 0.75rem;
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

        .current-time-display {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .current-time-display h3 {
            margin: 0;
            color: var(--security-color);
            font-weight: 700;
            font-size: 1.5rem;
        }

        .current-time-display p {
            margin: 5px 0 0 0;
            color: var(--dark-gray);
            font-size: 0.9rem;
        }

        .handover-banner {
            background: #fff8e5;
            border: 1px solid #ffe08a;
            border-left: 4px solid var(--warning-color);
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .handover-banner .banner-title {
            font-weight: 700;
            color: var(--primary-color);
            margin: 0 0 6px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .handover-banner .banner-meta {
            font-size: 0.85rem;
            color: var(--dark-gray);
            margin-bottom: 8px;
        }

        .handover-banner .banner-note {
            white-space: pre-wrap;
            color: var(--dark-gray);
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
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

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }

            .dashboard-content {
                padding: 20px;
            }

            .quick-actions {
                flex-wrap: wrap;
            }

            .quick-btn {
                flex: 1;
                min-width: calc(50% - 5px);
                justify-content: center;
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
                <a href="index.php" class="nav-link active">
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
                <button class="mobile-menu-btn d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Security Dashboard</h1>
            </div>
            
            <div class="navbar-actions">
                <button class="action-btn" title="Alerts">
                    <i class="fas fa-bell"></i>
                    <?php if ($activeAlerts > 0): ?>
                    <span class="notification-badge"><?= $activeAlerts ?></span>
                    <?php endif; ?>
                </button>
                <button class="action-btn" title="Radio">
                    <i class="fas fa-broadcast-tower"></i>
                </button>
                <a href="logout.php" class="action-btn" title="End Shift">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Current Time Display -->
            <div class="current-time-display">
                <h3 id="currentTime"></h3>
                <p><?= date('l, F j, Y') ?></p>
            </div>

            <?php if (!empty($handoverNote)): ?>
            <!-- Handover Note from Previous Shift -->
            <div class="handover-banner" id="handoverBanner">
                <div class="banner-title">
                    <i class="fas fa-handshake"></i>
                    Handover Note from Previous Shift
                    <button class="btn btn-sm btn-outline-secondary ms-auto" onclick="document.getElementById('handoverBanner').style.display='none'">Dismiss</button>
                </div>
                <div class="banner-meta">
                    From: <?= htmlspecialchars($handoverNote['officer_name'] ?? 'Unknown') ?> •
                    <?= date('Y-m-d H:i', strtotime($handoverNote['generated_at'])) ?>
                </div>
                <div class="banner-note">
                    <?= nl2br(htmlspecialchars($handoverNote['notes'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="../visitor/step1.php" class="quick-btn">
                    <i class="fas fa-user-plus"></i>
                    New Visitor
                </a>
                <a href="overview.php" class="quick-btn">
                    <i class="fas fa-eye"></i>
                    Monitor
                </a>
                <a href="alerts.php" class="quick-btn">
                    <i class="fas fa-exclamation-triangle"></i>
                    View Alerts
                </a>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card security">
                    <div class="stat-icon">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                    <div class="stat-number"><?= $todayCheckIns ?></div>
                    <div class="stat-label">Check-ins Today</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <div class="stat-number"><?= $todayCheckOuts ?></div>
                    <div class="stat-label">Check-outs Today</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?= $currentVisitors ?></div>
                    <div class="stat-label">Currently Inside</div>
                </div>

                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?= $activeAlerts ?></div>
                    <div class="stat-label">Active Alerts</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Visitors -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users"></i>
                            Recent Visitor Activity
                        </h3>
                    </div>
                    <div class="visitor-list">
                        <?php if (!empty($recentVisitors)): ?>
                        <?php foreach ($recentVisitors as $visitor): ?>
                        <div class="visitor-item">
                            <div class="visitor-info">
                                <h6><?= htmlspecialchars($visitor['visitor_name']) ?></h6>
                                <p>ID: <?= htmlspecialchars($visitor['id_number']) ?> • 
                                   Room: <?= htmlspecialchars($visitor['room_number'] ?? 'N/A') ?> • 
                                   Host: <?= htmlspecialchars($visitor['host_name']) ?></p>
                            </div>
                            <div class="visitor-status">
                                <span class="status-badge status-<?= str_replace('_', '-', $visitor['visit_status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $visitor['visit_status'])) ?>
                                </span>
                                <div class="time-text">
                                    <?= date('H:i', strtotime($visitor['actual_checkin'])) ?>
                                    <?php if ($visitor['actual_checkout']): ?>
                                    - <?= date('H:i', strtotime($visitor['actual_checkout'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-clock"></i>
                            <h5>No Recent Activity</h5>
                            <p>No visitor activity recorded today.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Overdue Visitors & Alerts -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Priority Alerts
                        </h3>
                    </div>
                    <div class="visitor-list">
                        <?php if (!empty($overdueVisitors)): ?>
                        <?php foreach ($overdueVisitors as $overdue): ?>
                        <div class="alert-item">
                            <div class="alert-content">
                                <div class="alert-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="alert-details">
                                    <h6>Visitor Overdue</h6>
                                    <p><strong><?= htmlspecialchars($overdue['visitor_name']) ?></strong><br>
                                    Room: <?= htmlspecialchars($overdue['room_number'] ?? 'N/A') ?> • 
                                    Host: <?= htmlspecialchars($overdue['host_name']) ?></p>
                                    <div class="overdue-time">
                                        <?= $overdue['overdue_minutes'] ?> minutes overdue
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <h5>All Clear</h5>
                            <p>No overdue visitors or active alerts.</p>
                        </div>
                        <?php endif; ?>
                    </div>
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

            // Auto-refresh dashboard data every 30 seconds
            setInterval(function() {
                refreshSecurityDashboard();
            }, 30000);

            // Stat card hover effects
            $('.stat-card').hover(
                function() {
                    $(this).css('transform', 'translateY(-5px)');
                },
                function() {
                    $(this).css('transform', 'translateY(0)');
                }
            );
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

        function updateShiftDuration() {
            const nowSeconds = Math.floor(Date.now() / 1000);
            const elapsed = Math.max(0, nowSeconds - SHIFT_START_TS);
            const hours = Math.floor(elapsed / 3600);
            const minutes = Math.floor((elapsed % 3600) / 60);
            const seconds = elapsed % 60;
            const pad = (n) => n.toString().padStart(2, '0');
            $('#shiftDurationDisplay').text(`${pad(hours)}:${pad(minutes)}:${pad(seconds)}`);
        }

        function refreshSecurityDashboard() {
            // In a real implementation, this would make an AJAX call
            // to refresh dashboard statistics without page reload
            console.log('Refreshing security dashboard data...');
        }

        // Alert button click
        $('.action-btn').click(function() {
            const icon = $(this).find('i').attr('class');
            
            if (icon.includes('bell')) {
                window.location.href = 'alerts.php';
            } else if (icon.includes('broadcast-tower')) {
                // Show radio/communication modal
                console.log('Open radio communication');
            }
        });
    </script>
</body>
</html>