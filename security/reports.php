<?php
session_start();
require_once '../includes/functions.php';
require_security();
require_once __DIR__ . '/../vendor/autoload.php';

// Calculate shift duration for display
$shiftStart = $_SESSION['shift_start'] ?? time();
$shiftDuration = time() - $shiftStart;
$shiftHours = floor($shiftDuration / 3600);
$shiftMinutes = floor(($shiftDuration % 3600) / 60);
$shiftSeconds = $shiftDuration % 60;

// Handle AJAX requests for generating reports
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $action = $_POST['action'];
        
        if ($action === 'generate_daily_report') {
            $date = $_POST['date'] ?? date('Y-m-d');
            
            // Fetch shift data
            $stmt = $db->prepare("
                SELECT COUNT(*) as total_visitors,
                       SUM(CASE WHEN visit_status = 'checked_in' THEN 1 ELSE 0 END) as active_visitors,
                       SUM(CASE WHEN visit_status = 'checked_out' THEN 1 ELSE 0 END) as checked_out_visitors,
                       AVG(TIMESTAMPDIFF(MINUTE, actual_checkin, actual_checkout)) as avg_duration
                FROM visits
                WHERE DATE(actual_checkin) = ? OR DATE(actual_checkout) = ?
            ");
            $stmt->execute([$date, $date]);
            $shiftData = $stmt->fetch(PDO::FETCH_ASSOC);

            // Compute shift duration (for today's report only)
            $isToday = ($date === date('Y-m-d'));
            if ($isToday && isset($_SESSION['shift_start'])) {
                $shiftDuration = time() - (int)$_SESSION['shift_start'];
                $sdHours = max(0, (int)floor($shiftDuration / 3600));
                $sdMinutes = max(0, (int)floor(($shiftDuration % 3600) / 60));
                $sdSeconds = max(0, $shiftDuration % 60);
                $shiftDurationStr = sprintf('%02d:%02d:%02d', $sdHours, $sdMinutes, $sdSeconds);
            } else {
                $shiftDurationStr = 'N/A';
            }
            
            // Fetch visitors
            $visitorsStmt = $db->prepare("
                SELECT v.visit_id, CONCAT(vi.first_name, ' ', vi.last_name) as visitor_name,
                       vi.id_number, r.room_number, v.host_name, v.actual_checkin, v.actual_checkout,
                       v.visit_status, TIMESTAMPDIFF(MINUTE, v.actual_checkin, v.actual_checkout) as duration
                FROM visits v
                JOIN visitors vi ON v.visitor_id = vi.visitor_id
                LEFT JOIN rooms r ON v.room_id = r.room_id
                WHERE DATE(v.actual_checkin) = ? OR DATE(v.actual_checkout) = ?
                ORDER BY v.actual_checkin DESC
            ");
            $visitorsStmt->execute([$date, $date]);
            $visitors = $visitorsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch alerts
            $alertsStmt = $db->prepare("
                SELECT alert_type, message, created_at
                FROM security_alerts
                WHERE DATE(created_at) = ?
                ORDER BY created_at DESC
            ");
            $alertsStmt->execute([$date]);
            $alerts = $alertsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Generate PDF (simplified - in real implementation, use TCPDF or similar)
            $pdfContent = "Daily Shift Report for $date\n\n";
            $pdfContent .= "Shift Duration: $shiftDurationStr\n";
            $pdfContent .= "Total Visitors: {$shiftData['total_visitors']}\n";
            $pdfContent .= "Active Visitors: {$shiftData['active_visitors']}\n";
            $pdfContent .= "Checked Out: {$shiftData['checked_out_visitors']}\n";
            $pdfContent .= "Average Duration: " . round($shiftData['avg_duration'] ?? 0) . " minutes\n\n";
            
            $pdfContent .= "Visitors:\n";
            foreach ($visitors as $visitor) {
                $pdfContent .= "- {$visitor['visitor_name']} ({$visitor['id_number']}) - {$visitor['visit_status']}\n";
            }
            
            $pdfContent .= "\nAlerts:\n";
            foreach ($alerts as $alert) {
                $pdfContent .= "- {$alert['alert_type']}: {$alert['message']}\n";
            }
            
            // Ensure reports directory exists
            $reportsDir = __DIR__ . '/../uploads/reports/';
            if (!is_dir($reportsDir)) {
                @mkdir($reportsDir, 0775, true);
            }

            // Generate PDF and save to file (in uploads/reports)
            $fileName = "daily_shift_report_{$date}_{$_SESSION['user_id']}.pdf";
            $filePath = $reportsDir . $fileName;
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->setPaper('A4', 'portrait');
            // Render the plain-text content inside a <pre> block for simple formatting
            $dompdf->loadHtml('<pre style="font-family:DejaVu Sans, Arial; font-size:12px;">' . htmlspecialchars($pdfContent) . '</pre>');
            $dompdf->render();
            $output = $dompdf->output();
            file_put_contents($filePath, $output);
            
            // Persist using generated_reports and report_types
            // Ensure the 'daily_shift_report' type exists
            $typeCheck = $db->prepare("SELECT report_type_id FROM report_types WHERE report_name = ? LIMIT 1");
            $typeCheck->execute(['daily_shift_report']);
            $typeRow = $typeCheck->fetch(PDO::FETCH_ASSOC);
            if (!$typeRow) {
                $typeInsert = $db->prepare("INSERT INTO report_types (report_name, report_description) VALUES (?, ?)");
                $typeInsert->execute(['daily_shift_report', 'Security daily shift summary']);
                $typeId = (int)$db->lastInsertId();
            } else {
                $typeId = (int)$typeRow['report_type_id'];
            }

            // Insert the generated report metadata
            $genInsert = $db->prepare("INSERT INTO generated_reports (report_type_id, generated_by, generation_date, file_name, file_path) VALUES (?, ?, ?, ?, ?)");
            $genInsert->execute([$typeId, $_SESSION['user_id'], $date . ' 00:00:00', $fileName, $fileName]);
            
            echo json_encode(['success' => true, 'message' => 'Daily PDF report generated successfully', 'file' => $fileName]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Get recent reports
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("
        SELECT gr.*, u.first_name, u.last_name
        FROM generated_reports gr
        JOIN report_types rt ON gr.report_type_id = rt.report_type_id
        JOIN users u ON gr.generated_by = u.user_id
        WHERE rt.report_name = 'daily_shift_report'
        ORDER BY gr.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Security Reports error: " . $e->getMessage());
    $recentReports = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Sophen Security</title>
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

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--security-color);
            box-shadow: 0 0 0 0.2rem rgba(41, 128, 185, 0.25);
            outline: none;
        }

        .generate-btn {
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

        .generate-btn:hover {
            background: #21618c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(41, 128, 185, 0.3);
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
            background: rgba(41, 128, 185, 0.05);
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

        .alert-success, .alert-danger {
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
            border-top: 4px solid var(--security-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

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
                <a href="search.php" class="nav-link">
                    <i class="fas fa-search"></i>
                    Search
                </a>
            </div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link active">
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
            <h1 class="page-title">Shift Reports</h1>
            <div class="navbar-actions">
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
                    Generate Daily Shift Report
                </h3>
                
                <form id="reportForm">
                    <div class="form-group">
                        <label class="form-label">Select Date</label>
                        <input type="date" class="form-control" id="reportDate" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <button type="submit" class="generate-btn">
                        <i class="fas fa-file-pdf me-2"></i>
                        Generate PDF Report
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

            <!-- Recent Reports -->
            <div class="recent-reports">
                <div class="reports-header">
                    <h3 class="reports-title">
                        <i class="fas fa-history me-2"></i>
                        Recent Reports
                    </h3>
                </div>

                <?php if (!empty($recentReports)): ?>
                <div class="table-responsive">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Report Details</th>
                                <th>Generated By</th>
                                <th>Date Generated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentReports as $report): ?>
                            <tr>
                                <td>
                                    <div class="report-info">
                                        <div class="report-name">Daily Shift Report - <?= date('M j, Y', strtotime($report['generation_date'])) ?></div>
                                        <div class="report-description">Generated on <?= date('M j, Y H:i', strtotime($report['created_at'])) ?></div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($report['first_name'] . ' ' . $report['last_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($report['created_at'])) ?></td>
                                <td>
                                    <button class="download-btn" onclick="downloadReport('<?= $report['file_name'] ?>')">
                                        <i class="fas fa-download me-1"></i>Export
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
                    <p>Generate your first daily report to see it listed here.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        });
        // Report form submission
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'generate_daily_report');
            formData.append('date', document.getElementById('reportDate').value);
            
            // Show loading
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            fetch('reports.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingOverlay').style.display = 'none';
                
                if (data.success) {
                    document.getElementById('successAlert').style.display = 'block';
                    document.getElementById('successMessage').textContent = data.message;
                    setTimeout(() => {
                        document.getElementById('successAlert').style.display = 'none';
                        location.reload(); // Refresh to show new report
                    }, 2000);
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
                document.getElementById('errorAlert').style.display = 'block';
                document.getElementById('errorMessage').textContent = 'An error occurred: ' + error.message;
                setTimeout(() => {
                    document.getElementById('errorAlert').style.display = 'none';
                }, 5000);
            });
        });

        // Download report
        function downloadReport(fileName) {
            window.open('../uploads/reports/' + fileName, '_blank');
        }
    </script>
</body>
</html>
