<?php
/**
 * Password Reset Email Template
 */

function getPasswordResetTemplate($userName, $resetLink, $expiryMinutes = 30) {
    $monastery_name = defined('MONASTERY_NAME') ? MONASTERY_NAME : 'Seela Suwa Herath Bikshu Gilan Arana';
    $monastery_website = defined('MONASTERY_WEBSITE') ? MONASTERY_WEBSITE : 'http://localhost/test/';
    $name = htmlspecialchars($userName);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #f9f9f9;
        }
        .header {
            background: linear-gradient(135deg, #D4622A 0%, #F0A050 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
        }
        .icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .content {
            background: #fff;
            padding: 30px;
            border: 2px solid #D4622A;
            border-top: none;
        }
        .button-box {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            background: #D4622A;
            color: white !important;
            padding: 14px 40px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
        }
        .notice {
            background: #FEF3E8;
            padding: 15px;
            border-left: 4px solid #F0A050;
            margin: 20px 0;
            font-size: 14px;
        }
        .footer {
            background: #FEF3E8;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 10px 10px;
            margin-top: 0;
            font-size: 14px;
            color: #666;
        }
        .link-text {
            word-break: break-all;
            font-size: 12px;
            color: #999;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="icon">🔐</div>
        <h1>{$monastery_name}</h1>
        <p>Password Reset Request</p>
    </div>

    <div class="content">
        <h2>Hello {$name},</h2>

        <p>We received a request to reset your password. Click the button below to create a new password:</p>

        <div class="button-box">
            <a href="{$resetLink}" class="button">Reset My Password</a>
        </div>

        <div class="notice">
            <strong>⏰ This link expires in {$expiryMinutes} minutes.</strong><br>
            If you did not request a password reset, you can safely ignore this email. Your password will not be changed.
        </div>

        <p>For security reasons:</p>
        <ul>
            <li>This link can only be used once</li>
            <li>It expires after {$expiryMinutes} minutes</li>
            <li>Never share this link with anyone</li>
        </ul>

        <p class="link-text">
            If the button doesn't work, copy and paste this link into your browser:<br>
            {$resetLink}
        </p>

        <p>With metta,<br>
        <strong>{$monastery_name}</strong></p>
    </div>

    <div class="footer">
        <p>🙏 <strong>Theruwan Saranai!</strong></p>
        <hr style="border: none; border-top: 1px solid #ddd; margin: 15px 0;">
        <p style="font-size: 12px; color: #999;">
            This is an automated email. Please do not reply directly to this message.
        </p>
    </div>
</body>
</html>
HTML;
}
?>
