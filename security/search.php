<?php
session_start();
require_once '../includes/functions.php';
require_security();

// Handle search form submission
$searchResults = [];
$searchPerformed = false;

// Calculate shift duration for display
$shiftStart = $_SESSION['shift_start'] ?? time();
$shiftDuration = time() - $shiftStart;
$shiftHours = floor($shiftDuration / 3600);
$shiftMinutes = floor(($shiftDuration % 3600) / 60);
$shiftSeconds = $shiftDuration % 60;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $searchPerformed = true;
    $searchType = $_POST['search_type'] ?? 'visitors';
    $searchTerm = sanitize_input($_POST['search_term'] ?? '');
    $dateFrom = $_POST['date_from'] ?? '';
    $dateTo = $_POST['date_to'] ?? '';

    try {
        $database = new Database();
        $db = $database->getConnection();

        switch ($searchType) {
            case 'visitors':
                $query = "SELECT v.visitor_id, CONCAT(v.first_name, ' ', v.last_name) as name,
                                 v.id_number, v.phone, v.email, v.is_blocked, v.last_visit_date
                          FROM visitors v
                          WHERE (CONCAT(v.first_name, ' ', v.last_name) LIKE ? OR v.id_number LIKE ?)";
                $params = ["%$searchTerm%", "%$searchTerm%"];

                if ($dateFrom && $dateTo) {
                    $query .= " AND v.last_visit_date BETWEEN ? AND ?";
                    $params[] = $dateFrom;
                    $params[] = $dateTo;
                }

                $query .= " ORDER BY v.last_visit_date DESC";
                break;

            case 'residents':
                $query = "SELECT r.resident_id, CONCAT(r.first_name, ' ', r.last_name) as name,
                                 r.student_number, r.phone, r.email, r.room_id, r.is_active
                          FROM residents r
                          WHERE (CONCAT(r.first_name, ' ', r.last_name) LIKE ? OR r.student_number LIKE ?)";
                $params = ["%$searchTerm%", "%$searchTerm%"];

                if ($dateFrom && $dateTo) {
                    $query .= " AND r.created_at BETWEEN ? AND ?";
                    $params[] = $dateFrom;
                    $params[] = $dateTo;
                }

                $query .= " ORDER BY r.created_at DESC";
                break;

            case 'alerts':
                $query = "SELECT a.alert_id, a.alert_type, a.message, a.created_at, a.alert_status,
                                 CONCAT(v.first_name, ' ', v.last_name) as visitor_name, v.id_number
                          FROM security_alerts a
                          LEFT JOIN visitors v ON a.visitor_id = v.visitor_id
                          WHERE (a.message LIKE ? OR a.alert_type LIKE ?)";
                $params = ["%$searchTerm%", "%$searchTerm%"];

                if ($dateFrom && $dateTo) {
                    $query .= " AND a.created_at BETWEEN ? AND ?";
                    $params[] = $dateFrom;
                    $params[] = $dateTo;
                }

                $query .= " ORDER BY a.created_at DESC";
                break;

            default:
                $query = "";
                $params = [];
        }

        if ($query) {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Log the search activity
        log_activity($_SESSION['user_id'], 'search_performed', "Search type: $searchType, Term: $searchTerm");

    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        $searchResults = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - Sophen Security</title>
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

        .search-content {
            padding: 30px;
        }

        .search-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin-bottom: 30px;
        }

        .search-header {
            color: var(--primary-color);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }

        .search-header i {
            margin-right: 10px;
            color: var(--security-color);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
        }

        .form-label {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--security-color);
            box-shadow: 0 0 0 0.2rem rgba(41, 128, 185, 0.25);
            outline: none;
        }

        .search-btn {
            background: var(--security-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            align-self: flex-end;
        }

        .search-btn:hover {
            background: #21618c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(41, 128, 185, 0.3);
        }

        .results-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .results-header {
            background: var(--light-gray);
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .results-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
        }

        .results-count {
            background: var(--security-color);
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .results-table {
            width: 100%;
        }

        .results-table th {
            background: var(--light-gray);
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
            border: none;
        }

        .results-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            font-size: 0.9rem;
            vertical-align: middle;
        }

        .results-table tr:hover {
            background: rgba(41, 128, 185, 0.05);
        }

        .result-info {
            display: flex;
            flex-direction: column;
        }

        .result-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 2px;
        }

        .result-id {
            font-size: 0.8rem;
            color: var(--dark-gray);
            opacity: 0.8;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }

        .status-blocked {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .status-resolved {
            background: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
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

        .alert-info {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid var(--secondary-color);
            color: var(--secondary-color);
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

            .search-content {
                padding: 20px;
            }

            .results-table {
                font-size: 0.8rem;
            }

            .results-table th,
            .results-table td {
                padding: 10px 8px;
            }
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
                <a href="search.php" class="nav-link active">
                    <i class="fas fa-search"></i>
                    Search
                </a>
            </div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Reports
                </a>
            </div>
            <div class="nav-item">
                <a href="end-shift.php" class="nav-link">
                    <i class="fas fa-clock"></i>
                    End Shift
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    Settings
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
            <h1 class="page-title">Security Search</h1>
            <div class="navbar-actions">
                <a href="index.php" class="action-btn">
                    <i class="fas fa-tachometer-alt"></i>
                </a>
                <a href="logout.php" class="action-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Search Content -->
        <div class="search-content">
            <!-- Search Form -->
            <div class="search-container">
                <h3 class="search-header">
                    <i class="fas fa-search"></i>
                    Search Database
                </h3>

                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Search Type</label>
                                <select class="form-select" name="search_type" required>
                                    <option value="visitors">Visitors</option>
                                    <option value="residents">Residents</option>
                                    <option value="alerts">Security Alerts</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Search Term</label>
                                <input type="text" class="form-control" name="search_term" placeholder="Enter name, ID, or keyword" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Date From (Optional)</label>
                                <input type="date" class="form-control" name="date_from">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Date To (Optional)</label>
                                <input type="date" class="form-control" name="date_to">
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="search" class="search-btn">
                        <i class="fas fa-search me-2"></i>
                        Search
                    </button>
                </form>
            </div>

            <!-- Search Results -->
            <?php if ($searchPerformed): ?>
            <div class="results-container">
                <div class="results-header">
                    <h3 class="results-title">
                        <i class="fas fa-list"></i>
                        Search Results
                    </h3>
                    <span class="results-count"><?= count($searchResults) ?> Found</span>
                </div>

                <?php if (!empty($searchResults)): ?>
                <div class="table-responsive">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <?php if ($_POST['search_type'] === 'visitors'): ?>
                                <th>Name</th>
                                <th>ID Number</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Last Visit</th>
                                <?php elseif ($_POST['search_type'] === 'residents'): ?>
                                <th>Name</th>
                                <th>Student Number</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Room</th>
                                <th>Status</th>
                                <?php else: ?>
                                <th>Alert Type</th>
                                <th>Message</th>
                                <th>Visitor</th>
                                <th>Status</th>
                                <th>Date</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($searchResults as $result): ?>
                            <tr>
                                <?php if ($_POST['search_type'] === 'visitors'): ?>
                                <td>
                                    <div class="result-info">
                                        <div class="result-name"><?= htmlspecialchars($result['name']) ?></div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($result['id_number']) ?></td>
                                <td><?= htmlspecialchars($result['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($result['email'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= $result['is_blocked'] ? 'status-blocked' : 'status-active' ?>">
                                        <?= $result['is_blocked'] ? 'Blocked' : 'Active' ?>
                                    </span>
                                </td>
                                <td><?= $result['last_visit_date'] ? date('M j, Y', strtotime($result['last_visit_date'])) : 'Never' ?></td>
                                <?php elseif ($_POST['search_type'] === 'residents'): ?>
                                <td>
                                    <div class="result-info">
                                        <div class="result-name"><?= htmlspecialchars($result['name']) ?></div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($result['student_number']) ?></td>
                                <td><?= htmlspecialchars($result['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($result['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($result['room_id'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= $result['is_active'] ? 'status-active' : 'status-blocked' ?>">
                                        <?= $result['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <?php else: ?>
                                <td>
                                    <span class="status-badge status-<?= strtolower($result['alert_type']) ?>">
                                        <?= ucfirst($result['alert_type']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($result['message']) ?></td>
                                <td><?= htmlspecialchars($result['visitor_name'] ?? 'N/A') ?> (<?= htmlspecialchars($result['id_number'] ?? 'N/A') ?>)</td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($result['alert_status']) ?>">
                                        <?= ucfirst($result['alert_status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y H:i', strtotime($result['created_at'])) ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h4>No Results Found</h4>
                    <p>Try adjusting your search criteria or search term.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Info Alert -->
            <div class="alert alert-info" id="searchInfo">
                <i class="fas fa-info-circle me-2"></i>
                <span>Use the form above to search for visitors, residents, or security alerts in the system.</span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Show info alert if no search has been performed
        $(document).ready(function() {
            <?php if (!$searchPerformed): ?>
            $('#searchInfo').show();
            <?php endif; ?>
        });

        // Form validation
        $('form').on('submit', function(e) {
            const searchTerm = $('input[name="search_term"]').val().trim();
            if (searchTerm.length < 2) {
                alert('Please enter at least 2 characters for the search term.');
                e.preventDefault();
            }
        });

        // Shift duration updater
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
            updateShiftDuration();
            setInterval(updateShiftDuration, 1000);
        });
    </script>
</body>
</html>
