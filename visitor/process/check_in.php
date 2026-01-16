<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['visit_id'])) {
    $visit_id = intval($_POST['visit_id']);

    try {
        // Block check-in during quiet hours (from default cutoff to next day's start)
        if (is_quiet_hours()) {
            echo json_encode(['success' => false, 'message' => 'Check-in not allowed between 23:00 and 07:00.']);
            exit;
        }
        // Update visit status to checked_in
        $query = "UPDATE visits SET
                  visit_status = 'checked_in',
                  actual_checkin = NOW()
                  WHERE visit_id = ? AND visit_status = 'approved'";

        $stmt = $db->prepare($query);
        $stmt->execute([$visit_id]);

        if ($stmt->rowCount() > 0) {
            // Align expected_checkout to the system's daily cutoff (e.g., 23:00)
            $settings = get_system_settings();
            $defaultCheckoutTime = $settings['default_checkout_time'] ?? DEFAULT_CHECKOUT_TIME;
            $now = date('Y-m-d H:i:s');
            $today = date('Y-m-d');
            $todayCutoff = strtotime($today . ' ' . $defaultCheckoutTime);
            $nowTs = strtotime($now);
            if ($nowTs <= $todayCutoff) {
                $expected_checkout = date('Y-m-d H:i:s', $todayCutoff);
            } else {
                $tomorrow = date('Y-m-d', strtotime('+1 day', $nowTs));
                $expected_checkout = $tomorrow . ' ' . $defaultCheckoutTime;
            }
            $update = $db->prepare("UPDATE visits SET expected_checkout = ? WHERE visit_id = ?");
            $update->execute([$expected_checkout, $visit_id]);

            echo json_encode(['success' => true, 'message' => 'Visitor checked in successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Visit not found or already checked in']);
        }

    } catch (PDOException $e) {
        error_log("Check-in error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred during check-in']);
    }

} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>
