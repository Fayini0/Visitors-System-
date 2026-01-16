<?php
session_start();
require_once '../../includes/functions.php';
require_admin();

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = strtolower(trim($_GET['action'] ?? $_POST['action'] ?? ''));

    // Utility: map role string to role_id
    $getRoleId = function($roleStr) use ($db) {
        $roleStr = strtolower(trim($roleStr));
        // Normalize common labels
        if ($roleStr === 'administrator' || $roleStr === 'admin') { $roleStr = 'administrator'; }
        if ($roleStr === 'security' || $roleStr === 'guard') { $roleStr = 'security'; }

        $stmt = $db->prepare("SELECT role_id, role_name, is_admin FROM roles WHERE LOWER(role_name) = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$roleStr]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$role) {
            throw new Exception('Invalid role selected');
        }
        return $role;
    };

    if ($method === 'GET' && ($action === 'list' || $action === 'list_users')) {
        // Return all users joined with roles
        $stmt = $db->prepare("SELECT u.user_id, u.username, u.first_name, u.last_name, u.created_at, r.role_name, r.is_admin, u.is_active
                              FROM users u
                              JOIN roles r ON u.role_id = r.role_id
                              ORDER BY u.created_at DESC");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'users' => $users]);
        exit();
    }

    if ($method === 'GET' && ($action === 'get_user' || $action === 'view')) {
        $user_id = (int)($_GET['user_id'] ?? 0);
        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit();
        }
        $stmt = $db->prepare("SELECT u.user_id, u.username, u.first_name, u.last_name, u.created_at, u.is_active, r.role_name, r.role_id
                               FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        echo json_encode(['success' => true, 'user' => $user]);
        exit();
    }

    if ($method === 'POST' && ($action === 'create' || $action === 'create_user')) {
        $first_name = sanitize_input($_POST['first_name'] ?? '');
        $last_name = sanitize_input($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role_input = trim($_POST['role'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$first_name || !$last_name || !$username || !$role_input || !$password || !$confirm) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit();
        }
        if ($password !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
            exit();
        }
        if (!preg_match('/^[A-Za-z0-9_\.\-]{3,50}$/', $username)) {
            echo json_encode(['success' => false, 'message' => 'Invalid username format']);
            exit();
        }

        // Check duplicate username
        $check = $db->prepare('SELECT COUNT(*) AS c FROM users WHERE username = ?');
        $check->execute([$username]);
        if ((int)$check->fetch(PDO::FETCH_ASSOC)['c'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit();
        }

        // Resolve role id
        try {
            $role = $getRoleId($role_input);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }

        // Insert user
        $hash = hash_password($password);
        $created_by = $_SESSION['user_id'] ?? null;

        $stmt = $db->prepare('INSERT INTO users (username, password_hash, first_name, last_name, role_id, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())');
        $stmt->execute([$username, $hash, $first_name, $last_name, $role['role_id'], $created_by]);

        $new_id = $db->lastInsertId();
        $fetch = $db->prepare("SELECT u.user_id, u.username, u.first_name, u.last_name, u.created_at, r.role_name, r.is_admin
                               FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
        $fetch->execute([$new_id]);
        $user = $fetch->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'message' => 'Profile created successfully', 'user' => $user]);
        exit();
    }

    if ($method === 'POST' && ($action === 'update' || $action === 'update_user')) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $first_name = sanitize_input($_POST['first_name'] ?? '');
        $last_name = sanitize_input($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role_input = trim($_POST['role'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$user_id || !$first_name || !$last_name || !$username || !$role_input) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }
        if (!preg_match('/^[A-Za-z0-9_\.\-]{3,50}$/', $username)) {
            echo json_encode(['success' => false, 'message' => 'Invalid username format']);
            exit();
        }

        // Check user exists
        $exists = $db->prepare('SELECT user_id FROM users WHERE user_id = ?');
        $exists->execute([$user_id]);
        if (!$exists->fetch()) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }

        // Check duplicate username excluding current user
        $dup = $db->prepare('SELECT COUNT(*) AS c FROM users WHERE username = ? AND user_id <> ?');
        $dup->execute([$username, $user_id]);
        if ((int)$dup->fetch(PDO::FETCH_ASSOC)['c'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already in use']);
            exit();
        }

        // Resolve role id
        try {
            $role = $getRoleId($role_input);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }

        // Build update query
        if ($password !== '') {
            if ($password !== $confirm) {
                echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
                exit();
            }
            $hash = hash_password($password);
            $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, username = ?, role_id = ?, password_hash = ? WHERE user_id = ?');
            $stmt->execute([$first_name, $last_name, $username, $role['role_id'], $hash, $user_id]);
        } else {
            $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, username = ?, role_id = ? WHERE user_id = ?');
            $stmt->execute([$first_name, $last_name, $username, $role['role_id'], $user_id]);
        }

        // Fetch updated user
        $fetch = $db->prepare("SELECT u.user_id, u.username, u.first_name, u.last_name, u.created_at, r.role_name, r.is_admin
                               FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
        $fetch->execute([$user_id]);
        $user = $fetch->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully', 'user' => $user]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit();
}
?>