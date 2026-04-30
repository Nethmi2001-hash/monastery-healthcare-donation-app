<?php
/**
 * Appointment Reminder Email Template
 */

function getAppointmentReminderTemplate($appointment) {
    $monastery_name = MONASTERY_NAME;
    $monastery_website = MONASTERY_WEBSITE;
    
    $monk_name = htmlspecialchars($appointment['monk_name'] ?? 'Venerable');
    $doctor_name = htmlspecialchars($appointment['doctor_name'] ?? 'Doctor');
    $app_date = date('l, F d, Y', strtotime($appointment['app_date']));
    $app_time = date('h:i A', strtotime($appointment['app_time']));
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Reminder</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #f57c00 0%, #ff9800 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: #fff;
            padding: 30px;
            border: 2px solid #f57c00;
            border-top: none;
        }
        .appointment-box {
            background: linear-gradient(135deg, #f57c00 0%, #ff9800 100%);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            margin: 20px 0;
        }
        .appointment-box h2 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .details {
            background: #fff3e0;
            padding: 15px;
            border-left: 4px solid #f57c00;
            margin: 20px 0;
        }
        .footer {
            background: #fff3e0;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 10px 10px;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚕️ Appointment Reminder</h1>
        <p>{$monastery_name}</p>
    </div>
    
    <div class="content">
        <h2>Dear {$monk_name},</h2>
        
        <p>This is a friendly reminder about your upcoming medical appointment at our monastery healthcare center.</p>
        
        <div class="appointment-box">
            <h2>📅 {$app_date}</h2>
            <h2>🕐 {$app_time}</h2>
        </div>
        
        <div class="details">
            <p><strong>Doctor:</strong> {$doctor_name}</p>
            <p><strong>Location:</strong> Monastery Healthcare Center</p>
        </div>
        
        <p><strong>Important Reminders:</strong></p>
        <ul>
            <li>Please arrive 10 minutes before your appointment time</li>
            <li>Bring any previous medical records or prescriptions</li>
            <li>If you need to reschedule, please contact us as soon as possible</li>
        </ul>
        
        <p>If you have any questions, please contact our healthcare coordinator.</p>
        
        <p>With metta,<br>
        <strong>{$monastery_name} Healthcare Team</strong></p>
    </div>
    
    <div class="footer">
        <p><strong>May you be well and healthy! 🙏</strong></p>
        <p>
            Email: info@seelasuwa.lk | Phone: +94 XX XXX XXXX<br>
            Website: <a href="{$monastery_website}">{$monastery_website}</a>
        </p>
    </div>
</body>
</html>
HTML;

    return $html;
}
?>
