<?php
// includes/functions.php
require_once __DIR__ . '/../config/config.php';

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Create 'overstay' alerts for visits past expected checkout, avoiding duplicates
function process_overstay_alerts() {
    try {
        $database = new Database();
        $db = $database->getConnection();

        // Find overdue visits (approved or checked_in) with no active overstay alert
        $stmt = $db->prepare("SELECT v.visit_id, v.visitor_id, v.room_id, v.expected_checkout
                              FROM visits v
                              WHERE v.visit_status IN ('approved','checked_in')
                                AND v.expected_checkout IS NOT NULL
                                AND v.expected_checkout < NOW()
                                AND NOT EXISTS (
                                    SELECT 1 FROM security_alerts sa
                                    WHERE sa.visit_id = v.visit_id
                                      AND sa.alert_type = 'overstay'
                                      AND sa.alert_status IN ('new','acknowledged')
                                )");
        $stmt->execute();
        $overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($overdue as $o) {
            $overMin = max(0, (int)floor((time() - strtotime($o['expected_checkout'])) / 60));
            $severity = 'medium';
            if ($overMin >= 120) { $severity = 'critical'; }
            elseif ($overMin >= 60) { $severity = 'high'; }

            $message = sprintf(
                "Visitor overdue by %d minute%s. Expected checkout was %s.",
                $overMin,
                $overMin === 1 ? '' : 's',
                date('M j, Y - H:i', strtotime($o['expected_checkout']))
            );

            $ins = $db->prepare("INSERT INTO security_alerts (visit_id, visitor_id, room_id, alert_type, severity, message, alert_status, created_at)
                                  VALUES (?, ?, ?, 'overstay', ?, ?, 'new', NOW())");
            $ins->execute([(int)$o['visit_id'], (int)$o['visitor_id'], (int)$o['room_id'], $severity, $message]);
        }
    } catch (Exception $e) {
        error_log('process_overstay_alerts error: ' . $e->getMessage());
    }
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit();
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['role_id'] != 1) { // 1 = Admin
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit();
    }
}

function require_security() {
    require_login();
    if ($_SESSION['role_id'] != 2) { // 2 = Security
        header('Location: ' . SITE_URL . '/security/login.php');
        exit();
    }
}

function send_email($to, $subject, $body, $is_html = true) {
    require_once __DIR__ . '/../vendor/PHPMailer-6.8.0/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer-6.8.0/src/SMTP.php';
    require_once __DIR__ . '/../vendor/PHPMailer-6.8.0/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug = 0; // Disable debug output
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = 'ssl'; // For port 465
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        $mail->Body = $body;
        if ($is_html) {
            $mail->AltBody = strip_tags($body); // Plain text version
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error or handle
        error_log("Email send failed: " . $mail->ErrorInfo);
        return false;
    }
}

function log_activity($user_id, $action, $details = '') {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $action, $details]);
}

