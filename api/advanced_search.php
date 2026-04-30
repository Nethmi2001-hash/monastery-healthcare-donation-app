<?php
/**
 * API Endpoint for Advanced Search
 * Handles complex queries with multiple filters
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

$searchType = $_GET['searchType'] ?? 'monks';
$query = trim($_GET['query'] ?? '');
$results = [];

try {
    switch ($searchType) {
        case 'monks':
            $results = searchMonks($con, $_GET);
            break;
        case 'donations':
            $results = searchDonations($con, $_GET);
            break;
        case 'appointments':
            $results = searchAppointments($con, $_GET);
            break;
        default:
            $results = [];
    }
    
    echo json_encode(['success' => true, 'results' => $results, 'count' => count($results)]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$con->close();

/**
 * Search monks with filters
 */
function searchMonks($con, $filters) {
    $query = $filters['query'] ?? '';
    $bloodGroup = $filters['blood'] ?? '';
    $status = $filters['status'] ?? '';
    $chronic = $filters['chronic'] ?? '';
    
    $sql = "SELECT monk_id, full_name, phone, blood_group, status, chronic_conditions, allergies 
            FROM monks WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Search query
    if ($query) {
        $sql .= " AND (full_name LIKE ? OR phone LIKE ?)";
        $searchTerm = "%$query%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }
    
    // Blood group filter
    if ($bloodGroup) {
        $sql .= " AND blood_group = ?";
        $params[] = $bloodGroup;
        $types .= 's';
    }
    
    // Status filter
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Chronic condition filter
    if ($chronic === 'yes') {
        $sql .= " AND chronic_conditions IS NOT NULL AND chronic_conditions != ''";
    } elseif ($chronic === 'no') {
        $sql .= " AND (chronic_conditions IS NULL OR chronic_conditions = '')";
    }
    
    $sql .= " ORDER BY full_name LIMIT 50";
    
    $stmt = $con->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $monks = [];
    while ($row = $result->fetch_assoc()) {
        $monks[] = $row;
    }
    
    return $monks;
}

/**
 * Search donations with filters
 */
function searchDonations($con, $filters) {
    $query = $filters['query'] ?? '';
    $minAmount = $filters['min-amount'] ?? '';
    $maxAmount = $filters['max-amount'] ?? '';
    $status = $filters['status'] ?? '';
    $method = $filters['method'] ?? '';
    
    $sql = "SELECT d.donation_id, d.donor_name, d.donor_email, d.amount, d.method, 
                   d.status, d.created_at, c.name as category_name
            FROM donations d
            LEFT JOIN categories c ON d.category_id = c.category_id
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Search query
    if ($query) {
        $sql .= " AND (d.donor_name LIKE ? OR d.donor_email LIKE ?)";
        $searchTerm = "%$query%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }
    
    // Amount range
    if ($minAmount !== '') {
        $sql .= " AND d.amount >= ?";
        $params[] = (float)$minAmount;
        $types .= 'd';
    }
    
    if ($maxAmount !== '') {
        $sql .= " AND d.amount <= ?";
        $params[] = (float)$maxAmount;
        $types .= 'd';
    }
    
    // Status filter
    if ($status) {
        $sql .= " AND d.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Payment method filter
    if ($method) {
        $sql .= " AND d.method = ?";
        $params[] = $method;
        $types .= 's';
    }
    
    $sql .= " ORDER BY d.created_at DESC LIMIT 50";
    
    $stmt = $con->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $donations = [];
    while ($row = $result->fetch_assoc()) {
        $donations[] = $row;
    }
    
    return $donations;
}

/**
 * Search appointments with filters
 */
function searchAppointments($con, $filters) {
    $query = $filters['query'] ?? '';
    $fromDate = $filters['from-date'] ?? '';
    $toDate = $filters['to-date'] ?? '';
    $status = $filters['status'] ?? '';
    
    $sql = "SELECT a.app_id, a.app_date, a.app_time, a.status, a.notes,
                   m.full_name as monk_name, d.full_name as doctor_name
            FROM appointments a
            JOIN monks m ON a.monk_id = m.monk_id
            JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Search query
    if ($query) {
        $sql .= " AND (m.full_name LIKE ? OR d.full_name LIKE ?)";
        $searchTerm = "%$query%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }
    
    // Date range
    if ($fromDate) {
        $sql .= " AND a.app_date >= ?";
        $params[] = $fromDate;
        $types .= 's';
    }
    
    if ($toDate) {
        $sql .= " AND a.app_date <= ?";
        $params[] = $toDate;
        $types .= 's';
    }
    
    // Status filter
    if ($status) {
        $sql .= " AND a.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    $sql .= " ORDER BY a.app_date DESC, a.app_time DESC LIMIT 50";
    
    $stmt = $con->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    return $appointments;
}
?>
