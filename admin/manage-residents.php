<?php
session_start();
require_once '../includes/functions.php';
require_admin();

// Initialize variables
$residents = [];
$availableRooms = [];
$allRooms = [];
$roomTypes = [];

// Get residents and rooms data first
try {
    $database = new Database();
    $db = $database->getConnection();

    // Get all active residents with room info
    $stmt = $db->prepare("
        SELECT r.*, ro.room_number, rt.type_name
        FROM residents r
        LEFT JOIN rooms ro ON r.room_id = ro.room_id
        LEFT JOIN room_types rt ON ro.room_type_id = rt.room_type_id
        WHERE r.is_active = 1
        ORDER BY r.created_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get available rooms (where current occupancy < max_occupancy)
    $stmt = $db->prepare("
        SELECT r.room_id, r.room_number, rt.type_name, rt.max_occupancy, COALESCE(res_count, 0) as current_occupancy
        FROM rooms r
        JOIN room_types rt ON r.room_type_id = rt.room_type_id
        LEFT JOIN (SELECT room_id, COUNT(*) as res_count FROM residents WHERE is_active = 1 GROUP BY room_id) res ON r.room_id = res.room_id
        WHERE COALESCE(res_count, 0) < rt.max_occupancy
        ORDER BY r.room_number
    ");
    $stmt->execute();
    $availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all rooms for edit modal with occupancy info
    $stmt = $db->prepare("
        SELECT r.room_id, r.room_number, rt.type_name, rt.max_occupancy, COALESCE(res_count, 0) as current_occupancy
        FROM rooms r
        JOIN room_types rt ON r.room_type_id = rt.room_type_id
        LEFT JOIN (SELECT room_id, COUNT(*) as res_count FROM residents WHERE is_active = 1 GROUP BY room_id) res ON r.room_id = res.room_id
        ORDER BY r.room_number
    ");
    $stmt->execute();
    $allRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all room types
    $stmt = $db->prepare("
        SELECT * FROM room_types ORDER BY type_name
    ");
    $stmt->execute();
    $roomTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Manage residents error: " . $e->getMessage());
    $residents = [];
    $availableRooms = [];
    $allRooms = [];
    $roomTypes = [];
}

// Handle AJAX requests for resident actions
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $database = new Database();
        $db = $database->getConnection();

        $action = $_POST['action'];

        switch ($action) {
            case 'add':
                $firstName = trim($_POST['first_name']);
                $lastName = trim($_POST['last_name']);
                $studentNumber = trim($_POST['student_number']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $roomId = (int)$_POST['room_id'];
                $swapTargetResidentId = isset($_POST['swap_target_resident_id']) && $_POST['swap_target_resident_id'] !== '' ? (int)$_POST['swap_target_resident_id'] : null;

                // Check if student number already exists
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM residents WHERE student_number = ?");
                $stmt->execute([$studentNumber]);
                if ($stmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Student number already exists']);
                    break;
                }

                // Check if room is available (current occupancy < max_occupancy)
                $stmt = $db->prepare("
                    SELECT rt.max_occupancy, COUNT(r.resident_id) as current_count
                    FROM room_types rt
                    JOIN rooms ro ON rt.room_type_id = ro.room_type_id
                    LEFT JOIN residents r ON ro.room_id = r.room_id AND r.is_active = 1
                    WHERE ro.room_id = ?
                    GROUP BY ro.room_id, rt.max_occupancy
                ");
                $stmt->execute([$roomId]);
                $roomData = $stmt->fetch();
                if ($roomData['current_count'] >= $roomData['max_occupancy']) {
                    echo json_encode(['success' => false, 'message' => 'Room is fully occupied']);
                    break;
                }

                $stmt = $db->prepare("
                    INSERT INTO residents (first_name, last_name, student_number, email, phone, room_id, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$firstName, $lastName, $studentNumber, $email, $phone, $roomId]);

                // Update room occupancy status
                $stmt = $db->prepare("
                    UPDATE rooms
                    SET is_occupied = (
                        SELECT COUNT(*) FROM residents
                        WHERE room_id = rooms.room_id AND is_active = 1
                    ) >= (
                        SELECT max_occupancy FROM room_types
                        WHERE room_type_id = rooms.room_type_id
                    )
                    WHERE room_id = ?
                ");
                $stmt->execute([$roomId]);

                echo json_encode(['success' => true, 'message' => 'Resident added successfully']);
                break;

            case 'edit':
                $residentId = (int)$_POST['resident_id'];
                $firstName = trim($_POST['first_name']);
                $lastName = trim($_POST['last_name']);
                $studentNumber = trim($_POST['student_number']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $roomId = (int)$_POST['room_id'];
                $swapTargetResidentId = isset($_POST['swap_target_resident_id']) && $_POST['swap_target_resident_id'] !== '' ? (int)$_POST['swap_target_resident_id'] : null;

                // Check if student number exists for other residents
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM residents WHERE student_number = ? AND resident_id != ?");
                $stmt->execute([$studentNumber, $residentId]);
                if ($stmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Student number already exists']);
                    break;
                }

                // Get current room to update occupancy
                $stmt = $db->prepare("SELECT room_id FROM residents WHERE resident_id = ?");
                $stmt->execute([$residentId]);
                $currentRoom = $stmt->fetch()['room_id'];

                // If a swap target is provided and changing rooms, perform swap early
                if ($roomId != $currentRoom && $swapTargetResidentId) {
                    // Validate swap target belongs to the selected room and is active
                    $stmt = $db->prepare("SELECT resident_id, room_id FROM residents WHERE resident_id = ? AND is_active = 1");
                    $stmt->execute([$swapTargetResidentId]);
                    $target = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$target || (int)$target['room_id'] !== $roomId) {
                        echo json_encode(['success' => false, 'message' => 'Invalid swap target selected']);
                        break;
                    }

                    // Perform atomic swap
                    $db->beginTransaction();
                    try {
                        // Move target resident to current room
                        $stmt = $db->prepare("UPDATE residents SET room_id = ? WHERE resident_id = ?");
                        $stmt->execute([$currentRoom, $swapTargetResidentId]);

                        // Update this resident's details and move to target room
                        $stmt = $db->prepare("\n                            UPDATE residents\n                            SET first_name = ?, last_name = ?, student_number = ?, email = ?, phone = ?, room_id = ?\n                            WHERE resident_id = ?\n                        ");
                        $stmt->execute([$firstName, $lastName, $studentNumber, $email, $phone, $roomId, $residentId]);

                        $db->commit();

                        // Recalculate occupancy for both rooms
                        $stmt = $db->prepare("\n                            UPDATE rooms\n                            SET is_occupied = (\n                                SELECT COUNT(*) FROM residents\n                                WHERE room_id = rooms.room_id AND is_active = 1\n                            ) >= (\n                                SELECT max_occupancy FROM room_types\n                                WHERE room_type_id = rooms.room_type_id\n                            )\n                            WHERE room_id IN (?, ?)\n                        ");
                        $stmt->execute([$currentRoom, $roomId]);

                        echo json_encode(['success' => true, 'message' => 'Rooms swapped successfully']);
                        break;
                    } catch (Exception $e) {
                        $db->rollBack();
                        error_log('Swap rooms failed: ' . $e->getMessage());
                        echo json_encode(['success' => false, 'message' => 'Swap failed. Please try again']);
                        break;
                    }
                }

                // Check if new room is available (if different from current)
                if ($roomId != $currentRoom) {
                    $stmt = $db->prepare("
                        SELECT rt.max_occupancy, COUNT(r.resident_id) as current_count
                        FROM room_types rt
                        JOIN rooms ro ON rt.room_type_id = ro.room_type_id
                        LEFT JOIN residents r ON ro.room_id = r.room_id AND r.is_active = 1
                        WHERE ro.room_id = ?
                        GROUP BY ro.room_id, rt.max_occupancy
                    ");
                    $stmt->execute([$roomId]);
                    $roomData = $stmt->fetch();
                    if ($roomData['current_count'] >= $roomData['max_occupancy']) {
                        echo json_encode(['success' => false, 'message' => 'Room is fully occupied']);
                        break;
                    }
                }

                $stmt = $db->prepare("
                    UPDATE residents
                    SET first_name = ?, last_name = ?, student_number = ?, email = ?, phone = ?, room_id = ?
                    WHERE resident_id = ?
                ");
                $stmt->execute([$firstName, $lastName, $studentNumber, $email, $phone, $roomId, $residentId]);

                // Update room occupancy status for both old and new rooms
                $stmt = $db->prepare("
                    UPDATE rooms
                    SET is_occupied = (
                        SELECT COUNT(*) FROM residents
                        WHERE room_id = rooms.room_id AND is_active = 1
                    ) >= (
                        SELECT max_occupancy FROM room_types
                        WHERE room_type_id = rooms.room_type_id
                    )
                    WHERE room_id IN (?, ?)
                ");
                $stmt->execute([$currentRoom, $roomId]);

                echo json_encode(['success' => true, 'message' => 'Resident updated successfully']);
                break;

            case 'get_room_occupants':
                $roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
                if ($roomId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid room']);
                    break;
                }
                $stmt = $db->prepare("\n                    SELECT resident_id, first_name, last_name\n                    FROM residents\n                    WHERE room_id = ? AND is_active = 1\n                    ORDER BY created_at DESC\n                ");
                $stmt->execute([$roomId]);
                $occupants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'occupants' => $occupants]);
                break;
            case 'remove':
                $residentId = (int)$_POST['resident_id'];

                // Get room ID to free it up
                $stmt = $db->prepare("SELECT room_id FROM residents WHERE resident_id = ?");
                $stmt->execute([$residentId]);
                $roomId = $stmt->fetch()['room_id'];

                // Deactivate resident instead of deleting
                $stmt = $db->prepare("UPDATE residents SET is_active = 0 WHERE resident_id = ?");
                $stmt->execute([$residentId]);

                // Free up the room
                if ($roomId) {
                    $stmt = $db->prepare("UPDATE rooms SET is_occupied = 0 WHERE room_id = ?");
                    $stmt->execute([$roomId]);
                }

                echo json_encode(['success' => true, 'message' => 'Resident removed successfully']);
                break;

            case 'search':
                $searchTerm = $_POST['search_term'];
                $stmt = $db->prepare("
                    SELECT r.*, ro.room_number, rt.type_name
                    FROM residents r
                    LEFT JOIN rooms ro ON r.room_id = ro.room_id
                    LEFT JOIN room_types rt ON ro.room_type_id = rt.room_type_id
                    WHERE r.is_active = 1 AND (
                        r.first_name LIKE ? OR r.last_name LIKE ? OR
                        r.student_number LIKE ? OR r.email LIKE ?
                    )
                    ORDER BY r.created_at DESC
                    LIMIT 50
                ");
                $searchParam = "%$searchTerm%";
                $stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam]);
                $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'residents' => $residents]);
                break;

            case 'create_room':
                $roomNumber = trim($_POST['room_number']);
                $roomTypeId = (int)$_POST['room_type_id'];

                // Check if room number already exists
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM rooms WHERE room_number = ?");
                $stmt->execute([$roomNumber]);
                if ($stmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Room number already exists']);
                    break;
                }

                $stmt = $db->prepare("
                    INSERT INTO rooms (room_number, room_type_id, is_occupied)
                    VALUES (?, ?, 0)
                ");
                $stmt->execute([$roomNumber, $roomTypeId]);

                echo json_encode(['success' => true, 'message' => 'Room created successfully']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Action failed: ' . $e->getMessage()]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Residents - Sophen Admin</title>
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

        .manage-content {
            padding: 30px;
        }

        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .search-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: end;
        }

        .search-group {
            flex: 1;
        }

        .search-label {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
            font-size: 0.9rem;
        }

        .search-input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--admin-color);
            outline: none;
        }

        .action-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-primary {
            background: var(--admin-color);
            color: white;
        }

        .btn-primary:hover {
            background: #7d3c98;
            transform: translateY(-1px);
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #229954;
            transform: translateY(-1px);
            color: white;
        }

        .residents-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .container-header {
            background: var(--light-gray);
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .container-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
        }

        .residents-count {
            background: var(--admin-color);
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .residents-table {
            width: 100%;
            margin: 0;
        }

        .residents-table th {
            background: var(--light-gray);
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
            border: none;
        }

        .residents-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            font-size: 0.9rem;
            vertical-align: middle;
        }

        .residents-table tr:hover {
            background: rgba(142, 68, 173, 0.05);
        }

        .resident-info {
            display: flex;
            flex-direction: column;
        }

        .resident-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 3px;
        }

        .resident-student {
            font-size: 0.8rem;
            color: var(--dark-gray);
            opacity: 0.8;
        }

        .room-badge {
            background: var(--secondary-color);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            text-align: center;
        }

        .room-type {
            font-size: 0.7rem;
            color: var(--dark-gray);
            opacity: 0.7;
            margin-top: 2px;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
        }

        .contact-email {
            color: var(--secondary-color);
            margin-bottom: 3px;
        }

        .contact-phone {
            font-size: 0.8rem;
            color: var(--dark-gray);
            opacity: 0.8;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .table-action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .btn-edit {
            background: var(--secondary-color);
            color: white;
        }

        .btn-edit:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .btn-remove {
            background: var(--danger-color);
            color: white;
        }

        .btn-remove:hover {
            background: #c0392b;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--dark-gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Modal Styles */
        .modal-header {
            background: var(--admin-color);
            color: white;
        }

        .modal-title {
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            width: 100%;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--admin-color);
            box-shadow: 0 0 0 0.2rem rgba(142, 68, 173, 0.25);
            outline: none;
        }

        .alert-success, .alert-danger {
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
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

            .manage-content {
                padding: 20px;
            }

            .search-form {
                flex-direction: column;
                gap: 15px;
            }

            .action-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }

            .residents-table {
                font-size: 0.8rem;
            }

            .residents-table th,
            .residents-table td {
                padding: 10px 8px;
            }

            .action-buttons {
                flex-direction: column;
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
                <a href="manage-residents.php" class="nav-link active">
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
                <a href="reports.php" class="nav-link">
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
            <h1 class="page-title">Manage Residents</h1>
            <div class="navbar-actions">
                <a href="index.php" class="action-btn">
                    <i class="fas fa-tachometer-alt"></i>
                </a>
                <a href="logout.php" class="action-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Manage Content -->
        <div class="manage-content">
            <!-- Action Header -->
            <div class="action-header">
                <div class="search-section" style="flex: 1; margin: 0; margin-right: 20px;">
                    <form class="search-form" onsubmit="searchResidents(event)">
                        <div class="search-group">
                            <label class="search-label">Search residents</label>
                            <input type="text" class="search-input" id="searchInput" placeholder="Search by name, student number, or email...">
                        </div>
                        <button type="submit" class="action-btn btn-primary">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </form>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="action-btn btn-warning" data-bs-toggle="modal" data-bs-target="#createRoomModal">
                        <i class="fas fa-plus-circle me-2"></i>Create Room
                    </button>
                    <button class="action-btn btn-success" data-bs-toggle="modal" data-bs-target="#addResidentModal">
                        <i class="fas fa-plus me-2"></i>New Resident
                    </button>
                </div>
            </div>

            <!-- Success/Error Alerts -->
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle me-2"></i>
                <span id="successMessage">Action completed successfully</span>
            </div>
            <div class="alert alert-danger" id="errorAlert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span id="errorMessage">An error occurred</span>
            </div>

            <!-- Residents Table -->
            <div class="residents-container">
                <div class="container-header">
                    <h3 class="container-title">
                        <i class="fas fa-home me-2"></i>
                        All Residents
                    </h3>
                    <span class="residents-count" id="residentsCount"><?= count($residents) ?> residents</span>
                </div>

                <?php if (!empty($residents)): ?>
                <div class="table-responsive">
                    <table class="residents-table">
                        <thead>
                            <tr>
                                <th>Resident Information</th>
                                <th>Contact Details</th>
                                <th>Room Assignment</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="residentsTableBody">
                            <?php foreach ($residents as $resident): ?>
                            <tr data-resident-id="<?= $resident['resident_id'] ?>">
                                <td>
                                    <div class="resident-info">
                                        <div class="resident-name"><?= htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']) ?></div>
                                        <div class="resident-student">Student #: <?= htmlspecialchars($resident['student_number']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="contact-info">
                                        <div class="contact-email"><?= htmlspecialchars($resident['email'] ?? 'No email') ?></div>
                                        <div class="contact-phone"><?= htmlspecialchars($resident['phone'] ?? 'No phone') ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="text-align: center;">
                                        <div class="room-badge"><?= htmlspecialchars($resident['room_number'] ?? 'No room') ?></div>
                                        <div class="room-type"><?= htmlspecialchars($resident['type_name'] ?? '') ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="resident-info">
                                        <div class="resident-name"><?= date('M j, Y', strtotime($resident['created_at'])) ?></div>
                                        <div class="resident-student"><?= date('H:i', strtotime($resident['created_at'])) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="table-action-btn btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($resident)) ?>)">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </button>
                                        <button class="table-action-btn btn-remove" onclick="removeResident(<?= $resident['resident_id'] ?>, '<?= htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']) ?>')">
                                            <i class="fas fa-trash me-1"></i>Remove
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-home"></i>
                    <h4>No Residents Found</h4>
                    <p>No residents have been registered in the system yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Resident Modal -->
    <div class="modal fade" id="addResidentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add New Resident
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addResidentForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="addFirstName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="addLastName" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Student Number</label>
                            <input type="text" class="form-control" id="addStudentNumber" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="addEmail" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="addPhone">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Room Assignment</label>
                            <select class="form-select" id="addRoomId" required>
                                <option value="">Select a room</option>
                                <?php foreach ($availableRooms as $room): ?>
                                <option value="<?= $room['room_id'] ?>">
                                    <?= htmlspecialchars($room['room_number']) ?> - <?= htmlspecialchars($room['type_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Only available rooms are shown</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="addResident()">
                        <i class="fas fa-plus me-2"></i>Add Resident
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Resident Modal -->
    <div class="modal fade" id="editResidentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Resident
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editResidentForm">
                        <input type="hidden" id="editResidentId">
                        <input type="hidden" id="editOriginalRoomId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="editFirstName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="editLastName" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Student Number</label>
                            <input type="text" class="form-control" id="editStudentNumber" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="editEmail" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="editPhone">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Room Assignment</label>
                            <select class="form-select" id="editRoomId" required>
                                <option value="">Select a room</option>
                                <?php foreach ($allRooms as $room): ?>
                                <?php $occupied = ((int)$room['current_occupancy'] >= (int)$room['max_occupancy']); ?>
                                <option value="<?= $room['room_id'] ?>" <?= $occupied ? 'data-occupied="true"' : '' ?>>
                                    <?= htmlspecialchars($room['room_number']) ?> - <?= htmlspecialchars($room['type_name']) ?>
                                    <?= $occupied ? ' (Occupied)' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="swapContainer" class="alert alert-warning mt-2" style="display: none;">
                                <div>
                                    Selected room is fully occupied. You can swap rooms with an occupant.
                                </div>
                                <div class="form-group mt-2">
                                    <label class="form-label">Swap With Resident</label>
                                    <select class="form-select" id="swapTargetResidentId">
                                        <option value="">Select a resident to swap</option>
                                    </select>
                                    <small class="text-muted">Only residents currently in the selected room will appear.</small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateResident()">
                        <i class="fas fa-save me-2"></i>Update Resident
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Room Modal -->
    <div class="modal fade" id="createRoomModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Create New Room
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createRoomForm">
                        <div class="form-group">
                            <label class="form-label">Room Number</label>
                            <input type="text" class="form-control" id="createRoomNumber" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Room Type</label>
                            <select class="form-select" id="createRoomTypeId" required>
                                <option value="">Select room type</option>
                                <?php foreach ($roomTypes as $type): ?>
                                <option value="<?= $type['room_type_id'] ?>">
                                    <?= htmlspecialchars($type['type_name']) ?> (Max <?= $type['max_occupancy'] ?> residents)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="createRoom()">
                        <i class="fas fa-plus-circle me-2"></i>Create Room
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        function searchResidents(event) {
            event.preventDefault();
            const searchTerm = $('#searchInput').val().trim();

            if (!searchTerm) {
                showAlert('Please enter a search term', 'error');
                return;
            }

            $('#loadingOverlay').show();

            $.ajax({
                url: 'manage-residents.php',
                method: 'POST',
                data: {
                    action: 'search',
                    search_term: searchTerm
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        updateResidentsTable(response.residents);
                        $('#residentsCount').text(response.residents.length + ' residents');
                    } else {
                        showAlert(response.message, 'error');
                    }
                },
                error: function() {
                    showAlert('Search request failed', 'error');
                },
                complete: function() {
                    $('#loadingOverlay').hide();
                }
            });
        }

        function updateResidentsTable(residents) {
            const tbody = $('#residentsTableBody');
            tbody.empty();

            if (residents.length === 0) {
                tbody.parent().parent().html(`
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h4>No Residents Found</h4>
                        <p>No residents match your search criteria.</p>
                    </div>
                `);
                return;
            }

            residents.forEach(resident => {
                const createdDate = new Date(resident.created_at).toLocaleDateString('en-ZA');
                const createdTime = new Date(resident.created_at).toLocaleTimeString('en-ZA', {hour: '2-digit', minute: '2-digit'});

                const row = `
                    <tr data-resident-id="${resident.resident_id}">
                        <td>
                            <div class="resident-info">
                                <div class="resident-name">${resident.first_name} ${resident.last_name}</div>
                                <div class="resident-student">Student #: ${resident.student_number}</div>
                            </div>
                        </td>
                        <td>
                            <div class="contact-info">
                                <div class="contact-email">${resident.email || 'No email'}</div>
                                <div class="contact-phone">${resident.phone || 'No phone'}</div>
                            </div>
                        </td>
                        <td>
                            <div style="text-align: center;">
                                <div class="room-badge">${resident.room_number || 'No room'}</div>
                                <div class="room-type">${resident.type_name || ''}</div>
                            </div>
                        </td>
                        <td>
                            <div class="resident-info">
                                <div class="resident-name">${createdDate}</div>
                                <div class="resident-student">${createdTime}</div>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="table-action-btn btn-edit" onclick="openEditModal(${JSON.stringify(resident).replace(/"/g, '"')})">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <button class="table-action-btn btn-remove" onclick="removeResident(${resident.resident_id}, '${resident.first_name} ${resident.last_name}')">
                                    <i class="fas fa-trash me-1"></i>Remove
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        function addResident() {
            const firstName = $('#addFirstName').val().trim();
            const lastName = $('#addLastName').val().trim();
            const studentNumber = $('#addStudentNumber').val().trim();
            const email = $('#addEmail').val().trim();
            const phone = $('#addPhone').val().trim();
            const roomId = $('#addRoomId').val();

            if (!firstName || !lastName || !studentNumber || !email || !roomId) {
                showAlert('Please fill in all required fields', 'error');
                return;
            }

            $('#loadingOverlay').show();
            $('#addResidentModal').modal('hide');

            $.ajax({
                url: 'manage-residents.php',
                method: 'POST',
                data: {
                    action: 'add',
                    first_name: firstName,
                    last_name: lastName,
                    student_number: studentNumber,
                    email: email,
                    phone: phone,
                    room_id: roomId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        // Reset form and reload page
                        $('#addResidentForm')[0].reset();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showAlert(response.message, 'error');
                    }
                },
                error: function() {
                    showAlert('Add request failed', 'error');
                },
                complete: function() {
                    $('#loadingOverlay').hide();
                }
            });
        }

        function openEditModal(resident) {
            $('#editResidentId').val(resident.resident_id);
            $('#editFirstName').val(resident.first_name);
            $('#editLastName').val(resident.last_name);
            $('#editStudentNumber').val(resident.student_number);
            $('#editEmail').val(resident.email);
            $('#editPhone').val(resident.phone);
            $('#editRoomId').val(resident.room_id);
            $('#editOriginalRoomId').val(resident.room_id);
            $('#swapTargetResidentId').val('');
            $('#swapContainer').hide();
            
            // Enable all room options, but highlight current room
            $('#editRoomId option').each(function() {
                if ($(this).val() == resident.room_id) {
                    $(this).prop('disabled', false);
                }
            });
            
            // Bind change handler to show swap option when selecting an occupied room
            $('#editRoomId').off('change').on('change', function() {
                const selected = $(this).find('option:selected');
                const selectedRoomId = $(this).val();
                const originalRoomId = $('#editOriginalRoomId').val();
                const isOccupied = selected.data('occupied') === true || selected.attr('data-occupied') === 'true';
                if (selectedRoomId && selectedRoomId != originalRoomId && isOccupied) {
                    $('#swapContainer').show();
                    loadRoomOccupants(selectedRoomId);
                } else {
                    $('#swapContainer').hide();
                    $('#swapTargetResidentId').empty().append('<option value="">Select a resident to swap</option>');
                }
            });
            
            $('#editResidentModal').modal('show');
        }

        function loadRoomOccupants(roomId) {
            $('#swapTargetResidentId').empty().append('<option value="">Loading occupants...</option>');
            $.ajax({
                url: 'manage-residents.php',
                method: 'POST',
                data: {
                    action: 'get_room_occupants',
                    room_id: roomId
                },
                dataType: 'json',
                success: function(resp) {
                    const $sel = $('#swapTargetResidentId');
                    $sel.empty().append('<option value="">Select a resident to swap</option>');
                    if (resp.success && Array.isArray(resp.occupants)) {
                        if (resp.occupants.length) {
                            resp.occupants.forEach(o => {
                                $sel.append(`<option value="${o.resident_id}">${o.first_name} ${o.last_name}</option>`);
                            });
                        } else {
                            $sel.append('<option value="">No occupants found</option>');
                        }
                    } else {
                        showAlert(resp.message || 'Failed to load occupants', 'error');
                    }
                },
                error: function() {
                    showAlert('Failed to load occupants', 'error');
                }
            });
        }

        function updateResident() {
            const residentId = $('#editResidentId').val();
            const firstName = $('#editFirstName').val().trim();
            const lastName = $('#editLastName').val().trim();
            const studentNumber = $('#editStudentNumber').val().trim();
            const email = $('#editEmail').val().trim();
            const phone = $('#editPhone').val().trim();
            const roomId = $('#editRoomId').val();
            const originalRoomId = $('#editOriginalRoomId').val();
            const selectedOption = $('#editRoomId').find('option:selected');
            const isOccupied = selectedOption.data('occupied') === true || selectedOption.attr('data-occupied') === 'true';
            const swapTargetId = $('#swapTargetResidentId').val();

            if (!firstName || !lastName || !studentNumber || !email || !roomId) {
                showAlert('Please fill in all required fields', 'error');
                return;
            }

            if (roomId !== originalRoomId && isOccupied && (!swapTargetId || swapTargetId === '')) {
                showAlert('Selected room is occupied. Choose a resident to swap.', 'error');
                return;
            }

            $('#loadingOverlay').show();
            $('#editResidentModal').modal('hide');

            $.ajax({
                url: 'manage-residents.php',
                method: 'POST',
                data: {
                    action: 'edit',
                    resident_id: residentId,
                    first_name: firstName,
                    last_name: lastName,
                    student_number: studentNumber,
                    email: email,
                    phone: phone,
                    room_id: roomId,
                    swap_target_resident_id: swapTargetId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showAlert(response.message, 'error');
                    }
                },
                error: function() {
                    showAlert('Update request failed', 'error');
                },
                complete: function() {
                    $('#loadingOverlay').hide();
                }
            });
        }

        function removeResident(residentId, residentName) {
            if (!confirm(`Are you sure you want to remove ${residentName} from the residence? This will free up their room for assignment to another resident.`)) {
                return;
            }

            $('#loadingOverlay').show();

            $.ajax({
                url: 'manage-residents.php',
                method: 'POST',
                data: {
                    action: 'remove',
                    resident_id: residentId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        // Remove the row
                        $(`tr[data-resident-id="${residentId}"]`).fadeOut(300, function() {
                            $(this).remove();
                            updateResidentCount();
                        });
                    } else {
                        showAlert(response.message, 'error');
                    }
                },
                error: function() {
                    showAlert('Remove request failed', 'error');
                },
                complete: function() {
                    $('#loadingOverlay').hide();
                }
            });
        }

        function updateResidentCount() {
            const count = $('#residentsTableBody tr').length;
            $('#residentsCount').text(count + ' residents');
        }

        function showAlert(message, type) {
            const alertId = type === 'success' ? '#successAlert' : '#errorAlert';
            const messageId = type === 'success' ? '#successMessage' : '#errorMessage';

            $(messageId).text(message);
            $(alertId).fadeIn();

            setTimeout(() => {
                $(alertId).fadeOut();
            }, 5000);
        }

        function createRoom() {
            const roomNumber = $('#createRoomNumber').val().trim();
            const roomTypeId = $('#createRoomTypeId').val();

            if (!roomNumber || !roomTypeId) {
                showAlert('Please fill in all required fields', 'error');
                return;
            }

            $('#loadingOverlay').show();
            $('#createRoomModal').modal('hide');

            $.ajax({
                url: 'manage-residents.php',
                method: 'POST',
                data: {
                    action: 'create_room',
                    room_number: roomNumber,
                    room_type_id: roomTypeId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        // Reset form and reload page
                        $('#createRoomForm')[0].reset();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showAlert(response.message, 'error');
                    }
                },
                error: function() {
                    showAlert('Create room request failed', 'error');
                },
                complete: function() {
                    $('#loadingOverlay').hide();
                }
            });
        }

        $(document).ready(function() {
            // Real-time search
            let searchTimeout;
            $('#searchInput').on('input', function() {
                clearTimeout(searchTimeout);
                const searchTerm = $(this).val().trim();
                
                if (searchTerm.length >= 2) {
                    searchTimeout = setTimeout(() => {
                        const event = { preventDefault: () => {} };
                        searchResidents(event);
                    }, 500);
                } else if (searchTerm.length === 0) {
                    window.location.reload();
                }
            });

            // Format phone numbers
            $('#addPhone, #editPhone').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.length > 0) {
                    if (value.startsWith('27')) {
                        value = '+27 ' + value.substring(2);
                    } else if (!value.startsWith('+')) {
                        value = '+27 ' + value;
                    }
                    value = value.replace(/(\+27)(\d{3})(\d{3})(\d{3})/, '$1 $2 $3 $4');
                }
                $(this).val(value);
            });

            // Auto-capitalize names
            $('#addFirstName, #addLastName, #editFirstName, #editLastName').on('input', function() {
                const value = $(this).val();
                const capitalized = value.replace(/\b\w/g, l => l.toUpperCase());
                $(this).val(capitalized);
            });
        });
    </script>
</body>
</html>
