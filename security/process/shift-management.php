<?php
// security/process/shift-management.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!is_logged_in() || $_SESSION['role_id'] != 2) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    $database = new Database();
    $db = $database->getConnection();

    switch ($action) {
        case 'end_shift':
            // Get shift data
            $shiftStart = $_SESSION['shift_start'] ?? time();
            $shiftDuration = time() - $shiftStart;
            $shiftHours = floor($shiftDuration / 3600);
            $shiftMinutes = floor(($shiftDuration % 3600) / 60);

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

            // Total incidents (alerts)
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM security_alerts WHERE DATE(created_at) = ?");
            $stmt->execute([$today]);
            $todayIncidents = $stmt->fetch()['count'];

            // Get shift notes
            $shiftNotes = sanitize_input($_POST['shift_notes'] ?? '');
            // Include shift duration at the top of notes
            $composedNotes = "Shift Duration: {$shiftHours}h {$shiftMinutes}m" . ($shiftNotes !== '' ? "\n" . $shiftNotes : '');

            // Detect whether daily_reports table uses 'notes' or 'shift_notes'
            $notesColumn = 'notes';
            try {
                $colCheck = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_reports' AND COLUMN_NAME = 'notes'");
                $colCheck->execute();
                $hasNotes = (int)$colCheck->fetch()['c'] > 0;
                if (!$hasNotes) {
                    $notesColumn = 'shift_notes';
                }
            } catch (Exception $e) {
                // Fallback silently to 'notes'
            }

            // Update or insert daily report
            $stmt = $db->prepare("
                SELECT report_id FROM daily_reports
                WHERE report_date = ? AND generated_by = ?
                ORDER BY generated_at DESC LIMIT 1
            ");
            $stmt->execute([$today, $_SESSION['user_id']]);
            $existingReport = $stmt->fetch();

            if ($existingReport) {
                // Update existing report
                $updateSql = "UPDATE daily_reports SET total_checkins = ?, total_checkouts = ?, total_incidents = ?, {$notesColumn} = ?, report_status = 'submitted', exported_at = NOW() WHERE report_id = ?";
                $stmt = $db->prepare($updateSql);
                $stmt->execute([$todayCheckIns, $todayCheckOuts, $todayIncidents, $composedNotes, $existingReport['report_id']]);
            } else {
                // Create new report
                $insertSql = "INSERT INTO daily_reports (report_date, generated_by, officer_name, total_checkins, total_checkouts, total_incidents, {$notesColumn}, report_status, generated_at, exported_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted', NOW(), NOW())";
                $stmt = $db->prepare($insertSql);
                $stmt->execute([
                    $today,
                    $_SESSION['user_id'],
                    $_SESSION['full_name'],
                    $todayCheckIns,
                    $todayCheckOuts,
                    $todayIncidents,
                    $composedNotes
                ]);
            }

            // Log the activity
            log_activity($_SESSION['user_id'], 'shift_ended', "Shift duration: {$shiftHours}h {$shiftMinutes}m, Notes: " . $shiftNotes);

            echo json_encode([
                'success' => true,
                'message' => 'Shift ended successfully',
                'data' => [
                    'shift_duration' => sprintf('%02d:%02d', $shiftHours, $shiftMinutes),
                    'checkins' => $todayCheckIns,
                    'checkouts' => $todayCheckOuts,
                    'incidents' => $todayIncidents
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    error_log("Shift management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing the request']);
}
?>
