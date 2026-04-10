<?php
require_once __DIR__ . '/../includes/init.php';
/**
 * Process donation date request from public page.
 */
session_start();
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: public_donate.php#date-request');
    exit;
}

if (!validateCSRFToken()) {
    header('Location: public_donate.php?date_error=' . urlencode('Security validation failed. Please refresh and try again.') . '#date-request');
    exit;
}

$conn = getDBConnection();
$conn->query("CREATE TABLE IF NOT EXISTS donation_date_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    donor_name VARCHAR(120) NOT NULL,
    donor_email VARCHAR(160) NOT NULL,
    donor_phone VARCHAR(40) NOT NULL,
    requested_date DATE NOT NULL,
    meal_type VARCHAR(20) NOT NULL DEFAULT 'lunch',
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_requested_date (requested_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$mealColRes = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_date_requests' AND COLUMN_NAME = 'meal_type'");
if ($mealColRes && (int)$mealColRes->fetch_assoc()['c'] === 0) {
    $conn->query("ALTER TABLE donation_date_requests ADD COLUMN meal_type VARCHAR(20) NOT NULL DEFAULT 'lunch' AFTER requested_date");
}

$donor_name = trim($_POST['donor_name'] ?? '');
$donor_email = trim($_POST['donor_email'] ?? '');
$donor_phone = trim($_POST['donor_phone'] ?? '');
$requested_date = trim($_POST['requested_date'] ?? '');
$meal_type = trim($_POST['meal_type'] ?? 'lunch');
$allowed_meals = ['morning_food', 'lunch'];
if (!in_array($meal_type, $allowed_meals, true)) {
    $meal_type = 'lunch';
}

$errors = [];
if ($donor_name === '') $errors[] = 'Full name is required';
if ($donor_phone === '') $errors[] = 'Phone is required';
if ($donor_email === '' || !filter_var($donor_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
if ($requested_date === '') $errors[] = 'Donation date is required';
if ($meal_type === '') $errors[] = 'Meal selection is required';

$today = date('Y-m-d');
if ($requested_date !== '' && $requested_date < $today) {
    $errors[] = 'Donation date cannot be in the past';
}

if (!empty($errors)) {
    $conn->close();
    header('Location: public_donate.php?date_error=' . urlencode(implode('. ', $errors)) . '#date-request');
    exit;
}

$check = $conn->prepare("SELECT request_id FROM donation_date_requests WHERE requested_date = ? AND status IN ('pending','approved') LIMIT 1");
$check->bind_param('s', $requested_date);
$check->execute();
$checkRes = $check->get_result();
if ($checkRes && $checkRes->num_rows > 0) {
    $check->close();
    $conn->close();
    header('Location: public_donate.php?date_error=' . urlencode('That date is already reserved. Please choose another.') . '#date-request');
    exit;
}
$check->close();

$stmt = $conn->prepare("INSERT INTO donation_date_requests (donor_name, donor_email, donor_phone, requested_date, meal_type, status, created_at)
    VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
$stmt->bind_param('sssss', $donor_name, $donor_email, $donor_phone, $requested_date, $meal_type);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header('Location: public_donate.php?date_success=1#date-request');
    exit;
}

$error = $stmt->error;
$stmt->close();
$conn->close();
header('Location: public_donate.php?date_error=' . urlencode('Failed to submit request. Please try again.') . '#date-request');
exit;
?>