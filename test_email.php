<?php
session_start();

// Access control
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include 'navbar.php';
require_once __DIR__ . '/includes/email_helper.php';

$test_result = '';
$test_error = '';

// Handle test email submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_test'])) {
    $test_email = trim($_POST['test_email']);
    
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $test_error = "Please enter a valid email address";
    } else {
        // Create sample donation data for testing
        $sample_donation = [
            'donation_id' => 999,
            'donor_name' => 'Test Donor',
            'donor_email' => $test_email,
            'donor_phone' => '+94 77 123 4567',
            'amount' => 5000.00,
            'category_id' => 1,
            'category_name' => 'General Donation',
            'payment_method' => 'payhere',
            'reference_number' => 'TEST-' . time(),
            'status' => 'verified',
            'notes' => 'This is a test donation email',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        if (sendDonationThankYou($sample_donation)) {
            $test_result = "✅ Test email sent successfully to: $test_email<br>Check your inbox (and spam folder)!";
        } else {
            $test_error = "❌ Failed to send test email. Check your SMTP configuration in includes/email_config.php";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test - Monastery System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --monastery-saffron: #f57c00;
            --monastery-orange: #ff9800;
            --monastery-light: #ffa726;
            --monastery-dark: #e65100;
            --monastery-pale: #fff3e0;
        }
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .lotus-divider {
            text-align: center;
            color: var(--monastery-saffron);
            font-size: 24px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--monastery-saffron) 0%, var(--monastery-orange) 100%); color: white;">
                        <h4 class="mb-0"><i class="bi bi-envelope-check"></i> Email System Configuration Test</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($test_result): ?>
                            <div class="alert alert-success"><?= $test_result ?></div>
                        <?php endif; ?>
                        
                        <?php if ($test_error): ?>
                            <div class="alert alert-danger"><?= $test_error ?></div>
                        <?php endif; ?>

                        <h5>📧 Email System Status</h5>
                        <div class="alert alert-info">
                            <strong>SMTP Configuration:</strong><br>
                            <ul class="mb-0">
                                <li><strong>Status:</strong> <?= SMTP_ENABLED ? '✅ Enabled' : '❌ Disabled' ?></li>
                                <li><strong>Host:</strong> <?= SMTP_HOST ?></li>
                                <li><strong>Port:</strong> <?= SMTP_PORT ?></li>
                                <li><strong>Security:</strong> <?= SMTP_SECURE ?></li>
                                <li><strong>From:</strong> <?= EMAIL_FROM_NAME ?> &lt;<?= EMAIL_FROM ?>&gt;</li>
                                <li><strong>Dev Mode:</strong> <?= DEV_MODE ? '✅ ON (emails go to ' . DEV_EMAIL . ')' : '❌ OFF (production mode)' ?></li>
                            </ul>
                        </div>

                        <div class="lotus-divider">🪷</div>

                        <h5>🧪 Send Test Email</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Test Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="test_email" class="form-control" placeholder="your.email@example.com" required>
                                <small class="text-muted">We'll send a sample donation thank you email to this address</small>
                            </div>
                            <button type="submit" name="send_test" class="btn btn-primary">
                                <i class="bi bi-send"></i> Send Test Email
                            </button>
                        </form>

                        <div class="lotus-divider">🪷</div>

                        <h5>⚙️ Setup Instructions</h5>
                        <div class="accordion" id="setupAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#gmailSetup">
                                        Gmail Setup (Recommended)
                                    </button>
                                </h2>
                                <div id="gmailSetup" class="accordion-collapse collapse show" data-bs-parent="#setupAccordion">
                                    <div class="accordion-body">
                                        <ol>
                                            <li>Go to your <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a></li>
                                            <li>Enable <strong>2-Step Verification</strong></li>
                                            <li>Go to <strong>App Passwords</strong></li>
                                            <li>Select app: <strong>Mail</strong>, device: <strong>Other (Custom name)</strong></li>
                                            <li>Enter name: "Monastery System"</li>
                                            <li>Copy the 16-character app password</li>
                                            <li>Open <code>includes/email_config.php</code></li>
                                            <li>Update:<br>
                                                <code>define('SMTP_USERNAME', 'your_email@gmail.com');</code><br>
                                                <code>define('SMTP_PASSWORD', 'your_16_char_app_password');</code>
                                            </li>
                                            <li>Update <code>EMAIL_FROM</code> to your email</li>
                                            <li>Save and test!</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#devMode">
                                        Development Mode
                                    </button>
                                </h2>
                                <div id="devMode" class="accordion-collapse collapse" data-bs-parent="#setupAccordion">
                                    <div class="accordion-body">
                                        <p><strong>Dev Mode is currently: <?= DEV_MODE ? 'ON' : 'OFF' ?></strong></p>
                                        <p>When Dev Mode is ON, all emails are redirected to: <code><?= DEV_EMAIL ?></code></p>
                                        <p>This prevents accidentally emailing real donors during testing.</p>
                                        <p><strong>To disable Dev Mode (for production):</strong></p>
                                        <ol>
                                            <li>Open <code>includes/email_config.php</code></li>
                                            <li>Find: <code>define('DEV_MODE', true);</code></li>
                                            <li>Change to: <code>define('DEV_MODE', false);</code></li>
                                        </ol>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#troubleshoot">
                                        Troubleshooting
                                    </button>
                                </h2>
                                <div id="troubleshoot" class="accordion-collapse collapse" data-bs-parent="#setupAccordion">
                                    <div class="accordion-body">
                                        <p><strong>Common Issues:</strong></p>
                                        <ul>
                                            <li><strong>Authentication failed:</strong> Check username/password, use App Password for Gmail</li>
                                            <li><strong>Connection failed:</strong> Check firewall, port, and host settings</li>
                                            <li><strong>Email not received:</strong> Check spam folder, verify recipient email</li>
                                            <li><strong>SSL/TLS errors:</strong> Try changing SMTP_SECURE from 'tls' to 'ssl' or vice versa</li>
                                        </ul>
                                        <p><strong>Enable Debug Mode:</strong></p>
                                        <p>In <code>email_config.php</code>, set: <code>define('EMAIL_DEBUG', 2);</code></p>
                                        <p>This will show detailed SMTP conversation in the browser.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="lotus-divider">🪷</div>

                        <h5>✨ Email Features</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title"><i class="bi bi-envelope-heart"></i> Donation Thank You</h6>
                                        <p class="card-text small">Automatic email sent when donation is verified with PDF receipt attached</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title"><i class="bi bi-calendar-check"></i> Appointment Reminders</h6>
                                        <p class="card-text small">Scheduled reminders 24 hours before appointments (requires cron job)</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <a href="donation_management.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Donations
                            </a>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
