<?php
session_start();
require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_admin();

// Handle GET requests for visitor actions (from links)
if ($_GET && isset($_GET['action'])) {
    $action = $_GET['action'];
    $visitor_id = (int)($_GET['visitor_id'] ?? 0);

    if (!$visitor_id) {
        header('Location: ../manage-visitors.php?error=Invalid visitor ID');
        exit();
    }

    try {
        $database = new Database();
        $db = $database->getConnection();

        switch ($action) {
            case 'block':
                // Use default block reason and period
                $reason_id = 1; // Default reason
                $block_days = 7; // Default period

                // Check if visitor exists
                $stmt = $db->prepare("SELECT visitor_id FROM visitors WHERE visitor_id = ?");
                $stmt->execute([$visitor_id]);
                if (!$stmt->fetch()) {
                    header('Location: ../manage-visitors.php?error=Visitor not found');
                    exit();
                }

                // Insert block record
                $stmt = $db->prepare("
                    INSERT INTO visitor_blocks (visitor_id, blocked_by_admin_id, reason_id, block_period_days)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$visitor_id, $_SESSION['user_id'], $reason_id, $block_days]);

                // Update visitor status
                $stmt = $db->prepare("UPDATE visitors SET is_blocked = 1 WHERE visitor_id = ?");
                $stmt->execute([$visitor_id]);

                // Log activity
                log_activity($_SESSION['user_id'], 'block_visitor', "Blocked visitor ID: $visitor_id for $block_days days");

                header('Location: ../manage-visitors.php?success=Visitor blocked successfully');
                break;

            case 'unblock':
                // Update block records
                $stmt = $db->prepare("
                    UPDATE visitor_blocks
                    SET block_status = 'unblocked', unblocked_by_admin_id = ?, unblock_date = NOW()
                    WHERE visitor_id = ? AND block_status = 'active'
                ");
                $stmt->execute([$_SESSION['user_id'], $visitor_id]);

                // Update visitor status
                $stmt = $db->prepare("UPDATE visitors SET is_blocked = 0 WHERE visitor_id = ?");
                $stmt->execute([$visitor_id]);

                // Log activity
                log_activity($_SESSION['user_id'], 'unblock_visitor', "Unblocked visitor ID: $visitor_id");

                header('Location: ../manage-visitors.php?success=Visitor unblocked successfully');
                break;

            case 'delete':
                // Check for active visits
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE visitor_id = ? AND visit_status IN ('approved', 'checked_in')");
                $stmt->execute([$visitor_id]);
                $activeVisits = $stmt->fetch()['count'];

                if ($activeVisits > 0) {
                    header('Location: ../manage-visitors.php?error=Cannot delete visitor with active visits');
                    exit();
                }

                // Delete related records to avoid foreign key constraints
                $stmt = $db->prepare("DELETE FROM security_alerts WHERE visitor_id = ?");
                $stmt->execute([$visitor_id]);

                $stmt = $db->prepare("DELETE FROM visits WHERE visitor_id = ?");
                $stmt->execute([$visitor_id]);

                $stmt = $db->prepare("DELETE FROM visitor_blocks WHERE visitor_id = ?");
                $stmt->execute([$visitor_id]);

                // Delete visitor
                $stmt = $db->prepare("DELETE FROM visitors WHERE visitor_id = ?");
                $stmt->execute([$visitor_id]);

                if ($stmt->rowCount() > 0) {
                    // Log activity
                    log_activity($_SESSION['user_id'], 'delete_visitor', "Deleted visitor ID: $visitor_id and all related records");

                    header('Location: ../manage-visitors.php?success=Visitor deleted successfully');
                } else {
                    header('Location: ../manage-visitors.php?error=Visitor not found');
                }
                break;

            default:
                header('Location: ../manage-visitors.php?error=Invalid action');
        }
    } catch (Exception $e) {
        header('Location: ../manage-visitors.php?error=Action failed: ' . urlencode($e->getMessage()));
    }
    exit();
}

