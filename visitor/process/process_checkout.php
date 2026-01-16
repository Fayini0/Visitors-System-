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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get all approved and checked-in visitors (approved visitors have permission to be on premises)
        $query = "SELECT v.visit_id, v.visitor_id, v.room_id, v.host_name, v.purpose, v.visit_status,
                         v.actual_checkin, v.expected_checkout, v.created_at,
                         vi.first_name, vi.last_name, vi.id_number, vi.email,
                         r.room_number
                  FROM visits v
                  INNER JOIN visitors vi ON v.visitor_id = vi.visitor_id
                  LEFT JOIN rooms r ON v.room_id = r.room_id
                  WHERE v.visit_status IN ('approved', 'checked_in')
                  ORDER BY v.actual_checkin DESC";

        $stmt = $db->prepare($query);
        $stmt->execute();
        $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $visitors = [];
        foreach ($visits as $visit) {
            $checkin_time = strtotime($visit['actual_checkin']);
            $visitors[] = [
                'visit_id' => $visit['visit_id'],
                'name' => $visit['first_name'] . ' ' . $visit['last_name'],
                'id_number' => $visit['id_number'],
                'checkin_time' => date('M j, Y - H:i', $checkin_time),
                'checkin_timestamp' => $visit['actual_checkin'], // Raw timestamp for JS parsing
                'room_number' => $visit['room_number'] ?: 'N/A',
                'host_name' => $visit['host_name']
            ];
        }

        echo json_encode(['success' => true, 'visitors' => $visitors]);
    } catch (PDOException $e) {
        error_log("Get visitors error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Block checkout during quiet hours
    if (is_quiet_hours()) {
        echo json_encode(['error' => 'Checkout is not allowed between 23:00 and 07:00. Please try again after 07:00.']);
        exit;
    }
    $visitor_id = trim($_POST['visitor_id'] ?? '');
    $visitor_name = trim($_POST['visitor_name'] ?? '');

    if (empty($visitor_id) && empty($visitor_name)) {
        echo json_encode(['error' => 'Please provide visitor ID or full name']);
        exit;
    }

    try {
        // Build query based on provided fields
        $where_conditions = [];
        $params = [];

        if (!empty($visitor_id)) {
            $where_conditions[] = "vi.id_number = ?";
            $params[] = $visitor_id;
        }

        if (!empty($visitor_name)) {
            // Split name into first and last name for search
            $name_parts = explode(' ', $visitor_name, 2);
            if (count($name_parts) == 2) {
                $where_conditions[] = "(vi.first_name LIKE ? AND vi.last_name LIKE ?)";
                $params[] = '%' . $name_parts[0] . '%';
                $params[] = '%' . $name_parts[1] . '%';
            } else {
                $where_conditions[] = "(vi.first_name LIKE ? OR vi.last_name LIKE ?)";
                $params[] = '%' . $visitor_name . '%';
                $params[] = '%' . $visitor_name . '%';
            }
        }

        $where_clause = implode(' OR ', $where_conditions);

        // Find active visit for this visitor (approved or checked_in status)
        $query = "SELECT v.visit_id, v.visitor_id, v.room_id, v.host_name, v.purpose, v.visit_status,
                         v.actual_checkin, v.expected_checkout, v.created_at,
                         vi.first_name, vi.last_name, vi.id_number, vi.email,
                         r.room_number,
                         res.email as resident_email
                  FROM visits v
                  INNER JOIN visitors vi ON v.visitor_id = vi.visitor_id
                  LEFT JOIN rooms r ON v.room_id = r.room_id
                  LEFT JOIN residents res ON r.room_id = res.room_id AND CONCAT(res.first_name, ' ', res.last_name) = v.host_name
                  WHERE ($where_clause) AND v.visit_status IN ('approved', 'checked_in')
                  ORDER BY v.actual_checkin DESC
                  LIMIT 1";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$visit) {
            echo json_encode(['error' => 'No active visit found for the provided information']);
            exit;
        }

        // Update visit status to checked_out
        $update_query = "UPDATE visits SET
                        visit_status = 'checked_out',
                        actual_checkout = NOW()
                        WHERE visit_id = ?";

        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$visit['visit_id']]);

        // Calculate visit duration
        $checkin_time = strtotime($visit['actual_checkin']);
        $checkout_time = time();
        $duration_minutes = round(($checkout_time - $checkin_time) / 60);

        // Prepare response data
        $response = [
            'success' => true,
            'message' => 'Checkout successful',
            'visitor' => [
                'name' => $visit['first_name'] . ' ' . $visit['last_name'],
                'id_number' => $visit['id_number'],
                'checkin_time' => date('M j, Y - H:i', $checkin_time),
                'checkout_time' => date('M j, Y - H:i'),
                'room_number' => $visit['room_number'] ?: 'N/A',
                'host_name' => $visit['host_name'],
                'duration' => $duration_minutes
            ]
        ];

        echo json_encode($response);

    } catch (PDOException $e) {
        error_log("Checkout error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred during checkout']);
    }

} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>
