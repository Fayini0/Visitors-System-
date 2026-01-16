<?php
session_start();

// Check if user is logged in and has security role
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'mark_all_read':
            $stmt = $db->prepare("UPDATE security_alerts SET alert_status = 'acknowledged', acknowledged_by = ? WHERE alert_status = 'new'");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode(['success' => true, 'message' => 'All alerts marked as read']);
            break;

        case 'force_checkout':
            $alert_id = (int)$_POST['alert_id'];
            
            // Block force checkout during quiet hours
            if (is_quiet_hours()) {
                echo json_encode(['success' => false, 'message' => 'Force checkout is not allowed between 23:00 and 07:00. Please try again after 07:00.']);
                break;
            }
            
            // Get alert details
            $stmt = $db->prepare("SELECT visit_id, visitor_id FROM security_alerts WHERE alert_id = ?");
            $stmt->execute([$alert_id]);
            $alert = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($alert) {
                // Update visit status to checked_out
                $stmt = $db->prepare("UPDATE visits SET visit_status = 'checked_out', actual_checkout = NOW(), checked_out_by = ? WHERE visit_id = ?");
                $stmt->execute([$_SESSION['user_id'], $alert['visit_id']]);
                
                // Update alert status
                $stmt = $db->prepare("UPDATE security_alerts SET alert_status = 'resolved', resolved_by = ?, resolved_at = NOW() WHERE alert_id = ?");
                $stmt->execute([$_SESSION['user_id'], $alert_id]);
                
                echo json_encode(['success' => true, 'message' => 'Visitor force checked out successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Alert not found']);
            }
            break;

        case 'extend_visit':
            $alert_id = (int)$_POST['alert_id'];
            $hours = (int)$_POST['hours'];
            
            // Get alert details
            $stmt = $db->prepare("SELECT visit_id FROM security_alerts WHERE alert_id = ?");
            $stmt->execute([$alert_id]);
            $alert = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($alert) {
                // Extend checkout time
                $stmt = $db->prepare("UPDATE visits SET expected_checkout = DATE_ADD(expected_checkout, INTERVAL ? HOUR) WHERE visit_id = ?");
                $stmt->execute([$hours, $alert['visit_id']]);
                
                // Update alert status
                $stmt = $db->prepare("UPDATE security_alerts SET alert_status = 'resolved', resolved_by = ?, resolved_at = NOW() WHERE alert_id = ?");
                $stmt->execute([$_SESSION['user_id'], $alert_id]);
                
                echo json_encode(['success' => true, 'message' => 'Visit extended successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Alert not found']);
            }
            break;

        case 'send_reminder':
            $alert_id = (int)$_POST['alert_id'];
            
            // Get alert details
            $stmt = $db->prepare("SELECT sa.*, v.first_name, v.last_name, v.email FROM security_alerts sa LEFT JOIN visitors v ON sa.visitor_id = v.visitor_id WHERE sa.alert_id = ?");
            $stmt->execute([$alert_id]);
            $alert = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($alert) {
                // Send reminder email (placeholder)
                $subject = "Security Reminder - Sophen Residence";
                $body = "Dear {$alert['first_name']} {$alert['last_name']},\n\nThis is a reminder regarding your visit at Sophen Residence.\n\n{$alert['message']}\n\nPlease contact security if you need assistance.\n\nBest regards,\nSophen Security Team";
                
                // Assuming send_email function is implemented
                if (send_email($alert['email'], $subject, $body)) {
                    // Update alert status
                    $stmt = $db->prepare("UPDATE security_alerts SET alert_status = 'acknowledged', acknowledged_by = ? WHERE alert_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $alert_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Reminder sent successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send reminder']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Alert not found']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Alert action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing the request']);
}
?>
