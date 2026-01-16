<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed!");
}

// Get visit_id from URL
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

if (!$visit_id) {
    header('Location: step1.php');
    exit;
}

try {
    // Fetch visit details with related data
    $query = "SELECT v.visit_id, v.visitor_id, v.room_id, v.host_name, v.purpose, v.visit_status, 
                     v.actual_checkin, v.expected_checkout, v.created_at,
                     vis.first_name as visitor_first, vis.last_name as visitor_last, vis.id_number, vis.email as visitor_email,
                     r.room_number,
                     res.email as resident_email
              FROM visits v
              INNER JOIN visitors vis ON v.visitor_id = vis.visitor_id
              INNER JOIN rooms r ON v.room_id = r.room_id
              LEFT JOIN residents res ON r.room_id = res.room_id AND CONCAT(res.first_name, ' ', res.last_name) = v.host_name
              WHERE v.visit_id = ? AND v.visit_status = 'approved'";

    $stmt = $db->prepare($query);
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visit) {
        // Visit not found or not approved
        header('Location: step6.php');
        exit;
    }

    // Determine if current time is within quiet hours (23:00–07:00)
    $quiet_hours_blocked = is_quiet_hours();

    // Update visit status to checked_in and set times
    $now = date('Y-m-d H:i:s');
    // Determine expected checkout based on system default daily cutoff
    $settings = get_system_settings();
    $defaultCheckoutTime = $settings['default_checkout_time'] ?? DEFAULT_CHECKOUT_TIME;

    // Build today's cutoff datetime
    $today = date('Y-m-d');
    $todayCutoff = strtotime($today . ' ' . $defaultCheckoutTime);
    $nowTs = strtotime($now);

    if ($nowTs <= $todayCutoff) {
        $expected_checkout = date('Y-m-d H:i:s', $todayCutoff);
    } else {
        // If current time past cutoff, set to next day's cutoff
        $tomorrow = date('Y-m-d', strtotime('+1 day', $nowTs));
        $expected_checkout = $tomorrow . ' ' . $defaultCheckoutTime;
    }

    if (!$quiet_hours_blocked) {
        $update_query = "UPDATE visits SET visit_status = 'checked_in', 
                         actual_checkin = COALESCE(actual_checkin, ?), 
                         expected_checkout = ? 
                         WHERE visit_id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$now, $expected_checkout, $visit_id]);

        // Refresh visit data
        $visit['actual_checkin'] = $now;
        $visit['expected_checkout'] = $expected_checkout;
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Prepare data for display
$visitor_name = htmlspecialchars($visit['visitor_first'] . ' ' . $visit['visitor_last']);
$visitor_id = htmlspecialchars($visit['id_number']);
$room_number = htmlspecialchars($visit['room_number']);
$host_name = htmlspecialchars($visit['host_name']);
$checkin_time = date('M j, Y - H:i', strtotime($visit['actual_checkin']));
$checkout_time = date('M j, Y - H:i', strtotime($visit['expected_checkout']));
$checkout_datetime = $visit['expected_checkout'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sophen - Visit <?= $quiet_hours_blocked ? 'Pending' : 'Confirmed' ?></title>
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

        .confirmation-card {
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

        .success-icon {
            width: 120px;
            height: 120px;
            background: var(--success-color);
            border-radius: 50%;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 15px 40px rgba(39, 174, 96, 0.4);
            animation: successPulse 2s ease-in-out infinite alternate;
        }

        .success-icon i {
            font-size: 60px;
            color: white;
        }

        .page-title {
            color: var(--success-color);
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .page-subtitle {
            color: var(--dark-gray);
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .visit-details {
            background: rgba(39, 174, 96, 0.1);
            border: 2px solid var(--success-color);
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            text-align: left;
        }

        .details-header {
            color: var(--success-color);
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .details-header i {
            margin-right: 10px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(39, 174, 96, 0.2);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .detail-value {
            color: var(--primary-color);
            font-weight: 500;
        }

        .visit-timer {
            background: var(--light-gray);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
        }

        .timer-label {
            color: var(--dark-gray);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .time-remaining {
            font-size: 2rem;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: 5px;
        }

        .time-subtitle {
            color: var(--dark-gray);
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .important-info {
            background: rgba(52, 152, 219, 0.1);
            border-left: 4px solid var(--secondary-color);
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
        }

        .important-info h5 {
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .important-info h5 i {
            margin-right: 10px;
        }

        .important-info ul {
            margin: 0;
            padding-left: 20px;
        }

        .important-info li {
            color: var(--dark-gray);
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .emergency-info {
            background: rgba(231, 76, 60, 0.1);
            border-left: 4px solid var(--danger-color);
            padding: 15px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
        }

        .emergency-info h6 {
            color: var(--danger-color);
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .emergency-info h6 i {
            margin-right: 8px;
        }

        .emergency-info p {
            margin: 0;
            color: var(--dark-gray);
        }

        .qr-code-section {
            background: white;
            border: 2px solid var(--success-color);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
        }

        .qr-code-section h6 {
            color: var(--success-color);
            font-weight: 700;
            margin-bottom: 15px;
            text-align: center;
        }

        .qr-placeholder {
            width: 150px;
            height: 150px;
            background: var(--light-gray);
            border: 2px dashed var(--success-color);
            border-radius: 10px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--success-color);
        }

        .qr-placeholder i {
            font-size: 3rem;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .proceed-button {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
            max-width: 200px;
        }

        .proceed-button:hover {
            background: #229954;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.4);
        }

        .email-button {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
            max-width: 200px;
        }

        .email-button:hover {
            background: #2980b9;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }

        .countdown-circle {
            position: relative;
            display: inline-block;
        }

        .progress-ring {
            transform: rotate(-90deg);
        }

        .progress-ring-circle {
            stroke: var(--success-color);
            stroke-dasharray: 283;
            stroke-dashoffset: 283;
            transition: stroke-dashoffset 1s linear;
        }

        @media (max-width: 768px) {
            .confirmation-card {
                padding: 30px 25px;
                margin: 20px;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .success-icon {
                width: 100px;
                height: 100px;
            }

            .success-icon i {
                font-size: 50px;
            }

            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .proceed-button,
            .email-button {
                max-width: none;
            }

            .time-remaining {
                font-size: 1.5rem;
            }
        }

        .animate-entrance {
            animation: slideInUp 0.8s ease-out;
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

        @keyframes successPulse {
            from {
                transform: scale(1);
                box-shadow: 0 15px 40px rgba(39, 174, 96, 0.4);
            }
            to {
                transform: scale(1.05);
                box-shadow: 0 20px 50px rgba(39, 174, 96, 0.6);
            }
        }

        .email-sent {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid var(--secondary-color);
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 10px;
            color: var(--secondary-color);
            font-size: 0.9rem;
            display: none;
        }

        .email-sent.show {
            display: block;
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="confirmation-card animate-entrance">
            <div class="success-icon" style="background: <?= $quiet_hours_blocked ? 'var(--warning-color)' : 'var(--success-color)' ?>;">
                <i class="fas <?= $quiet_hours_blocked ? 'fa-clock' : 'fa-check' ?>"></i>
            </div>

            <h1 class="page-title"><?= $quiet_hours_blocked ? 'Check-in Pending' : 'Visit Confirmed!' ?></h1>
            <p class="page-subtitle">
                <?= $quiet_hours_blocked 
                    ? 'Check-in is not allowed between 23:00 and 07:00. Please return at 07:00 to complete your check-in.' 
                    : 'Welcome to Sophen Residence! Your visit has been approved and you may now enter the premises.' ?>
            </p>

            <div class="visit-details">
                <div class="details-header">
                    <i class="fas fa-ticket-alt"></i>
                    Your Visit Pass
                </div>
                <div class="detail-row">
                    <span class="detail-label">Visitor:</span>
                    <span class="detail-value"><?php echo $visitor_name; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">ID Number:</span>
                    <span class="detail-value"><?php echo $visitor_id; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Room:</span>
                    <span class="detail-value"><?php echo $room_number; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Host:</span>
                    <span class="detail-value"><?php echo $host_name; ?></span>
                </div>
                <?php if (!$quiet_hours_blocked): ?>
                    <div class="detail-row">
                        <span class="detail-label">Check-in Time:</span>
                        <span class="detail-value"><?php echo $checkin_time; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Must Check Out By:</span>
                        <span class="detail-value"><?php echo $checkout_time; ?></span>
                    </div>
                <?php else: ?>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">Pending (quiet hours)</span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$quiet_hours_blocked): ?>
                <div class="visit-timer">
                    <div class="timer-label">Time Remaining</div>
                    <div class="time-remaining" id="timeRemaining">6h 30m</div>
                    <div class="time-subtitle">You will receive a reminder 30 minutes before checkout</div>
                </div>
            <?php endif; ?>

            <div class="qr-code-section">
                <h6><i class="fas fa-qrcode me-2"></i>Digital Visit Pass</h6>
                <div class="qr-placeholder">
                    <i class="fas fa-qrcode"></i>
                </div>
                <p style="text-align: center; margin: 0; color: var(--dark-gray); font-size: 0.9rem;">
                    Show this to security if requested
                </p>
            </div>

            <div class="important-info">
                <h5><i class="fas fa-exclamation-circle"></i>Important Reminders</h5>
                <ul>
                    <li>Keep your ID with you at all times during your visit</li>
                    <li>Respect residence rules and other residents</li>
                    <li>You must check out by the specified time above</li>
                    <li>Report any issues or concerns to security</li>
                    <li>Follow all safety protocols and emergency procedures</li>
                </ul>
            </div>

            <div class="emergency-info">
                <h6><i class="fas fa-phone"></i>Emergency Contact</h6>
                <p>Security: <strong>+27 123 456 789</strong> | Emergency: <strong>10111</strong></p>
            </div>

            <?php if (!$quiet_hours_blocked): ?>
                <div class="action-buttons">
                    <button class="proceed-button" onclick="proceedToResidence()">
                        <i class="fas fa-door-open me-2"></i>
                        Enter Residence
                    </button>
                    <button class="email-button" onclick="emailPass()">
                        <i class="fas fa-envelope me-2"></i>
                        Email Pass
                    </button>
                </div>
            <?php else: ?>
                <div class="action-buttons">
                    <button class="proceed-button" onclick="window.location.href='step1.php'" style="background: var(--warning-color);">
                        <i class="fas fa-clock me-2"></i>
                        Return at 07:00
                    </button>
                </div>
            <?php endif; ?>

            <div class="email-sent" id="emailSent">
                <i class="fas fa-check-circle me-2"></i>
                Visit pass sent to your email address!
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        let checkoutTime = new Date('<?php echo $checkout_datetime; ?>');
        let timerInterval;
        let visitId = <?php echo $visit_id; ?>;

        $(document).ready(function() {
            // Start countdown timer
            startCountdownTimer();

            // Generate QR code
            generateQRCode();
        });

        function startCountdownTimer() {
            timerInterval = setInterval(() => {
                const now = new Date();
                const timeDiff = checkoutTime - now;

                if (timeDiff <= 0) {
                    $('#timeRemaining').text('Time Expired');
                    $('#timeRemaining').css('color', 'var(--danger-color)');
                    clearInterval(timerInterval);
                    return;
                }

                const hours = Math.floor(timeDiff / (1000 * 60 * 60));
                const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));

                $('#timeRemaining').text(`${hours}h ${minutes}m`);

                // Change color when less than 1 hour remaining
                if (timeDiff < 3600000) { // 1 hour in milliseconds
                    $('#timeRemaining').css('color', 'var(--warning-color)');
                }

                // Change to red when less than 30 minutes
                if (timeDiff < 1800000) { // 30 minutes in milliseconds
                    $('#timeRemaining').css('color', 'var(--danger-color)');
                }
            }, 60000); // Update every minute
        }

        function generateQRCode() {
            // In real implementation, you would generate an actual QR code
            // containing visit information for security scanning
            const qrPlaceholder = $('.qr-placeholder');
            qrPlaceholder.html(`
                <div style="font-size: 0.8rem; text-align: center; color: var(--success-color);">
                    <i class="fas fa-qrcode" style="font-size: 2rem; margin-bottom: 5px;"></i><br>
                    QR Code<br>
                    <small>Visit ID: ${visitId}</small>
                </div>
            `);
        }

        function proceedToResidence() {
            const button = $('.proceed-button');
            const originalText = button.html();
            
            // Show success animation
            button.prop('disabled', true)
                  .html('<i class="fas fa-check me-2"></i>Access Granted!');

            // In real implementation, this might update the visit status in the database
            setTimeout(() => {
                // Show success message
                showSuccessMessage();
            }, 2000);
        }

        function showSuccessMessage() {
            const card = $('.confirmation-card');
            card.html(`
                <div class="success-icon" style="background: var(--success-color);">
                    <i class="fas fa-door-open"></i>
                </div>
                <h1 class="page-title" style="color: var(--success-color);">Welcome!</h1>
                <p class="page-subtitle" style="font-size: 1.2rem; color: var(--success-color);">
                    Enjoy your visit and remember to check out before your allocated time expires.
                </p>
                <div style="margin: 30px 0;">
                    <button class="proceed-button" onclick="window.location.href='../index.php'">
                        <i class="fas fa-home me-2"></i>
                        Return to Home
                    </button>
                </div>
            `);
        }

        function emailPass() {
            const button = $('.email-button');
            const originalText = button.html();
            
            // Show loading state
            button.prop('disabled', true)
                  .html('<i class="fas fa-spinner fa-spin me-2"></i>Sending...');

            // Send actual email via AJAX
            $.ajax({
                url: 'process/send_visit_pass.php',
                type: 'POST',
                data: { visit_id: visitId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#emailSent').text(response.message).addClass('show');
                        button.prop('disabled', false).html(originalText);
                        
                        // Hide success message after 5 seconds
                        setTimeout(() => {
                            $('#emailSent').removeClass('show');
                        }, 5000);
                    } else {
                        alert('Error: ' + response.message);
                        button.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    alert('Request failed. Please try again.');
                    button.prop('disabled', false).html(originalText);
                }
            });
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-ZA', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Clean up timer when leaving page
        window.addEventListener('beforeunload', function() {
            if (timerInterval) {
                clearInterval(timerInterval);
            }
        });
    </script>
</body>
</html>