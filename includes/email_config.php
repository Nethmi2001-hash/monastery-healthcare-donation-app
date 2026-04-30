<?php
/**
 * Email Configuration File
 * Configure SMTP settings for sending emails
 */

// Email Configuration
define('SMTP_ENABLED', true); // Set to false to disable email sending (for testing)

// SMTP Server Settings
define('SMTP_HOST', 'smtp.gmail.com'); // Gmail SMTP server
define('SMTP_PORT', 587); // TLS port (or 465 for SSL)
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('SMTP_AUTH', true);

// SMTP Credentials
// IMPORTANT: For Gmail, use App Password (not regular password)
// How to create App Password:
// 1. Go to Google Account > Security
// 2. Enable 2-Step Verification
// 3. Go to App Passwords
// 4. Generate password for "Mail" application
define('SMTP_USERNAME', 'your_email@gmail.com'); // Change this
define('SMTP_PASSWORD', 'your_app_password'); // Use App Password, not regular password

// Email From Settings
define('EMAIL_FROM', 'monastery@seelasuwa.lk');
define('EMAIL_FROM_NAME', 'Seela Suwa Herath Bikshu Gilan Arana');

// Email Reply-To
define('EMAIL_REPLY_TO', 'admin@seelasuwa.lk');
define('EMAIL_REPLY_NAME', 'Monastery Admin');

// Email Settings
define('EMAIL_CHARSET', 'UTF-8');
define('EMAIL_DEBUG', 0); // 0=off, 1=client, 2=server, 3=full

// Monastery Information
define('MONASTERY_NAME', 'Seela Suwa Herath Bikshu Gilan Arana');
define('MONASTERY_ADDRESS', 'Your Monastery Address Here');
define('MONASTERY_PHONE', '+94 XX XXX XXXX');
define('MONASTERY_EMAIL', 'info@seelasuwa.lk');
define('MONASTERY_WEBSITE', 'http://localhost/test/');

// Email Templates Directory
define('EMAIL_TEMPLATES_DIR', __DIR__ . '/../email_templates/');

/**
 * Alternative SMTP Configurations (uncomment as needed)
 */

// SendGrid
/*
define('SMTP_HOST', 'smtp.sendgrid.net');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'apikey');
define('SMTP_PASSWORD', 'your_sendgrid_api_key');
*/

// Mailgun
/*
define('SMTP_HOST', 'smtp.mailgun.org');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'postmaster@your-domain.mailgun.org');
define('SMTP_PASSWORD', 'your_mailgun_password');
*/

// Office 365
/*
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@outlook.com');
define('SMTP_PASSWORD', 'your_password');
*/

// Development Mode: Send all emails to test address
define('DEV_MODE', true); // Set to false in production
define('DEV_EMAIL', 'test@example.com'); // Test email address
?>
