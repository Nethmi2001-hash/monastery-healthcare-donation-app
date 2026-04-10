<?php
/**
 * Database Configuration File
 * Monastery Healthcare & Donation Management System
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'monastery_healthcare');

/**
 * Get database connection
 * @return mysqli Database connection object
 */
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        // Log error (don't show details to user in production)
        error_log("Database connection failed: " . $conn->connect_error);
        die("Database connection error. Please contact the system administrator.");
    }
    
    // Set charset to UTF-8 for proper Sinhala support
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

/**
 * Execute a prepared statement safely
 * @param mysqli $conn Database connection
 * @param string $query SQL query with placeholders
 * @param string $types Parameter types (e.g., "sis" for string, int, string)
 * @param array $params Parameters array
 * @return mysqli_stmt Prepared statement
 */
function executePreparedStatement($conn, $query, $types, $params) {
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Statement preparation failed: " . $conn->error);
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    
    return $stmt;
}

/**
 * Sanitize output to prevent XSS
 * @param string $data Data to sanitize
 * @return string Sanitized data
 */
function sanitizeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize input data
 * @param string $data Input data
 * @return string Cleaned data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Desired format (default: 'Y-m-d')
 * @return string Formatted date
 */
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date) || $date == '0000-00-00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Format currency (LKR)
 * @param float $amount Amount
 * @return string Formatted currency
 */
function formatCurrency($amount) {
    return 'LKR ' . number_format($amount, 2);
}

// Set timezone
date_default_timezone_set('Asia/Colombo');

// Error reporting (disable in production)
if ($_SERVER['SERVER_NAME'] == 'localhost') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
?>
