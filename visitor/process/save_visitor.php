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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required_fields = ['full_name', 'id_number', 'room_number', 'host_name', 'email'];
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
        exit;
    }

    try {
        $db->beginTransaction();

        // Process visitor data
        $full_name = trim($_POST['full_name']);
        $names = explode(' ', $full_name, 2);
        $first_name = $names[0];
        $last_name = isset($names[1]) ? $names[1] : '';
        $id_number = trim($_POST['id_number']);
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email']);
        $room_number = trim($_POST['room_number']);
        $host_name = trim($_POST['host_name']);

        // Check if visitor exists
        $check_visitor = $db->prepare("SELECT visitor_id FROM visitors WHERE id_number = ?");
        $check_visitor->execute([$id_number]);
        $existing_visitor = $check_visitor->fetch(PDO::FETCH_ASSOC);

        if ($existing_visitor) {
            $visitor_id = $existing_visitor['visitor_id'];
            // Update visit count
            $update_count = $db->prepare("UPDATE visitors SET visit_count = visit_count + 1, last_visit_date = CURRENT_TIMESTAMP WHERE visitor_id = ?");
            $update_count->execute([$visitor_id]);
        } else {
            // Insert new visitor
            $insert_visitor = $db->prepare("INSERT INTO visitors (first_name, last_name, id_number, phone, email) VALUES (?, ?, ?, ?, ?)");
            $insert_visitor->execute([$first_name, $last_name, $id_number, $phone, $email]);
            $visitor_id = $db->lastInsertId();
        }

        // Get room_id
        $get_room = $db->prepare("SELECT room_id FROM rooms WHERE room_number = ?");
        $get_room->execute([$room_number]);
        $room = $get_room->fetch(PDO::FETCH_ASSOC);
        if (!$room) {
            throw new Exception("Room not found: " . $room_number);
        }
        $room_id = $room['room_id'];

        // Validate that host_name corresponds to a resident in the selected room
        $get_resident_in_room = $db->prepare("SELECT r.email FROM residents r INNER JOIN rooms rm ON r.room_id = rm.room_id WHERE rm.room_id = ? AND CONCAT(r.first_name, ' ', r.last_name) = ? AND r.is_active = TRUE");
        $get_resident_in_room->execute([$room_id, $host_name]);
        $resident_in_room = $get_resident_in_room->fetch(PDO::FETCH_ASSOC);
        if (!$resident_in_room) {
            throw new Exception("Selected resident '{$host_name}' does not reside in room '{$room_number}'. Please select a valid resident for the room.");
        }
        $resident_email = $resident_in_room['email'];

        // Insert visit
        $purpose = 'other'; // Default, can be adjusted
        $insert_visit = $db->prepare("INSERT INTO visits (visitor_id, room_id, host_name, purpose, visit_status) VALUES (?, ?, ?, ?, 'pending')");
        $insert_visit->execute([$visitor_id, $room_id, $host_name, $purpose]);
        $visit_id = $db->lastInsertId();

        // Set expected checkout to today's cutoff or next day's if past cutoff
        $settings = get_system_settings();
        $defaultCheckoutTime = $settings['default_checkout_time'] ?? DEFAULT_CHECKOUT_TIME;
        $today = date('Y-m-d');
        $nowTs = time();
        $todayCutoff = strtotime($today . ' ' . $defaultCheckoutTime);
        if ($nowTs <= $todayCutoff) {
            $expected_checkout = date('Y-m-d H:i:s', $todayCutoff);
        } else {
            $tomorrow = date('Y-m-d', strtotime('+1 day', $nowTs));
            $expected_checkout = $tomorrow . ' ' . $defaultCheckoutTime;
        }
        $update_expected = $db->prepare("UPDATE visits SET expected_checkout = ? WHERE visit_id = ?");
        $update_expected->execute([$expected_checkout, $visit_id]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Visitor information saved successfully',
            'visit_id' => $visit_id,
            'visitor_id' => $visitor_id,
            'resident_email' => $resident_email
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['error' => 'Error processing visit: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
