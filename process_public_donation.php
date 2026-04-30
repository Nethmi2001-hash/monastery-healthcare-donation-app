<?php
/**
 * Process Public Donation - Bank Transfer / Slip Upload
 * Handles form submission from public_donate.php
 */
session_start();
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/csrf.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: public_donate.php');
    exit;
}

$conn = getDBConnection();

if (!validateCSRFToken()) {
    header('Location: public_donate.php?error=' . urlencode('Security validation failed. Please refresh and try again.'));
    exit;
}

function resolveDonationCategoryId(mysqli $conn, string $categorySlug): int {
    $map = [
        'general' => ['General Donation'],
        'healthcare' => ['Medicine', 'Healthcare'],
        'food' => ['Food & Beverages', 'Food'],
        'housing' => ['General Donation', 'Housing']
    ];

    $candidates = $map[$categorySlug] ?? ['General Donation'];
    foreach ($candidates as $name) {
        $stmt = $conn->prepare("SELECT category_id FROM categories WHERE type='donation' AND name = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($row && isset($row['category_id'])) {
                return (int)$row['category_id'];
            }
        }
    }

    $fallback = $conn->query("SELECT category_id FROM categories WHERE type='donation' ORDER BY category_id ASC LIMIT 1");
    if ($fallback) {
        $row = $fallback->fetch_assoc();
        if ($row && isset($row['category_id'])) {
            return (int)$row['category_id'];
        }
    }
    return 0;
}

// Collect and sanitize form data from public_donate.php
$anonymous = isset($_POST['anonymous']);
$donor_name = trim($_POST['donor_name'] ?? '');
$donor_email = trim($_POST['donor_email'] ?? '');
$donor_phone = trim($_POST['donor_phone'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);
$category_slug = strtolower(trim((string)($_POST['category'] ?? 'general')));
$notes = trim((string)($_POST['message'] ?? ''));
$payment_method_raw = strtolower(trim((string)($_POST['pay_method'] ?? $_POST['payment_method'] ?? 'payhere')));
$bank_reference = trim((string)($_POST['bank_reference'] ?? ''));

$category_id = resolveDonationCategoryId($conn, $category_slug);
$method = ($payment_method_raw === 'bank_slip') ? 'bank' : 'card_sandbox';
$status = 'pending';

if ($anonymous) {
    $donor_name = 'Anonymous';
    $donor_email = '';
    $donor_phone = '';
}

// Validate required fields
$errors = [];
if (empty($donor_name)) $errors[] = 'Donor name is required';
if (!$anonymous && (empty($donor_email) || !filter_var($donor_email, FILTER_VALIDATE_EMAIL))) $errors[] = 'Valid email is required';
if ($amount < 100) $errors[] = 'Minimum donation is Rs. 100';
if ($category_id <= 0) $errors[] = 'Please select a donation category';

// Validate bank slip upload only for bank transfer method
$slip_path = null;
if ($method === 'bank') {
    if (empty($_FILES['bank_slip']['name']) || $_FILES['bank_slip']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Bank slip upload is required for bank transfer method';
    } else {
    $file = $_FILES['bank_slip'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = 'Invalid file type. Please upload JPG, PNG, GIF, WebP, or PDF';
    }
    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds 5MB limit';
    }
    }
}

// If validation errors, redirect back
if (!empty($errors)) {
    $error_msg = implode('. ', $errors);
    header('Location: public_donate.php?error=' . urlencode($error_msg) . '#donate');
    exit;
}

if ($method === 'bank') {
    // Create uploads directory if not exists
    $upload_dir = __DIR__ . '/uploads/bank_slips/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $file_ext = strtolower(pathinfo($_FILES['bank_slip']['name'], PATHINFO_EXTENSION));
    $unique_name = 'slip_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
    $destination = $upload_dir . $unique_name;

    // Move uploaded file
    if (!move_uploaded_file($_FILES['bank_slip']['tmp_name'], $destination)) {
        header('Location: public_donate.php?error=' . urlencode('Failed to upload file. Please try again.'));
        exit;
    }

    $slip_path = 'uploads/bank_slips/' . $unique_name;
}

// Check if donor is a registered user (by email)
$donor_user_id = null;
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->bind_param("s", $donor_email);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $donor_user_id = $row['user_id'];
}
$stmt->close();

// Insert donation record
$stmt = $conn->prepare("INSERT INTO donations (
    donor_user_id, donor_name, donor_email, donor_phone, 
    amount, currency, category_id, method, 
    bank_reference, slip_path, 
    status, notes, created_at
) VALUES (?, ?, ?, ?, ?, 'LKR', ?, ?, ?, ?, ?, ?, NOW())");

$stmt->bind_param(
    "isssdisssss",
    $donor_user_id, $donor_name, $donor_email, $donor_phone,
    $amount, $category_id, $method,
    $bank_reference, $slip_path,
    $status, $notes
);

if ($stmt->execute()) {
    $donation_id = $stmt->insert_id;
    $stmt->close();
    $conn->close();
    
    // Redirect with success
    header('Location: public_donate.php?success_ref=' . $donation_id);
    exit;
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    
    // Clean up uploaded file on failure
    if (isset($destination) && file_exists($destination)) {
        unlink($destination);
    }
    
    header('Location: public_donate.php?error=' . urlencode('Failed to save donation. Please try again.'));
    exit;
}
