<?php
session_start();
require_once '../../includes/functions.php';
require_admin();
// Composer autoload for third-party libraries (e.g., Dompdf)
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Handle AJAX requests for report generation
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $database = new Database();
        $db = $database->getConnection();

        $action = $_POST['action'];

        switch ($action) {
            case 'generate_report':
                $reportType = $_POST['report_type'];
                $dateFrom = $_POST['date_from'];
                $dateTo = $_POST['date_to'];
                $format = $_POST['format'] ?? 'pdf';

                // Validate input
                if (empty($reportType) || empty($dateFrom) || empty($dateTo)) {
                    echo json_encode(['success' => false, 'message' => 'All fields are required']);
                    break;
                }

                // Generate report data
                $reportData = generateReportData($db, $reportType, $dateFrom, $dateTo);

                if (empty($reportData)) {
                    echo json_encode(['success' => false, 'message' => 'No data found for the selected criteria']);
                    break;
                }

                // Create report record
                $stmt = $db->prepare("
                    INSERT INTO generated_reports (report_type_id, generated_by, generation_date, file_name, report_status)
                    VALUES ((SELECT report_type_id FROM report_types WHERE report_name = ?), ?, NOW(), ?, 'generated')
                ");
                $fileName = $reportType . '_' . date('Y-m-d_H-i-s') . '.pdf';
                $stmt->execute([$reportType, $_SESSION['user_id'], $fileName]);
                $reportId = $db->lastInsertId();

                // Generate the actual report file
                $filePath = generateReportFile($reportType, $reportData, $dateFrom, $dateTo, $fileName);

                // Update report record with file path
                $stmt = $db->prepare("UPDATE generated_reports SET file_path = ?, report_status = 'generated' WHERE report_id = ?");
                $stmt->execute([$filePath, $reportId]);

                // Log activity
                log_activity($_SESSION['user_id'], 'generate_report', "Generated $reportType report: $fileName");

                echo json_encode([
                    'success' => true,
                    'message' => 'Report generated successfully',
                    'report_id' => $reportId,
                    'file_name' => $fileName,
                    // Provide a direct URL to the generated file under the web root
                    'download_url' => SITE_URL . '/uploads/reports/' . $fileName
                ]);
                break;

            case 'get_report_types':
                $stmt = $db->prepare("SELECT report_name, report_description FROM report_types WHERE is_active = 1 ORDER BY report_name");
                $stmt->execute();
                $reportTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'report_types' => $reportTypes]);
                break;

            case 'get_recent_reports':
                $stmt = $db->prepare("
                    SELECT gr.*, rt.report_name, rt.report_description,
                           CONCAT(u.first_name, ' ', u.last_name) as generated_by_name
                    FROM generated_reports gr
                    LEFT JOIN report_types rt ON gr.report_type_id = rt.report_type_id
                    JOIN users u ON gr.generated_by = u.user_id
                    ORDER BY gr.generation_date DESC
                    LIMIT 10
                ");
                $stmt->execute();
                $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'reports' => $reports]);
                break;

            case 'delete_report':
                $reportId = (int)$_POST['report_id'];

                // Get file path before deletion
                $stmt = $db->prepare("SELECT file_path FROM generated_reports WHERE report_id = ?");
                $stmt->execute([$reportId]);
                $filePath = $stmt->fetch()['file_path'];

                // Delete file if exists
                if ($filePath && file_exists($filePath)) {
                    unlink($filePath);
                }

                // Delete database record
                $stmt = $db->prepare("DELETE FROM generated_reports WHERE report_id = ?");
                $stmt->execute([$reportId]);

                // Log activity
                log_activity($_SESSION['user_id'], 'delete_report', "Deleted report ID: $reportId");

                echo json_encode(['success' => true, 'message' => 'Report deleted successfully']);
                break;

            case 'download_report':
                $reportId = (int)($_POST['report_id'] ?? 0);

                if (!$reportId) {
                    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
                    break;
                }

                // Fetch report record
                $stmt = $db->prepare("SELECT file_path, file_name, report_status FROM generated_reports WHERE report_id = ?");
                $stmt->execute([$reportId]);
                $report = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$report) {
                    echo json_encode(['success' => false, 'message' => 'Report not found']);
                    break;
                }

                // Ensure the file exists on disk
                $diskPath = $report['file_path']; // e.g. '../../uploads/reports/<file>' relative to this script
                $fileName = $report['file_name'] ?: basename($diskPath);

                if (!$diskPath || !file_exists($diskPath)) {
                    echo json_encode(['success' => false, 'message' => 'File not found. It may have been moved or deleted.']);
                    break;
                }

                // Build a URL accessible from the browser
                $downloadUrl = SITE_URL . '/uploads/reports/' . $fileName;

                echo json_encode([
                    'success' => true,
                    'message' => 'Download ready',
                    'download_url' => $downloadUrl
                ]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Report generation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Report generation failed: ' . $e->getMessage()]);
    }
    exit();
}

