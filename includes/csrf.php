<?php
/**
 * CSRF Token Generation and Validation
 * Include this in forms to prevent Cross-Site Request Forgery attacks
 */

/**
 * Generate CSRF token and store in session
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token from session
 * @return string CSRF token
 */
function getCSRFToken() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Validate CSRF token from POST request
 * @return bool True if valid
 */
function validateCSRFToken() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/**
 * Output CSRF hidden input field for forms
 */
function csrfField() {
    $token = generateCSRFToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Check CSRF token and die if invalid
 */
function requireCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCSRFToken()) {
        http_response_code(403);
        die("CSRF token validation failed. Please refresh the page and try again.");
    }
}
?>
