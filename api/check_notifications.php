<?php
/**
 * API Endpoint to Check for New Notifications
 * Returns count of new activities since last check
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db_config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$con = getDBConnection();

// Get last check timestamp from session
$lastCheck = $_SESSION['last_notification_check'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));

try {
    // Check new donations
    $newDonations = 0;
    $stmt = $con->prepare("SELECT COUNT(*) as count FROM donations WHERE created_at > ?");
    $stmt->bind_param('s', $lastCheck);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $newDonations = $result['count'];
    
    // Check new appointments
    $newAppointments = 0;
    $stmt = $con->prepare("SELECT COUNT(*) as count FROM appointments WHERE created_at > ?");
    $stmt->bind_param('s', $lastCheck);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $newAppointments = $result['count'];
    
    // Check pending bills
    $pendingBills = 0;
    $stmt = $con->prepare("SELECT COUNT(*) as count FROM bills WHERE status = 'pending' AND due_date <= DATE_ADD(NOW(), INTERVAL 3 DAY)");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $pendingBills = $result['count'];
    
    // Get latest donation for notification
    $latestDonation = null;
    if ($newDonations > 0) {
        $stmt = $con->prepare("SELECT donor_name, amount FROM donations WHERE created_at > ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param('s', $lastCheck);
        $stmt->execute();
        $latestDonation = $stmt->get_result()->fetch_assoc();
    }
    
    // Update last check time
    $_SESSION['last_notification_check'] = date('Y-m-d H:i:s');
    
    echo json_encode([
        'success' => true,
        'newDonations' => $newDonations,
        'newAppointments' => $newAppointments,
        'pendingBills' => $pendingBills,
        'latestDonation' => $latestDonation,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$con->close();
?>
