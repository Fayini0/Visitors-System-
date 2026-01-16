<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php'; // For PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed!");
}

$error_message = '';
$success_message = '';
$visit_id = null;
$visitor_data = null;

// Handle POST from step4
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
        $error_message = 'Missing required fields: ' . implode(', ', $missing_fields);
    } else {
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
        $update_count = $db->prepare("UPDATE visitors SET visit_count = visit_count + 1, last_visit_date = CURRENT_TIMESTAMP WHERE visitor_id = ?");
        $update_count->execute([$visitor_id]);
    } else {
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

    // Validate resident
    $get_resident_in_room = $db->prepare("SELECT r.email FROM residents r WHERE r.room_id = ? AND CONCAT(r.first_name, ' ', r.last_name) = ? AND r.is_active = TRUE");
    $get_resident_in_room->execute([$room_id, $host_name]);
    $resident_in_room = $get_resident_in_room->fetch(PDO::FETCH_ASSOC);
    if (!$resident_in_room) {
        throw new Exception("Selected resident '{$host_name}' does not reside in room '{$room_number}'. Please select a valid resident for the room.");
    }

    // Insert visit
    $purpose = 'other';
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

    // CRITICAL FIX: Commit transaction BEFORE calling external script
    $db->commit();

    // Add small delay to ensure database commit is complete
    usleep(100000); // 0.1 second delay

    // Now send verification email
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/sophen-residence-system/visitor/process/send_verification.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['visit_id' => $visit_id]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200 && $response) {
        $result = json_decode($response, true);
        if ($result && isset($result['success']) && $result['success']) {
            $success_message = $result['message'];
        } else {
            $error_detail = isset($result['message']) ? $result['message'] : 'Unknown error';
            $debug_info = isset($result['debug']) ? json_encode($result['debug']) : 'No debug info';
            error_log("Email sending failed: {$error_detail}. Debug: {$debug_info}");
            $success_message = 'Visit submitted, but email verification failed: ' . htmlspecialchars($error_detail) . ' Please contact security.';
        }
    } else {
        error_log("cURL failed: HTTP {$http_code}, Error: {$curl_error}, Response: {$response}");
        $success_message = 'Visit submitted, but email sending failed (HTTP ' . $http_code . '). Please contact security.';
    }

    // Store data for display
    $visitor_data = [
        'visitor_name' => $full_name,
        'room_number' => $room_number,
        'host_name' => $host_name,
        'submit_time' => date('Y-m-d H:i:s'),
        'status' => 'pending'
    ];

    $_SESSION['current_visit_id'] = $visit_id;

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Visit processing error: " . $e->getMessage());
    $error_message = 'Error processing visit: ' . $e->getMessage();
}
    }
} else {
    // No POST, redirect to step4 or step1
    header('Location: step4.php');
    exit;
}

