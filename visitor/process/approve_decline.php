<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../vendor/autoload.php'; // For PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed!");
}

$message = '';
$action = $_GET['action'] ?? '';
$visit_id = intval($_GET['visit_id'] ?? 0);
$token = $_GET['token'] ?? '';

if (!$visit_id || !in_array($action, ['approve', 'decline'])) {
    $message = 'Invalid request.';
} else {
    // Verify token using shared secret (original simple hash)
    $expected_token = hash('sha256', $visit_id . 'sophen_secret_key');
    if ($token !== $expected_token) {
        $message = 'Invalid or expired link.';
    } else {
        // Get visit details
        $query = "SELECT v.visit_id, v.visitor_id, v.room_id, v.host_name, vis.first_name as visitor_first, vis.last_name as visitor_last, vis.email as visitor_email, r.first_name as resident_first, r.last_name as resident_last
                  FROM visits v
                  JOIN visitors vis ON v.visitor_id = vis.visitor_id
                  JOIN residents r ON v.room_id = r.room_id AND CONCAT(r.first_name, ' ', r.last_name) = v.host_name
                  WHERE v.visit_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$visit_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$visit) {
            $message = 'Visit not found.';
        } else {
            $visitor_name = $visit['visitor_first'] . ' ' . $visit['visitor_last'];
            $resident_name = $visit['resident_first'] . ' ' . $visit['resident_last'];

            if ($action === 'approve') {
                // Update status to approved
                $update = $db->prepare("UPDATE visits SET visit_status = 'approved' WHERE visit_id = ?");
                $update->execute([$visit_id]);

                // Send email to visitor
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USERNAME;
                    $mail->Password   = SMTP_PASSWORD;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = SMTP_PORT;
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
                    $mail->addAddress($visit['visitor_email']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Visit Approved - Sophen Residence';
                    $mail->Body    = "
                        <h2>Your Visit Has Been Approved!</h2>
                        <p>Dear {$visitor_name},</p>
                        <p>Your visit request has been approved by {$resident_name}. You can now proceed to check-in at the security desk.</p>
                        <p>Thank you,<br>Sophen Residence Security</p>
                    ";
                    $mail->AltBody = "Your visit has been approved by {$resident_name}. Proceed to check-in.";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Visitor email error: {$mail->ErrorInfo}");
                }

                // Notify security
                // Get security emails from users table joined with roles where role_name = 'Security'
                $security_query = "SELECT u.email FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'Security' AND u.is_active = TRUE";
                $security_stmt = $db->prepare($security_query);
                $security_stmt->execute();
                $security_emails = $security_stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($security_emails as $sec_email) {
                    $mail2 = new PHPMailer(true);
                    try {
                        $mail2->isSMTP();
                        $mail2->Host       = SMTP_HOST;
                        $mail2->SMTPAuth   = true;
                        $mail2->Username   = SMTP_USERNAME;
                        $mail2->Password   = SMTP_PASSWORD;
                        $mail2->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail2->Port       = SMTP_PORT;
                        $mail2->CharSet    = 'UTF-8';

                        $mail2->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
                        $mail2->addAddress($sec_email);

                        $mail2->isHTML(true);
                        $mail2->Subject = 'Visitor Approved Notification - Sophen Residence';
                        $mail2->Body    = "
                            <h2>Visitor Approval Notification</h2>
                            <p>{$visitor_name} has been approved by {$resident_name} for visit ID {$visit_id}.</p>
                            <p>Please prepare for check-in.</p>
                        ";
                        $mail2->AltBody = "{$visitor_name} approved by {$resident_name}.";

                        $mail2->send();
                    } catch (Exception $e) {
                        error_log("Security email error: {$mail->ErrorInfo}");
                    }
                }

                $message = 'Visit approved successfully. The visitor has been notified.';
            } elseif ($action === 'decline') {
                // Update status to declined
                $update = $db->prepare("UPDATE visits SET visit_status = 'declined' WHERE visit_id = ?");
                $update->execute([$visit_id]);

                // Optionally send decline email to visitor
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USERNAME;
                    $mail->Password   = SMTP_PASSWORD;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = SMTP_PORT;
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
                    $mail->addAddress($visit['visitor_email']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Visit Declined - Sophen Residence';
                    $mail->Body    = "
                        <h2>Your Visit Has Been Declined</h2>
                        <p>Dear {$visitor_name},</p>
                        <p>Your visit request has been declined by {$resident_name}. Please contact the resident for more information.</p>
                        <p>Thank you,<br>Sophen Residence Security</p>
                    ";
                    $mail->AltBody = "Your visit has been declined by {$resident_name}.";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Decline email error: {$mail->ErrorInfo}");
                }

                $message = 'Visit declined successfully.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sophen - Visit <?php echo ucfirst($action); ?>ed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h1 class="card-title"><?php echo ucfirst($action); ?> Confirmation</h1>
                        <p class="card-text"><?php echo htmlspecialchars($message); ?></p>
                        <a href="../step1.php" class="btn btn-primary">Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
