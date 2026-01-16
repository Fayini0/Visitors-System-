<?php
session_start();
require_once '../includes/functions.php';
require_admin();

// Get dashboard statistics
try {
    $database = new Database();
    $db = $database->getConnection();
    // Generate checkout reminder alerts and send visitor emails when applicable
    process_checkout_alerts();
    
    // Get today's statistics
    $today = date('Y-m-d');
    
    // Total visitors today
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE DATE(actual_checkin) = ?");
    $stmt->execute([$today]);
    $todayVisitors = $stmt->fetch()['count'];
    
    // Currently checked in visitors
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE visit_status IN ('checked_in', 'approved')");
    $stmt->execute();
    $currentVisitors = $stmt->fetch()['count'];
    
    // Total active alerts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM security_alerts WHERE alert_status = 'new'");
    $stmt->execute();
    $activeAlerts = $stmt->fetch()['count'];
    
    // Total residents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM residents WHERE is_active = 1");
    $stmt->execute();
    $totalResidents = $stmt->fetch()['count'];
    
    // Recent visits (last 10)
    $stmt = $db->prepare("
        SELECT v.visit_id, 
               CONCAT(vi.first_name, ' ', vi.last_name) as visitor_name,
               vi.id_number,
               r.room_number,
               v.host_name,
               v.actual_checkin,
               v.visit_status
        FROM visits v
        JOIN visitors vi ON v.visitor_id = vi.visitor_id
        LEFT JOIN rooms r ON v.room_id = r.room_id
        ORDER BY v.actual_checkin DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentVisits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent alerts
    $stmt = $db->prepare("
        SELECT a.*, 
               CONCAT(v.first_name, ' ', v.last_name) as visitor_name,
               r.room_number
        FROM security_alerts a
        LEFT JOIN visitors v ON a.visitor_id = v.visitor_id
        LEFT JOIN rooms r ON a.room_id = r.room_id
        WHERE a.alert_status = 'new'
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $todayVisitors = $currentVisitors = $activeAlerts = $totalResidents = 0;
    $recentVisits = $recentAlerts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sophen Residence</title>
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
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

        .stat-card.primary { border-left-color: var(--admin-color); }
        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.info { border-left-color: var(--secondary-color); }

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

        .stat-card.primary .stat-icon { background: var(--admin-color); }
        .stat-card.success .stat-icon { background: var(--success-color); }
        .stat-card.warning .stat-icon { background: var(--warning-color); }
        .stat-card.info .stat-icon { background: var(--secondary-color); }

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
            color: var(--admin-color);
        }

        .card-body {
            padding: 0;
        }

        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }

        .visits-table {
            width: 100%;
            margin: 0;
        }

        .visits-table th {
            background: var(--light-gray);
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .visits-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f3f4;
            font-size: 0.9rem;
        }

        .visits-table tr:hover {
            background: rgba(142, 68, 173, 0.05);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-checked-in {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }

        .status-pending {
            background: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }

        .status-approved {
            background: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }

        .alert-item {
            padding: 15px 25px;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.3s ease;
        }

        .alert-item:hover {
            background: rgba(231, 76, 60, 0.05);
        }

        .alert-item:last-child {
            border-bottom: none;
        }

        .alert-content {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .alert-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: white;
            flex-shrink: 0;
        }

        .alert-medium { background: var(--warning-color); }
        .alert-high { background: var(--danger-color); }
        .alert-low { background: var(--secondary-color); }

        .alert-details h6 {
            margin: 0 0 5px 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .alert-details p {
            margin: 0;
            font-size: 0.8rem;
            color: var(--dark-gray);
            line-height: 1.4;
        }

        .alert-time {
            font-size: 0.75rem;
            color: var(--dark-gray);
            opacity: 0.7;
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
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }

            .dashboard-content {
                padding: 20px;
            }
        }

        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .quick-btn {
            background: var(--admin-color);
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
            background: #7d3c98;
            transform: translateY(-2px);
            color: white;
        }

        .quick-btn i {
            margin-right: 8px;
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
            
            .quick-actions {
                flex-wrap: wrap;
            }
            
            .quick-btn {
                flex: 1;
                min-width: calc(50% - 5px);
                justify-content: center;
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
                <a href="settings.php" class="nav-link">
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
            <h1 class="page-title">Dashboard</h1>
            
            <div class="navbar-actions">
                <button id="sidebarToggle" class="action-btn d-md-none" title="Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <button class="action-btn" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($activeAlerts > 0): ?>
                    <span class="notification-badge"><?= $activeAlerts ?></span>
                    <?php endif; ?>
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

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="../visitor/step1.php" class="quick-btn">
                    <i class="fas fa-plus"></i>
                    New Visitor
                </a>
                <a href="overview.php" class="quick-btn">
                    <i class="fas fa-eye"></i>
                    Monitor Visits
                </a>
                <a href="reports.php" class="quick-btn">
                    <i class="fas fa-download"></i>
                    Generate Report
                </a>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-number"><?= $todayVisitors ?></div>
                    <div class="stat-label">Today's Visitors</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?= $currentVisitors ?></div>
                    <div class="stat-label">Currently Checked In</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?= $activeAlerts ?></div>
                    <div class="stat-label">Active Alerts</div>
                </div>

                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="stat-number"><?= $totalResidents ?></div>
                    <div class="stat-label">Total Residents</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Visits -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-clock"></i>
                            Recent Visits
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentVisits)): ?>
                        <div class="table-responsive">
                            <table class="visits-table">
                                <thead>
                                    <tr>
                                        <th>Visitor</th>
                                        <th>Room</th>
                                        <th>Host</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentVisits as $visit): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($visit['visitor_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($visit['id_number']) ?></small>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($visit['room_number'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($visit['host_name']) ?></td>
                                        <td>
                                            <small><?= date('H:i', strtotime($visit['actual_checkin'])) ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= str_replace('_', '-', $visit['visit_status']) ?>">
                                                <?= ucfirst(str_replace('_', ' ', $visit['visit_status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h5>No Recent Visits</h5>
                            <p>No visitor activity recorded today.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Security Alerts -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-shield-alt"></i>
                            Security Alerts
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentAlerts)): ?>
                        <?php foreach ($recentAlerts as $alert): ?>
                        <div class="alert-item">
                            <div class="alert-content">
                                <div class="alert-icon alert-<?= $alert['severity'] ?>">
                                    <i class="fas fa-<?= $alert['alert_type'] === 'overstay' ? 'clock' : 'exclamation-triangle' ?>"></i>
                                </div>
                                <div class="alert-details">
                                    <h6><?= ucfirst($alert['alert_type']) ?> Alert</h6>
                                    <p><?= htmlspecialchars($alert['message']) ?></p>
                                    <?php if ($alert['visitor_name']): ?>
                                    <p><strong>Visitor:</strong> <?= htmlspecialchars($alert['visitor_name']) ?>
                                    <?php if ($alert['room_number']): ?>
                                     - Room <?= htmlspecialchars($alert['room_number']) ?>
                                    <?php endif; ?>
                                    </p>
                                    <?php endif; ?>
                                    <div class="alert-time">
                                        <?= date('M j, H:i', strtotime($alert['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <h5>No Active Alerts</h5>
                            <p>All systems running smoothly.</p>
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
        $(document).ready(function() {
            // Mobile menu toggle
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('show');
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
                refreshDashboard();
            }, 30000);

            // Stat card animations
            $('.stat-card').hover(
                function() {
                    $(this).css('transform', 'translateY(-5px)');
                },
                function() {
                    $(this).css('transform', 'translateY(0)');
                }
            );

            // Real-time clock
            updateClock();
            setInterval(updateClock, 1000);
        });

        function refreshDashboard() {
            // In a real implementation, this would make an AJAX call
            // to refresh dashboard statistics without page reload
            console.log('Refreshing dashboard data...');
        }

        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-ZA', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Update any clock elements if they exist
            $('.current-time').text(timeString);
        }

        // Notification handling
        $('.action-btn').click(function() {
            const icon = $(this).find('i').attr('class');
            
            if (icon.includes('bell')) {
                // Show notifications dropdown
                console.log('Show notifications');
            } else if (icon.includes('envelope')) {
                // Show messages
                console.log('Show messages');
            } else if (icon.includes('cog')) {
                // Go to settings
                window.location.href = 'settings.php';
            }
        });
    </script>
</body>
</html>