// If no visit_id, redirect
if (!$visit_id) {
    header('Location: step1.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sophen - Verification Pending</title>
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
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
        }

        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verification-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        .verification-icon {
            width: 100px;
            height: 100px;
            background: var(--warning-color);
            border-radius: 50%;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.3);
            animation: pulse 2s infinite;
        }

        .verification-icon i {
            font-size: 50px;
            color: white;
        }

        .page-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .page-subtitle {
            color: var(--dark-gray);
            font-size: 1rem;
            opacity: 0.8;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .verification-steps {
            background: rgba(243, 156, 18, 0.1);
            border-left: 4px solid var(--warning-color);
            padding: 25px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
        }

        .verification-steps h5 {
            color: var(--warning-color);
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .verification-steps h5 i {
            margin-right: 10px;
        }

        .verification-steps ol {
            margin: 0;
            padding-left: 20px;
        }

        .verification-steps li {
            color: var(--dark-gray);
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .status-display {
            background: var(--light-gray);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-label {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .status-value {
            color: var(--primary-color);
            font-weight: 500;
        }

        .status-pending {
            color: var(--warning-color);
            display: flex;
            align-items: center;
        }

        .status-pending i {
            margin-left: 8px;
            animation: spin 2s linear infinite;
        }

        .loading-animation {
            margin: 30px 0;
        }

        .loading-dots {
            display: flex;
            justify-content: center;
            gap: 8px;
        }

        .dot {
            width: 12px;
            height: 12px;
            background: var(--warning-color);
            border-radius: 50%;
            animation: bounce 1.4s ease-in-out infinite both;
        }

        .dot:nth-child(1) { animation-delay: -0.32s; }
        .dot:nth-child(2) { animation-delay: -0.16s; }

        .refresh-button {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 20px 10px;
        }

        .refresh-button:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .cancel-button {
            background: var(--light-gray);
            color: var(--dark-gray);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 20px 10px;
            text-decoration: none;
            display: inline-block;
        }

        .cancel-button:hover {
            background: #bdc3c7;
            color: var(--dark-gray);
        }

        .contact-info {
            background: rgba(52, 152, 219, 0.1);
            border-left: 4px solid var(--secondary-color);
            padding: 15px;
            border-radius: 8px;
            margin: 25px 0;
            font-size: 0.9rem;
            text-align: left;
        }

        .contact-info p {
            margin: 0;
            color: var(--dark-gray);
        }

        .error-alert {
            background: rgba(231, 76, 60, 0.1);
            border-left: 4px solid var(--danger-color);
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            color: var(--danger-color);
        }

        .success-alert {
            background: rgba(39, 174, 96, 0.1);
            border-left: 4px solid var(--success-color);
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            color: var(--success-color);
        }

        @media (max-width: 768px) {
            .verification-card {
                padding: 30px 25px;
                margin: 20px;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .verification-icon {
                width: 80px;
                height: 80px;
            }

            .verification-icon i {
                font-size: 40px;
            }

            .status-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }

        .animate-entrance {
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes bounce {
            0%, 80%, 100% {
                transform: scale(0);
            }
            40% {
                transform: scale(1);
            }
        }

        .auto-refresh-info {
            color: var(--dark-gray);
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="verification-card animate-entrance">
            <?php if (!empty($error_message)): ?>
                <div class="error-alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <a href="step4.php" class="btn btn-secondary">Go Back</a>
            <?php elseif (!empty($success_message)): ?>
                <div class="success-alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($visitor_data): ?>
                <div class="verification-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>

                <h1 class="page-title">Verification Pending</h1>
                <p class="page-subtitle">
                    We're verifying your visit with the resident. Please wait while we process your request.
                </p>

                <div class="verification-steps">
                    <h5><i class="fas fa-list-check"></i>Verification Process</h5>
                    <ol>
                        <li>Your visit details have been submitted</li>
                        <li>A verification email has been sent to the resident</li>
                        <li>Waiting for resident confirmation</li>
                        <li>Once confirmed, you'll be granted access</li>
                    </ol>
                </div>

                <div class="status-display">
                    <div class="status-item">
                        <span class="status-label">Visitor:</span>
                        <span class="status-value"><?php echo htmlspecialchars($visitor_data['visitor_name']); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Room:</span>
                        <span class="status-value"><?php echo htmlspecialchars($visitor_data['room_number']); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Host:</span>
                        <span class="status-value"><?php echo htmlspecialchars($visitor_data['host_name']); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Status:</span>
                        <span class="status-pending" id="verificationStatus">
                            Pending Verification
                            <i class="fas fa-spinner"></i>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Submitted:</span>
                        <span class="status-value"><?php echo date('M j, H:i', strtotime($visitor_data['submit_time'])); ?></span>
                    </div>
                </div>

                <div class="loading-animation">
                    <div class="loading-dots">
                        <div class="dot"></div>
                        <div class="dot"></div>
                        <div class="dot"></div>
                    </div>
                    <p class="auto-refresh-info">
                        Checking status automatically every 10 seconds...
                    </p>
                </div>

                <div class="contact-info">
                    <p>
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> If verification takes longer than expected, please contact the resident directly or ask security for assistance.
                    </p>
                </div>

                <div>
                    <button class="refresh-button" onclick="checkStatus()">
                        <i class="fas fa-sync-alt me-2"></i>
                        Check Status Now
                    </button>
                    <a href="step1.php" class="cancel-button">
                        <i class="fas fa-times me-2"></i>
                        Cancel Visit
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        let statusCheckInterval;
        let visitId = <?php echo json_encode($visit_id); ?>;

        $(document).ready(function() {
            if (visitId) {
                // Start automatic status checking
                startStatusChecking();
            }
        });

        function checkStatus() {
            const button = $('.refresh-button');
            const originalText = button.html();

            // Show loading state
            button.prop('disabled', true)
                  .html('<i class="fas fa-spinner fa-spin me-2"></i>Checking...');

            // AJAX call to check status
            $.ajax({
                url: 'process/check_status.php',
                type: 'POST',
                data: { visit_id: visitId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.visit && response.visit.visit_status === 'approved') {
                        clearInterval(statusCheckInterval);
                        showApprovalMessage();
                    } else if (response.success && response.visit && response.visit.visit_status === 'declined') {
                        clearInterval(statusCheckInterval);
                        showDeclineMessage();
                    } else {
                        updateStatus(response.visit && response.visit.visit_status ? response.visit.visit_status : 'pending');
                        button.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error checking status:', error);
                    updateStatus('pending');
                    button.prop('disabled', false).html(originalText);
                }
            });
        }

        function startStatusChecking() {
            // Check status every 10 seconds
            statusCheckInterval = setInterval(() => {
                checkStatusSilently();
            }, 10000);
        }

        function checkStatusSilently() {
            // Silent check
            $.ajax({
                url: 'process/check_status.php',
                type: 'POST',
                data: { visit_id: visitId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.visit && response.visit.visit_status === 'approved') {
                        clearInterval(statusCheckInterval);
                        showApprovalMessage();
                    } else if (response.success && response.visit && response.visit.visit_status === 'declined') {
                        clearInterval(statusCheckInterval);
                        showDeclineMessage();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Silent status check error:', error);
                }
            });
        }

        function showApprovalMessage() {
            const card = $('.verification-card');
            card.html(`
                <div class="verification-icon" style="background: var(--success-color);">
                    <i class="fas fa-check"></i>
                </div>
                <h1 class="page-title" style="color: var(--success-color);">Verification Approved!</h1>
                <p class="page-subtitle">Your visit has been confirmed. Redirecting you now...</p>
                <div class="loading-animation">
                    <div class="loading-dots">
                        <div class="dot"></div>
                        <div class="dot"></div>
                        <div class="dot"></div>
                    </div>
                </div>
            `);

            setTimeout(() => {
                window.location.href = 'step8.php?visit_id=' + visitId;
            }, 3000);
        }

        function showDeclineMessage() {
            const card = $('.verification-card');
            card.html(`
                <div class="verification-icon" style="background: var(--danger-color);">
                    <i class="fas fa-times"></i>
                </div>
                <h1 class="page-title" style="color: var(--danger-color);">Visit Declined</h1>
                <p class="page-subtitle">Your visit request has been declined by the resident. Please contact them for more information.</p>
                <a href="step1.php" class="btn btn-primary">Submit Another Request</a>
            `);
        }

        function updateStatus(status) {
            if (status === 'pending') {
                $('#verificationStatus').html(`
                    Pending Verification
                    <i class="fas fa-spinner"></i>
                `);
            } else if (status === 'approved') {
                $('#verificationStatus').html(`
                    Approved
                    <i class="fas fa-check"></i>
                `);
            } else if (status === 'declined') {
                showDeclineMessage();
            }
        }

        // Clean up interval when leaving page
        window.addEventListener('beforeunload', function() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
            }
        });
    </script>
</body>
</html>
