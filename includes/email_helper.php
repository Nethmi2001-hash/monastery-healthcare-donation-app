<?php
/**
 * Email Helper Functions
 * Send emails using PHPMailer
 */

require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';
require_once __DIR__ . '/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send Email Function
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body HTML email body
 * @param string $toName Recipient name (optional)
 * @param array $attachments Array of file paths to attach (optional)
 * @return bool Success status
 */
function sendEmail($to, $subject, $body, $toName = '', $attachments = []) {
    // Check if email is enabled
    if (!SMTP_ENABLED) {
        error_log("Email sending disabled. Would send to: $to - Subject: $subject");
        return true; // Return true to avoid breaking workflow
    }
    
    // Development mode: redirect to test email
    if (DEV_MODE && defined('DEV_EMAIL')) {
        $original_to = $to;
        $to = DEV_EMAIL;
        $subject = "[DEV - Originally to: $original_to] " . $subject;
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = SMTP_AUTH;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = EMAIL_CHARSET;
        $mail->SMTPDebug = EMAIL_DEBUG;
        
        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->addReplyTo(EMAIL_REPLY_TO, EMAIL_REPLY_NAME);
        
        // Attachments
        if (!empty($attachments)) {
            foreach ($attachments as $file) {
                if (file_exists($file)) {
                    $mail->addAttachment($file);
                }
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body); // Plain text version
        
        // Send
        $result = $mail->send();
        
        // Log success
        logEmail($to, $subject, $result ? 'sent' : 'failed');
        
        return $result;
        
    } catch (Exception $e) {
        // Log error
        error_log("Email Error: {$mail->ErrorInfo}");
        logEmail($to, $subject, 'failed', $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send Donation Thank You Email
 * @param array $donation Donation details
 * @return bool Success status
 */
function sendDonationThankYou($donation) {
    require EMAIL_TEMPLATES_DIR . 'donation_thankyou.php';
    $body = getDonationThankYouTemplate($donation);
    
    $subject = "Thank You for Your Generous Donation - " . MONASTERY_NAME;
    
    // Attach PDF receipt if donation is verified
    $attachments = [];
    if ($donation['status'] == 'verified') {
        // Generate PDF receipt
        $receipt_path = generateReceiptPDF($donation['donation_id']);
        if ($receipt_path && file_exists($receipt_path)) {
            $attachments[] = $receipt_path;
        }
    }
    
    $result = sendEmail(
        $donation['donor_email'],
        $subject,
        $body,
        $donation['donor_name'],
        $attachments
    );
    
    // Clean up temporary receipt file
    if (!empty($attachments)) {
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
    
    return $result;
}

/**
 * Send Appointment Reminder Email
 * @param array $appointment Appointment details
 * @return bool Success status
 */
function sendAppointmentReminder($appointment) {
    require EMAIL_TEMPLATES_DIR . 'appointment_reminder.php';
    $body = getAppointmentReminderTemplate($appointment);
    
    $subject = "Appointment Reminder - " . MONASTERY_NAME;
    
    return sendEmail(
        $appointment['monk_email'] ?? '',
        $subject,
        $body,
        $appointment['monk_name'] ?? ''
    );
}

/**
 * Generate PDF Receipt and save to temp file
 * @param int $donation_id
 * @return string|false File path or false on failure
 */
function generateReceiptPDF($donation_id) {
    // Create temp directory if it doesn't exist
    $temp_dir = __DIR__ . '/../temp/';
    if (!file_exists($temp_dir)) {
        mkdir($temp_dir, 0777, true);
    }
    
    // Database connection
    $conn = new mysqli("localhost", "root", "", "monastery_healthcare");
    if ($conn->connect_error) {
        return false;
    }
    
    // Get donation details
    $stmt = $conn->prepare("
        SELECT d.*, c.name as category_name 
        FROM donations d 
        LEFT JOIN categories c ON d.category_id = c.category_id 
        WHERE d.donation_id = ?
    ");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $donation = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    if (!$donation) {
        return false;
    }
    
    // Check if FPDF exists
    $fpdf_path = __DIR__ . '/../fpdf/fpdf.php';
    if (!file_exists($fpdf_path)) {
        return false;
    }
    
    require_once($fpdf_path);
    
    // Generate PDF
    class ReceiptPDF extends FPDF {
        function Header() {
            $this->SetFillColor(245, 124, 0);
            $this->Rect(0, 0, 210, 30, 'F');
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 20);
            $this->Cell(0, 15, 'Seela Suwa Herath Bikshu Gilan Arana', 0, 1, 'C');
            $this->SetFont('Arial', '', 12);
            $this->Cell(0, 8, 'Healthcare & Donation Management System', 0, 1, 'C');
            $this->Ln(10);
            $this->SetTextColor(0, 0, 0);
        }
    }
    
    $pdf = new ReceiptPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(245, 124, 0);
    $pdf->Cell(0, 10, 'DONATION RECEIPT', 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, 'Receipt No: DON-' . str_pad($donation['donation_id'], 6, '0', STR_PAD_LEFT), 0, 1, 'C');
    $pdf->Cell(0, 8, 'Date: ' . date('F d, Y', strtotime($donation['created_at'])), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Donor info
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Donor Information', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(50, 8, 'Name:', 0, 0);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, $donation['donor_name'], 0, 1);
    
    if ($donation['donor_email']) {
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(50, 8, 'Email:', 0, 0);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, $donation['donor_email'], 0, 1);
    }
    $pdf->Ln(5);
    
    // Amount
    $pdf->SetFont('Arial', 'B', 24);
    $pdf->SetTextColor(245, 124, 0);
    $pdf->Cell(0, 15, 'Rs. ' . number_format($donation['amount'], 2), 0, 1, 'C');
    
    // Save to temp file
    $filename = $temp_dir . 'receipt_' . $donation_id . '_' . time() . '.pdf';
    $pdf->Output('F', $filename);
    
    return $filename;
}

/**
 * Log email sending attempts
 * @param string $to Recipient
 * @param string $subject Subject
 * @param string $status Status (sent/failed)
 * @param string $error Error message (optional)
 */
function logEmail($to, $subject, $status, $error = '') {
    $conn = new mysqli("localhost", "root", "", "monastery_healthcare");
    if ($conn->connect_error) {
        return;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO email_notifications (recipient_email, subject, body, type, status, error_message, sent_at) 
        VALUES (?, ?, '', 'general', ?, ?, NOW())
    ");
    $stmt->bind_param("ssss", $to, $subject, $status, $error);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

/**
 * Get pending emails from queue
 * @return array Pending emails
 */
function getPendingEmails() {
    $conn = new mysqli("localhost", "root", "", "monastery_healthcare");
    if ($conn->connect_error) {
        return [];
    }
    
    $result = $conn->query("
        SELECT * FROM email_notifications 
        WHERE status = 'pending' 
        ORDER BY created_at ASC 
        LIMIT 50
    ");
    
    $emails = [];
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row;
    }
    
    $conn->close();
    return $emails;
}
?>
