<?php
session_start();
require_once '../includes/functions.php';
require_admin();

// Handle AJAX requests for blocked visitor actions
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $action = $_POST['action'];
        
        switch ($action) {
            case 'unblock':
                $blockId = (int)$_POST['block_id'];
                $unblockReason = trim($_POST['unblock_reason']);
                
                $stmt = $db->prepare("
                    UPDATE visitor_blocks 
                    SET block_status = 'unblocked', 
                        unblocked_by_admin_id = ?, 
                        unblock_date = NOW(),
                        unblock_reason = ?
                    WHERE block_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $unblockReason, $blockId]);
                
                // Unblock the visitor
                $stmt = $db->prepare("
                    UPDATE visitors v 
                    JOIN visitor_blocks vb ON v.visitor_id = vb.visitor_id 
                    SET v.is_blocked = 0 
                    WHERE vb.block_id = ?
                ");
                $stmt->execute([$blockId]);
                
                echo json_encode(['success' => true, 'message' => 'Visitor unblocked successfully']);
                break;
                
            case 'extend':
                $blockId = (int)$_POST['block_id'];
                $additionalDays = (int)$_POST['additional_days'];
                
                // Add extension record
                $stmt = $db->prepare("
                    INSERT INTO block_extensions (block_id, extended_by_admin_id, extension_days, extension_reason)
                    VALUES (?, ?, ?, 'Extended by administrator')
                ");
                $stmt->execute([$blockId, $_SESSION['user_id'], $additionalDays]);
                
                // Update block period
                $stmt = $db->prepare("
                    UPDATE visitor_blocks 
                    SET block_period_days = block_period_days + ?
                    WHERE block_id = ?
                ");
                $stmt->execute([$additionalDays, $blockId]);
                
                echo json_encode(['success' => true, 'message' => "Block period extended by $additionalDays days"]);
                break;
                
            case 'search':
                $searchTerm = $_POST['search_term'];
                $stmt = $db->prepare("
                    SELECT vb.*, 
                           CONCAT(v.first_name, ' ', v.last_name) as visitor_name,
                           v.id_number, v.email, v.phone,
                           br.reason_description, br.severity_level,
                           CONCAT(u.first_name, ' ', u.last_name) as blocked_by_name,
                           DATE_ADD(vb.block_start_date, INTERVAL vb.block_period_days DAY) as block_end_date,
                           CASE 
                               WHEN vb.block_status = 'active' AND DATE_ADD(vb.block_start_date, INTERVAL vb.block_period_days DAY) < NOW() THEN 'expired'
                               ELSE vb.block_status 
                           END as actual_status
                    FROM visitor_blocks vb
                    JOIN visitors v ON vb.visitor_id = v.visitor_id
                    LEFT JOIN block_reasons br ON vb.reason_id = br.reason_id
                    JOIN users u ON vb.blocked_by_admin_id = u.user_id
                    WHERE vb.block_status IN ('active', 'unblocked') AND (
                        v.first_name LIKE ? OR v.last_name LIKE ? OR v.id_number LIKE ?
                    )
                    ORDER BY vb.created_at DESC
                    LIMIT 50
                ");
                $searchParam = "%$searchTerm%";
                $stmt->execute([$searchParam, $searchParam, $searchParam]);
                $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'blocks' => $blocks]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Action failed: ' . $e->getMessage()]);
    }
    exit();
}

// Get blocked visitors data
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all blocked visitors with details
    $stmt = $db->prepare("
        SELECT vb.*, 
               CONCAT(v.first_name, ' ', v.last_name) as visitor_name,
               v.id_number, v.email, v.phone,
               br.reason_description, br.severity_level,
               CONCAT(u.first_name, ' ', u.last_name) as blocked_by_name,
               DATE_ADD(vb.block_start_date, INTERVAL vb.block_period_days DAY) as block_end_date,
               CASE 
                   WHEN vb.block_status = 'active' AND DATE_ADD(vb.block_start_date, INTERVAL vb.block_period_days DAY) < NOW() THEN 'expired'
                   ELSE vb.block_status 
               END as actual_status
        FROM visitor_blocks vb
        JOIN visitors v ON vb.visitor_id = v.visitor_id
        LEFT JOIN block_reasons br ON vb.reason_id = br.reason_id
        JOIN users u ON vb.blocked_by_admin_id = u.user_id
        WHERE vb.block_status IN ('active', 'unblocked')
        ORDER BY vb.created_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $blockedVisitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visitor_blocks WHERE block_status = 'active'");
    $stmt->execute();
    $activeBlocks = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visitor_blocks WHERE block_status = 'unblocked'");
    $stmt->execute();
    $unblockedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visitor_blocks WHERE block_status = 'active' AND DATE_ADD(block_start_date, INTERVAL block_period_days DAY) < NOW()");
    $stmt->execute();
    $expiredBlocks = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Load active block reasons for the block modal
    $stmt = $db->prepare("SELECT reason_id, reason_description, default_block_days, severity_level FROM block_reasons WHERE is_active = 1 ORDER BY severity_level DESC, reason_description");
    $stmt->execute();
    $blockReasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load available (not currently blocked) visitors for the inline dropdown
    $stmt = $db->prepare("SELECT visitor_id, first_name, last_name, id_number, email FROM visitors WHERE is_blocked = 0 ORDER BY last_visit_date DESC, first_name ASC LIMIT 200");
    $stmt->execute();
    $availableVisitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Blocked visitors error: " . $e->getMessage());
    $blockedVisitors = [];
    $activeBlocks = $unblockedCount = $expiredBlocks = 0;
    $blockReasons = [];
    $availableVisitors = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blocked Visitors - Sophen Admin</title>
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

        /* Sidebar styles (consistent with other admin pages) */
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

        .navbar-actions {
            display: flex;
            gap: 15px;
        }

        .action-btn {
            color: var(--dark-gray);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background 0.3s ease;
            font-size: 1.1rem;
        }

        .action-btn:hover {
            background: var(--light-gray);
            color: var(--primary-color);
        }

        .blocked-content {
            padding: 30px;
        }

        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid;
            text-align: center;
        }

        .stat-card.danger { border-left-color: var(--danger-color); }
        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-number.danger { color: var(--danger-color); }
        .stat-number.success { color: var(--success-color); }
        .stat-number.warning { color: var(--warning-color); }

        .stat-label {
            color: var(--dark-gray);
            font-size: 0.9rem;
            font-weight: 600;
        }

        .search-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: end;
        }

        .search-group {
            flex: 1;
        }

        .search-label {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
            font-size: 0.9rem;
        }

        .search-input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--admin-color);
            outline: none;
        }

        .search-btn {
            background: var(--admin-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: #7d3c98;
            transform: translateY(-1px);
        }

        .blocks-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .container-header {
            background: var(--light-gray);
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .container-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
        }

        .blocks-count {
            background: var(--danger-color);
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .blocks-table {
            width: 100%;
            margin: 0;
        }

        .blocks-table th {
            background: var(--light-gray);
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
            border: none;
        }

        .blocks-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            font-size: 0.9rem;
            vertical-align: middle;
        }

        .blocks-table tr:hover {
            background: rgba(231, 76, 60, 0.05);
        }

        .visitor-info {
            display: flex;
            flex-direction: column;
        }

        .visitor-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 3px;
        }

        .visitor-id {
            font-size: 0.8rem;
            color: var(--dark-gray);
            opacity: 0.8;
        }

        .block-reason {
            background: var(--danger-color);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 5px;
        }

        .severity-level {
            font-size: 0.7rem;
            color: var(--dark-gray);
            opacity: 0.7;
        }

        .block-dates {
            display: flex;
            flex-direction: column;
            font-size: 0.8rem;
        }

        .block-start {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 3px;
        }

        .block-end {
            color: var(--dark-gray);
        }

        .block-end.expired {
            color: var(--warning-color);
            font-weight: 600;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            text-align: center;
        }

        .status-active {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .status-expired {
            background: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }

        .status-unblocked {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .table-action-btn {
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

        .btn-unblock {
            background: var(--success-color);
            color: white;
        }

        .btn-unblock:hover {
            background: #229954;
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

        .btn-disabled {
            background: var(--light-gray);
            color: var(--dark-gray);
            cursor: not-allowed;
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

        /* Modal Styles */
        .modal-header {
            background: var(--admin-color);
            color: white;
        }

        .modal-title {
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            width: 100%;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--admin-color);
            box-shadow: 0 0 0 0.2rem rgba(142, 68, 173, 0.25);
            outline: none;
        }

        .alert-success, .alert-danger {
            border-radius: 8px;
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

            .blocked-content {
                padding: 20px;
            }

            .search-form {
                flex-direction: column;
                gap: 15px;
            }

            .stats-section {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .blocks-table {
                font-size: 0.8rem;
            }

            .blocks-table th,
            .blocks-table td {
                padding: 10px 8px;
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
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

        /* Top action area */
        .top-action-area {
            display: flex;
            justify-content: flex-end;
            margin: 10px 0 20px;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Sidebar -->
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
                <a href="blocked-visitors.php" class="nav-link active">
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
            <h1 class="page-title">Blocked Visitors</h1>
            <div class="navbar-actions">
                <a href="index.php" class="action-btn">
                    <i class="fas fa-tachometer-alt"></i>
                </a>
                <a href="logout.php" class="action-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Blocked Content -->
        <div class="blocked-content">
            <!-- Statistics Section -->
            <div class="stats-section">
                <div class="stat-card danger">
                    <div class="stat-number danger"><?= $activeBlocks ?></div>
                    <div class="stat-label">Currently Blocked</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-number warning"><?= $expiredBlocks ?></div>
                    <div class="stat-label">Expired Blocks</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-number success"><?= $unblockedCount ?></div>
                    <div class="stat-label">Unblocked</div>
                </div>
            </div>

            <!-- Top-right Block button (opens modal) -->
            <div class="top-action-area">
                <button type="button" class="btn btn-danger" onclick="openBlockModal()">
                    <i class="fas fa-ban me-1"></i>Block Visitor
                </button>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <form class="search-form" onsubmit="searchBlocks(event)">
                    <div class="search-group">
                        <label class="search-label">Search blocked visitors</label>
                        <input type="text" class="search-input" id="searchInput" placeholder="Search by name or ID number...">
                    </div>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                </form>
            </div>

            <!-- Success/Error Alerts -->
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle me-2"></i>
                <span id="successMessage">Action completed successfully</span>
            </div>
            <div class="alert alert-danger" id="errorAlert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span id="errorMessage">An error occurred</span>
            </div>

            <!-- Blocked Visitors Table -->
            <div class="blocks-container">
                <div class="container-header">
                    <h3 class="container-title">
                        <i class="fas fa-user-slash me-2"></i>
                        Blocked Visitors History
                    </h3>
                    <span class="blocks-count" id="blocksCount"><?= count($blockedVisitors) ?> records</span>
                </div>

                <?php if (!empty($blockedVisitors)): ?>
                <div class="table-responsive">
                    <table class="blocks-table">
                        <thead>
                            <tr>
                                <th>Visitor Information</th>
                                <th>Block Reason</th>
                                <th>Block Period</th>
                                <th>Blocked By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="blocksTableBody">
                            <?php foreach ($blockedVisitors as $block): ?>
                            <tr data-block-id="<?= $block['block_id'] ?>">
                                <td>
                                    <div class="visitor-info">
                                        <div class="visitor-name"><?= htmlspecialchars($block['visitor_name']) ?></div>
                                        <div class="visitor-id">ID: <?= htmlspecialchars($block['id_number']) ?></div>
                                        <?php if ($block['email']): ?>
                                        <div class="visitor-id"><?= htmlspecialchars($block['email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="block-reason"><?= htmlspecialchars($block['reason_description'] ?? 'No reason specified') ?></div>
                                    <div class="severity-level">Severity: <?= $block['severity_level'] ?? 'N/A' ?>/5</div>
                                </td>
                                <td>
                                    <div class="block-dates">
                                        <div class="block-start">From: <?= date('M j, Y', strtotime($block['block_start_date'])) ?></div>
                                        <div class="block-end <?= $block['actual_status'] === 'expired' ? 'expired' : '' ?>">
                                            Until: <?= date('M j, Y', strtotime($block['block_end_date'])) ?>
                                        </div>
                                        <div class="visitor-id"><?= $block['block_period_days'] ?> days</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="visitor-info">
                                        <div class="visitor-name"><?= htmlspecialchars($block['blocked_by_name']) ?></div>
                                        <div class="visitor-id"><?= date('M j, H:i', strtotime($block['created_at'])) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $block['actual_status'] ?>">
                                        <?= $block['actual_status'] === 'active' ? 'Blocked' : ucfirst($block['actual_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($block['actual_status'] === 'active'): ?>
                                        <button class="table-action-btn btn-unblock" onclick="openUnblockModal(<?= $block['block_id'] ?>, '<?= addslashes(htmlspecialchars($block['visitor_name'])) ?>')">
                                            <i class="fas fa-unlock me-1"></i>Unblock
                                        </button>
                                        <button class="table-action-btn btn-extend" onclick="openExtendModal(<?= $block['block_id'] ?>, '<?= addslashes(htmlspecialchars($block['visitor_name'])) ?>')">
                                            <i class="fas fa-plus-circle me-1"></i>Extend
                                        </button>
                                        <?php elseif ($block['actual_status'] === 'expired'): ?>
                                        <button class="table-action-btn btn-unblock" onclick="openUnblockModal(<?= $block['block_id'] ?>, '<?= addslashes(htmlspecialchars($block['visitor_name'])) ?>')">
                                            <i class="fas fa-unlock me-1"></i>Remove Block
                                        </button>
                                        <?php else: ?>
                                        <button class="table-action-btn btn-disabled" disabled>
                                            <i class="fas fa-check me-1"></i>Unblocked
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h4>No Blocked Visitors</h4>
                    <p>No visitors have been blocked in the system yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Unblock Visitor Modal -->
    <!-- Block Visitor Modal -->
    <div class="modal fade" id="blockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-slash me-2"></i>Block Visitor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="blockForm" onsubmit="return false;">
                        <div class="mb-3">
                            <label for="selectVisitorModal" class="form-label">Select visitor</label>
                            <select id="selectVisitorModal" class="form-select">
                                <option value="" selected>Select visitor to block</option>
                                <?php foreach ($availableVisitors as $v): ?>
                                    <option value="<?= (int)$v['visitor_id'] ?>">
                                        <?= htmlspecialchars($v['first_name'] . ' ' . $v['last_name']) ?> (<?= htmlspecialchars($v['id_number']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-7">
                                <label for="blockReasonModal" class="form-label">Reason</label>
                                <select id="blockReasonModal" class="form-select">
                                    <option value="" selected disabled>Select reason</option>
                                    <?php if (!empty($blockReasons)): ?>
                                        <?php foreach ($blockReasons as $reason): ?>
                                            <option value="<?= (int)$reason['reason_id'] ?>" data-default-days="<?= (int)$reason['default_block_days'] ?>">
                                                <?= htmlspecialchars($reason['reason_description']) ?> (Severity <?= (int)$reason['severity_level'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="1">Default</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="blockDaysModal" class="form-label">Block days</label>
                                <input type="number" id="blockDaysModal" class="form-control" min="1" value="7" />
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmBlock()">
                        <i class="fas fa-ban me-1"></i>Block Visitor
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="unblockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-unlock me-2"></i>Unblock Visitor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to unblock <strong id="unblockVisitorName"></strong> and allow them to visit the residence again.</p>
                    
                    <form id="unblockForm">
                        <input type="hidden" id="unblockBlockId">
                        
                        <div class="form-group">
                            <label class="form-label">Reason for unblocking</label>
                            <textarea class="form-control" id="unblockReason" rows="3" placeholder="Explain why this visitor is being unblocked..." required></textarea>
                            <small class="text-muted">This will be recorded for audit purposes</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="confirmUnblock()">
                        <i class="fas fa-unlock me-2"></i>Unblock Visitor
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Extend Block Modal -->
    <div class="modal fade" id="extendModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Extend Block Period
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to extend the block period for <strong id="extendVisitorName"></strong>.</p>
                    
                    <form id="extendForm">
                        <input type="hidden" id="extendBlockId">
                        
                        <div class="form-group">
                            <label class="form-label">Additional days to extend</label>
                            <input type="number" class="form-control" id="extendDays" min="1" max="365" value="7" required>
                            <small class="text-muted">Number of additional days to add to the current block period</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="confirmExtend()">
                        <i class="fas fa-plus-circle me-2"></i>Extend Block
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        function searchBlocks(event) {
            event.preventDefault();
            const searchTerm = $('#searchInput').val().trim();
            
            if (!searchTerm) {
                showAlert('Please enter a search term', 'error');
                return;
            }

            $('#loadingOverlay').show();

            $.ajax({
                url: 'blocked-visitors.php',
                method: 'POST',
                data: {
                    action: 'search',
                    search_term: searchTerm
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        updateBlocksTable(response.blocks);
                        $('#blocksCount').text(response.blocks.length + ' records');
                    } else {
                        showAlert(response.message, 'error');
                    }
                },
                error: function() {
                    showAlert('Search request failed', 'error');
                },
                complete: function() {
                    $('#loadingOverlay').hide();
                }
            });
        }

        function updateBlocksTable(blocks) {
            const tbody = $('#blocksTableBody');
            tbody.empty();

            if (blocks.length === 0) {
                tbody.html(`
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-search"></i>
                            <h4>No Records Found</h4>
                            <p>No blocked visitors match your search criteria.</p>
                        </td>
                    </tr>
                `);
                return;
            }

            blocks.forEach(block => {
                const blockStart = new Date(block.block_start_date).toLocaleDateString('en-ZA', {month: 'short', day: 'numeric', year: 'numeric'});
                const blockEnd = new Date(block.block_end_date).toLocaleDateString('en-ZA', {month: 'short', day: 'numeric', year: 'numeric'});
                const createdAt = new Date(block.created_at).toLocaleDateString('en-ZA', {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});

                let actionButtons = '';
                if (block.actual_status === 'active') {
                    actionButtons = `
                        <button class="table-action-btn btn-unblock" onclick="openUnblockModal(${block.block_id}, '${block.visitor_name.replace(/'/g, "\\'")}')">
                            <i class="fas fa-unlock me-1"></i>Unblock
                        </button>
                        <button class="table-action-btn btn-extend" onclick="openExtendModal(${block.block_id}, '${block.visitor_name.replace(/'/g, "\\'")}')">
                            <i class="fas fa-plus-circle me-1"></i>Extend
                        </button>
                    `;
                } else if (block.actual_status === 'expired') {
                    actionButtons = `
                        <button class="table-action-btn btn-unblock" onclick="openUnblockModal(${block.block_id}, '${block.visitor_name.replace(/'/g, "\\'")}')">
                            <i class="fas fa-unlock me-1"></i>Remove Block
                        </button>
                    `;
                } else {
                    actionButtons = `
                        <button class="table-action-btn btn-disabled" disabled>
                            <i class="fas fa-check me-1"></i>Unblocked
                        </button>
                    `;
                }

                const row = `
                    <tr data-block-id="${block.block_id}">
                        <td>
                            <div class="visitor-info">
                                <div class="visitor-name">${block.visitor_name}</div>
                                <div class="visitor-id">ID: ${block.id_number}</div>
                                ${block.email ? `<div class="visitor-id">${block.email}</div>` : ''}
                            </div>
                        </td>
                        <td>
                            <div class="block-reason">${block.reason_description || 'No reason specified'}</div>
                            <div class="severity-level">Severity: ${block.severity_level || 'N/A'}/5</div>
                        </td>
                        <td>
                            <div class="block-dates">
                                <div class="block-start">From: ${blockStart}</div>
                                <div class="block-end ${block.actual_status === 'expired' ? 'expired' : ''}">Until: ${blockEnd}</div>
                                <div class="visitor-id">${block.block_period_days} days</div>
                             </div>
                        </td>
                        <td>
                            <div class="visitor-info">
                                <div class="visitor-name">${block.blocked_by_name}</div>
                                <div class="visitor-id">${createdAt}</div>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge status-${block.actual_status}">
                                ${block.actual_status === 'active' ? 'Blocked' : (block.actual_status.charAt(0).toUpperCase() + block.actual_status.slice(1))}
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                ${actionButtons}
                            </div>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        function openUnblockModal(blockId, visitorName) {
            $('#unblockBlockId').val(blockId);
            $('#unblockVisitorName').text(visitorName);
            $('#unblockReason').val('');
            $('#unblockModal').modal('show');
        }

        function confirmUnblock() {
            const blockId = $('#unblockBlockId').val();
            const reason = $('#unblockReason').val().trim();

            if (!reason) {
                showAlert('Please provide a reason for unblocking', 'error');
                return;
            }

            $('#loadingOverlay').show();
            $('#unblockModal').modal('hide');

            $.ajax({
                url: 'blocked-visitors.php',
                method: 'POST',
                data: {
                    action: 'unblock',
                    block_id: blockId,
                    unblock_reason: reason
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        // Update the row status
                        const row = $(`tr[data-block-id="${blockId}"]`);
                        row.find('.status-badge').removeClass('status-active status-expired').addClass('status-unblocked').text('Unblocked');
                        row.find('.action-buttons').html('<button class="table-action-btn btn-disabled" disabled><i class="fas fa-check me-1"></i>Unblocked</button>');
                        
                        // Update statistics
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showAlert(response.message, 'error');
                    }
                },
                error: function() {
                    showAlert('Unblock request failed', 'error');
                },
                complete: function() {
                    $('#loadingOverlay').hide();
                }
            });
        }

        function openExtendModal(blockId, visitorName) {
            $('#extendBlockId').val(blockId);
            $('#extendVisitorName').text(visitorName);
            $('#extendDays').val(7);
            $('#extendModal').modal('show');
        }

        function confirmExtend() {
            const blockId = $('#extendBlockId').val();
            const additionalDays = $('#extendDays').val();

            if (!additionalDays || additionalDays < 1) {
                showAlert('Please enter a valid number of days', 'error');
                return;
            }

            $('#loadingOverlay').show();
            $('#extendModal').modal('hide');

            $.ajax({
                url: 'blocked-visitors.php',
                method: 'POST',
                data: {
                    action: 'extend',
                    block_id: blockId,
                    additional_days: additionalDays
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showAlert(response.message, 'error');
                    }
                },
                error: function() {
                    showAlert('Extend request failed', 'error');
                },
                complete: function() {
                    $('#loadingOverlay').hide();
                }
            });
        }

        function showAlert(message, type) {
            const alertId = type === 'success' ? '#successAlert' : '#errorAlert';
            const messageId = type === 'success' ? '#successMessage' : '#errorMessage';
            
            $(messageId).text(message);
            $(alertId).fadeIn();
            
            setTimeout(() => {
                $(alertId).fadeOut();
            }, 5000);
        }

        $(document).ready(function() {
            // Modal reason selector updates modal block days
            $('#blockReasonModal').on('change', function() {
                const defDays = $(this).find('option:selected').data('default-days');
                if (defDays && Number(defDays) > 0) {
                    $('#blockDaysModal').val(defDays);
                }
            });
            // Real-time search
            let searchTimeout;
            $('#searchInput').on('input', function() {
                clearTimeout(searchTimeout);
                const searchTerm = $(this).val().trim();
                
                if (searchTerm.length >= 2) {
                    searchTimeout = setTimeout(() => {
                        const event = { preventDefault: () => {} };
                        searchBlocks(event);
                    }, 500);
                } else if (searchTerm.length === 0) {
                    window.location.reload();
                }
            });

            // Auto-refresh expired blocks every 5 minutes
            setInterval(() => {
                console.log('Checking for expired blocks...');
                // In a real implementation, this would check and update expired blocks
            }, 300000);
        });

        function openBlockModal() {
            $('#selectVisitorModal').val('');
            $('#blockReasonModal').val('');
            $('#blockDaysModal').val(7);
            $('#blockModal').modal('show');
        }

        // Removed ID-search flow in favor of modal dropdown selection

        function confirmBlock() {
            const visitorId = $('#selectVisitorModal').val();
            const reasonId = $('#blockReasonModal').val();
            const blockDays = parseInt($('#blockDaysModal').val(), 10);
            if (!visitorId) {
                showAlert('Please select a visitor to block', 'error');
                return;
            }
            if (!reasonId) {
                showAlert('Please select a block reason', 'error');
                return;
            }
            if (!blockDays || blockDays < 1) {
                showAlert('Please enter a valid number of days', 'error');
                return;
            }
            $('#loadingOverlay').show();
            $.ajax({
                url: 'process/visitor-actions.php',
                method: 'POST',
                data: {
                    action: 'block_visitor',
                    visitor_id: visitorId,
                    reason_id: reasonId,
                    block_days: blockDays
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Visitor blocked successfully', 'success');
                        $('#blockModal').modal('hide');
                        setTimeout(() => { window.location.reload(); }, 1500);
                    } else {
                        showAlert(response.message || 'Block request failed', 'error');
                    }
                },
                error: function() {
                    showAlert('Block request failed', 'error');
                },
                complete: function() {
                    $('#loadingOverlay').hide();
                }
            });
        }

        // Inline flow removed in favor of modal popup
    </script>
</body>
</html>
