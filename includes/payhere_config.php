<?php
/**
 * PayHere Payment Gateway Configuration
 * 
 * SETUP INSTRUCTIONS:
 * 1. Register at https://www.payhere.lk/
 * 2. Get your Merchant ID and Secret from Dashboard
 * 3. Replace values below
 * 4. For testing, keep SANDBOX_MODE = true
 * 5. For production, set SANDBOX_MODE = false and update with live credentials
 */

// PayHere Credentials
define('PAYHERE_MERCHANT_ID', '1221149');  // Replace with your Merchant ID
define('PAYHERE_MERCHANT_SECRET', 'YOUR_MERCHANT_SECRET_HERE');  // IMPORTANT: Replace this!

// Environment Settings
define('PAYHERE_SANDBOX_MODE', true);  // TRUE = Sandbox (testing), FALSE = Live (production)

// Currency
define('PAYHERE_CURRENCY', 'LKR');  // Sri Lankan Rupee

// Minimum donation amount
define('PAYHERE_MIN_AMOUNT', 100);  // Rs. 100 minimum

// URLs
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$project_path = '/test';  // Adjust if your project is in different folder

define('PAYHERE_RETURN_URL', $base_url . $project_path . '/payhere_return.php');
define('PAYHERE_CANCEL_URL', $base_url . $project_path . '/payhere_cancel.php');
define('PAYHERE_NOTIFY_URL', $base_url . $project_path . '/payhere_notify.php');

/**
 * Generate PayHere MD5 Signature
 * Used for verifying payment notifications
 */
function generatePayHereSignature($merchant_id, $order_id, $amount, $currency, $status_code) {
    $merchant_secret = PAYHERE_MERCHANT_SECRET;
    
    return strtoupper(
        md5(
            $merchant_id . 
            $order_id . 
            number_format($amount, 2, '.', '') . 
            $currency . 
            $status_code . 
            strtoupper(md5($merchant_secret))
        )
    );
}

/**
 * Verify PayHere notification signature
 */
function verifyPayHereNotification($post_data) {
    $merchant_id = $post_data['merchant_id'] ?? '';
    $order_id = $post_data['order_id'] ?? '';
    $amount = $post_data['payhere_amount'] ?? 0;
    $currency = $post_data['payhere_currency'] ?? '';
    $status_code = $post_data['status_code'] ?? '';
    $received_md5sig = $post_data['md5sig'] ?? '';
    
    $local_md5sig = generatePayHereSignature($merchant_id, $order_id, $amount, $currency, $status_code);
    
    return ($local_md5sig === $received_md5sig);
}

/**
 * PayHere Test Card Numbers (Sandbox Mode Only)
 */
function getPayHereTestCards() {
    return [
        'visa' => [
            'number' => '4111 1111 1111 1111',
            'name' => 'VISA Test Card'
        ],
        'mastercard' => [
            'number' => '5555 5555 5555 4444',
            'name' => 'MasterCard Test Card'
        ],
        'amex' => [
            'number' => '3782 822463 10005',
            'name' => 'AMEX Test Card'
        ]
    ];
}

/**
 * Get PayHere status message
 */
function getPayHereStatusMessage($status_code) {
    $statuses = [
        '-3' => 'Payment Chargeback',
        '-2' => 'Payment Failed',
        '-1' => 'Payment Cancelled',
        '0' => 'Payment Pending',
        '1' => 'Payment Processing',
        '2' => 'Payment Success',
    ];
    
    return $statuses[$status_code] ?? 'Unknown Status';
}

/**
 * Log PayHere transaction
 */
function logPayHereTransaction($order_id, $status, $message, $data = []) {
    $log_file = __DIR__ . '/../logs/payhere_transactions.log';
    $log_dir = dirname($log_file);
    
    // Create logs directory if it doesn't exist
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_entry = sprintf(
        "[%s] Order: %s | Status: %s | Message: %s | Data: %s\n",
        date('Y-m-d H:i:s'),
        $order_id,
        $status,
        $message,
        json_encode($data)
    );
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
?>
