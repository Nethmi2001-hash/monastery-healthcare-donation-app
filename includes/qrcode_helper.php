<?php
/**
 * QR Code Generator Helper
 * Uses phpqrcode library (included in most PHP installations)
 * If not available, falls back to Google Charts API
 */

/**
 * Generate QR code image
 * @param string $data Data to encode
 * @param string $filename Output filename (optional)
 * @param string $size Size (S/M/L)
 * @return string Base64 encoded image or file path
 */
function generateQRCode($data, $filename = null, $size = 'M') {
    $sizes = [
        'S' => 150,
        'M' => 250,
        'L' => 400
    ];
    
    $qr_size = $sizes[$size] ?? 250;
    
    // Method 1: Try phpqrcode library
    if (class_exists('QRcode')) {
        require_once __DIR__ . '/../phpqrcode/qrlib.php';
        
        if ($filename) {
            QRcode::png($data, $filename, QR_ECLEVEL_L, 4);
            return $filename;
        } else {
            ob_start();
            QRcode::png($data, null, QR_ECLEVEL_L, 4);
            $image = ob_get_contents();
            ob_end_clean();
            return 'data:image/png;base64,' . base64_encode($image);
        }
    }
    
    // Method 2: Google Charts API (fallback - works always)
    $encoded_data = urlencode($data);
    $api_url = "https://chart.googleapis.com/chart?chs={$qr_size}x{$qr_size}&cht=qr&chl={$encoded_data}&choe=UTF-8";
    
    if ($filename) {
        $image_data = file_get_contents($api_url);
        file_put_contents($filename, $image_data);
        return $filename;
    }
    
    return $api_url;
}

/**
 * Generate QR code for donation receipt
 * @param int $donation_id
 * @return string QR code image URL/base64
 */
function generateDonationQR($donation_id) {
    global $con;
    
    // Get donation details
    $stmt = $con->prepare("SELECT donation_id, donor_name, amount, created_at 
                           FROM donations 
                           WHERE donation_id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $donation = $result->fetch_assoc();
    
    if (!$donation) {
        return false;
    }
    
    // Create verification URL
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                . "://" . $_SERVER['HTTP_HOST'];
    $verify_url = $base_url . "/test/verify_donation.php?id=" . $donation_id 
                  . "&hash=" . md5($donation_id . $donation['amount']);
    
    // Generate QR code
    return generateQRCode($verify_url, null, 'M');
}

/**
 * Generate QR code for monk ID
 * @param int $monk_id
 * @return string QR code image URL/base64
 */
function generateMonkQR($monk_id) {
    global $con;
    
    // Create monk profile URL
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                . "://" . $_SERVER['HTTP_HOST'];
    $profile_url = $base_url . "/test/monk_profile.php?id=" . $monk_id;
    
    // Generate QR code
    return generateQRCode($profile_url, null, 'M');
}

/**
 * Generate QR code for appointment
 * @param int $appointment_id
 * @return string QR code image URL/base64
 */
function generateAppointmentQR($appointment_id) {
    global $con;
    
    $stmt = $con->prepare("SELECT app_id, monk_id, doctor_id, app_date, app_time 
                           FROM appointments 
                           WHERE app_id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    
    if (!$appointment) {
        return false;
    }
    
    // Create check-in URL
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                . "://" . $_SERVER['HTTP_HOST'];
    $checkin_url = $base_url . "/test/appointment_checkin.php?id=" . $appointment_id;
    
    // Generate QR code
    return generateQRCode($checkin_url, null, 'M');
}

/**
 * Verify QR code data
 * @param string $data Scanned data
 * @return array Verification result
 */
function verifyQRCode($data) {
    // Parse URL and extract parameters
    $parsed = parse_url($data);
    
    if (!isset($parsed['query'])) {
        return ['valid' => false, 'message' => 'Invalid QR code'];
    }
    
    parse_str($parsed['query'], $params);
    
    // Return verification details
    return [
        'valid' => true,
        'type' => basename($parsed['path'], '.php'),
        'params' => $params
    ];
}
?>
