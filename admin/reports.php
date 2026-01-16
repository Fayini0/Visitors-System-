<?php
session_start();
require_once '../includes/functions.php';
require_admin();

// Handle AJAX requests by redirecting to report-generation.php
if ($_POST && isset($_POST['action'])) {
    require_once 'process/report-generation.php';
    exit();
}

// Get recent reports
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("
        SELECT gr.*, rt.report_name, rt.report_description,
               CONCAT(u.first_name, ' ', u.last_name) as generated_by_name
        FROM generated_reports gr
        LEFT JOIN report_types rt ON gr.report_type_id = rt.report_type_id
        JOIN users u ON gr.generated_by = u.user_id
        ORDER BY gr.generation_date DESC
        LIMIT 20
    ");
    $stmt->execute();
    $recentReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Reports page error: " . $e->getMessage());
    $recentReports = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Sophen Admin</title>
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
            align-items: center;
            gap: 10px;
        }

        .action-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 1.2rem;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: rgba(142, 68, 173, 0.1);
            color: var(--admin-color);
        }

        .reports-content {
            padding: 30px;
        }

        .report-generator {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin-bottom: 30px;
        }

        .generator-header {
            color: var(--primary-color);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }

        .generator-header i {
            margin-right: 10px;
            color: var(--admin-color);
        }

        .report-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 20px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
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
            border-color: var(--admin-color);
            box-shadow: 0 0 0 0.2rem rgba(142, 68, 173, 0.25);
            outline: none;
        }

        .generate-btn {
            background: var(--admin-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            height: fit-content;
        }

        .generate-btn:hover {
            background: #7d3c98;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(142, 68, 173, 0.3);
        }

        .report-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .report-type-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--admin-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .report-type-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .report-card-icon {
            width: 50px;
            height: 50px;
            background: var(--admin-color);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .report-card-icon i {
            font-size: 24px;
            color: white;
        }

        .report-card-title {
            color: var(--primary-color);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .report-card-description {
            color: var(--dark-gray);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .recent-reports {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .reports-header {
            background: var(--light-gray);
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
        }

        .reports-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
        }

        .reports-table {
            width: 100%;
        }

        .reports-table th {
            background: var(--light-gray);
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
            border: none;
        }

        .reports-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            font-size: 0.9rem;
            vertical-align: middle;
        }

        .reports-table tr:hover {
            background: rgba(142, 68, 173, 0.05);
        }

        .report-info {
            display: flex;
            flex-direction: column;
        }

        .report-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 3px;
        }

        .report-description {
            font-size: 0.8rem;
            color: var(--dark-gray);
            opacity: 0.8;
        }

        .download-btn {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 6px 15px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .download-btn:hover {
            background: #229954;
            transform: translateY(-1px);
        }

        .alert-success, .alert-danger, .alert-info {
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
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
            .report-form {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .report-types-grid {
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

            .reports-content {
                padding: 20px;
            }

            .reports-table {
                font-size: 0.8rem;
            }

            .reports-table th,
            .reports-table td {
                padding: 10px 8px;
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
                <a href="blocked-visitors.php" class="nav-link">
                    <i class="fas fa-user-slash"></i>
                    Blocked Visitors
                </a>
            </div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link active">
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
            <h1 class="page-title">System Reports</h1>
            <div class="navbar-actions">
                <button id="sidebarToggle" class="action-btn d-md-none">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="index.php" class="action-btn">
                    <i class="fas fa-tachometer-alt"></i>
                </a>
                <a href="logout.php" class="action-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Reports Content -->
        <div class="reports-content">
            <!-- Report Generator -->
            <div class="report-generator">
                <h3 class="generator-header">
                    <i class="fas fa-file-pdf"></i>
                    Generate New Report
                </h3>
                
                <form class="report-form" id="reportForm">
                    <div class="form-group">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" id="reportType" name="report_type" required>
                            <option value="">Select Report Type</option>
                            <option value="daily_visitor_log">Daily Visitor Log</option>
                            <option value="weekly_visitor_report">Weekly Visitor Report</option>
                            <option value="monthly_visitor_log">Monthly Visitor Log</option>
                            <option value="security_incident_report">Security Incident Report</option>
                            <option value="blocked_visitor_report">Blocked Visitor Report</option>
                            <option value="visitor_frequency_report">Visitor Frequency Report</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" id="dateFrom" name="date_from" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" id="dateTo" name="date_to" required>
                    </div>

                    
                    <button type="submit" class="generate-btn">
                        <i class="fas fa-file-pdf me-2"></i>
                        Generate PDF
                    </button>
                </form>
            </div>

            <!-- Success/Error Alerts -->
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle me-2"></i>
                <span id="successMessage">Report generated successfully</span>
            </div>
            <div class="alert alert-danger" id="errorAlert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span id="errorMessage">An error occurred</span>
            </div>
            <div class="alert alert-info" id="infoAlert">
                <i class="fas fa-info-circle me-2"></i>
                <span id="infoMessage">Report is being generated...</span>
            </div>

            <!-- Report Types Grid -->
            <div class="report-types-grid">
                <div class="report-type-card" onclick="selectReportType('daily_visitor_log')">
                    <div class="report-card-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="report-card-title">Daily Visitor Log</div>
                    <div class="report-card-description">
                        Detailed log of all visitor check-ins and check-outs for a specific day or date range.
                    </div>
                </div>

                <div class="report-type-card" onclick="selectReportType('weekly_visitor_report')">
                    <div class="report-card-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="report-card-title">Weekly Visitor Report</div>
                    <div class="report-card-description">
                        Summary of visitor activity aggregated by day over a weekly period.
                    </div>
                </div>

                <div class="report-type-card" onclick="selectReportType('monthly_visitor_log')">
                    <div class="report-card-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="report-card-title">Monthly Visitor Log</div>
                    <div class="report-card-description">
                        Monthly statistics including total visits, unique visitors, and average duration.
                    </div>
                </div>

                <div class="report-type-card" onclick="selectReportType('security_incident_report')">
                    <div class="report-card-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="report-card-title">Security Incident Report</div>
                    <div class="report-card-description">
                        Comprehensive report of all security alerts, incidents, and responses.
                    </div>
                </div>

                <div class="report-type-card" onclick="selectReportType('blocked_visitor_report')">
                    <div class="report-card-icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="report-card-title">Blocked Visitor Report</div>
                    <div class="report-card-description">
                        Report of all blocked visitors, including reasons and block periods.
                    </div>
                </div>

                <div class="report-type-card" onclick="selectReportType('visitor_frequency_report')">
                    <div class="report-card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="report-card-title">Visitor Frequency Report</div>
                    <div class="report-card-description">
                        Analysis of visitor patterns, frequency, and duration statistics.
                    </div>
                </div>
            </div>

            <!-- Recent Reports -->
                <div class="recent-reports">
                    <div class="reports-header">
                        <h3 class="reports-title">
                            <i class="fas fa-history me-2"></i>
                            Recent Reports
                        </h3>
                        <button class="action-btn" id="refreshReportsBtn" onclick="refreshRecentReports()" title="Refresh Recent Reports" aria-label="Refresh Recent Reports">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    </div>

                <?php if (!empty($recentReports)): ?>
                <div class="table-responsive">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Report Details</th>
                                <th>Generated By</th>
                                <th>Date Generated</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recentReportsBody">
                            <?php foreach ($recentReports as $report): ?>
                            <tr>
                                <td>
                                    <div class="report-info">
                                        <div class="report-name"><?= htmlspecialchars($report['file_name'] ?? 'Unknown Report') ?></div>
                                        <div class="report-description"><?= htmlspecialchars($report['report_description'] ?? $report['report_name'] ?? 'No description') ?></div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($report['generated_by_name'] ?? 'System') ?></td>
                                <td><?= date('M j, Y H:i', strtotime($report['generation_date'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $report['report_status'] ?? 'unknown' ?>">
                                        <?= ucfirst($report['report_status'] ?? 'Unknown') ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="download-btn" style="margin-right:8px;" onclick="downloadReport(<?= $report['report_id'] ?>)" title="Download" aria-label="Download">
                                        <i class="fas fa-download me-1"></i>Download
                                    </button>
                                    <button class="download-btn" style="margin-right:8px;" onclick="viewReport(<?= $report['report_id'] ?>)" title="View" aria-label="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="download-btn" onclick="deleteReport(<?= $report['report_id'] ?>)" title="Delete" aria-label="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h4>No Reports Yet</h4>
                    <p>Generate your first report to see it listed here.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });

        // Report form submission
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const typeEl = document.getElementById('reportType');
            const fromEl = document.getElementById('dateFrom');
            const toEl = document.getElementById('dateTo');

            const reportType = typeEl.value;
            const fromVal = fromEl.value;
            const toVal = toEl.value;

            // Hide previous alerts
            document.getElementById('successAlert').style.display = 'none';
            document.getElementById('errorAlert').style.display = 'none';
            document.getElementById('infoAlert').style.display = 'none';

            // Basic validation
            if (!reportType) {
                document.getElementById('errorAlert').style.display = 'block';
                document.getElementById('errorMessage').textContent = 'Please select a report type.';
                return;
            }

            if (!fromVal || !toVal) {
                document.getElementById('errorAlert').style.display = 'block';
                document.getElementById('errorMessage').textContent = 'Please select both From and To dates.';
                return;
            }

            const fromDate = new Date(fromVal);
            const toDate = new Date(toVal);
            if (fromDate > toDate) {
                document.getElementById('errorAlert').style.display = 'block';
                document.getElementById('errorMessage').textContent = 'From Date must be on or before To Date.';
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'generate_report');

            // Show loading + info
            document.getElementById('loadingOverlay').style.display = 'flex';
            document.getElementById('infoAlert').style.display = 'block';
            document.getElementById('infoMessage').textContent = 'Report is being generated...';

            fetch('process/report-generation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingOverlay').style.display = 'none';
                document.getElementById('infoAlert').style.display = 'none';

                if (data.success) {
                    document.getElementById('successAlert').style.display = 'block';
                    document.getElementById('successMessage').textContent = data.message;
                    // Refresh recent reports without a manual reload
                    refreshRecentReports();
                    setTimeout(() => {
                        document.getElementById('successAlert').style.display = 'none';
                    }, 5000);
                } else {
                    document.getElementById('errorAlert').style.display = 'block';
                    document.getElementById('errorMessage').textContent = data.message;
                    setTimeout(() => {
                        document.getElementById('errorAlert').style.display = 'none';
                    }, 5000);
                }
            })
            .catch(error => {
                document.getElementById('loadingOverlay').style.display = 'none';
                document.getElementById('infoAlert').style.display = 'none';
                document.getElementById('errorAlert').style.display = 'block';
                document.getElementById('errorMessage').textContent = 'An error occurred: ' + error.message;
                setTimeout(() => {
                    document.getElementById('errorAlert').style.display = 'none';
                }, 5000);
            });
        });

        // Select report type from card
        function selectReportType(type) {
            document.getElementById('reportType').value = type;
            document.getElementById('reportForm').scrollIntoView({ behavior: 'smooth' });
        }

        // View report (opens PDF in a new tab)
        function viewReport(reportId) {
            const formData = new FormData();
            formData.append('action', 'download_report');
            formData.append('report_id', reportId);

            fetch('process/report-generation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.download_url) {
                    window.open(data.download_url, '_blank');
                } else {
                    alert('View failed: ' + (data.message || 'Unknown error'));
                }
            });
        }

        // Download report (forces browser download)
        function downloadReport(reportId) {
            const formData = new FormData();
            formData.append('action', 'download_report');
            formData.append('report_id', reportId);

            fetch('process/report-generation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.download_url) {
                    const a = document.createElement('a');
                    a.href = data.download_url;
                    try {
                        const name = data.download_url.split('/').pop();
                        a.download = name || '';
                    } catch (e) {}
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                } else {
                    alert('Download failed: ' + (data.message || 'Unknown error'));
                }
            });
        }

        // Delete report
        function deleteReport(reportId) {
            if (!confirm('Delete this report? This cannot be undone.')) return;
            const formData = new FormData();
            formData.append('action', 'delete_report');
            formData.append('report_id', reportId);

            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.style.display = 'flex';

            fetch('process/report-generation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (overlay) overlay.style.display = 'none';
                if (data.success) {
                    const success = document.getElementById('successAlert');
                    const msg = document.getElementById('successMessage');
                    if (success && msg) {
                        success.style.display = 'block';
                        msg.textContent = data.message || 'Report deleted successfully';
                        setTimeout(() => { success.style.display = 'none'; }, 4000);
                    }
                    refreshRecentReports();
                } else {
                    const err = document.getElementById('errorAlert');
                    const emsg = document.getElementById('errorMessage');
                    if (err && emsg) {
                        err.style.display = 'block';
                        emsg.textContent = data.message || 'Deletion failed';
                        setTimeout(() => { err.style.display = 'none'; }, 4000);
                    }
                }
            })
            .catch(error => {
                if (overlay) overlay.style.display = 'none';
                const err = document.getElementById('errorAlert');
                const emsg = document.getElementById('errorMessage');
                if (err && emsg) {
                    err.style.display = 'block';
                    emsg.textContent = 'An error occurred: ' + error.message;
                    setTimeout(() => { err.style.display = 'none'; }, 4000);
                }
            });
        }

        // Escape HTML helper
        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function capitalize(str) {
            if (!str) return '';
            return String(str).charAt(0).toUpperCase() + String(str).slice(1);
        }

        function formatDateStr(input) {
            try {
                const dt = new Date(input);
                return dt.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            } catch (e) { return escapeHTML(input || ''); }
        }

        // Refresh recent reports list
        function refreshRecentReports() {
            const fd = new FormData();
            fd.append('action', 'get_recent_reports');
            fetch('process/report-generation.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const container = document.querySelector('.recent-reports');
                    const empty = container ? container.querySelector('.empty-state') : null;
                    let tbody = document.getElementById('recentReportsBody');
                    if (!tbody && container) {
                        const tableHtml = `
                            <div class="table-responsive">
                                <table class="reports-table">
                                    <thead>
                                        <tr>
                                            <th>Report Details</th>
                                            <th>Generated By</th>
                                            <th>Date Generated</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentReportsBody"></tbody>
                                </table>
                            </div>`;
                        if (empty) { empty.insertAdjacentHTML('beforebegin', tableHtml); }
                        else { container.insertAdjacentHTML('beforeend', tableHtml); }
                        tbody = document.getElementById('recentReportsBody');
                    }
                    if (!tbody) return;
                    tbody.innerHTML = '';
                    const reports = Array.isArray(data.reports) ? data.reports : [];
                    if (reports.length) {
                        if (empty) empty.style.display = 'none';
                        reports.forEach(r => {
                            const status = r.report_status || 'unknown';
                            const row = `
                                <tr>
                                    <td>
                                        <div class="report-info">
                                            <div class="report-name">${escapeHTML(r.file_name || 'Unknown Report')}</div>
                                            <div class="report-description">${escapeHTML(r.report_description || r.report_name || 'No description')}</div>
                                        </div>
                                    </td>
                                    <td>${escapeHTML(r.generated_by_name || 'System')}</td>
                                    <td>${formatDateStr(r.generation_date)}</td>
                                    <td><span class="status-badge status-${escapeHTML(status)}">${escapeHTML(capitalize(status))}</span></td>
                                    <td>
                                        <button class="download-btn" style="margin-right:8px;" onclick="downloadReport(${r.report_id})" title="Download" aria-label="Download">
                                            <i class="fas fa-download me-1"></i>Download
                                        </button>
                                        <button class="download-btn" style="margin-right:8px;" onclick="viewReport(${r.report_id})" title="View" aria-label="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="download-btn" onclick="deleteReport(${r.report_id})" title="Delete" aria-label="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>`;
                            tbody.insertAdjacentHTML('beforeend', row);
                        });
                    } else {
                        if (empty) empty.style.display = 'block';
                    }
                })
                .catch(() => {});
        }

        // Set default dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const lastWeek = new Date(today);
            lastWeek.setDate(today.getDate() - 7);
            
            document.getElementById('dateFrom').value = lastWeek.toISOString().split('T')[0];
            document.getElementById('dateTo').value = today.toISOString().split('T')[0];
            // Try to populate recent reports on load as well
            refreshRecentReports();
            // Auto-refresh recent reports periodically
            setInterval(refreshRecentReports, 30000);
        });
    </script>
</body>
</html>
?>