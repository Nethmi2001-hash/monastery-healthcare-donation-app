<?php
/**
 * PayHere Server Notification (IPN - Instant Payment Notification)
 * This file receives POST notifications from PayHere server when payment is completed
 * IMPORTANT: This must be accessible from internet (use ngrok for local testing)
 */

require_once 'includes/payhere_config.php';
require_once 'includes/db_config.php';

$conn = getDBConnection();

// Get PayHere notification data
$post_data = $_POST;
$merchant_id = $post_data['merchant_id'] ?? '';
$order_id = $post_data['order_id'] ?? '';
$payhere_amount = $post_data['payhere_amount'] ?? 0;
$payhere_currency = $post_data['payhere_currency'] ?? '';
$status_code = $post_data['status_code'] ?? '';
$method = $post_data['method'] ?? '';
$status_message = $post_data['status_message'] ?? '';
$card_holder_name = $post_data['card_holder_name'] ?? '';
$card_no = $post_data['card_no'] ?? '';
$custom_1 = $post_data['custom_1'] ?? '';  // category_id
$custom_2 = $post_data['custom_2'] ?? '';

// Log the notification
logPayHereTransaction($order_id, $status_code, "Notification received", $post_data);

// Verify signature matches
if (verifyPayHereNotification($post_data)) {
    // Signature is valid
    
    if ($status_code == 2) {
        // Payment successful (status_code 2 = success)
        
        // Extract donor info from POST
        $donor_name = $_POST['first_name'] . ' ' . $_POST['last_name'];
        $donor_email = $_POST['email'] ?? '';
        $donor_phone = $_POST['phone'] ?? '';
        $items = $_POST['items'] ?? 'General Donation';
        
        // Default category (you can customize this)
        $category_id = !empty($custom_1) ? intval($custom_1) : 1;  // Default to first donation category
        
        // Save donation to database (current schema uses method/order_id/txn_ref/raw_payload_json)
        $stmt = $conn->prepare("INSERT INTO donations (donor_name, donor_email, donor_phone, amount, category_id, method, gateway_name, order_id, txn_ref, raw_payload_json, notes, status, created_at) VALUES (?, ?, ?, ?, ?, 'card_sandbox', 'PayHere', ?, ?, ?, ?, 'verified', NOW())");

        $txn_ref = $_POST['payment_id'] ?? $order_id;
        $raw_payload_json = json_encode($post_data, JSON_UNESCAPED_UNICODE);
        $notes = "PayHere Payment - Order ID: $order_id, Method: $method, Card: $card_no";
        $stmt->bind_param("sssdissss", $donor_name, $donor_email, $donor_phone, $payhere_amount, $category_id, $order_id, $txn_ref, $raw_payload_json, $notes);
        
        if ($stmt->execute()) {
            $donation_id = $stmt->insert_id;
            logPayHereTransaction($order_id, 'SUCCESS', "Donation ID $donation_id saved", ['donation_id' => $donation_id]);
            
            // Send thank you email with receipt (if email helper exists)
            if (file_exists(__DIR__ . '/includes/email_helper.php') && !empty($donor_email)) {
                require_once __DIR__ . '/includes/email_helper.php';
                sendDonationThankYouEmail($donor_email, $donor_name, $payhere_amount, $order_id, $donation_id);
            }
        } else {
            logPayHereTransaction($order_id, 'ERROR', "Failed to save donation: " . $stmt->error);
        }
        $stmt->close();
        
    } else {
        // Payment failed or other status
        logPayHereTransaction($order_id, 'FAILED', $status_message, ['status_code' => $status_code]);
    }
    
} else {
    // Signature mismatch - possible fraud
    logPayHereTransaction($order_id, 'SECURITY_ERROR', "MD5 signature mismatch - possible fraud attempt", $post_data);
}

$conn->close();

// Return 200 OK to PayHere
http_response_code(200);
echo "OK";
?>
