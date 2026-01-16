<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed!']));
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$visit_id = intval($_POST['visit_id'] ?? 0);

if (!$visit_id) {
    echo json_encode(['success' => false, 'message' => 'Visit ID is required.']);
    exit;
}

// FIXED: Simplified query - first get visit details
$query = "SELECT v.visit_id, v.visitor_id, v.room_id, v.host_name, 
          vis.first_name as visitor_first, vis.last_name as visitor_last, 
          vis.id_number, vis.email as visitor_email, vis.phone, 
          r.room_number
          FROM visits v
          JOIN visitors vis ON v.visitor_id = vis.visitor_id
          JOIN rooms r ON v.room_id = r.room_id
          WHERE v.visit_id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$visit_id]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    error_log("Visit not found for visit_id: " . $visit_id);
    echo json_encode([
        'success' => false, 
        'message' => 'Visit not found.',
        'debug' => 'Visit ID: ' . $visit_id
    ]);
    exit;
}

// FIXED: Now get resident email separately with better error handling
$resident_query = "SELECT res.email 
                   FROM residents res 
                   WHERE res.room_id = ? 
                   AND CONCAT(res.first_name, ' ', res.last_name) = ? 
                   AND res.is_active = TRUE 
                   LIMIT 1";

$resident_stmt = $db->prepare($resident_query);
$resident_stmt->execute([$visit['room_id'], $visit['host_name']]);
$resident = $resident_stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident || empty($resident['email'])) {
    error_log("Resident not found for host_name: " . $visit['host_name'] . " in room_id: " . $visit['room_id']);
    
    // Try alternative query without exact name match
    $alt_query = "SELECT res.email, CONCAT(res.first_name, ' ', res.last_name) as full_name
                  FROM residents res 
                  WHERE res.room_id = ? 
                  AND res.is_active = TRUE";
    
    $alt_stmt = $db->prepare($alt_query);
    $alt_stmt->execute([$visit['room_id']]);
    $all_residents = $alt_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => false, 
        'message' => 'Resident email not found for host: ' . $visit['host_name'],
        'debug' => [
            'room_id' => $visit['room_id'],
            'host_name' => $visit['host_name'],
            'available_residents' => $all_residents
        ]
    ]);
    exit;
}

$resident_email = $resident['email'];

// Generate token using shared secret (original simple hash)
$token = hash('sha256', $visit_id . 'sophen_secret_key');

// Prepare email data
$full_name = $visit['visitor_first'] . ' ' . $visit['visitor_last'];
$id_number = $visit['id_number'];
$email = $visit['visitor_email'];
$phone = $visit['phone'];
$room_number = $visit['room_number'];

// Send verification email
$mail = new PHPMailer(true);
try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    // Recipients
    $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
    $mail->addAddress($resident_email);

    // FIXED: Use proper URL construction
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $approve_link = "{$base_url}/sophen-residence-system/visitor/process/approve_decline.php?action=approve&visit_id={$visit_id}&token={$token}";
    $decline_link = "{$base_url}/sophen-residence-system/visitor/process/approve_decline.php?action=decline&visit_id={$visit_id}&token={$token}";

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Visitor Verification Request - Sophen Residence';
    $mail->Body    = "
        <h2>Visitor Verification Required</h2>
        <p>A visitor has requested to visit you:</p>
        <ul>
            <li><strong>Visitor:</strong> {$full_name}</li>
            <li><strong>ID:</strong> {$id_number}</li>
            <li><strong>Email:</strong> {$email}</li>
            <li><strong>Phone:</strong> {$phone}</li>
            <li><strong>Room:</strong> {$room_number}</li>
            <li><strong>Visit ID:</strong> {$visit_id}</li>
        </ul>
        <p>Please approve or decline the visit:</p>
        <p><a href='{$approve_link}' style='background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Approve Visit</a></p>
        <p><a href='{$decline_link}' style='background-color: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Decline Visit</a></p>
        <p>Thank you,<br>Sophen Residence Security</p>
    ";
    $mail->AltBody = "Visitor verification: {$full_name} (ID: {$id_number}) is visiting room {$room_number}. Approve: {$approve_link} Decline: {$decline_link}";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Verification email sent successfully!']);
} catch (Exception $e) {
    error_log("Email error: {$mail->ErrorInfo}");
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to send verification email: ' . $mail->ErrorInfo
    ]);
}