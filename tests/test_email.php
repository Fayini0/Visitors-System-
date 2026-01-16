<?php
// test_email.php
require_once 'config/config.php';
require_once 'includes/functions.php';

$test_email = 'fayiinfika@gmail.com'; // Send to the Gmail account itself for testing
$subject = 'Test E
mail from Sophen Residence System';
$body = '<h1>Test Email</h1><p>This is a test email to verify Gmail SMTP configuration.</p>';

if (send_email($test_email, $subject, $body)) {
    echo 'Test email sent successfully!';
} else {
    echo 'Failed to send test email.';
}
?>