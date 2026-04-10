<?php
/**
 * Authentication Check - Include this at the top of protected pages
 * Ensures user is logged in and has proper session
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login page
    header("Location: ../login.php");
    exit();
}

// Session timeout - 30 minutes of inactivity
$timeout_duration = 1800; // 30 minutes in seconds

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: ../login.php?timeout=1");
    exit();
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

/**
 * Check if user has required role
 * @param array $allowed_roles Array of allowed role names
 */
function checkRole($allowed_roles) {
    if (!isset($_SESSION['role_name']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
        // Access denied
        header("HTTP/1.1 403 Forbidden");
        die("Access Denied. You don't have permission to view this page.");
    }
}

/**
 * Get current user ID
 * @return int User ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? 0;
}

/**
 * Get current username
 * @return string Username
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? 'Guest';
}

/**
 * Get current user role
 * @return string Role name
 */
function getCurrentUserRole() {
    return $_SESSION['role_name'] ?? 'Unknown';
}

/**
 * Check if current user is admin
 * @return bool True if admin
 */
function isAdmin() {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin';
}

/**
 * Check if current user is doctor
 * @return bool True if doctor
 */
function isDoctor() {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Doctor';
}

?>