// If not a POST request, redirect or show error
header('Location: ../reports.php');
exit();

function generateReportData($db, $reportType, $dateFrom, $dateTo) {
    switch ($reportType) {
        case 'daily_visitor_log':
            $stmt = $db->prepare("
                SELECT v.visit_id,
                       CONCAT(vi.first_name, ' ', vi.last_name) as visitor_name,
                       vi.id_number, r.room_number, v.host_name,
                       v.actual_checkin, v.actual_checkout, v.visit_status,
                       TIMESTAMPDIFF(MINUTE, v.actual_checkin, v.actual_checkout) as duration_minutes
                FROM visits v
                JOIN visitors vi ON v.visitor_id = vi.visitor_id
                LEFT JOIN rooms r ON v.room_id = r.room_id
                WHERE DATE(v.actual_checkin) BETWEEN ? AND ?
                ORDER BY v.actual_checkin DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'weekly_visitor_report':
            $stmt = $db->prepare("
                SELECT DATE(v.actual_checkin) as visit_date,
                       COUNT(*) as total_visits,
                       COUNT(CASE WHEN v.visit_status = 'checked_out' THEN 1 END) as completed_visits,
                       COUNT(CASE WHEN v.actual_checkout IS NULL THEN 1 END) as ongoing_visits,
                       COUNT(DISTINCT v.visitor_id) as unique_visitors,
                       AVG(CASE WHEN v.actual_checkout IS NOT NULL
                                THEN TIMESTAMPDIFF(MINUTE, v.actual_checkin, v.actual_checkout)
                                ELSE NULL END) as avg_duration_minutes
                FROM visits v
                WHERE DATE(v.actual_checkin) BETWEEN ? AND ?
                GROUP BY DATE(v.actual_checkin)
                ORDER BY visit_date DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'monthly_visitor_log':
            $stmt = $db->prepare("
                SELECT YEAR(v.actual_checkin) as year, MONTH(v.actual_checkin) as month,
                       COUNT(*) as total_visits,
                       COUNT(DISTINCT v.visitor_id) as unique_visitors,
                       AVG(TIMESTAMPDIFF(HOUR, v.actual_checkin, v.actual_checkout)) as avg_duration_hours,
                       SUM(TIMESTAMPDIFF(HOUR, v.actual_checkin, v.actual_checkout)) as total_duration_hours
                FROM visits v
                WHERE DATE(v.actual_checkin) BETWEEN ? AND ?
                AND v.actual_checkout IS NOT NULL
                GROUP BY YEAR(v.actual_checkin), MONTH(v.actual_checkin)
                ORDER BY year DESC, month DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'security_incident_report':
            $stmt = $db->prepare("
                SELECT a.alert_id, a.alert_type, a.severity, a.message,
                       CONCAT(v.first_name, ' ', v.last_name) as visitor_name,
                       v.id_number, r.room_number, a.created_at, a.alert_status,
                       CONCAT(u.first_name, ' ', u.last_name) as acknowledged_by_name
                FROM security_alerts a
                LEFT JOIN visitors v ON a.visitor_id = v.visitor_id
                LEFT JOIN rooms r ON a.room_id = r.room_id
                LEFT JOIN users u ON a.acknowledged_by = u.user_id
                WHERE DATE(a.created_at) BETWEEN ? AND ?
                ORDER BY a.created_at DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'blocked_visitor_report':
            $stmt = $db->prepare("
                SELECT CONCAT(v.first_name, ' ', v.last_name) as visitor_name,
                       v.id_number, v.email, br.reason_description,
                       vb.block_start_date, vb.block_period_days, vb.block_status,
                       CONCAT(u.first_name, ' ', u.last_name) as blocked_by_name
                FROM visitor_blocks vb
                JOIN visitors v ON vb.visitor_id = v.visitor_id
                LEFT JOIN block_reasons br ON vb.reason_id = br.reason_id
                LEFT JOIN users u ON vb.blocked_by_admin_id = u.user_id
                WHERE DATE(vb.block_start_date) BETWEEN ? AND ?
                ORDER BY vb.block_start_date DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'visitor_frequency_report':
            $stmt = $db->prepare("
                SELECT CONCAT(v.first_name, ' ', v.last_name) as visitor_name,
                       v.id_number, v.email, v.phone,
                       COUNT(vi.visit_id) as visit_count,
                       MAX(vi.actual_checkin) as last_visit,
                       AVG(TIMESTAMPDIFF(HOUR, vi.actual_checkin, vi.actual_checkout)) as avg_duration_hours,
                       SUM(TIMESTAMPDIFF(HOUR, vi.actual_checkin, vi.actual_checkout)) as total_duration_hours
                FROM visitors v
                LEFT JOIN visits vi ON v.visitor_id = vi.visitor_id
                WHERE DATE(vi.actual_checkin) BETWEEN ? AND ?
                GROUP BY v.visitor_id
                HAVING visit_count > 0
                ORDER BY visit_count DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'resident_report':
            $stmt = $db->prepare("
                SELECT r.resident_id,
                       CONCAT(r.first_name, ' ', r.last_name) as resident_name,
                       r.student_number, r.email, r.phone,
                       ro.room_number, rt.type_name,
                       r.is_active, r.created_at
                FROM residents r
                LEFT JOIN rooms ro ON r.room_id = ro.room_id
                LEFT JOIN room_types rt ON ro.room_type_id = rt.room_type_id
                WHERE DATE(r.created_at) BETWEEN ? AND ?
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'room_occupancy_report':
            $stmt = $db->prepare("
                SELECT ro.room_number, rt.type_name, rt.max_occupancy,
                       ro.is_occupied,
                       CASE WHEN ro.is_occupied = 1 THEN
                           CONCAT(r.first_name, ' ', r.last_name)
                       ELSE 'Vacant' END as occupant_name,
                       CASE WHEN ro.is_occupied = 1 THEN r.student_number ELSE NULL END as student_number
                FROM rooms ro
                JOIN room_types rt ON ro.room_type_id = rt.room_type_id
                LEFT JOIN residents r ON ro.room_id = r.room_id AND r.is_active = 1
                ORDER BY ro.room_number
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        default:
            return [];
    }
}

function generateReportFile($reportType, $data, $dateFrom, $dateTo, $fileName) {
    // Create reports directory if it doesn't exist
    $reportsDir = '../../uploads/reports/';
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0755, true);
    }

    $filePath = $reportsDir . $fileName;

    // Generate HTML content for the report
    $html = generateReportHTML($reportType, $data, $dateFrom, $dateTo);

    // Render a real PDF using Dompdf
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $output = $dompdf->output();
    file_put_contents($filePath, $output);

    return $filePath;
}

function generateReportHTML($reportType, $data, $dateFrom, $dateTo) {
    $title = ucwords(str_replace('_', ' ', $reportType));
    $generatedDate = date('F j, Y \a\t g:i A');
    $logoUrl = SITE_URL . '/assets/images/logo.png';

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>{$title}</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
            .brand { display: flex; align-items: center; justify-content: center; gap: 10px; }
            .brand .logo { height: 28px; }
            .report-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
            .report-info { font-size: 14px; color: #666; }
            .report-date { margin-bottom: 5px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .summary { margin-top: 20px; padding: 15px; background-color: #f0f8ff; border-left: 4px solid #007cba; }
            .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            tr { page-break-inside: avoid; }
            td { word-wrap: break-word; }
            .watermark { position: fixed; top: 40%; left: 20%; opacity: 0.06; z-index: -1; }
            .watermark img { width: 400px; }
        </style>
    </head>
    <body>
        <div class='watermark'>
            <img src='{$logoUrl}' alt='Sophen'>
        </div>
        <div class='header'>
            <div class='brand'><img class='logo' src='{$logoUrl}' alt='Sophen Logo'><span>Sophen Residence Management System</span></div>
            <h1 class='report-title'>{$title}</h1>
            <div class='report-info'>
                <div class='report-date'>Report Period: " . date('M j, Y', strtotime($dateFrom)) . " to " . date('M j, Y', strtotime($dateTo)) . "</div>
                <div class='report-date'>Generated: {$generatedDate}</div>
            </div>
        </div>
    ";

    // Summary section for quick insights
    $summary = buildSummaryList($reportType, $data);
    $html .= renderSummary($summary);
    if (is_array($data) && count($data) > 30) {
        $html .= "<div style='page-break-before: always;'></div>";
    }

    // Add report-specific content
    switch ($reportType) {
        case 'daily_visitor_log':
            $html .= generateVisitorLogTable($data);
            break;
        case 'weekly_visitor_report':
            $html .= generateWeeklyReportTable($data);
            break;
        case 'monthly_visitor_log':
            $html .= generateMonthlyReportTable($data);
            break;
        case 'security_incident_report':
            $html .= generateSecurityReportTable($data);
            break;
        case 'blocked_visitor_report':
            $html .= generateBlockedVisitorTable($data);
            break;
        case 'visitor_frequency_report':
            $html .= generateFrequencyReportTable($data);
            break;
        case 'resident_report':
            $html .= generateResidentReportTable($data);
            break;
        case 'room_occupancy_report':
            $html .= generateOccupancyReportTable($data);
            break;
        default:
            $html .= "<p>No data available for this report type.</p>";
    }

    $html .= "
        <div class='footer'>
            <p>Sophen Residence Management System</p>
        </div>
        <script type='text/php'>
            if (isset(\$pdf)) {
                \$font = \$fontMetrics->get_font('Helvetica', 'normal');
                \$size = 9;
                \$color = [0,0,0];
                \$pdf->page_text(520, 810, 'Page {PAGE_NUM} of {PAGE_COUNT}', \$font, \$size, \$color);
                \$pdf->page_text(36, 810, 'Sophen Residence Management System', \$font, \$size, [0.47, 0.27, 0.68]);
            }
        </script>
    </body>
    </html>
    ";

    return $html;
}

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatStatusText($status) {
    $map = [
        'checked_in' => 'Checked In',
        'checked_out' => 'Checked Out',
        'pending' => 'Pending',
        'active' => 'Blocked', // For blocks, show "Blocked" when status is active
        'expired' => 'Expired',
        'unblocked' => 'Unblocked',
        1 => 'Active',
        0 => 'Inactive',
        true => 'Active',
        false => 'Inactive',
    ];
    $key = is_string($status) ? strtolower(trim($status)) : $status;
    return $map[$key] ?? (is_numeric($status) ? ($status ? 'Active' : 'Inactive') : ucfirst((string)$status));
}

function formatBooleanText($value, $labels = ['Yes', 'No']) {
    return (int)$value ? $labels[0] : $labels[1];
}

function formatHMFromMinutes($minutes) {
    if ($minutes === null || $minutes === '' || !is_numeric($minutes)) {
        return 'Ongoing';
    }
    $m = (int)round($minutes);
    $h = intdiv($m, 60);
    $rem = $m % 60;
    return ($h ? "{$h}h " : '') . "{$rem}m";
}

function formatHMFromHours($hours) {
    if ($hours === null || $hours === '' || !is_numeric($hours)) {
        return 'N/A';
    }
    $totalMinutes = (int)round($hours * 60);
    $h = intdiv($totalMinutes, 60);
    $rem = $totalMinutes % 60;
    return "{$h}h {$rem}m";
}

function buildSummaryList($reportType, $data) {
    $summary = [];
    switch ($reportType) {
        case 'daily_visitor_log':
            $total = count($data);
            $completed = 0;
            $ongoing = 0;
            $uniqueIds = [];
            foreach ($data as $r) {
                if (!empty($r['id_number'])) { $uniqueIds[$r['id_number']] = true; }
                if (!empty($r['actual_checkout'])) { $completed++; } else { $ongoing++; }
            }
            $summary = [
                'Total visits' => $total,
                'Completed' => $completed,
                'Ongoing' => $ongoing,
                'Unique visitors' => count($uniqueIds),
            ];
            break;
        case 'weekly_visitor_report':
            $total = 0; $completed = 0; $ongoing = 0; $unique = 0; $avg = 0; $avgCount = 0;
            foreach ($data as $r) {
                $total += (int)($r['total_visits'] ?? 0);
                $completed += (int)($r['completed_visits'] ?? 0);
                $ongoing += (int)($r['ongoing_visits'] ?? 0);
                $unique += (int)($r['unique_visitors'] ?? 0);
                if (!empty($r['avg_duration_minutes'])) { $avg += (float)$r['avg_duration_minutes']; $avgCount++; }
            }
            $summary = [
                'Total visits' => $total,
                'Completed' => $completed,
                'Ongoing' => $ongoing,
                'Unique visitors (sum of daily)' => $unique,
                'Avg daily duration' => $avgCount ? formatHMFromMinutes($avg / $avgCount) : 'N/A',
            ];
            break;
        case 'monthly_visitor_log':
            $total = 0; $unique = 0; $avg = 0; $avgCount = 0;
            foreach ($data as $r) {
                $total += (int)($r['total_visits'] ?? 0);
                $unique += (int)($r['unique_visitors'] ?? 0);
                if (!empty($r['avg_duration_hours'])) { $avg += (float)$r['avg_duration_hours']; $avgCount++; }
            }
            $summary = [
                'Total visits' => $total,
                'Unique visitors' => $unique,
                'Avg duration per visit' => $avgCount ? formatHMFromHours($avg / $avgCount) : 'N/A',
            ];
            break;
        case 'security_incident_report':
            $total = count($data);
            $severityCounts = ['Critical' => 0, 'High' => 0, 'Medium' => 0, 'Low' => 0];
            foreach ($data as $r) {
                $sev = ucfirst(strtolower((string)($r['severity'] ?? '')));
                if (isset($severityCounts[$sev])) { $severityCounts[$sev]++; }
            }
            $summary = array_merge(['Total alerts' => $total], $severityCounts);
            break;
        case 'blocked_visitor_report':
            $total = count($data);
            $counts = ['Blocked' => 0, 'Expired' => 0, 'Unblocked' => 0];
            foreach ($data as $r) {
                $status = formatStatusText($r['block_status'] ?? '');
                if (isset($counts[$status])) { $counts[$status]++; }
            }
            $summary = array_merge(['Total blocks' => $total], $counts);
            break;
        case 'visitor_frequency_report':
            $totalVisitors = count($data);
            $totalVisits = 0;
            foreach ($data as $r) { $totalVisits += (int)($r['visit_count'] ?? 0); }
            $summary = [
                'Unique visitors' => $totalVisitors,
                'Total visits' => $totalVisits,
                'Avg visits per visitor' => $totalVisitors ? number_format($totalVisits / $totalVisitors, 2) : '0.00',
            ];
            break;
        case 'resident_report':
            $total = count($data);
            $active = 0;
            foreach ($data as $r) { if ((int)($r['is_active'] ?? 0)) { $active++; } }
            $summary = [
                'Total residents' => $total,
                'Active' => $active,
                'Inactive' => $total - $active,
            ];
            break;
        case 'room_occupancy_report':
            $total = count($data);
            $occupied = 0;
            foreach ($data as $r) { if ((int)($r['is_occupied'] ?? 0)) { $occupied++; } }
            $summary = [
                'Total rooms' => $total,
                'Occupied' => $occupied,
                'Vacant' => $total - $occupied,
            ];
            break;
    }
    return $summary;
}

function renderSummary($summary) {
    if (empty($summary)) { return ''; }
    $html = "<div class='summary'><strong>Summary</strong><ul style='margin:10px 0 0 20px;'>";
    foreach ($summary as $label => $value) {
        $html .= "<li>" . h($label) . ": " . h((string)$value) . "</li>";
    }
    $html .= "</ul></div>";
    return $html;
}

function groupByRoom($data, $key = 'room_number') {
    $groups = [];
    foreach ($data as $row) {
        $room = trim((string)($row[$key] ?? ''));
        if ($room === '') { $room = 'No Room'; }
        if (!isset($groups[$room])) { $groups[$room] = []; }
        $groups[$room][] = $row;
    }
    // Sort rooms naturally for easier reading (e.g., 101, 102, A103)
    $rooms = array_keys($groups);
    usort($rooms, 'strnatcasecmp');
    $sorted = [];
    foreach ($rooms as $r) { $sorted[$r] = $groups[$r]; }
    return $sorted;
}

function generateVisitorLogTable($data) {
    $groups = groupByRoom($data, 'room_number');
    $html = '';
    foreach ($groups as $room => $rows) {
        $html .= "<div style='margin-top:15px; font-weight:bold;'>Room: " . h($room) . "</div>";
        // Sort by check-in time descending within each room
        usort($rows, function($a, $b) {
            return strcmp((string)($b['actual_checkin'] ?? ''), (string)($a['actual_checkin'] ?? ''));
        });
        $html .= "<table>
            <thead>
                <tr>
                    <th>Visit ID</th>
                    <th>Visitor Name</th>
                    <th>ID Number</th>
                    <th>Host</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Status</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>";
        foreach ($rows as $row) {
            $duration = formatHMFromMinutes($row['duration_minutes'] ?? null);
            $checkin = !empty($row['actual_checkin']) ? date('M j, Y g:i A', strtotime($row['actual_checkin'])) : '-';
            $checkout = !empty($row['actual_checkout']) ? date('M j, Y g:i A', strtotime($row['actual_checkout'])) : 'Not checked out';
            $html .= "<tr>
                <td>" . h($row['visit_id'] ?? '') . "</td>
                <td>" . h($row['visitor_name'] ?? '') . "</td>
                <td>" . h($row['id_number'] ?? '') . "</td>
                <td>" . h($row['host_name'] ?? '-') . "</td>
                <td>" . h($checkin) . "</td>
                <td>" . h($checkout) . "</td>
                <td>" . h(formatStatusText($row['visit_status'] ?? '')) . "</td>
                <td>" . h($duration) . "</td>
            </tr>";
        }
        $html .= "</tbody></table>";
    }
    return $html;
}

function generateWeeklyReportTable($data) {
    $html = "<table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Total Visits</th>
                <th>Completed</th>
                <th>Ongoing</th>
                <th>Unique Visitors</th>
                <th>Avg Duration</th>
            </tr>
        </thead>
        <tbody>";

    foreach ($data as $row) {
        $avg = !empty($row['avg_duration_minutes']) ? formatHMFromMinutes($row['avg_duration_minutes']) : 'N/A';
        $html .= "<tr>
            <td>" . h(date('M j, Y', strtotime($row['visit_date']))) . "</td>
            <td>" . h($row['total_visits'] ?? 0) . "</td>
            <td>" . h($row['completed_visits'] ?? 0) . "</td>
            <td>" . h($row['ongoing_visits'] ?? 0) . "</td>
            <td>" . h($row['unique_visitors'] ?? 0) . "</td>
            <td>" . h($avg) . "</td>
        </tr>";
    }

    $html .= "</tbody></table>";
    return $html;
}

function generateMonthlyReportTable($data) {
    $html = "<table>
        <thead>
            <tr>
                <th>Month</th>
                <th>Total Visits</th>
                <th>Unique Visitors</th>
                <th>Avg Duration</th>
                <th>Total Duration</th>
            </tr>
        </thead>
        <tbody>";

    foreach ($data as $row) {
        $avg = formatHMFromHours($row['avg_duration_hours'] ?? null);
        $total = formatHMFromHours($row['total_duration_hours'] ?? null);
        $html .= "<tr>
            <td>" . h(($row['month'] ?? '') . '/' . ($row['year'] ?? '')) . "</td>
            <td>" . h($row['total_visits'] ?? 0) . "</td>
            <td>" . h($row['unique_visitors'] ?? 0) . "</td>
            <td>" . h($avg) . "</td>
            <td>" . h($total) . "</td>
        </tr>";
    }

    $html .= "</tbody></table>";
    return $html;
}

function generateSecurityReportTable($data) {
    $groups = groupByRoom($data, 'room_number');
    $html = '';
    foreach ($groups as $room => $rows) {
        $html .= "<div style='margin-top:15px; font-weight:bold;'>Room: " . h($room) . "</div>";
        // Sort by incident date descending within each room
        usort($rows, function($a, $b) {
            return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
        });
        $html .= "<table>
            <thead>
                <tr>
                    <th>Alert ID</th>
                    <th>Type</th>
                    <th>Severity</th>
                    <th>Visitor</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>";
        foreach ($rows as $row) {
            $html .= "<tr>
                <td>" . h($row['alert_id'] ?? '') . "</td>
                <td>" . h($row['alert_type'] ?? '') . "</td>
                <td>" . h(ucfirst(strtolower((string)($row['severity'] ?? '')))) . "</td>
                <td>" . h($row['visitor_name'] ?? '-') . "</td>
                <td>" . h($row['message'] ?? '') . "</td>
                <td>" . h(formatStatusText($row['alert_status'] ?? '')) . "</td>
                <td>" . h(date('M j, Y g:i A', strtotime($row['created_at']))) . "</td>
            </tr>";
        }
        $html .= "</tbody></table>";
    }
    return $html;
}

function generateBlockedVisitorTable($data) {
    $html = "<table>
        <thead>
            <tr>
                <th>Visitor Name</th>
                <th>ID Number</th>
                <th>Email</th>
                <th>Reason</th>
                <th>Block Date</th>
                <th>Duration (days)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>";

    foreach ($data as $row) {
        $html .= "<tr>
            <td>" . h($row['visitor_name'] ?? '') . "</td>
            <td>" . h($row['id_number'] ?? '') . "</td>
            <td>" . h($row['email'] ?? '') . "</td>
            <td>" . h($row['reason_description'] ?? '-') . "</td>
            <td>" . h(date('M j, Y', strtotime($row['block_start_date']))) . "</td>
            <td>" . h($row['block_period_days'] ?? 0) . "</td>
            <td>" . h(formatStatusText($row['block_status'] ?? '')) . "</td>
        </tr>";
    }

    $html .= "</tbody></table>";
    return $html;
}

function generateFrequencyReportTable($data) {
    $html = "<table>
        <thead>
            <tr>
                <th>Visitor Name</th>
                <th>ID Number</th>
                <th>Email</th>
                <th>Visit Count</th>
                <th>Last Visit</th>
                <th>Avg Duration</th>
                <th>Total Duration</th>
            </tr>
        </thead>
        <tbody>";

    foreach ($data as $row) {
        $avg = formatHMFromHours($row['avg_duration_hours'] ?? null);
        $total = formatHMFromHours($row['total_duration_hours'] ?? null);
        $last = !empty($row['last_visit']) ? date('M j, Y g:i A', strtotime($row['last_visit'])) : '-';
        $html .= "<tr>
            <td>" . h($row['visitor_name'] ?? '') . "</td>
            <td>" . h($row['id_number'] ?? '') . "</td>
            <td>" . h($row['email'] ?? '') . "</td>
            <td>" . h($row['visit_count'] ?? 0) . "</td>
            <td>" . h($last) . "</td>
            <td>" . h($avg) . "</td>
            <td>" . h($total) . "</td>
        </tr>";
    }

    $html .= "</tbody></table>";
    return $html;
}

function generateResidentReportTable($data) {
    $groups = groupByRoom($data, 'room_number');
    $html = '';
    foreach ($groups as $room => $rows) {
        $html .= "<div style='margin-top:15px; font-weight:bold;'>Room: " . h($room) . "</div>";
        // Sort by resident name within each room
        usort($rows, function($a, $b) {
            return strcasecmp((string)($a['resident_name'] ?? ''), (string)($b['resident_name'] ?? ''));
        });
        $html .= "<table>
            <thead>
                <tr>
                    <th>Resident Name</th>
                    <th>Student Number</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Room Type</th>
                    <th>Status</th>
                    <th>Registration Date</th>
                </tr>
            </thead>
            <tbody>";
        foreach ($rows as $row) {
            $html .= "<tr>
                <td>" . h($row['resident_name'] ?? '') . "</td>
                <td>" . h($row['student_number'] ?? '') . "</td>
                <td>" . h($row['email'] ?? '') . "</td>
                <td>" . h($row['phone'] ?? '') . "</td>
                <td>" . h($row['type_name'] ?? '-') . "</td>
                <td>" . h(formatBooleanText($row['is_active'] ?? 0, ['Active', 'Inactive'])) . "</td>
                <td>" . h(date('M j, Y', strtotime($row['created_at']))) . "</td>
            </tr>";
        }
        $html .= "</tbody></table>";
    }
    return $html;
}

function generateOccupancyReportTable($data) {
    $html = "<table>
        <thead>
            <tr>
                <th>Room Number</th>
                <th>Room Type</th>
                <th>Max Occupancy</th>
                <th>Status</th>
                <th>Occupant</th>
                <th>Student Number</th>
            </tr>
        </thead>
        <tbody>";

    foreach ($data as $row) {
        $html .= "<tr>
            <td>" . h($row['room_number'] ?? '') . "</td>
            <td>" . h($row['type_name'] ?? '') . "</td>
            <td>" . h($row['max_occupancy'] ?? 0) . "</td>
            <td>" . h(formatBooleanText($row['is_occupied'] ?? 0, ['Occupied', 'Vacant'])) . "</td>
            <td>" . h($row['occupant_name'] ?? 'Vacant') . "</td>
            <td>" . h($row['student_number'] ?? '') . "</td>
        </tr>";
    }

    $html .= "</tbody></table>";
    return $html;
}
?>
