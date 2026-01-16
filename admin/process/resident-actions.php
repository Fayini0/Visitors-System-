<?php
session_start();
require_once '../../includes/functions.php';
require_admin();

// Handle AJAX requests for resident actions
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $database = new Database();
        $db = $database->getConnection();

        $action = $_POST['action'];

        switch ($action) {
            case 'add_resident':
                $firstName = trim($_POST['first_name']);
                $lastName = trim($_POST['last_name']);
                $studentNumber = trim($_POST['student_number']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $roomId = (int)$_POST['room_id'];

                // Validate required fields
                if (empty($firstName) || empty($lastName) || empty($studentNumber) || empty($email) || empty($roomId)) {
                    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
                    break;
                }

                // Check if student number already exists
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM residents WHERE student_number = ?");
                $stmt->execute([$studentNumber]);
                if ($stmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Student number already exists']);
                    break;
                }

                // Check if email already exists
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM residents WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Email address already exists']);
                    break;
                }

                // Check if room is available
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM residents WHERE room_id = ? AND is_active = 1");
                $stmt->execute([$roomId]);
                if ($stmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Room is already occupied']);
                    break;
                }

                // Insert new resident
                $stmt = $db->prepare("
                    INSERT INTO residents (first_name, last_name, student_number, email, phone, room_id, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$firstName, $lastName, $studentNumber, $email, $phone, $roomId]);

                // Update room occupancy
                $stmt = $db->prepare("UPDATE rooms SET is_occupied = 1 WHERE room_id = ?");
                $stmt->execute([$roomId]);

                // Log activity
                log_activity($_SESSION['user_id'], 'add_resident', "Added resident: $firstName $lastName (Student #: $studentNumber)");

                echo json_encode(['success' => true, 'message' => 'Resident added successfully']);
                break;

            case 'edit_resident':
                $residentId = (int)$_POST['resident_id'];
                $firstName = trim($_POST['first_name']);
                $lastName = trim($_POST['last_name']);
                $studentNumber = trim($_POST['student_number']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $roomId = (int)$_POST['room_id'];

                // Validate required fields
                if (empty($firstName) || empty($lastName) || empty($studentNumber) || empty($email) || empty($roomId)) {
                    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
                    break;
                }

                // Check if resident exists
                $stmt = $db->prepare("SELECT room_id FROM residents WHERE resident_id = ?");
                $stmt->execute([$residentId]);
                $currentResident = $stmt->fetch();
                if (!$currentResident) {
                    echo json_encode(['success' => false, 'message' => 'Resident not found']);
                    break;
                }

                // Check if student number exists for other residents
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM residents WHERE student_number = ? AND resident_id != ?");
                $stmt->execute([$studentNumber, $residentId]);
                if ($stmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Student number already exists']);
                    break;
                }

                // Check if email exists for other residents
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM residents WHERE email = ? AND resident_id != ?");
                $stmt->execute([$email, $residentId]);
                if ($stmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Email address already exists']);
                    break;
                }

                $currentRoomId = $currentResident['room_id'];

                // Check if new room is available (if different from current)
                if ($roomId != $currentRoomId) {
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM residents WHERE room_id = ? AND is_active = 1");
                    $stmt->execute([$roomId]);
                    if ($stmt->fetch()['count'] > 0) {
                        echo json_encode(['success' => false, 'message' => 'Room is already occupied']);
                        break;
                    }
                }

                // Update resident
                $stmt = $db->prepare("
                    UPDATE residents
                    SET first_name = ?, last_name = ?, student_number = ?, email = ?, phone = ?, room_id = ?
                    WHERE resident_id = ?
                ");
                $stmt->execute([$firstName, $lastName, $studentNumber, $email, $phone, $roomId, $residentId]);

                // Update room occupancy if room changed
                if ($roomId != $currentRoomId) {
                    $stmt = $db->prepare("UPDATE rooms SET is_occupied = 0 WHERE room_id = ?");
                    $stmt->execute([$currentRoomId]);
                    $stmt = $db->prepare("UPDATE rooms SET is_occupied = 1 WHERE room_id = ?");
                    $stmt->execute([$roomId]);
                }

                // Log activity
                log_activity($_SESSION['user_id'], 'edit_resident', "Updated resident ID: $residentId ($firstName $lastName)");

                echo json_encode(['success' => true, 'message' => 'Resident updated successfully']);
                break;

            case 'remove_resident':
                $residentId = (int)$_POST['resident_id'];

                // Check if resident exists
                $stmt = $db->prepare("SELECT first_name, last_name, room_id FROM residents WHERE resident_id = ?");
                $stmt->execute([$residentId]);
                $resident = $stmt->fetch();

                if (!$resident) {
                    echo json_encode(['success' => false, 'message' => 'Resident not found']);
                    break;
                }

                // Deactivate resident instead of deleting
                $stmt = $db->prepare("UPDATE residents SET is_active = 0 WHERE resident_id = ?");
                $stmt->execute([$residentId]);

                // Free up the room
                $roomId = $resident['room_id'];
                if ($roomId) {
                    $stmt = $db->prepare("UPDATE rooms SET is_occupied = 0 WHERE room_id = ?");
                    $stmt->execute([$roomId]);
                }

                // Log activity
                log_activity($_SESSION['user_id'], 'remove_resident', "Removed resident: {$resident['first_name']} {$resident['last_name']} (ID: $residentId)");

                echo json_encode(['success' => true, 'message' => 'Resident removed successfully']);
                break;

            case 'search_residents':
                $searchTerm = $_POST['search_term'];

                if (empty($searchTerm)) {
                    echo json_encode(['success' => false, 'message' => 'Search term is required']);
                    break;
                }

                $stmt = $db->prepare("
                    SELECT r.*, ro.room_number, rt.type_name
                    FROM residents r
                    LEFT JOIN rooms ro ON r.room_id = ro.room_id
                    LEFT JOIN room_types rt ON ro.room_type_id = rt.room_type_id
                    WHERE r.is_active = 1 AND (
                        r.first_name LIKE ? OR r.last_name LIKE ? OR
                        r.student_number LIKE ? OR r.email LIKE ? OR
                        ro.room_number LIKE ?
                    )
                    ORDER BY r.created_at DESC
                    LIMIT 50
                ");
                $searchParam = "%$searchTerm%";
                $stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
                $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'residents' => $residents]);
                break;

            case 'get_resident_details':
                $residentId = (int)$_POST['resident_id'];

                $stmt = $db->prepare("
                    SELECT r.*, ro.room_number, rt.type_name
                    FROM residents r
                    LEFT JOIN rooms ro ON r.room_id = ro.room_id
                    LEFT JOIN room_types rt ON ro.room_type_id = rt.room_type_id
                    WHERE r.resident_id = ?
                ");
                $stmt->execute([$residentId]);
                $resident = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($resident) {
                    echo json_encode(['success' => true, 'resident' => $resident]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Resident not found']);
                }
                break;

            case 'get_available_rooms':
                $stmt = $db->prepare("
                    SELECT r.room_id, r.room_number, rt.type_name, rt.max_occupancy
                    FROM rooms r
                    JOIN room_types rt ON r.room_type_id = rt.room_type_id
                    WHERE r.is_occupied = 0
                    ORDER BY r.room_number
                ");
                $stmt->execute();
                $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'rooms' => $rooms]);
                break;

            case 'get_all_rooms':
                $stmt = $db->prepare("
                    SELECT r.room_id, r.room_number, rt.type_name, r.is_occupied
                    FROM rooms r
                    JOIN room_types rt ON r.room_type_id = rt.room_type_id
                    ORDER BY r.room_number
                ");
                $stmt->execute();
                $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'rooms' => $rooms]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Resident actions error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Action failed: ' . $e->getMessage()]);
    }
    exit();
}

// If not a POST request, redirect or show error
header('Location: ../manage-residents.php');
exit();
?>
