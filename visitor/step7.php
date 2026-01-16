<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed!");
}

// Handle POST from step5
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitor_id = intval($_POST['visitor_id'] ?? 0);
    $room_number = trim($_POST['room_number'] ?? '');
    $host_name = trim($_POST['host_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!$visitor_id || !$room_number || !$host_name) {
        die("Invalid data received from step5");
    }

    try {
        $db->beginTransaction();

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

        // Update visitor phone if provided
        if (!empty($phone)) {
            $update_phone = $db->prepare("UPDATE visitors SET phone = ? WHERE visitor_id = ?");
            $update_phone->execute([$phone, $visitor_id]);
        }

        // Insert visit
        $purpose = 'returning_visitor';
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

        // Commit transaction
        $db->commit();

        // Send verification email
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/sophen-residence-system/visitor/process/send_verification.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['visit_id' => $visit_id]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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
                error_log("Email sending failed: {$error_detail}. Debug: " . json_encode($result));
                $success_message = 'Visit submitted, but email verification failed: ' . htmlspecialchars($error_detail) . ' Please contact security.';
            }
        } else {
            error_log("cURL failed: HTTP {$http_code}, Error: {$curl_error}, Response: {$response}");
            $success_message = 'Visit submitted, but email sending failed (HTTP ' . $http_code . '). Please contact security.';
        }

        // Store visit_id for JavaScript
        $current_visit_id = $visit_id;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Visit processing error: " . $e->getMessage());
        die("Error processing visit: " . $e->getMessage());
    }
} else {
    // No POST, redirect to step5
    header('Location: step5.php');
    exit;
}

