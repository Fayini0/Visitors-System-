<?php
// config/config.php
require_once 'database.php';

define('SITE_URL', 'http://localhost/sophen-residence-system');
define('EMAIL_FROM', 'noreply@sophen.com');
define('EMAIL_FROM_NAME', 'Sophen Residence System');

// Email configuration (using PHPMailer)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'fayiinfika@gmail.com');
define('SMTP_PASSWORD', 'plzw ytpu gavr lrjc');
define('SMTP_PORT', 587); // Changed to 587 for TLS

// Default settings
define('DEFAULT_CHECKOUT_TIME', '23:00:00');
define('DEFAULT_CHECKIN_START_TIME', '07:00:00');
define('DEFAULT_ALERT_MINUTES', 30);
define('DEFAULT_MAX_VISIT_HOURS', 8);
?>