// Handle AJAX requests for visitor actions
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $database = new Database();
        $db = $database->getConnection();

        $action = $_POST['action'];

        switch ($action) {
            case 'approve_visit':
                $visitId = (int)$_POST['visit_id'];

                $stmt = $db->prepare("
                    UPDATE visits
                    SET visit_status = 'approved', approved_at = NOW()
                    WHERE visit_id = ? AND visit_status = 'pending'
                ");
                $stmt->execute([$visitId]);

                if ($stmt->rowCount() > 0) {
                    // Log activity
                    log_activity($_SESSION['user_id'], 'approve_visit', "Approved visit ID: $visitId");

                    echo json_encode(['success' => true, 'message' => 'Visit approved successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Visit not found or already processed']);
                }
                break;

            case 'reject_visit':
                $visitId = (int)$_POST['visit_id'];
                $reason = trim($_POST['reason'] ?? '');

                $stmt = $db->prepare("
                    UPDATE visits
                    SET visit_status = 'rejected', notes = ?, rejected_at = NOW()
                    WHERE visit_id = ? AND visit_status = 'pending'
                ");
                $stmt->execute([$reason, $visitId]);

                if ($stmt->rowCount() > 0) {
                    // Log activity
                    log_activity($_SESSION['user_id'], 'reject_visit', "Rejected visit ID: $visitId");

                    echo json_encode(['success' => true, 'message' => 'Visit rejected successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Visit not found or already processed']);
                }
                break;

            case 'block_visitor':
                $visitorId = (int)$_POST['visitor_id'];
                $reasonId = (int)$_POST['reason_id'];
                $blockDays = (int)$_POST['block_days'];

                // Check if visitor exists
                $stmt = $db->prepare("SELECT visitor_id FROM visitors WHERE visitor_id = ?");
                $stmt->execute([$visitorId]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Visitor not found']);
                    break;
                }

                // Insert block record
                $stmt = $db->prepare("
                    INSERT INTO visitor_blocks (visitor_id, blocked_by_admin_id, reason_id, block_period_days)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$visitorId, $_SESSION['user_id'], $reasonId, $blockDays]);

                // Update visitor status
                $stmt = $db->prepare("UPDATE visitors SET is_blocked = 1 WHERE visitor_id = ?");
                $stmt->execute([$visitorId]);

                // Log activity
                log_activity($_SESSION['user_id'], 'block_visitor', "Blocked visitor ID: $visitorId for $blockDays days");

                echo json_encode(['success' => true, 'message' => 'Visitor blocked successfully']);
                break;

            case 'unblock_visitor':
                $visitorId = (int)$_POST['visitor_id'];
                $unblockReason = trim($_POST['unblock_reason']);

                // Update block records
                $stmt = $db->prepare("
                    UPDATE visitor_blocks
                    SET block_status = 'unblocked', unblocked_by_admin_id = ?, unblock_date = NOW(), unblock_reason = ?
                    WHERE visitor_id = ? AND block_status = 'active'
                ");
                $stmt->execute([$_SESSION['user_id'], $unblockReason, $visitorId]);

                // Update visitor status
                $stmt = $db->prepare("UPDATE visitors SET is_blocked = 0 WHERE visitor_id = ?");
                $stmt->execute([$visitorId]);

                // Log activity
                log_activity($_SESSION['user_id'], 'unblock_visitor', "Unblocked visitor ID: $visitorId");

                echo json_encode(['success' => true, 'message' => 'Visitor unblocked successfully']);
                break;

            case 'delete_visitor':
                $visitorId = (int)$_POST['visitor_id'];

                // Check for active visits
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE visitor_id = ? AND visit_status IN ('approved', 'checked_in')");
                $stmt->execute([$visitorId]);
                $activeVisits = $stmt->fetch()['count'];

                if ($activeVisits > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete visitor with active visits']);
                    break;
                }

                // Delete related records to avoid foreign key constraints
                $stmt = $db->prepare("DELETE FROM security_alerts WHERE visitor_id = ?");
                $stmt->execute([$visitorId]);

                $stmt = $db->prepare("DELETE FROM visits WHERE visitor_id = ?");
                $stmt->execute([$visitorId]);

                $stmt = $db->prepare("DELETE FROM visitor_blocks WHERE visitor_id = ?");
                $stmt->execute([$visitorId]);

                // Delete visitor
                $stmt = $db->prepare("DELETE FROM visitors WHERE visitor_id = ?");
                $stmt->execute([$visitorId]);

                if ($stmt->rowCount() > 0) {
                    // Log activity
                    log_activity($_SESSION['user_id'], 'delete_visitor', "Deleted visitor ID: $visitorId and all related records");

                    echo json_encode(['success' => true, 'message' => 'Visitor deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Visitor not found']);
                }
                break;

            case 'search_visitors':
                $searchTerm = $_POST['search_term'];
                $stmt = $db->prepare("
                    SELECT v.*, COUNT(vs.visit_id) as visit_count
                    FROM visitors v
                    LEFT JOIN visits vs ON v.visitor_id = vs.visitor_id
                    WHERE v.first_name LIKE ? OR v.last_name LIKE ? OR v.id_number LIKE ?
                    GROUP BY v.visitor_id
                    ORDER BY v.created_at DESC
                    LIMIT 50
                ");
                $searchParam = "%$searchTerm%";
                $stmt->execute([$searchParam, $searchParam, $searchParam]);
                $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'visitors' => $visitors]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Action failed: ' . $e->getMessage()]);
    }
    exit();
}

// If not a POST request, redirect or show error
header('Location: ../manage-visitors.php');
exit();
?>
