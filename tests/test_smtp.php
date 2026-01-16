<?php
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "Testing SMTP connection...\n";

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'fayiinfika@gmail.com';
    $mail->Password = 'plzw ytpu gavr lrjc';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom('fayiinfika@gmail.com', 'Test');
    $mail->addAddress('test@example.com');
    $mail->Subject = 'Test Email';
    $mail->Body = 'This is a test email.';

    $mail->send();
    echo "Email sent successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $mail->ErrorInfo . "\n";
}
?>
