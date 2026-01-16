<?php
session_start();
require_once '../includes/functions.php';
require_admin();

// Get current visitor data
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all current visitors
    $stmt = $db->prepare("
        SELECT v.visit_id, 
               CONCAT(vi.first_name, ' ', vi.last_name) as visitor_name,
               vi.id_number,
               r.room_number,
               v.host_name,
               v.actual_checkin,
               v.expected_checkout,
               v.visit_status,
               TIMESTAMPDIFF(HOUR, v.actual_checkin, NOW()) as hours_spent,
               CASE 
                   WHEN v.expected_checkout < NOW() THEN 1 
                   ELSE 0 
               END as is_overdue
        FROM visits v
        JOIN visitors vi ON v.visitor_id = vi.visitor_id
        LEFT JOIN rooms r ON v.room_id = r.room_id
        WHERE v.visit_status IN ('approved', 'checked_in', 'pending')
        ORDER BY v.actual_checkin DESC
    ");
    $stmt->execute();
    $currentVisitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Overview error: " . $e->getMessage());
    $currentVisitors = [];
}

// Handle visitor actions via AJAX
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $visitId = (int)$_POST['visit_id'];
        $action = $_POST['action'];
        
        // Block checkout actions during quiet hours (23:00–07:00)
        if (in_array($action, ['checkout', 'force_checkout'], true) && is_quiet_hours()) {
            echo json_encode(['success' => false, 'message' => 'Checkout is not allowed between 23:00 and 07:00. Please try again after 07:00.']);
            exit();
        }
        
        switch ($action) {
            case 'checkout':
                $stmt = $db->prepare("
                    UPDATE visits 
                    SET actual_checkout = NOW(), 
                        visit_status = 'checked_out',
                        checked_out_by = ?
                    WHERE visit_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $visitId]);
                echo json_encode(['success' => true, 'message' => 'Visitor checked out successfully']);
                break;
                
            case 'force_checkout':
                $stmt = $db->prepare("
                    UPDATE visits 
                    SET actual_checkout = NOW(), 
                        visit_status = 'checked_out',
                        checked_out_by = ?,
                        notes = CONCAT(COALESCE(notes, ''), 'Force checkout by admin. ')
                    WHERE visit_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $visitId]);
                echo json_encode(['success' => true, 'message' => 'Visitor force checked out']);
                break;
                
            case 'extend':
                $newCheckout = date('Y-m-d H:i:s', strtotime('+2 hours'));
                $stmt = $db->prepare("
                    UPDATE visits 
                    SET expected_checkout = ?
                    WHERE visit_id = ?
                ");
                $stmt->execute([$newCheckout, $visitId]);
                echo json_encode(['success' => true, 'message' => 'Visit extended by 2 hours']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Action failed: ' . $e->getMessage()]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview - Sophen Admin</title>
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

        /* Sidebar styles (same as dashboard) */
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

        .overview-content {
            padding: 30px;
        }

        .overview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .refresh-btn {
            background: var(--admin-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            background: #7d3c98;
            transform: translateY(-2px);
        }

        .visitors-table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .table-header {
            background: var(--light-gray);
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .table-title i {
            margin-right: 10px;
            color: var(--admin-color);
        }

        .visitors-count {
            background: var(--admin-color);
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .visitors-table {
            width: 100%;
            margin: 0;
        }

        .visitors-table th {
            background: var(--light-gray);
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
            border: none;
        }

        .visitors-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            font-size: 0.9rem;
            vertical-align: middle;
        }

        .visitors-table tr:hover {
            background: rgba(142, 68, 173, 0.05);
        }

        .visitor-info {
            display: flex;
            flex-direction: column;
        }

        .visitor-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 2px;
        }

        .visitor-id {
            font-size: 0.8rem;
            color: var(--dark-gray);
            opacity: 0.8;
        }

        .room-badge {
            background: var(--secondary-color);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .time-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .checkin-time {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 2px;
        }

        .duration {
            font-size: 0.8rem;
            color: var(--dark-gray);
            opacity: 0.8;
        }

        .checkout-time {
            font-size: 0.8rem;
            color: var(--warning-color);
            font-weight: 500;
        }

        .overdue {
            color: var(--danger-color) !important;
            font-weight: 600;
        }

        .status-badge {
            padding: 6px 12px;
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

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .btn-checkout {
            background: var(--success-color);
            color: white;
        }

        .btn-checkout:hover {
            background: #229954;
            transform: translateY(-1px);
        }

        .btn-force {
            background: var(--danger-color);
            color: white;
        }

        .btn-force:hover {
            background: #c0392b;
            transform: translateY(-1px);
        }

        .btn-extend {
            background: var(--warning-color);
            color: white;
        }

        .btn-extend:hover {
            background: #e67e22;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--dark-gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h4 {
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
            display: none;
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

            .overview-content {
                padding: 20px;
            }

            .overview-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }

            .visitors-table {
                font-size: 0.8rem;
            }

            .visitors-table th,
            .visitors-table td {
                padding: 10px 8px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
                margin-bottom: 5px;
            }
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            display: none;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--admin-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Sidebar (same as dashboard) -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-home"></i>
            </div>
            <h3 class="sidebar-title">Sophen</h3>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="overview.php" class="nav-link active">
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
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <h1 class="page-title">Visitor Overview</h1>
            <div class="navbar-actions">
                <button class="action-btn" onclick="window.location.href='index.php'">
                    <i class="fas fa-tachometer-alt"></i>
                </button>
                <a href="logout.php" class="action-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Overview Content -->
        <div class="overview-content">
            <div class="overview-header">
                <div>
                    <h2 style="color: var(--primary-color); margin: 0;">Active Visitors</h2>
                    <p style="color: var(--dark-gray); margin: 5px 0 0 0;">Monitor and manage current visitor activity</p>
                </div>
                <button class="refresh-btn" onclick="refreshOverview()">
                    <i class="fas fa-sync-alt me-2"></i>
                    Refresh
                </button>
            </div>

            <div class="alert-success" id="actionAlert">
                <i class="fas fa-check-circle me-2"></i>
                <span id="alertMessage">Action completed successfully</span>
            </div>

            <div class="visitors-table-container" style="position: relative;">
                <div class="loading-overlay" id="loadingOverlay">
                    <div class="spinner"></div>
                </div>

                <div class="table-header">
                    <h3 class="table-title">
                        <i class="fas fa-users"></i>
                        Current Visitors
                    </h3>
                    <span class="visitors-count"><?= count($currentVisitors) ?> Active</span>
                </div>

                <?php if (!empty($currentVisitors)): ?>
                <div class="table-responsive">
                    <table class="visitors-table">
                        <thead>
                            <tr>
                                <th>Visitor</th>
                                <th>Room</th>
                                <th>Host</th>
                                <th>Check-in Time</th>
                                <th>Expected Checkout</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="visitorsTableBody">
                            <?php foreach ($currentVisitors as $visitor): ?>
                            <tr data-visit-id="<?= $visitor['visit_id'] ?>">
                                <td>
                                    <div class="visitor-info">
                                        <div class="visitor-name"><?= htmlspecialchars($visitor['visitor_name']) ?></div>
                                        <div class="visitor-id"><?= htmlspecialchars($visitor['id_number']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($visitor['room_number']): ?>
                                    <span class="room-badge"><?= htmlspecialchars($visitor['room_number']) ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($visitor['host_name']) ?></td>
                                <td>
                                    <div class="time-info">
                                        <div class="checkin-time"><?= date('H:i', strtotime($visitor['actual_checkin'])) ?></div>
                                        <div class="duration"><?= $visitor['hours_spent'] ?>h ago</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="checkout-time <?= $visitor['is_overdue'] ? 'overdue' : '' ?>">
                                        <?= date('H:i', strtotime($visitor['expected_checkout'])) ?>
                                        <?php if ($visitor['is_overdue']): ?>
                                        <br><small>OVERDUE</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= str_replace('_', '-', $visitor['visit_status']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $visitor['visit_status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if (!$visitor['is_overdue']): ?>
                                        <button class="action-btn btn-checkout" onclick="performAction(<?= $visitor['visit_id'] ?>, 'checkout')">
                                            <i class="fas fa-sign-out-alt me-1"></i>
                                            Check Out
                                        </button>
                                        <?php else: ?>
                                        <button class="action-btn btn-force" onclick="performAction(<?= $visitor['visit_id'] ?>, 'force_checkout')">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Force Out
                                        </button>
                                        <?php endif; ?>
                                        <button class="action-btn btn-extend" onclick="performAction(<?= $visitor['visit_id'] ?>, 'extend')">
                                            <i class="fas fa-clock me-1"></i>
                                            Extend
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-clock"></i>
                    <h4>No Active Visitors</h4>
                    <p>All visitors have checked out or no visits are currently in progress.</p>
                    <a href="../visitor/step1.php" class="refresh-btn" style="text-decoration: none; margin-top: 15px; display: inline-block;">
                        <i class="fas fa-plus me-2"></i>
                        Start New Visit
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        function performAction(visitId, action) {
            const actionBtn = event.target;
            const originalText = actionBtn.innerHTML;
            
            // Show loading state
            actionBtn.disabled = true;
            actionBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
            
            // Show loading overlay
            $('#loadingOverlay').show();
            
            $.ajax({
                url: 'overview.php',
                method: 'POST',
                data: {
                    visit_id: visitId,
                    action: action
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        
                        // Remove the row if checked out
                        if (action === 'checkout' || action === 'force_checkout') {
                            $(`tr[data-visit-id="${visitId}"]`).fadeOut(300, function() {
                                $(this).remove();
                                updateVisitorCount();
                            });
                        } else if (action === 'extend') {
                            // Refresh the page to show updated times
                            setTimeout(() => {
                                refreshOverview();
                            }, 1000);
                        }
                    } else {
                        showAlert(response.message, 'error');
                        actionBtn.disabled = false;
                        actionBtn.innerHTML = originalText;
                    }
                },
                error: function() {
                    showAlert('An error occurred while processing the request', 'error');
                    actionBtn.disabled = false;
                    actionBtn.innerHTML = originalText;
                },
                complete: function() {
                    $('#loadingOverlay').hide();
                }
            });
        }

        function showAlert(message, type) {
            const alert = $('#actionAlert');
            const alertMessage = $('#alertMessage');
            
            alertMessage.text(message);
            
            if (type === 'success') {
                alert.removeClass('alert-danger').addClass('alert-success');
                alert.find('i').removeClass('fa-exclamation-triangle').addClass('fa-check-circle');
            } else {
                alert.removeClass('alert-success').addClass('alert-danger');
                alert.find('i').removeClass('fa-check-circle').addClass('fa-exclamation-triangle');
            }
            
            alert.fadeIn();
            
            setTimeout(() => {
                alert.fadeOut();
            }, 5000);
        }

        function updateVisitorCount() {
            const count = $('#visitorsTableBody tr').length;
            $('.visitors-count').text(count + ' Active');
            
            if (count === 0) {
                $('.visitors-table-container').html(`
                    <div class="empty-state">
                        <i class="fas fa-user-clock"></i>
                        <h4>No Active Visitors</h4>
                        <p>All visitors have checked out or no visits are currently in progress.</p>
                        <a href="../visitor/step1.php" class="refresh-btn" style="text-decoration: none; margin-top: 15px; display: inline-block;">
                            <i class="fas fa-plus me-2"></i>
                            Start New Visit
                        </a>
                    </div>
                `);
            }
        }

        function refreshOverview() {
            window.location.reload();
        }

        // Auto-refresh every 2 minutes
        setInterval(function() {
            refreshOverview();
        }, 120000);

        // Update times every minute
        setInterval(function() {
            updateRelativeTimes();
        }, 60000);

        function updateRelativeTimes() {
            $('.duration').each(function() {
                const row = $(this).closest('tr');
                const visitId = row.data('visit-id');
                // In a real implementation, you would calculate this based on actual checkin time
                // For now, we'll just increment the hour count
                const currentText = $(this).text();
                const hours = parseInt(currentText.match(/\d+/)[0]);
                $(this).text((hours + 1) + 'h ago');
            });
        }

        $(document).ready(function() {
            // Add CSS class for alert-danger if needed
            const style = document.createElement('style');
            style.textContent = `
                .alert-danger {
                    background: rgba(231, 76, 60, 0.1);
                    border: 1px solid var(--danger-color);
                    color: var(--danger-color);
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>