// Ensure we have a visit ID before proceeding
if (!isset($current_visit_id)) {
    header('Location: step5.php');
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
            max-width: 550px;
            width: 100%;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        .verification-icon {
            width: 100px;
            height: 100px;
            background: var(--success-color);
            border-radius: 50%;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
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

        .welcome-back-msg {
            background: rgba(39, 174, 96, 0.1);
            border-left: 4px solid var(--success-color);
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
        }

        .welcome-back-msg h5 {
            color: var(--success-color);
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .welcome-back-msg h5 i {
            margin-right: 10px;
        }

        .welcome-back-msg p {
            margin: 0;
            color: var(--dark-gray);
            line-height: 1.5;
        }

        .visitor-info {
            background: var(--light-gray);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            text-align: left;
        }

        .info-header {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .info-header i {
            margin-right: 8px;
            color: var(--success-color);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .info-value {
            color: var(--primary-color);
            font-weight: 500;
        }

        .current-visit {
            background: rgba(243, 156, 18, 0.1);
            border: 2px solid var(--warning-color);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
        }

        .current-visit h6 {
            color: var(--warning-color);
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .current-visit h6 i {
            margin-right: 8px;
        }

        .status-pending {
            color: var(--warning-color);
            display: flex;
            align-items: center;
            justify-content: flex-end;
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
            background: var(--success-color);
            border-radius: 50%;
            animation: bounce 1.4s ease-in-out infinite both;
        }

        .dot:nth-child(1) { animation-delay: -0.32s; }
        .dot:nth-child(2) { animation-delay: -0.16s; }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .refresh-button {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .refresh-button:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
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
            text-decoration: none;
            display: inline-block;
        }

        .cancel-button:hover {
            background: #bdc3c7;
            color: var(--dark-gray);
        }

        .previous-visits {
            background: rgba(52, 152, 219, 0.1);
            border-left: 4px solid var(--secondary-color);
            padding: 15px;
            border-radius: 8px;
            margin: 25px 0;
            font-size: 0.9rem;
            text-align: left;
        }

        .previous-visits h6 {
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .previous-visits h6 i {
            margin-right: 8px;
        }

        .visit-item {
            padding: 5px 0;
            color: var(--dark-gray);
            border-bottom: 1px solid rgba(52, 152, 219, 0.2);
        }

        .visit-item:last-child {
            border-bottom: none;
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

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .action-buttons {
                flex-direction: column;
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
            <div class="verification-icon">
                <i class="fas fa-user-check"></i>
            </div>

            <h1 class="page-title">Welcome Back!</h1>
            <p class="page-subtitle">
                We're verifying your visit with the resident. Since you're a returning visitor, this should be quick!
            </p>

            <div class="welcome-back-msg">
                <h5><i class="fas fa-heart"></i>Great to see you again!</h5>
                <p>We found your previous visit information. We're just confirming your current visit with the resident.</p>
            </div>

            <div class="visitor-info">
                <div class="info-header">
                    <i class="fas fa-user"></i>
                    Your Information
                </div>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value" id="visitorName">Loading...</span>
                </div>
                <div class="info-row">
                    <span class="info-label">ID Number:</span>
                    <span class="info-value" id="visitorId">Loading...</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value" id="visitorEmail">Loading...</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Visits:</span>
                    <span class="info-value" id="totalVisits">Loading...</span>
                </div>
            </div>

            <div class="current-visit">
                <h6><i class="fas fa-clock"></i>Current Visit Request</h6>
                <div class="info-row">
                    <span class="info-label">Room:</span>
                    <span class="info-value" id="roomNumber">Loading...</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Host:</span>
                    <span class="info-value" id="hostName">Loading...</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Submitted:</span>
                    <span class="info-value" id="submitTime">Loading...</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="status-pending" id="verificationStatus">
                        Pending Verification
                        <i class="fas fa-spinner"></i>
                    </span>
                </div>
            </div>

            <div class="previous-visits">
                <h6><i class="fas fa-history"></i>Recent Visits</h6>
                <div class="visit-item">Room B302 • Mike Johnson • Dec 15, 2024</div>
                <div class="visit-item">Room A105 • Sarah Davis • Dec 10, 2024</div>
                <div class="visit-item">Room B302 • Mike Johnson • Dec 5, 2024</div>
            </div>

            <div class="loading-animation">
                <div class="loading-dots">
                    <div class="dot"></div>
                    <div class="dot"></div>
                    <div class="dot"></div>
                </div>
                <p class="auto-refresh-info">
                    Checking verification status every 10 seconds...
                </p>
            </div>

            <div class="action-buttons">
                <button class="refresh-button" onclick="checkStatus()">
                    <i class="fas fa-sync-alt me-2"></i>
                    Check Status Now
                </button>
                <a href="step1.php" class="cancel-button">
                    <i class="fas fa-times me-2"></i>
                    Cancel Visit
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        let statusCheckInterval;
        let visitId = <?php echo isset($current_visit_id) ? json_encode($current_visit_id) : 'null'; ?>;

        $(document).ready(function() {
            console.log('Initial visitId from PHP:', visitId); // Debug log

            // Load visit details
            loadVisitDetails();

            // Start automatic status checking
            startStatusChecking();
        });

        function loadVisitDetails() {
            if (!visitId) {
                alert('No visit ID found. Please restart the process.');
                window.location.href = 'step5.php';
                return;
            }

            // Fetch real visit data from backend
            $.ajax({
                url: 'process/check_status.php',
                type: 'POST',
                data: { visit_id: visitId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const visitData = response.visit;
                        $('#visitorName').text(visitData.visitor_name);
                        $('#visitorId').text(visitData.visitor_id);
                        $('#visitorEmail').text(visitData.visitor_email);
                        $('#totalVisits').text(visitData.total_visits);
                        $('#roomNumber').text(visitData.room_number);
                        $('#hostName').text(visitData.host_name);
                        $('#submitTime').text(formatDateTime(visitData.submit_time));

                        // Update status
                        updateStatus(visitData.visit_status);
                    } else {
                        alert('Error loading visit details: ' + response.message);
                        window.location.href = 'step5.php';
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading visit details:', error);
                    alert('Error loading visit details. Please try again.');
                    window.location.href = 'step5.php';
                }
            });
        }

        function checkStatus() {
            const button = $('.refresh-button');
            const originalText = button.html();

            // Show loading state
            button.prop('disabled', true)
                  .html('<i class="fas fa-spinner fa-spin me-2"></i>Checking...');

            // Real API call to check verification status
            $.ajax({
                url: 'process/check_status.php',
                type: 'POST',
                data: { visit_id: visitId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const visitData = response.visit;
                        updateStatus(visitData.visit_status);

                        if (visitData.visit_status === 'approved') {
                            // Visit approved - redirect to confirmation page
                            clearInterval(statusCheckInterval);
                            showApprovalMessage();
                        } else if (visitData.visit_status === 'declined') {
                            // Visit declined - show message and redirect
                            clearInterval(statusCheckInterval);
                            showDeclineMessage();
                        } else {
                            // Still pending
                            button.prop('disabled', false).html(originalText);
                        }
                    } else {
                        alert('Error checking status: ' + response.message);
                        button.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error checking status:', error);
                    alert('Error checking status. Please try again.');
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
            // Silent status check without UI feedback
            $.ajax({
                url: 'process/check_status.php',
                type: 'POST',
                data: { visit_id: visitId },
                dataType: 'json',
                success: function(response) {
                    console.log('Silent status check response:', response); // Debug log
                    if (response.success) {
                        const visitData = response.visit;
                        console.log('Current status:', visitData.visit_status); // Debug log
                        updateStatus(visitData.visit_status);

                        if (visitData.visit_status === 'approved') {
                            clearInterval(statusCheckInterval);
                            showApprovalMessage();
                        } else if (visitData.visit_status === 'declined') {
                            clearInterval(statusCheckInterval);
                            showDeclineMessage();
                        }
                        // If still pending, continue checking
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Silent status check error:', error);
                    // Continue checking even on error
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
                <p class="page-subtitle">Welcome back! Your visit has been confirmed. Redirecting you now...</p>
                <div class="loading-animation">
                    <div class="loading-dots">
                        <div class="dot"></div>
                        <div class="dot"></div>
                        <div class="dot"></div>
                    </div>
                </div>
            `);
            
            setTimeout(() => {
                window.location.href = 'step9.php?visit_id=' + visitId;
            }, 3000);
        }

        function updateStatus(status) {
            if (status === 'pending') {
                $('#verificationStatus').html(`
                    Pending Verification
                    <i class="fas fa-spinner"></i>
                `);
            } else if (status === 'approved') {
                $('#verificationStatus').html(`
                    <span style="color: var(--success-color);">
                        Approved
                        <i class="fas fa-check"></i>
                    </span>
                `);
            } else if (status === 'declined') {
                $('#verificationStatus').html(`
                    <span style="color: var(--danger-color);">
                        Declined
                        <i class="fas fa-times"></i>
                    </span>
                `);
            } else if (status === 'checked_out') {
                $('#verificationStatus').html(`
                    <span style="color: var(--secondary-color);">
                        Checked Out
                        <i class="fas fa-sign-out-alt"></i>
                    </span>
                `);
            }
        }

        function showDeclineMessage() {
            const card = $('.verification-card');
            card.html(`
                <div class="verification-icon" style="background: var(--danger-color);">
                    <i class="fas fa-times"></i>
                </div>
                <h1 class="page-title" style="color: var(--danger-color);">Verification Declined</h1>
                <p class="page-subtitle">Your visit request has been declined by the resident. Please contact the resident or security for more information.</p>
                <div class="action-buttons">
                    <a href="step1.php" class="cancel-button">
                        <i class="fas fa-home me-2"></i>
                        Start New Visit
                    </a>
                </div>
            `);
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-ZA', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
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