function format_date($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

function get_visitor_by_id($id_number) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM visitors WHERE id_number = ? AND is_blocked = 0";
    $stmt = $db->prepare($query);
    $stmt->execute([$id_number]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function create_visit($visitor_id, $room_id, $host_name, $purpose) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO visits (visitor_id, room_id, host_name, purpose, visit_status) VALUES (?, ?, ?, ?, 'pending')";
    $stmt = $db->prepare($query);
    $ok = $stmt->execute([$visitor_id, $room_id, $host_name, $purpose]);

    if ($ok) {
        // Set expected_checkout to the daily cutoff (e.g., 23:00)
        $visit_id = $db->lastInsertId();
        $settings = get_system_settings();
        $defaultCheckoutTime = $settings['default_checkout_time'] ?? (defined('DEFAULT_CHECKOUT_TIME') ? DEFAULT_CHECKOUT_TIME : '23:00:00');
        $today = date('Y-m-d');
        $nowTs = time();
        $todayCutoff = strtotime($today . ' ' . $defaultCheckoutTime);
        if ($nowTs <= $todayCutoff) {
            $expected_checkout = date('Y-m-d H:i:s', $todayCutoff);
        } else {
            $tomorrow = date('Y-m-d', strtotime('+1 day', $nowTs));
            $expected_checkout = $tomorrow . ' ' . $defaultCheckoutTime;
        }
        $update = $db->prepare("UPDATE visits SET expected_checkout = ? WHERE visit_id = ?");
        $update->execute([$expected_checkout, $visit_id]);
    }

    return $ok;
}

function get_active_visit($visitor_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM visits WHERE visitor_id = ? AND visit_status IN ('checked_in', 'approved') ORDER BY created_at DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$visitor_id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_system_settings() {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM system_settings LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function update_system_settings($settings) {
    $database = new Database();
    $db = $database->getConnection();

    // Load current settings for fallback when certain keys are omitted
    $current = get_system_settings() ?: [];

    $default_checkout_time = $settings['default_checkout_time']
        ?? ($current['default_checkout_time'] ?? (defined('DEFAULT_CHECKOUT_TIME') ? DEFAULT_CHECKOUT_TIME : '23:00:00'));
    $checkout_alert_minutes = (int)($settings['checkout_alert_minutes']
        ?? ($current['checkout_alert_minutes'] ?? (defined('DEFAULT_ALERT_MINUTES') ? DEFAULT_ALERT_MINUTES : 30)));
    $max_visit_duration_hours = (float)($settings['max_visit_duration_hours']
        ?? ($current['max_visit_duration_hours'] ?? (defined('DEFAULT_MAX_VISIT_HOURS') ? DEFAULT_MAX_VISIT_HOURS : 8)));

    // Email/SMTP fields fall back to existing DB values or config constants if not provided
    $smtp_host = $settings['smtp_host'] ?? ($current['smtp_host'] ?? (defined('SMTP_HOST') ? SMTP_HOST : ''));
    $smtp_username = $settings['smtp_username'] ?? ($current['smtp_username'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : ''));
    $smtp_password = $settings['smtp_password'] ?? ($current['smtp_password'] ?? (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : ''));
    $smtp_port = (int)($settings['smtp_port'] ?? ($current['smtp_port'] ?? (defined('SMTP_PORT') ? SMTP_PORT : 0)));
    $email_from = $settings['email_from'] ?? ($current['email_from'] ?? (defined('EMAIL_FROM') ? EMAIL_FROM : ''));
    $email_from_name = $settings['email_from_name'] ?? ($current['email_from_name'] ?? (defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : ''));

    $query = "UPDATE system_settings SET 
              default_checkout_time = ?,
              checkout_alert_minutes = ?,
              max_visit_duration_hours = ?,
              smtp_host = ?,
              smtp_username = ?,
              smtp_password = ?,
              smtp_port = ?,
              email_from = ?,
              email_from_name = ?,
              updated_at = NOW()";
    $stmt = $db->prepare($query);

    return $stmt->execute([
        $default_checkout_time,
        $checkout_alert_minutes,
        $max_visit_duration_hours,
        $smtp_host,
        $smtp_username,
        $smtp_password,
        $smtp_port,
        $email_from,
        $email_from_name
    ]);
}

// Determine if current time is within quiet hours (from daily cutoff to next day's check-in start)
function is_quiet_hours() {
    $settings = get_system_settings();
    $cutoff = $settings['default_checkout_time'] ?? (defined('DEFAULT_CHECKOUT_TIME') ? DEFAULT_CHECKOUT_TIME : '23:00:00');
    $checkinStart = defined('DEFAULT_CHECKIN_START_TIME') ? DEFAULT_CHECKIN_START_TIME : '07:00:00';
    $nowTime = date('H:i:s');
    // Quiet hours when time >= cutoff (e.g., 23:00) OR time < checkin start (e.g., 07:00)
    return ($nowTime >= $cutoff || $nowTime < $checkinStart);
}

// Process and generate checkout reminder alerts, and send emails to visitors
// - Creates a 'checkout_reminder' alert when time remaining <= checkout_alert_minutes
// - Creates a final reminder alert when time remaining <= 5 minutes
// - Avoids duplicates by checking existing alerts for the visit and severity
function process_checkout_alerts() {
    try {
        $database = new Database();
        $db = $database->getConnection();

        $settings = get_system_settings();
        $alertMinutes = (int)($settings['checkout_alert_minutes'] ?? (defined('DEFAULT_ALERT_MINUTES') ? DEFAULT_ALERT_MINUTES : 30));

        // Fetch active visits with expected checkout
        $stmt = $db->prepare("SELECT v.visit_id, v.visitor_id, v.room_id, v.host_name, v.expected_checkout, v.actual_checkin, v.visit_status,
                                     vi.email AS visitor_email, CONCAT(vi.first_name, ' ', vi.last_name) AS visitor_name, vi.id_number,
                                     r.room_number
                              FROM visits v
                              JOIN visitors vi ON v.visitor_id = vi.visitor_id
                              LEFT JOIN rooms r ON v.room_id = r.room_id
                              WHERE v.visit_status IN ('approved','checked_in') AND v.expected_checkout IS NOT NULL");
        $stmt->execute();
        $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $nowTs = time();

        foreach ($visits as $visit) {
            $expectedTs = strtotime($visit['expected_checkout']);
            if ($expectedTs === false) { continue; }
            $minutesLeft = (int)floor(($expectedTs - $nowTs) / 60);

            // Skip if already past expected checkout (overstay handled elsewhere)
            if ($minutesLeft < 0) { continue; }

            // Near-end reminder: within alertMinutes window, but more than 5 minutes left
            if ($minutesLeft <= $alertMinutes && $minutesLeft > 5) {
                $check = $db->prepare("SELECT COUNT(*) AS c FROM security_alerts WHERE visit_id = ? AND alert_type = 'checkout_reminder' AND severity = 'medium'");
                $check->execute([$visit['visit_id']]);
                $existsNear = (int)($check->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) > 0;
                if (!$existsNear) {
                    $message = sprintf(
                        "Checkout reminder: Visit ends in %d minute%s at %s.",
                        $minutesLeft,
                        $minutesLeft === 1 ? '' : 's',
                        date('H:i', $expectedTs)
                    );
                    $insert = $db->prepare("INSERT INTO security_alerts (visit_id, visitor_id, room_id, alert_type, severity, message, alert_status, created_at)
                                             VALUES (?, ?, ?, 'checkout_reminder', 'medium', ?, 'new', NOW())");
                    $insert->execute([$visit['visit_id'], $visit['visitor_id'], $visit['room_id'], $message]);

                    // Send email to visitor
                    if (!empty($visit['visitor_email'])) {
                        $subject = 'Checkout Reminder - Sophen Residence';
                        $body = "<html><body style='font-family:Arial,sans-serif;'>"
                              . "<h2 style='color:#3498db;margin-bottom:10px;'>Your visit is nearing its end</h2>"
                              . "<p>Dear " . htmlspecialchars($visit['visitor_name']) . ",</p>"
                              . "<p>This is a reminder that your visit is scheduled to end in <strong>" . $minutesLeft . " minute" . ($minutesLeft === 1 ? "" : "s") . "</strong> at <strong>" . date('M j, Y - H:i', $expectedTs) . "</strong>.</p>"
                              . "<p>Please plan to check out on time. If you need more time, contact your host or security for assistance.</p>"
                              . "<hr><p style='color:#7f8c8d;'>Room: " . htmlspecialchars((string)$visit['room_number']) . " • Visitor ID: " . htmlspecialchars((string)$visit['id_number']) . "</p>"
                              . "<p style='color:#7f8c8d;font-size:12px;'>This is an automated notification from Sophen Residence.</p>"
                              . "</body></html>";
                        send_email($visit['visitor_email'], $subject, $body, true);
                    }
                }
            }

            // Final 5-minute reminder
            if ($minutesLeft <= 5 && $minutesLeft >= 0) {
                $check2 = $db->prepare("SELECT COUNT(*) AS c FROM security_alerts WHERE visit_id = ? AND alert_type = 'checkout_reminder' AND severity = 'high'");
                $check2->execute([$visit['visit_id']]);
                $existsFinal = (int)($check2->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) > 0;
                if (!$existsFinal) {
                    $message2 = "Final checkout reminder: 5 minutes left (until " . date('H:i', $expectedTs) . ").";
                    $insert2 = $db->prepare("INSERT INTO security_alerts (visit_id, visitor_id, room_id, alert_type, severity, message, alert_status, created_at)
                                              VALUES (?, ?, ?, 'checkout_reminder', 'high', ?, 'new', NOW())");
                    $insert2->execute([$visit['visit_id'], $visit['visitor_id'], $visit['room_id'], $message2]);

                    // Send final email to visitor
                    if (!empty($visit['visitor_email'])) {
                        $subject2 = 'Final Checkout Reminder - Sophen Residence';
                        $body2 = "<html><body style='font-family:Arial,sans-serif;'>"
                               . "<h2 style='color:#e74c3c;margin-bottom:10px;'>5 minutes left</h2>"
                               . "<p>Dear " . htmlspecialchars($visit['visitor_name']) . ",</p>"
                               . "<p>Your visit is scheduled to end in <strong>5 minutes</strong> at <strong>" . date('M j, Y - H:i', $expectedTs) . "</strong>.</p>"
                               . "<p>Please proceed to checkout or contact security if you need assistance.</p>"
                               . "<hr><p style='color:#7f8c8d;'>Room: " . htmlspecialchars((string)$visit['room_number']) . " • Visitor ID: " . htmlspecialchars((string)$visit['id_number']) . "</p>"
                               . "<p style='color:#7f8c8d;font-size:12px;'>This is an automated notification from Sophen Residence.</p>"
                               . "</body></html>";
                        send_email($visit['visitor_email'], $subject2, $body2, true);
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('process_checkout_alerts error: ' . $e->getMessage());
    }
}
