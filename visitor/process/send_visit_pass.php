<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/config.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$visit_id = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : 0;

if (!$visit_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid visit ID']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch visit details (same query as in step8.php)
    $query = "SELECT v.visit_id, v.visitor_id, v.room_id, v.host_name, v.purpose, v.visit_status,
                     v.actual_checkin, v.expected_checkout, v.created_at,
                     vis.first_name as visitor_first, vis.last_name as visitor_last, vis.id_number, vis.email as visitor_email,
                     r.room_number,
                     res.email as resident_email
              FROM visits v
              INNER JOIN visitors vis ON v.visitor_id = vis.visitor_id
              INNER JOIN rooms r ON v.room_id = r.room_id
              LEFT JOIN residents res ON r.room_id = res.room_id AND CONCAT(res.first_name, ' ', res.last_name) = v.host_name
              WHERE v.visit_id = ? AND v.visit_status IN ('approved', 'checked_in')";

    $stmt = $db->prepare($query);
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visit) {
        echo json_encode(['success' => false, 'message' => 'Visit not found or not approved/checked in']);
        exit;
    }

    // Prepare email content
    $visitor_name = htmlspecialchars($visit['visitor_first'] . ' ' . $visit['visitor_last']);
    $visitor_id_num = htmlspecialchars($visit['id_number']);
    $room_number = htmlspecialchars($visit['room_number']);
    $host_name = htmlspecialchars($visit['host_name']);
    $checkin_time = date('M j, Y - H:i', strtotime($visit['actual_checkin']));
    $checkout_time = date('M j, Y - H:i', strtotime($visit['expected_checkout']));

    $subject = 'Your Visit Pass - Sophen Residence';

    // QR content: original simple text embedded in the image URL

    $body = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Your Visit Pass - Sophen Residence</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            .header { text-align: center; color: #2c3e50; margin-bottom: 30px; }
            .header h1 { margin: 0; font-size: 28px; }
            .success-icon { width: 80px; height: 80px; background-color: #27ae60; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; }
            .success-icon i { font-size: 40px; color: white; }
            .visit-details { background-color: rgba(39, 174, 96, 0.1); border: 2px solid #27ae60; border-radius: 10px; padding: 20px; margin: 20px 0; }
            .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(39, 174, 96, 0.2); }
            .detail-row:last-child { border-bottom: none; }
            .detail-label { font-weight: bold; color: #2c3e50; }
            .detail-value { color: #34495e; }
            .qr-section { text-align: center; background-color: #ecf0f1; border: 2px solid #27ae60; border-radius: 10px; padding: 20px; margin: 20px 0; }
            .qr-placeholder { width: 120px; height: 120px; background-color: #bdc3c7; border: 2px dashed #27ae60; border-radius: 10px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; }
            .qr-placeholder i { font-size: 3rem; color: #27ae60; }
            .reminders { background-color: rgba(52, 152, 219, 0.1); border-left: 4px solid #3498db; padding: 15px; margin: 20px 0; }
            .reminders h3 { margin-top: 0; color: #3498db; }
            .reminders ul { margin: 10px 0 0 0; padding-left: 20px; }
            .footer { text-align: center; color: #7f8c8d; font-size: 12px; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='success-icon'>
                    <i class='fas fa-check'></i>
                </div>
                <h1>Visit Confirmed!</h1>
                <p>Welcome to Sophen Residence! Your visit has been approved and you may now enter the premises.</p>
            </div>

            <div class='visit-details'>
                <h2 style='color: #27ae60; margin-top: 0;'>Your Visit Pass</h2>
                <div class='detail-row'>
                    <span class='detail-label'>Visitor:</span>
                    <span class='detail-value'>{$visitor_name}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>ID Number:</span>
                    <span class='detail-value'>{$visitor_id_num}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>Room:</span>
                    <span class='detail-value'>{$room_number}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>Host:</span>
                    <span class='detail-value'>{$host_name}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>Check-in Time:</span>
                    <span class='detail-value'>{$checkin_time}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>Must Check Out By:</span>
                    <span class='detail-value'>{$checkout_time}</span>
                </div>
            </div>

            <div class='qr-section'>
                <h3>Digital Visit Pass</h3>
                <img src='https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=Visit ID: {$visit_id}' alt='QR Code' style='width: 150px; height: 150px; border: 2px solid #27ae60; border-radius: 10px;' />
                <p>Show this QR to security at the gate</p>
            </div>

            <div class='reminders'>
                <h3><i class='fas fa-exclamation-circle'></i> Important Reminders</h3>
                <ul>
                    <li>Keep your ID with you at all times during your visit</li>
                    <li>Respect residence rules and other residents</li>
                    <li>You must check out by the specified time above</li>
                    <li>Report any issues or concerns to security</li>
                    <li>Follow all safety protocols and emergency procedures</li>
                </ul>
            </div>

            <div class='footer'>
                <p>Security: +27 123 456 789 | Emergency: 10111</p>
                <p>This is an automated message from Sophen Residence System. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Check if email is available
    if (empty($visit['visitor_email'])) {
        echo json_encode(['success' => false, 'message' => 'Visitor email not found']);
        exit;
    }

    // Send email
    $email_sent = send_email($visit['visitor_email'], $subject, $body, true);

    if ($email_sent) {
        // Log the activity
        log_activity(0, 'email_visit_pass', "Visit pass emailed to {$visit['visitor_email']} for visit ID: {$visit_id}");

        echo json_encode(['success' => true, 'message' => 'Visit pass sent successfully to your email']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again or contact security.']);
    }

} catch (Exception $e) {
    error_log("Email send error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while sending the email']);
}
?>
